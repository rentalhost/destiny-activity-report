<?php

namespace Application\Controllers;

use Application\Contracts\RouterSetupContract;
use Application\Libraries\QueryPool;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Route;

/**
 * Class ProcessController
 */
class ProcessController extends Controller implements RouterSetupContract
{
    /**
     * Number of activities to consider.
     */
    private const ACTIVITY_COUNT_LIMIT = 25;

    /**
     * Number of days to consider as a valid activity.
     */
    private const ACTIVITY_DAYS_LIMIT = 60;

    /**
     * Score addition specific for party size.
     */
    private const PARTY_DISTRIBUTION = [
        0  => [ 0.00 ],
        1  => [ 1.00, 0.00 ],
        2  => [ 1.00, 0.80, 0.00 ],
        5  => [ 1.00, 0.90, 0.70, 0.50, 0.20, 0.00 ],
        11 => [ 1.00, 0.95, 0.90, 0.85, 0.80, 0.75, 0.60, 0.50, 0.40, 0.20, 0.10, 0.00 ],
    ];

    /**
     * Score limit on entanglement.
     */
    const  POINTS_ENTANGLEMENT = 200;

    /**
     * Score limit on recentivity.
     */
    const  POINTS_RECENTIVITY = 200;

    /**
     * Score addition specific for each part of recentivity.
     */
    private const RECENTIVITY_DISTRIBUTION = [ 1.0, 0.9, 0.6, 0.4, 0.2, 0.1, 0.1, 0.1 ];

    /**
     * Defines all controller routes.
     */
    static public function routerSetup(): void
    {
        set_time_limit(3600);

        Route::get('/process/clan', 'ProcessController@routeClan');
        Route::get('/process/clan/members', 'ProcessController@routeClanMembers');
        Route::post('/process/member/activities', 'ProcessController@routeMemberActivities');
        Route::post('/process/member/details', 'ProcessController@routeMemberDetails');
    }

    /**
     * Collect and return all character IDs from account response.
     * @param $accountResponse
     * @return array
     */
    private static function collectCharacters($accountResponse): array
    {
        $characterIds         = [];
        $accountCharacters    = array_pluck(array_get($accountResponse, 'Response.data.characters'), 'characterBase');
        $accountActivityLimit = Carbon::today()->subDays(static::ACTIVITY_DAYS_LIMIT);

        foreach ($accountCharacters as $accountCharacter) {
            $accountCharacterLastPlayed = new Carbon($accountCharacter['dateLastPlayed']);
            if ($accountCharacterLastPlayed->gt($accountActivityLimit)) {
                $characterIds[] = $accountCharacter['characterId'];
            }
        }

        return $characterIds;
    }

    /**
     * Return the game mode settings.
     * @return array[][]
     */
    private static function getGameModes(): array
    {
        return [
            'general'  => [
                'modes'    => [ 0 ],
                'withClan' => false,
            ],
            'clan'     => [
                'modes'    => [ 2, 6, 18, 20 ],
                'withClan' => true,
            ],
            'raid'     => [
                'modes'    => [ 4 ],
                'withClan' => true,
            ],
            'crucible' => [
                'modes'    => [ 5 ],
                'withClan' => true,
            ],
            'osiris'   => [
                'modes'    => [ 14 ],
                'withClan' => true,
            ],
        ];
    }

    /**
     * Prepares the activities collection, slicing it to 25 items max.
     * @param Collection $charactersActivities Last player activities.
     * @return Collection
     */
    private static function prepareActivitiesCollection($charactersActivities): Collection
    {
        $charactersActivities = $charactersActivities->map(function ($characterActivity) {
            $characterActivity['carbonPeriod'] = new Carbon($characterActivity['period']);

            return $characterActivity;
        });

        // Excludes very old periods.
        $accountActivityLimit = Carbon::today()->subDays(static::ACTIVITY_DAYS_LIMIT);

        /** @var Collection $charactersActivities */
        $charactersActivities = $charactersActivities->filter(function ($characterActivity) use ($accountActivityLimit) {
            /** @var Carbon $carbonPeriod */
            $carbonPeriod = $characterActivity['carbonPeriod'];

            return $carbonPeriod->gte($accountActivityLimit);
        });

        // Excludes activities with less than 2 minutes of duration.
        $charactersActivities = $charactersActivities->reject(function ($characterActivity) {
            return array_get($characterActivity, 'values.activityDurationSeconds.basic.value') < 120;
        });

        // Order by period.
        $charactersActivities = $charactersActivities->sortByDesc(function ($characterActivity) {
            /** @var Carbon $carbonPeriod */
            $carbonPeriod = $characterActivity['carbonPeriod'];

            return $carbonPeriod->getTimestamp();
        });

        // Select only most recent periods.
        $charactersActivities = $charactersActivities->slice(0, static::ACTIVITY_COUNT_LIMIT);

        return $charactersActivities;
    }

