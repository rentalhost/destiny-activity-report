<template>
    <textarea class="Textarea" :class="{ expansible: expansible }"></textarea>
</template>

<script>
    import Query from '../modules/Query';

    export default {
        props: {
            expansible: Boolean,
            query: String
        },
        mounted(){
            const queryValue = Query.get(this.$props['query']);

            if (queryValue) {
                this.$el.value = queryValue;
            }
        }
    }
</script>
