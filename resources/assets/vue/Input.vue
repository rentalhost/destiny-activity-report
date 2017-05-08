<template>
    <input class="Input" type="text" />
</template>

<script>
    import Query from '../modules/Query';

    export default{
        props: {
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