    /**
     * Collect the infos about clan.
     * @return array
     */
    public function routeClan(): array
    {
        $clanIdentifier = strtolower(array_get($_GET, 'clanIdentifier'));
        $clanId         = $clanIdentifier;

        if (!$clanIdentifier) {
            return QueryPool::generateError('Internal:ClanIdentifierIsEmpty');
        }

        if (!is_numeric($clanIdentifier)) {
            $clanIdentifierResponse = QueryPool::unique(sprintf('/Group/Name/%s/', $clanIdentifier), 720);

            if (array_has($clanIdentifierResponse, 'data.code')) {
                return $clanIdentifierResponse;
            }

            $clanId = (int) array_get($clanIdentifierResponse, 'Response.detail.groupId');
        }

        $clanResponse = QueryPool::unique(sprintf('/Group/%u/', $clanId), 720);

        if (array_has($clanResponse, 'data.code')) {
            return $clanResponse;
        }

        $clanAdminIds        = [];
        $clanAdminsQuery     = sprintf('/Group/%u/AdminsV2/?currentPage=%%u&itemsPerPage=50', $clanId);
        $clanAdminsQueryPage = 0;

        do {
            $clanAdminsQueryPage++;
            $clanAdminsQueryPaginated = sprintf($clanAdminsQuery, $clanAdminsQueryPage);
            $clanAdminsResponse       = QueryPool::unique($clanAdminsQueryPaginated, 1);

            if (array_has($clanAdminsResponse, 'data.code')) {
                return $clanAdminsResponse;
            }

            /** @var array $clanAdminResults */
            $clanAdminResults = array_get($clanAdminsResponse, 'Response.results');

            foreach ($clanAdminResults as $clanAdminResult) {
                $clanAdminIds[] = array_get($clanAdminResult, 'user.membershipId');
            }
        }
        while (array_get($clanAdminsResponse, 'Response.hasMore'));

        return QueryPool::generateResult([
            'id'       => array_get($clanResponse, 'Response.detail.groupId'),
            'name'     => array_get($clanResponse, 'Response.detail.name'),
            'adminIds' => $clanAdminIds,
        ]);
    }

