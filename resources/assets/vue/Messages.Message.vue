<template>
    <div class="message" :class="{ temporary: temporary, error: error, warning: warning, separator: separator }">
        <my-clock v-if="!separator"></my-clock>
        <div class="contents" v-if="!separator">
            <span v-if="message" v-html="message"></span>
            <i class="fa fa-spinner fa-spin" v-if="loading"></i>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            message: String,
            temporary: Boolean,
            error: Boolean,
            warning: Boolean,
            loading: Boolean,
            separator: Boolean
        }
    }
</script>
