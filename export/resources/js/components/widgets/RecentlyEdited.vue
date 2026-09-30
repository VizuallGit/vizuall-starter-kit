<script setup>
import Icon from './lib/Icon.vue';
import { timeAgo } from './lib/time.js';
import Card from './lib/Card.vue';

defineProps({
    handle: { type: String, default: '' },
    rows: { type: Array, default: () => [] },
});

function target(row) {
    return row.preview_url || row.edit_url;
}
</script>

<template>
    <Card :handle="handle" title="Senest redigeret">
        <p v-if="!rows.length" class="dash-empty">
            Intet er redigeret endnu. Det første du gemmer, lander her.
        </p>

        <a v-for="row in rows" :key="row.id" class="dash-row-item" :href="target(row)">
            <span class="dash-row-item__body">
                <span class="dash-row-item__title">{{ row.title }}</span>
                <span class="dash-row-item__sub">{{ row.collection_label }} · {{ timeAgo(row.modified_at) }}</span>
            </span>
            <span v-if="row.status === 'draft'" class="dash-badge">Kladde</span>
            <Icon v-else class="dash-chevron" name="chevron" />
        </a>
    </Card>
</template>