    /**
     * Collect the members from clans.
     * @return array
     */
    public function routeClanMembers(): array
    {
        $clanIds = (array) array_get($_GET, 'clanIds');

        if (!$clanIds) {
            return QueryPool::generateError('Internal:ClanIdsIsEmpty');
        }

        $results = [ 'clanMembers' => array_fill_keys($clanIds, []) ];

        foreach ($clanIds as $clanId) {
            $clansQueryPool = new QueryPool;

            $clansQueryPool->addQuery(sprintf('/Group/%u/', $clanId), 720);
            $clansQueryPool->addQuery(sprintf('/Group/%u/AdminsV2/?currentPage=1&platformType=1&itemsPerPage=50', $clanId), 8);

            $clansQueryPool->then(function ($carry, $clanResponse, $clanAdminsReponse) use ($clanId, &$results) {
                $clanAdminIds  = array_map('intval', array_pluck(array_get($clanAdminsReponse, 'Response.results'), 'user.membershipId'));
                $clanFounderId = (int) array_get($clanResponse, 'Response.founderMembershipId');

                $clanMembersQuery      = sprintf('/Group/%u/ClanMembers/?currentPage=%%u&platformType=1', $clanId);
                $clanMembersTotalPages = ceil(array_get($clanResponse, 'Response.clanMembershipTypes.0.memberCount') / 10);

                $clanMembersQueryPool = new QueryPool;

                for ($clanMembersQueryPage = 1; $clanMembersQueryPage <= $clanMembersTotalPages; $clanMembersQueryPage++) {
                    $clanMembersQueryPaginated = sprintf($clanMembersQuery, $clanMembersQueryPage);
                    $clanMembersQueryPool->addQuery($clanMembersQueryPaginated, $clanMembersQueryPage !== $clanMembersTotalPages ? 8 : 1,
                        function ($response) use (&$results, $clanAdminIds, $clanFounderId, $clanId) {
                            /** @var array $clanMemberResults */
                            $clanMemberResults = array_get($response, 'Response.results');

                            foreach ($clanMemberResults as $clanMemberResult) {
                                $memberId              = (int) array_get($clanMemberResult, 'bungieNetUserInfo.membershipId');
                                $membershipId          = array_get($clanMemberResult, 'destinyUserInfo.membershipId');
                                $membershipDisplayName = array_get($clanMemberResult, 'destinyUserInfo.displayName');

                                $results['clanMembers'][$clanId][] = [
                                    'memberId'              => $memberId,
                                    'membershipId'          => $membershipId,
                                    'membershipDisplayName' => $membershipDisplayName,
                                    'isFounder'             => $memberId === $clanFounderId,
                                    'isAdmin'               => in_array($memberId, $clanAdminIds, true),
                                ];
                            }
                        });
                }

                if (!$clanMembersQueryPool->process()) {
                    return $clanMembersQueryPool->getLastError();
                }

                /** @var array[] $sortingItems */
                $sortingItems   = $results['clanMembers'][$clanId];
                $sortingFounder = [];
                $sortingAdmins  = [];
                $sortingNames   = [];
                asort($sortingItems);

                foreach ($sortingItems as $sortingItemKey => $sortingItem) {
                    $sortingFounder[$sortingItemKey] = (int) $sortingItem['isFounder'];
                    $sortingAdmins[$sortingItemKey]  = (int) $sortingItem['isAdmin'];
                    $sortingNames[$sortingItemKey]   = $sortingItem['membershipDisplayName'];
                }

                array_multisort(
                    $sortingFounder, SORT_DESC,
                    $sortingAdmins, SORT_DESC,
                    $sortingNames, SORT_NATURAL,
                    $sortingItems
                );

                $results['clanMembers'][$clanId] = array_values($sortingItems);

                return null;
            });

            if (!$clansQueryPool->process()) {
                return $clansQueryPool->getLastError();
            }
        }

        return QueryPool::generateResult($results);
    }

