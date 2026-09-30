<script setup>
import Icon from './Icon.vue';

defineProps({
    page: { type: Number, required: true },
    pages: { type: Number, required: true },
    label: { type: String, default: '' },
});

defineEmits(['go']);
</script>

<template>
    <div v-if="pages > 1" class="dash-pager">
        <span>{{ label || `Side ${page + 1} af ${pages}` }}</span>
        <span class="dash-pager__buttons">
            <button type="button" class="dash-head-btn" :disabled="page === 0" @click="$emit('go', page - 1)">
                <Icon name="caret-left" /> Forrige
            </button>
            <button type="button" class="dash-head-btn" :disabled="page >= pages - 1" @click="$emit('go', page + 1)">
                Næste <Icon name="chevron" />
            </button>
        </span>
    </div>
</template>
