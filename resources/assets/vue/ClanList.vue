<template>
    <div>
        <table class="Table ClanList" v-for="clan in clans" :key="clan.clanId">
            <colgroup>
                <col width="7.5%" />
                <col />
                <col width="5%" />
                <col width="12.5%" />
                <col width="12.5%" />
                <col width="12.5%" />
                <col width="12.5%" />
                <col width="12.5%" />
            </colgroup>
            <thead>
                <tr class="details">
                    <th colspan="8">
                        <em v-if="clan.clanId">#{{ clan.clanId }}</em>
                        <i class="fa fa-fw fa-spinner fa-pulse loading" v-if="clan.loading"></i>
                        <slot v-if="!groupByAll">
                            <i class="fa fa-fw fa-star primary" title="Primary clan." v-if="clan.isPrimary"></i>
                            <i class="fa fa-fw fa-star-o ally" title="Ally clan." v-if="!clan.isPrimary"></i>
                        </slot>
                        <i class="fa fa-fw fa-globe all" title="All members of the clans together." v-if="groupByAll"></i>
                        <strong class="name" @click="toggleGroupMode">{{ clan.clanName }}</strong>
                    </th>
                </tr>
                <tr class="header" v-if="!clan.loading && clan.hasMembers">
                    <th>Ranking</th>
                    <th class="sortable left"
                        :class="{ active: ordering === 'default' }"
                        @click="setOrdering($event, 'default')">Gamertag
                    </th>
                    <th class="index" title="Clan index, based on input order.">Clan</th>
                    <th class="sortable" title="Activities independent of clan."
                        :class="{ active: ordering === 'general' }"
                        @click="setOrdering($event, 'general')"><span>General</span></th>
                    <th class="sortable" title="Activities with some clan member."
                        :class="{ active: ordering === 'clan' }"
                        @click="setOrdering($event, 'clan')"><span>Clan</span></th>
                    <th class="sortable" title="Raid activities with some clan member."
                        :class="{ active: ordering === 'raid' }"
                        @click="setOrdering($event, 'raid')"><span>Raid</span></th>
                    <th class="sortable" title="Crucible activities with some clan member."
                        :class="{ active: ordering === 'crucible' }"
                        @click="setOrdering($event, 'crucible')"><span>Crucible</span></th>
                    <th class="sortable" title="Osiris activities with some clan member."
                        :class="{ active: ordering === 'osiris' }"
                        @click="setOrdering($event, 'osiris')"><span>Osiris</span></th>
                </tr>
            </thead>
            <tbody v-if="!clan.loading && !clan.hasMembers">
                <tr class="none">
                    <td>No members on this clan.</td>
                </tr>
            </tbody>
            <tbody v-if="!clan.loading && clan.hasMembers">
                <tr class="hoverable" v-for="member in clan.members"
                    :data-membership-id="member.membershipId"
                    :class="{ loading: member.loadingStatus === LoadingStatus.LOADING }">
                    <td class="ranking">{{ getIndex(member) + 1 }}</td>
                    <td>
                        <span class="displayName"
                            v-text="member.membershipDisplayName"
                            @click="forceLoadMember(member)"></span>
                        <i class="fa fa-fw fa-user founder" v-if="member.isFounder"
                            :title="`Clan founder and administrator of &quot;${member.clan.clanName}&quot;.`"></i>
                        <i class="fa fa-fw fa-key administrator" v-if="member.isAdmin && !member.isFounder"
                            :title="`Clan administrator of &quot;${member.clan.clanName}&quot;.`"></i>
                    </td>
                    <td class="index" v-text="romanize(member.clan.clanIndex)"
                        :title="`Member of &quot;${member.clan.clanName}&quot;`"></td>
                    <td v-if="member.loadingStatus === LoadingStatus.IDLE" colspan="6"></td>
                    <td class="loading" v-if="member.loadingStatus === LoadingStatus.LOADING" colspan="6">
                        <i class="fa fa-spinner fa-pulse"></i>
                    </td>
                    <td class="activity" v-for="(activity, activityKey) in member.activities" :key="activity.membershipDisplayName"
                        v-if="member.loadingStatus === LoadingStatus.LOADED"
                        @click="toggleDisplayMode">
                        <div v-for="(gameScore, gameScoreKey) in gameScores" v-if="inRange(activity.score, gameScore.min, gameScore.max, gameScore.inclusive)"
                            :class="gameScoreKey" :title="textMode ? scoreActivity(activity.score) : null">
                            <span class="score" v-if="!textMode" v-text="scoreActivity(activity.score, true)"></span>
                            <span class="score" v-if="textMode" v-text="gameScore.text"></span>
                            <i v-if="gameScore.details && activityKey !== 'general'"
                                class="fa fa-fw fa-info-circle moreInfo" title="Click to see all details."
                                @click.stop="getAccountDetails(member, activityKey)"></i>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
    import EventBus from '../modules/EventBus';
    import Format from '../modules/Format';
    import _ from 'lodash';
    import $ from 'jquery';
    import Vue from 'vue';
    import LoadingStatus from '../enums/LoadingStatus';
    import Query from '../modules/Query';

    const ClanList = {
        initialData() {
            return {
                LoadingStatus: LoadingStatus,
                gameScores: {
                    veryHigh: { inclusive: true, min: 8000, max: 10000, details: true, text: 'Very High' },
                    high: { inclusive: false, min: 5000, max: 8000, details: true, text: 'High' },
                    medium: { inclusive: false, min: 3000, max: 5000, details: true, text: 'Medium' },
                    low: { inclusive: false, min: 1000, max: 3000, details: true, text: 'Low' },
                    veryLow: { inclusive: false, min: 1, max: 1000, details: true, text: 'Very Low' },
                    noData: { inclusive: false, min: 0, max: 1, details: true, text: 'No Data' }
                },
                clans: {},
                ordering: 'default',
                textMode: true,
                groupByAll: false,
                clansOriginal: null,
                clanIndex: 0,
            };
        },
        data(){
            return ClanList.initialData();
        },
        methods: {
            inRange: function (value, start, end, inclusive) {
                return value >= start && (inclusive ? value <= end : value < end);
            },
            romanize: Format.romanize,
            scoreActivity(score, onlyPercent){
                return (onlyPercent !== true ? 'Activity: ' : '') + Format.thousands(score);
            },
            toggleDisplayMode(){
                this.$data['textMode'] = !this.$data['textMode'];
            },
            toggleGroupMode(){
                if (!this.$data['groupByAll'] && _.size(this.$data['clans']) <= 1) {
                    return;
                }

                this.$data['groupByAll'] = !this.$data['groupByAll'];

                if (this.$data['groupByAll']) {
                    const clanMembers = this.getMembers();
                    const isLoading   = _.find(this.$data['clans'], function (clanDetails) {
                        return clanDetails.loading;
                    });

                    this.$data['clansOriginal'] = this.$data['clans'];
                    this.$data['clans']         = {
                        'all': {
                            clanId: null,
                            clanName: 'All',
                            hasMembers: !!_.size(clanMembers),
                            isPrimary: false,
                            loading: isLoading === true,
                            members: clanMembers
                        }
                    };
                }
                else {
                    this.$data['clans']         = this.$data['clansOriginal'];
                    this.$data['clansOriginal'] = null;
                }

                this.updateOrdering();
            },
            getIndex(member){
                const clanId = this.getClanId(member.clan.clanId);

                return _.indexOf(_.keys(this.$data['clans'][clanId]['members']), member.membershipId);
            },
            getClanId(clanId){
                return this.$data['groupByAll'] ? 'all' : clanId;
            },
            getNextMember(){
                return _.find(this.getMembers(), function (clanMember) {
                    return clanMember.loadingStatus === LoadingStatus.IDLE;
                });
            },
            getAccountDetails(member, gameMode){
                EventBus.$emit('ClanAccount:getDetails', member, gameMode);
            },
            updateOrdering(){
                _.each(this.$data['clans'], (clanDetails) => clanDetails['members'] = this.sortMembers(clanDetails['members']));
            },
            forceLoadMember(member){
                const membershipId = member.membershipId;

                member.forceLoading = true;
                this.updateOrdering();

                setImmediate(() => $(document.body).animate({ scrollTop: $(`tr[data-membership-id="${membershipId}"]`).position().top - 30 }));
            },
            sortMembers(membersToSort){
                const ordering = this.$data['ordering'];

                let memberOrdering = _.chain(membersToSort).orderBy(function (clanMember) {
                    return clanMember['membershipDisplayName'];
                }).orderBy(function (clanMember) {
                    return clanMember['isAdmin'];
                }, 'desc').orderBy(function (clanMember) {
                    return clanMember['isFounder'];
                }, 'desc').orderBy(function (clanMember) {
                    return clanMember['forceLoading'];
                }, 'desc').orderBy(function (clanMember) {
                    return clanMember['loadingStatus'] === LoadingStatus.LOADING;
                }, 'desc');

                if (ordering !== 'default') {
                    memberOrdering = memberOrdering.orderBy(function (clanMember) {
                        return !_.isEmpty(clanMember['activities'])
                            ? clanMember['activities'][ordering]['score']
                            : -Infinity;
                    }, 'desc')
                }

                return memberOrdering.keyBy('membershipId').value();
            },
            setOrdering(ev, orderingMode){
                $(ev.currentTarget).addClass('active')
                    .siblings().removeClass('active');

                this.$data.ordering = orderingMode;
                this.updateOrdering();
            },
            clear() {
                _.assignIn(this.$data, ClanList.initialData());
            },
            createClan(clanId, clanName, isPrimary) {
                const clanIndex = ++this.$data['clanIndex'];

                Vue.set(this.$data['clans'], clanId, {
                    clanIndex: clanIndex,
                    clanId: clanId,
                    clanName: clanName,
                    isPrimary: isPrimary,
                    loading: true,
                    hasMembers: false,
                    members: {}
                });
            },
            getMembers(callback){
                const members = _.assignIn({}, ... _.map(this.$data['clans'], 'members'));

                if (callback) {
                    callback(members);
                }

                return members;
            },
            setMembers(clanId, clanMembers){
                const clanIdNow = this.getClanId(clanId),
                      clan      = this.$data['clans'][clanId];

                Vue.set(this.$data['clans'][clanIdNow], 'members', _.mapKeys(_.map(clanMembers, function (clanMember) {
                    return $.extend(clanMember, {
                        clan: clan,
                        loadingStatus: LoadingStatus.IDLE,
                        forceLoading: false,
                        activities: {}
                    });
                }), function (clanMember) {
                    return clanMember.membershipId;
                }));

                this.$data['clans'][clanIdNow].hasMembers = !_.isEmpty(clanMembers);
                this.loaded(clanId);
            },
            setMemberLoading(clanId, memberId){
                this.$data['clans'][this.getClanId(clanId)]['members'][memberId].loadingStatus = LoadingStatus.LOADING;
            },
            setMemberActivities(clanId, memberId, memberActivities){
                this.$data['clans'][this.getClanId(clanId)]['members'][memberId].loadingStatus = LoadingStatus.LOADED;
                this.$data['clans'][this.getClanId(clanId)]['members'][memberId].activities    = memberActivities;
                this.updateOrdering();
            },
            beforeActivities(){
                if (Query.get('group') === 'all') {
                    this.toggleGroupMode();
                }
            },
            loaded(clanId) {
                this.$data['clans'][this.getClanId(clanId)]['loading'] = false;
            }
        },
        mounted(){
            EventBus.$on('Process:clear', this.clear.bind(this));
            EventBus.$on('ClanList:createClan', this.createClan.bind(this));
            EventBus.$on('ClanList:setMembers', this.setMembers.bind(this));
            EventBus.$on('ClanList:getMembers', this.getMembers.bind(this));
            EventBus.$on('ClanList:setMemberLoading', this.setMemberLoading.bind(this));
            EventBus.$on('ClanList:setMemberActivities', this.setMemberActivities.bind(this));
            EventBus.$on('ClanList:getNextMember', (callback) => callback(this.getNextMember()));
            EventBus.$on('ClanList:loaded', this.loaded.bind(this));
            EventBus.$on('ClanList:beforeActivities', this.beforeActivities.bind(this));
        }
    };

    export default ClanList;
</script>