    /**
     * Load all member activities.
     */
    public function routeMemberActivities(): array
    {
        $membershipId = array_get($_POST, 'membershipId');

        if (!$membershipId) {
            return QueryPool::generateError('Internal:MembershipIdIsEmpty');
        }

        $memberIds = (array) array_get($_POST, 'memberIds');

        if (!$memberIds) {
            return QueryPool::generateError('Internal:MemberIdsIsEmpty');
        }

        $accountResponse = [
            'general'  => [ 'score' => 0 ],
            'clan'     => [ 'score' => 0 ],
            'raid'     => [ 'score' => 0 ],
            'crucible' => [ 'score' => 0 ],
            'osiris'   => [ 'score' => 0 ],
        ];

        $accountResponseQuery = QueryPool::unique(sprintf('/Destiny/1/Account/%u/', $membershipId), 8);

        if (array_has($accountResponseQuery, 'data.code')) {
            if ($accountResponseQuery['data']['code'] === 'UserCannotResolveCentralAccount') {
                return QueryPool::generateResult($accountResponse);
            }

            return $accountResponseQuery;
        }

        /** @var array $accountCharactersRaw */
        $characterIds = static::collectCharacters($accountResponseQuery);

        if (!$characterIds) {
            return QueryPool::generateResult($accountResponse);
        }

        $carbonNow = Carbon::now();
        $gameModes = static::getGameModes();

        // Check each game mode for character.
        foreach ($gameModes as $gameModeKey => $gameMode) {
            $gameModeQuery = sprintf('/Destiny/Stats/ActivityHistory/1/%u/%%u/?mode=%%u&count=%u&definitions=true',
                $membershipId, static::ACTIVITY_COUNT_LIMIT * 3);

            $charactersQueryPool  = new QueryPool;
            $charactersActivities = new Collection;

            $activitiesTypes = new Collection;

            /** @var int[] $gameModeTypes */
            $gameModeTypes = $gameMode['modes'];

            foreach ($gameModeTypes as $gameModeType) {
                foreach ($characterIds as $characterId) {
                    $characterQuery = sprintf($gameModeQuery, $characterId, $gameModeType);
                    $charactersQueryPool->addQuery($characterQuery, 8, function ($response) use (&$activitiesTypes, &$charactersActivities) {
                        $charactersActivities = $charactersActivities->merge(array_get($response, 'Response.data.activities'));
                        $activitiesTypes      = $activitiesTypes->merge(array_get($response, 'Response.definitions.activities'));
                    });
                }
            }

            if (!$charactersQueryPool->process()) {
                return $charactersQueryPool->getLastError();
            }

            /** @var Collection $charactersActivities */
            // Ignore if no activity.
            if (!$charactersActivities->count()) {
                continue;
            }

            $charactersActivities = static::prepareActivitiesCollection($charactersActivities);

            // In general, calculate the activity quality.
            if ($gameMode['withClan'] === false) {
                $accountResponse[$gameModeKey]['score'] = round($charactersActivities->map(function ($characterActivity) use ($carbonNow) {
                        /** @var Carbon $carbonPeriod */
                        $carbonPeriod = $characterActivity['carbonPeriod'];
                        $periodDiff   = $carbonPeriod->diffInDays($carbonNow);

                        return static::RECENTIVITY_DISTRIBUTION[(int) floor($periodDiff * 8 / static::ACTIVITY_DAYS_LIMIT)];
                    })->sum() * 400);
                continue;
            }

            $activitiesTypes = $activitiesTypes->keyBy('activityHash');

            $gameModeScore         = 0;
            $gameActivityQueryPool = new QueryPool;

            foreach ($charactersActivities as $charactersActivity) {
                $activityMode = array_get($charactersActivity, 'activityDetails.mode');

                $lastActivityQuery = sprintf('/Destiny/Stats/PostGameCarnageReport/%u/', array_get($charactersActivity, 'activityDetails.instanceId'));
                $gameActivityQueryPool->addQuery($lastActivityQuery, 720,
                    function ($characterActivity) use ($activitiesTypes, &$gameModeScore, $carbonNow, $memberIds, $membershipId, $activityMode) {
                        /** @var Collection $activityEntries */
                        $activityType    = $activitiesTypes->get(array_get($characterActivity, 'Response.data.activityDetails.referenceId'));
                        $activityEntries = (new Collection(array_get($characterActivity, 'Response.data.entries')))->sortByDesc(function ($activityEntry) {
                            return array_get($activityEntry, 'values.kills.basic.value');
                        })->unique(function ($activityEntry) {
                            return array_get($activityEntry, 'player.destinyUserInfo.membershipId');
                        });

                        $activityEntriesCount = 0;

                        foreach ($activityEntries as $activityEntry) {
                            if (!array_get($activityEntry, 'values.kills.basic.value')) {
                                if (!in_array($activityMode, [ 4, 16 ], true)) {
                                    continue;
                                }

                                $activityEntriesCount++;
                                continue;
                            }

                            if (in_array(array_get($activityEntry, 'player.destinyUserInfo.membershipId'), $memberIds, true)) {
                                $activityEntriesCount++;
                                continue;
                            }

                            if (in_array($activityMode, [ 4, 16 ], true)) {
                                $activityEntriesCount++;
                            }
                        }

                        $activityEntryFromClan = (new Collection($activityEntries))->filter(function ($activityEntry) use ($memberIds, $membershipId) {
                            $entryMembershipId = array_get($activityEntry, 'player.destinyUserInfo.membershipId');

                            return $entryMembershipId !== $membershipId &&
                                   in_array($entryMembershipId, $memberIds, true);
                        });

                        /** @var Carbon $periodCarbon */
                        $periodCarbon = new Carbon(array_get($characterActivity, 'Response.data.period'));
                        $periodDiff   = min(static::ACTIVITY_DAYS_LIMIT - 1, $periodCarbon->diffInDays($carbonNow));
                        $periodDelta  = static::RECENTIVITY_DISTRIBUTION[(int) floor($periodDiff * 8 / static::ACTIVITY_DAYS_LIMIT)];

                        $partyAllies       = array_get($activityType, 'maxParty') - 1;
                        $partyDistribution = static::PARTY_DISTRIBUTION[$partyAllies];
                        $partyCurrent      = $partyDistribution[max(0, $partyAllies - $activityEntryFromClan->count())];

                        $gameModeScore += ($partyCurrent * static::POINTS_ENTANGLEMENT) +
                                          ($periodDelta * static::POINTS_RECENTIVITY);
                    });
            }

            if (!$gameActivityQueryPool->process()) {
                return $gameActivityQueryPool->getLastError();
            }

            $accountResponse[$gameModeKey]['score'] = round($gameModeScore);
        }

        return QueryPool::generateResult($accountResponse);
    }

