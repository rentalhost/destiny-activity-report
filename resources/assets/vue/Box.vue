<template>
    <div class="Box"
        :style="{ width: width || '100%' }"
        :class="{ untitled: !label }">
        <div class="label" v-if="label">{{ label }}</div>
        <div class="component">
            <slot></slot>
        </div>
    </div>
</template>

<script>
    export default {
        props: ['width', 'label']
    }
</script>
