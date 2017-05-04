<template>
    <div class="Messages">
        <div class="contents" ref="contents">
            <message v-for="message in messages"
                :key="message.key"
                :message="message.message"
                :temporary="message.temporary"
                :error="message.error"
                :warning="message.warning"
                :loading="message.loading"
                :separator="message.separator"></message>
        </div>
    </div>
</template>

<script>
    import EventBus from '../modules/EventBus';
    import $ from "jquery";
    import _ from "lodash";

    export default {
        data() {
            return {
                key: 0,
                messages: []
            };
        },
        methods: {
            clear(){
                this.$data['messages'] = [];
            },
            clearTemporaries(){
                this.$data['messages'] = _.filter(this.$data['messages'], function (message) {
                    return !message.temporary;
                });
            },
            push(paramMessage, paramOptions)  {
                this.clearTemporaries();

                this.$data['messages'].push($.extend({
                    temporary: false,
                    error: false,
                    warning: false,
                    loading: false
                }, paramOptions, {
                    key: ++this.$data['key'],
                    message: paramMessage
                }));

                this.scroll();
            },
            separator(){
                this.clearTemporaries();
                this.$data['messages'].push({
                    separator: true
                });
            },
            scroll() {
                const contents = $(this.$refs['contents']);
                contents.stop().animate({ scrollTop: contents.height() });
            }
        },
        components: {
            message: require('./Messages.Message')
        },
        mounted() {
            EventBus.$on('Process:clear', this.clear.bind(this));
            EventBus.$on('Message:push', this.push.bind(this));
            EventBus.$on('Message:separator', this.separator.bind(this));
            this.scroll();
        }
    }
</script>
