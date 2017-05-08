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
                <tr class="clan">
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
                    <th>Gamertag</th>
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
                <tr class="noMembers">
                    <td>No members on this clan.</td>
                </tr>
            </tbody>
            <tbody v-if="!clan.loading && clan.hasMembers">
                <tr class="hoverable" v-for="member in clan.members" :class="{ loading: member.loadingStatus === LoadingStatus.LOADING }">
                    <td class="index">{{ getIndex(member) + 1 }}</td>
                    <td :colspan="member.loadingStatus === LoadingStatus.IDLE ? 7 : 1">
                        <span v-text="member.membershipDisplayName"></span>
                        <i class="fa fa-fw fa-user founder" v-if="member.isFounder"
                            :title="`Clan founder and administrator of &quot;${getClanName(member.clanId)}&quot;.`"></i>
                        <i class="fa fa-fw fa-key administrator" v-if="member.isAdmin && !member.isFounder"
                            :title="`Clan administrator of &quot;${getClanName(member.clanId)}&quot;.`"></i>
                    </td>
                    <td class="loading" v-if="member.loadingStatus === LoadingStatus.LOADING" colspan="5">
                        <i class="fa fa-spinner fa-pulse"></i>
                    </td>
                    <td class="activity" v-for="activity in member.activities" :key="activity.membershipDisplayName"
                        v-if="member.loadingStatus === LoadingStatus.LOADED"
                        @click="toggleDisplayMode">
                        <div class="veryHigh" v-if="inRange(activity.score, 73.9, 100, true)" :title="scorePercentual(activity.score)">
                            <span v-if="!textMode" v-text="scorePercentual(activity.score, true)"></span>
                            <span v-if="textMode">Very High</span>
                        </div>
                        <div class="high" v-if="inRange(activity.score, 50.1, 73.9)" :title="scorePercentual(activity.score)">
                            <span v-if="!textMode" v-text="scorePercentual(activity.score, true)"></span>
                            <span v-if="textMode">High</span>
                        </div>
                        <div class="medium" v-if="inRange(activity.score, 29.0, 50.1)" :title="scorePercentual(activity.score)">
                            <span v-if="!textMode" v-text="scorePercentual(activity.score, true)"></span>
                            <span v-if="textMode">Medium</span>
                        </div>
                        <div class="low" v-if="inRange(activity.score, 11.3, 29.0)" :title="scorePercentual(activity.score)">
                            <span v-if="!textMode" v-text="scorePercentual(activity.score, true)"></span>
                            <span v-if="textMode">Low</span>
                        </div>
                        <div class="veryLow" v-if="inRange(activity.score, 0.1, 11.3)" :title="scorePercentual(activity.score)">
                            <span v-if="!textMode" v-text="scorePercentual(activity.score, true)"></span>
                            <span v-if="textMode">Very Low</span>
                        </div>
                        <div class="noData" v-if="inRange(activity.score,0, 0.1)">
                            <span v-if="!textMode" v-text="scorePercentual(activity.score, true)"></span>
                            <span v-if="textMode">No data</span>
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

    const LoadingStatus = {
        IDLE: 0,
        LOADING: 1,
        LOADED: 2
    };

    const ClanList = {
        data(){
            return {
                clans: {},
                clanNames: {},
                ordering: 'raid',
                textMode: true,
                groupByAll: false,
                clansOriginal: null,
                LoadingStatus: LoadingStatus,
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
            updateOrdering(){
                _.each(this.$data['clans'], (clanDetails) => clanDetails['members'] = this.sortMembers(clanDetails['members']));
            },
            sortMembers(membersToSort){
                const ordering = this.$data['ordering'];

                return _.chain(membersToSort).orderBy(function (clanMember) {
                    return clanMember['membershipDisplayName'];
                }).orderBy(function (clanMember) {
                    return clanMember['isAdmin'];
                }, 'desc').orderBy(function (clanMember) {
                    return clanMember['isFounder'];
                }, 'desc').orderBy(function (clanMember) {
                    return clanMember['loadingStatus'] === LoadingStatus.LOADING;
                }, 'desc').orderBy(function (clanMember) {
                    return !_.isEmpty(clanMember['activities'])
                        ? clanMember['activities'][ordering]['score']
                        : -Infinity;
                }, 'desc').keyBy('membershipId').value();
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
            getMembers(){
                return _.assignIn({}, ... _.map(this.$data['clans'], 'members'));
            },
            setMembers(clanId, clanMembers){
                const clanIdNow = this.getClanId(clanId);

                Vue.set(this.$data['clans'][clanIdNow], 'members', _.mapKeys(_.map(clanMembers, function (clanMember) {
                    return $.extend(clanMember, {
                        clanId: clanId,
                        loadingStatus: LoadingStatus.IDLE,
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
            loaded(clanId) {
                this.$data['clans'][this.getClanId(clanId)]['loading'] = false;
            }
        },
        mounted(){
            EventBus.$on('Process:clear', this.clear.bind(this));
            EventBus.$on('ClanList:createClan', this.createClan.bind(this));
            EventBus.$on('ClanList:setMembers', this.setMembers.bind(this));
            EventBus.$on('ClanList:setMemberLoading', this.setMemberLoading.bind(this));
            EventBus.$on('ClanList:setMemberActivities', this.setMemberActivities.bind(this));
            EventBus.$on('ClanList:getNextMember', (callback) => callback(this.getNextMember()));
            EventBus.$on('ClanList:loaded', this.loaded.bind(this));
        }
    };

    export default ClanList;
</script>
