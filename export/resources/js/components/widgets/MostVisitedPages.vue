<script setup>
import Icon from './lib/Icon.vue';
import Card from './lib/Card.vue';

defineProps({
    handle: { type: String, default: '' },
    pages: { type: Array, default: () => [] },
});

function format(value) {
    return Number(value || 0).toLocaleString('da-DK');
}
</script>

<template>
    <Card :handle="handle" title="Mest besøgte sider">
        <p v-if="!pages.length" class="dash-empty">
            Ingen visninger endnu. Åbn en offentlig side, så dukker den op her.
        </p>

        <a
            v-for="(page, index) in pages"
            :key="page.id"
            class="dash-row-item"
            :href="page.edit_url || page.url"
        >
            <span class="dash-rank">{{ index + 1 }}</span>
            <span class="dash-row-item__body">
                <span class="dash-row-item__title">{{ page.title }}</span>
                <span class="dash-bar"><span class="dash-bar__fill" :style="{ width: page.percent + '%' }" /></span>
            </span>
            <span class="dash-row-item__aside">{{ format(page.views) }} visninger</span>
            <Icon class="dash-chevron" name="chevron" />
        </a>
    </Card>
</template>
