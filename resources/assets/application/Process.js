import EventBus from "../modules/EventBus";
import $ from "jquery";
import _ from "lodash";
import Query from "../modules/Query";

const mReadingMessage     = 'Reading <strong>clan signature</strong>%s...',
      mClanPrimaryMessage = 'Primary clan: <strong>%s</strong> <em>#%u</em>.',
      mClanAlliesMessage  = 'Ally clan: <strong>%s</strong> <em>#%u</em>.',
      rAlliesList         = /[\r\n,;]+/;

const Process = {
    init: function () {
        EventBus.$on('Process:clan', Process.clan);
        EventBus.$emit('Message:push', 'Enter the <strong>clan name</strong> or <strong>gamertag</strong> then click <strong>process</strong>.', { temporary: true });

        if (Query.get('clan')) {
            Process.clan();
        }
    },
    checkError: function (response, detailsConditional, details) {
        if (response.success === false) {
            let errorMessage = '<strong>Error:</strong> ' + response.data.code;

            if (response.data.code === detailsConditional) {
                errorMessage += ' ' + JSON.stringify(details, null, ' ');
            }

            EventBus.$emit('FormController:setEnabled', '.processComponents', true);
            EventBus.$emit('Message:push', errorMessage + '.', { temporary: true, error: true });
            return true;
        }
    },
    clan: function () {
        const clanName = $('.clanMaster').val().trim();

        EventBus.$emit('Process:clear');

        if (!clanName) {
            EventBus.$emit('Message:push', 'Please, enter the <strong>clan name</strong>.', { temporary: true, error: true });
            return;
        }

        const clanAllies     = $('.clanAllies').val(),
              clanAlliesList = _.map(clanAllies.split(rAlliesList), (clanAlly) => clanAlly.trim()),
              clanList       = _.chain([clanName]).concat(clanAlliesList).uniq().omitBy(_.isEmpty).value(),
              clanIds        = {},
              readingMessage = mReadingMessage.replace('%s', clanAlliesList.length > 1 ? ' (%u/' + _.size(clanList) + ')' : '');

        let clanLoadingPromise = Promise.resolve();

        EventBus.$emit('FormController:setEnabled', '.processComponents', false);

        _.each(clanList, function (clanIdentifier, index) {
            clanLoadingPromise = clanLoadingPromise.then(function (isValid) {
                if (isValid === false) {
                    return;
                }

                EventBus.$emit('Message:push', readingMessage.replace('%u', (Number(index) + 1).toString()), { temporary: true, loading: true });

                return $.get('/process/clan', { clanIdentifier: clanIdentifier }).then(function (response) {
                    if (Process.checkError(response, 'GroupNotFound', { clanIdentifier: clanIdentifier })) {
                        return false;
                    }

                    const clanMessage = Number(index) === 0 ? mClanPrimaryMessage : mClanAlliesMessage;
                    EventBus.$emit('Message:push', clanMessage.replace('%s', response.data.name).replace('%u', response.data.id));
                    clanIds[response.data.id] = response.data.name;
                });
            });
        });

        clanLoadingPromise.then(function (isValid) {
            if (isValid !== false) {
                return Process.clanMembers(clanIds);
            }
        });
    },
    clanMembers: function (clanIds) {
        const readingMessage   = _.size(clanIds) === 1
            ? 'Reading <strong>clan members</strong>...'
            : 'Reading <strong>clan members</strong> and from allies...';
        const noMembersWarning = '<strong>Warning:</strong> clan <strong>%s</strong> has no members.';
        const noMembersError   = '<strong>Error:</strong> no members do check.';

        EventBus.$emit('Message:push', readingMessage, { temporary: true });

        let clanMembersPromise = Promise.resolve(),
            hasMembers         = false,
            members            = null;

        clanMembersPromise.then(function () {
            _.each(clanIds, function (clanName, clanId) {
                EventBus.$emit('ClanList:createClan', clanId, clanName, _.first(_.keys(clanIds)) === clanId);
            });

            return $.get('/process/clan/members', { clanIds: _.keys(clanIds) }).then(function (response) {
                if (Process.checkError(response)) {
                    return false;
                }

                EventBus.$emit('Message:separator');

                let createSeparator = false;

                _.each(response.data.clanMembers, function (clanMembers, clanId) {
                    if (!_.size(clanMembers)) {
                        EventBus.$emit('Message:push', noMembersWarning.replace('%s', clanIds[clanId]), { warning: true });
                        EventBus.$emit('ClanList:loaded', clanId);
                        createSeparator = true;
                        return;
                    }

                    hasMembers = true;
                    EventBus.$emit('ClanList:setMembers', clanId, clanMembers);
                });

                if (createSeparator) {
                    EventBus.$emit('Message:separator');
                }

                members = response.data.clanMembers;
            });
        }).then(function () {
            if (!hasMembers) {
                EventBus.$emit('FormController:setEnabled', '.processComponents', true);
                EventBus.$emit('Message:push', noMembersError, { error: true });
                return;
            }

            EventBus.$emit('ClanList:beforeActivities');

            //noinspection JSCheckFunctionSignatures
            const memberIds           = _.chain(members).flatMap().map('membershipId').value();
            let loadActivitiesPromise = Process.loadActivities(memberIds);

            return loadActivitiesPromise.then(function () {
                EventBus.$emit('FormController:setEnabled', '.processComponents', true);
                EventBus.$emit('Message:push', 'Done!');
            });
        });

        return clanMembersPromise;
    },
    loadActivities: function (memberIds) {
        const memberActivityMessage = 'Loading activities from <strong>%s</strong>...';
        const nextPromise           = function () {
            const promise = new Promise(function (resolve) {
                EventBus.$emit('ClanList:getNextMember', function (clanMember) {
                    if (!clanMember) {
                        return resolve();
                    }

                    resolve(clanMember);
                });
            });

            return promise.then(function (clanMember) {
                if (!clanMember) {
                    return Promise.resolve();
                }

                EventBus.$emit('Message:push', memberActivityMessage.replace('%s', clanMember['membershipDisplayName']), { temporary: true, loading: true });
                EventBus.$emit('ClanList:setMemberLoading', clanMember.clan.clanId, clanMember.membershipId);

                return $.post('/process/member/activities', { membershipId: clanMember.membershipId, memberIds: memberIds }).then(function (response) {
                    if (Process.checkError(response)) {
                        return false;
                    }

                    EventBus.$emit('ClanList:setMemberActivities', clanMember.clan.clanId, clanMember.membershipId, response.data, true);

                    return nextPromise();
                }, function () {
                    EventBus.$emit('ClanList:setMemberLoading', clanMember.clan.clanId, clanMember.membershipId, false);

                    console.error('Server connection failed... trying again...');

                    return nextPromise();
                });
            });
        };

        return nextPromise();
    }
};

Process.init();
