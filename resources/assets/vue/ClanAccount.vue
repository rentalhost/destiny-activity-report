<template>
    <table class="Table ClanAccount" v-if="loadingStatus !== LoadingStatus.IDLE">
        <colgroup>
            <col width="10%" />
            <col />
            <col width="30%" />
            <col width="12.5%" />
            <col width="12.5%" />
            <col width="12.5%" />
        </colgroup>
        <thead>
            <tr class="details">
                <th colspan="6">
                    <em>#{{ member.memberId }}</em>
                    <i class="fa fa-fw fa-spinner fa-pulse loading" v-if="loadingStatus === LoadingStatus.LOADING"></i>
                    <i class="fa fa-fw fa-user user"></i>
                    <strong v-text="member.membershipDisplayName"></strong>
                    <span class="gameMode" v-text="gameMode.title"></span>
                </th>
            </tr>
            <tr class="header" v-if="activities.length">
                <th title="Based on when activity started.">Date</th>
                <th>Activity</th>
                <th>Players</th>
                <th title="Based on the number of allied members.x">Entanglement</th>
                <th title="Based on how recent the activity occurred.">Recentivity</th>
                <th title="Based on sum of entanglement and recentivity.">Total</th>
            </tr>
        </thead>
        <tbody v-if="!activities.length && loadingStatus === LoadingStatus.LOADED">
            <tr class="none">
                <td>No activitities from this player.</td>
            </tr>
        </tbody>
        <tbody v-if="activities.length">
            <tr class="activity hoverable" v-for="activity in activities">
                <td class="when" v-text="getPeriod(activity.period)"></td>
                <td class="title" v-text="activity.title"></td>
                <td class="players">
                    <i v-for="player in activity.players"
                        class="fa fa-fw fa-user"
                        :class="getPlayerClassname(player)"
                        :title="getPlayerTitle(player)"></i>
                </td>
                <td data-max="80" v-text="activity.scoreEntranglement.toFixed(2)"></td>
                <td data-max="20" v-text="activity.scoreRecentivity.toFixed(2)"></td>
                <td data-max="100" v-text="(activity.scoreEntranglement + activity.scoreRecentivity).toFixed(2)"></td>
            </tr>
        </tbody>
        <tfoot v-if="activities.length">
            <tr>
                <td class="total" colspan="3">Total of <strong v-text="(sum('scoreEntranglement', 'scoreRecentivity') / 25).toFixed(2) + '%'"></strong></td>
                <td data-max="2000" v-text="sum('scoreEntranglement').toFixed(2)"></td>
                <td data-max="500" v-text="sum('scoreRecentivity').toFixed(2)"></td>
                <td data-max="2500" v-text="sum('scoreEntranglement', 'scoreRecentivity').toFixed(2)"></td>
            </tr>
        </tfoot>
    </table>
</template>

<script>
    import EventBus from '../modules/EventBus';
    import _ from 'lodash';
    import $ from 'jquery';
    import moment from 'moment';
    import LoadingStatus from '../enums/LoadingStatus';

    const GameModes = {
        general: { title: 'in General' },
        clan: { title: 'in Clan activities' },
        raid: { title: 'in Clan raids' },
        crucible: { title: 'in Clan crucibles' },
        osiris: { title: 'in Clan Trials of Osiris' }
    };

    const PlayerTypes = {
        you: { classname: 'you', title: 'This is you!' },
        ally: { classname: 'ally', title: 'Ally player: %s' },
        external: { classname: 'external', title: 'External player: %s' },
        unconsidered: { classname: 'unconsidered', title: 'Unconsidered player: %s' }
    };

    export default {
        data(){
            return {
                LoadingStatus: LoadingStatus,
                loadingStatus: LoadingStatus.IDLE,
                gameMode: null,
                member: null,
                activities: []
            };
        },
        methods: {
            sum(...terms){
                const activities = this.$data['activities'];

                return _.reduce(terms, function (termCarry, term) {
                    return termCarry + _.sumBy(activities, term);
                }, 0);
            },
            getPeriod(period){
                return moment(period).fromNow();
            },
            getPlayerTitle(player){
                return PlayerTypes[player.type].title.replace('%s', player.displayName);
            },
            getPlayerClassname(player){
                return PlayerTypes[player.type].classname;
            },
            getDetails(member, gameMode){
                if (this.$data['loadingStatus'] === LoadingStatus.LOADING) {
                    return;
                }

                const component = this;

                this.$data['gameMode']      = GameModes[gameMode];
                this.$data['member']        = member;
                this.$data['activities']    = [];
                this.$data['loadingStatus'] = LoadingStatus.LOADING;

                (new Promise(function (resolve) {
                    EventBus.$emit('ClanList:getMembers', resolve);
                })).then(function (memberIds) {
                    $.post('/process/member/details', {
                        membershipId: member.membershipId,
                        memberIds: _.map(memberIds, 'membershipId'),
                        gameMode: gameMode
                    }).then(function (response) {
                        if (response.success) {
                            component.$data['activities'] = response.data;
                        }

                        component.$data['loadingStatus'] = LoadingStatus.LOADED;
                    });
                });
            }
        },
        mounted(){
            EventBus.$on('ClanAccount:getDetails', this.getDetails.bind(this));
        }
    }
</script>