    /**
     * Collect all details about a member.
     * @return array
     */
    public function routeMemberDetails(): array
    {
        $membershipId = array_get($_POST, 'membershipId');

        if (!$membershipId) {
            return QueryPool::generateError('Internal:MembershipIdIsEmpty');
        }

        $memberIds = (array) array_get($_POST, 'memberIds');

        if (!$memberIds) {
            return QueryPool::generateError('Internal:MemberIdsIsEmpty');
        }

        $gameMode = array_get($_POST, 'gameMode');

        if (!$gameMode) {
            return QueryPool::generateError('Internal:GameModeIsEmpty');
        }

        $gameModes = static::getGameModes();

        $accountResponseQuery = QueryPool::unique(sprintf('/Destiny/1/Account/%u/', $membershipId), 8);

        if (array_has($accountResponseQuery, 'data.code')) {
            if ($accountResponseQuery['data']['code'] === 'UserCannotResolveCentralAccount') {
                return QueryPool::generateResult([]);
            }

            return $accountResponseQuery;
        }

        $charactersActivities = new Collection;
        $activitiesTypes      = new Collection;

        foreach ($gameModes[$gameMode]['modes'] as $gameModeType) {
            $gameModeQuery = sprintf('/Destiny/Stats/ActivityHistory/1/%u/%%u/?mode=%u&count=%u&definitions=true',
                $membershipId, $gameModeType, static::ACTIVITY_COUNT_LIMIT * 3);

            $charactersQueryPool = new QueryPool;

            foreach (static::collectCharacters($accountResponseQuery) as $characterId) {
                $characterQuery = sprintf($gameModeQuery, $characterId);
                $charactersQueryPool->addQuery($characterQuery, 8, function ($response) use (&$charactersActivities, &$activitiesTypes) {
                    $charactersActivities = $charactersActivities->merge(array_get($response, 'Response.data.activities'));
                    $activitiesTypes      = $activitiesTypes->merge(array_get($response, 'Response.definitions.activities'));
                });
            }

            if (!$charactersQueryPool->process()) {
                return $charactersQueryPool->getLastError();
            }
        }

        $activitiesTypes = $activitiesTypes->keyBy('activityHash');

        /** @var Collection $charactersActivities */
        // Ignore if no activity.
        if (!$charactersActivities->count()) {
            return QueryPool::generateResult([]);
        }

        // Prepare the activities collection.
        $charactersActivities = static::prepareActivitiesCollection($charactersActivities);

        $carbonNow = Carbon::now();

        $gameActivityResponse  = [];
        $gameActivityQueryPool = new QueryPool;

        foreach ($charactersActivities as $charactersActivity) {
            $activityMode    = array_get($charactersActivity, 'activityDetails.mode');

            $lastActivityQuery = sprintf('/Destiny/Stats/PostGameCarnageReport/%u/', array_get($charactersActivity, 'activityDetails.instanceId'));
            $gameActivityQueryPool->addQuery($lastActivityQuery, 720,
                function ($characterActivity) use ( &$gameActivityResponse, $carbonNow, $memberIds, $membershipId, $activitiesTypes, $activityMode) {
                    /** @var Collection $activityEntries */
                    $activityType    = $activitiesTypes->get(array_get($characterActivity, 'Response.data.activityDetails.referenceId'));
                    $activityEntries = (new Collection(array_get($characterActivity, 'Response.data.entries')))->sortByDesc(function ($activityEntry) {
                        return array_get($activityEntry, 'values.kills.basic.value');
                    })->unique(function ($activityEntry) {
                        return array_get($activityEntry, 'player.destinyUserInfo.membershipId');
                    });

                    $activityEntriesCount = 0;

                    foreach ($activityEntries as $activityEntry) {
                        $playerDisplayName = array_get($activityEntry, 'player.destinyUserInfo.displayName');

                        if (array_get($activityEntry, 'player.destinyUserInfo.membershipId') === $membershipId) {
                            $players[] = [ 'type' => 'you', 'displayName' => $playerDisplayName ];
                            continue;
                        }

                        if (!array_get($activityEntry, 'values.kills.basic.value')) {
                            if (!in_array($activityMode, [ 4, 16 ], true)) {
                                continue;
                            }

                            $activityEntriesCount++;
                            $players[] = [ 'type' => 'unconsidered', 'displayName' => $playerDisplayName ];
                            continue;
                        }

                        if (in_array(array_get($activityEntry, 'player.destinyUserInfo.membershipId'), $memberIds, true)) {
                            $activityEntriesCount++;
                            $players[] = [ 'type' => 'ally', 'displayName' => $playerDisplayName ];
                            continue;
                        }

                        if (in_array($activityMode, [ 4, 16 ], true)) {
                            $activityEntriesCount++;
                            $players[] = [ 'type' => 'external', 'displayName' => $playerDisplayName ];
                        }
                    }

                    $activityEntryFromClan = (new Collection($activityEntries))->filter(function ($activityEntry) use ($memberIds, $membershipId) {
                        $entryMembershipId = array_get($activityEntry, 'player.destinyUserInfo.membershipId');

                        return $entryMembershipId !== $membershipId &&
                               in_array($entryMembershipId, $memberIds, true);
                    });

                    /** @var Carbon $periodCarbon */
                    $periodCarbon = new Carbon(array_get($characterActivity, 'Response.data.period'));
                    $periodDiff   = min(static::ACTIVITY_DAYS_LIMIT - 1, $periodCarbon->diffInDays($carbonNow));
                    $periodDelta  = static::RECENTIVITY_DISTRIBUTION[(int) floor($periodDiff * 8 / static::ACTIVITY_DAYS_LIMIT)];

                    $sortingTypes = [];
                    $sortingNames = [];
                    asort($players);

                    foreach ($players as $playerKey => $player) {
                        $sortingTypes[$playerKey] = array_search($player['type'], [ 'you', 'ally', 'external', 'unconsidered' ], true);
                        $sortingNames[$playerKey] = $player['displayName'];
                    }

                    array_multisort(
                        $sortingTypes, SORT_ASC,
                        $sortingNames, SORT_NATURAL,
                        $players
                    );

                    $partyAllies       = array_get($activityType, 'maxParty') - 1;
                    $partyDistribution = static::PARTY_DISTRIBUTION[$partyAllies];
                    $partyCurrent      = $partyDistribution[max(0, $partyAllies - $activityEntryFromClan->count())];

                    $gameActivityResponse[] = [
                        'period'            => array_get($characterActivity, 'Response.data.period'),
                        'title'             => array_get($activityType, 'activityName'),
                        'players'           => (new Collection($players))->unique('displayName')->values()->toArray(),
                        'scoreEntanglement' => round($partyCurrent * static::POINTS_ENTANGLEMENT),
                        'scoreRecentivity'  => round($periodDelta * static::POINTS_RECENTIVITY),
                    ];
                });
        }

        if (!$gameActivityQueryPool->process()) {
            return $gameActivityQueryPool->getLastError();
        }

        return QueryPool::generateResult($gameActivityResponse);
    }
}
