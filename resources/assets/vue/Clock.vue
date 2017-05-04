<template>
    <div class="Clock">{{ timing }}</div>
</template>

<script>
    const moment = require('moment');
    moment.relativeTimeThreshold('ss', 0);

    export default {
        data() {
            return {
                timing: moment().format('LT')
            };
        }
    }
</script>
