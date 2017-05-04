<template>
    <button class="Button" type="button">
        <slot></slot>
    </button>
</template>

<script>
    import  EventBus from '../modules/EventBus';
    export default {
        methods: {
            action: EventBus.$emit.bind(EventBus)
        }
    }
</script>
