<template>
    <textarea class="Textarea" :class="{ expansible: expansible }"></textarea>
</template>

<script>
    export default {
        props: {
            expansible: { type: Boolean }
        }
    }
</script>
