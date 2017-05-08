<template>
    <div>
        <table class="Table ClanList" v-for="clan in clans" :key="clan.clanId">
            <colgroup>
                <col width="7.5%" />
                <col />
                <col width="15%" />
                <col width="15%" />
                <col width="15%" />
                <col width="15%" />
                <col width="15%" />
            </colgroup>
            <thead>
                <tr class="details">
                    <th colspan="7">
                        <em v-if="clan.clanId">#{{ clan.clanId }}</em>
                        <i class="fa fa-fw fa-spinner fa-pulse loading" v-if="clan.loading"></i>
                        <slot v-if="!groupByAll">
                            <i class="fa fa-fw fa-star primary" title="Primary clan." v-if="clan.isPrimary"></i>
                            <i class="fa fa-fw fa-star-o ally" title="Ally clan." v-if="!clan.isPrimary"></i>
                        </slot>
                        <i class="fa fa-fw fa-globe all" title="All members of the clans together." v-if="groupByAll"></i>
                        <strong @click="toggleGroupMode">{{ clan.clanName }}</strong>
                    </th>
                </tr>
                <tr class="header" v-if="!clan.loading && clan.hasMembers">
                    <th>Ranking</th>
                    <th class="sortable left"
                        :class="{ active: ordering === 'default' }"
                        @click="setOrdering($event, 'default')">Gamertag
                    </th>
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
                    <td class="index">{{ getIndex(member) + 1 }}</td>
                    <td :colspan="member.loadingStatus === LoadingStatus.IDLE ? 7 : 1">
                        <span class="displayName"
                            v-text="member.membershipDisplayName"
                            @click="forceLoadMember($event, member)"></span>
                        <i class="fa fa-fw fa-user founder" v-if="member.isFounder"
                            :title="`Clan founder and administrator of &quot;${getClanName(member.clanId)}&quot;.`"></i>
                        <i class="fa fa-fw fa-key administrator" v-if="member.isAdmin && !member.isFounder"
                            :title="`Clan administrator of &quot;${getClanName(member.clanId)}&quot;.`"></i>
                    </td>
                    <td class="loading" v-if="member.loadingStatus === LoadingStatus.LOADING" colspan="5">
                        <i class="fa fa-spinner fa-pulse"></i>
                    </td>
                    <td class="activity" v-for="(activity, activityKey) in member.activities" :key="activity.membershipDisplayName"
                        v-if="member.loadingStatus === LoadingStatus.LOADED"
                        @click="toggleDisplayMode">
                        <div v-for="(gameScore, gameScoreKey) in gameScores" v-if="inRange(activity.score, gameScore.min, gameScore.max, gameScore.inclusive)"
                            :class="gameScoreKey" :title="textMode ? scorePercentual(activity.score) : null">
                            <span v-if="!textMode" v-text="scorePercentual(activity.score, true)"></span>
                            <span v-if="textMode" v-text="gameScore.text"></span>
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
    import _ from 'lodash';
    import $ from 'jquery';
    import Vue from 'vue';
    import LoadingStatus from '../enums/LoadingStatus';
    import Query from '../modules/Query';

    const ClanList = {
        data(){
            return {
                LoadingStatus: LoadingStatus,
                gameScores: {
                    veryHigh: { inclusive: true, min: 73.9, max: 100, details: true, text: 'Very High' },
                    high: { inclusive: false, min: 50.1, max: 73.9, details: true, text: 'High' },
                    medium: { inclusive: false, min: 29.0, max: 50.1, details: true, text: 'Medium' },
                    low: { inclusive: false, min: 11.3, max: 29.0, details: true, text: 'Low' },
                    veryLow: { inclusive: false, min: 0.1, max: 11.3, details: true, text: 'Very Low' },
                    noData: { inclusive: false, min: 0, max: 0.1, details: true, text: 'No Data' }
                },
                clans: {},
                clanNames: {},
                ordering: 'default',
                textMode: true,
                groupByAll: false,
                clansOriginal: null,
            };
        },
        methods: {
            inRange: function (value, start, end, inclusive) {
                return value >= start && (inclusive ? value <= end : value < end);
            },
            scorePercentual(score, onlyPercent){
                return (onlyPercent !== true ? 'Activity: ' : '') + score.toFixed(2) + '%';
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
                const clanId = this.getClanId(member.clanId);

                return _.indexOf(_.keys(this.$data['clans'][clanId]['members']), member.membershipId);
            },
            getClanId(clanId){
                return this.$data['groupByAll'] ? 'all' : clanId;
            },
            getClanName(clanId){
                return this.$data['clanNames'][clanId];
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
            forceLoadMember(ev, member){
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
                this.$data['clans'] = {};
            },
            createClan(clanId, clanName, isPrimary) {
                this.$data['clanNames'][clanId] = clanName;
                Vue.set(this.$data['clans'], clanId, {
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
                const clanIdNow = this.getClanId(clanId);

                Vue.set(this.$data['clans'][clanIdNow], 'members', _.mapKeys(_.map(clanMembers, function (clanMember) {
                    return $.extend(clanMember, {
                        clanId: clanId,
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
