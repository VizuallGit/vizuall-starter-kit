<script setup>
import Icon from './lib/Icon.vue';
import Card from './lib/Card.vue';

defineProps({
    handle: { type: String, default: '' },
    items: { type: Array, default: () => [] },
});

const icons = {
    comments: 'comment',
    drafts: 'draft',
    meta: 'tag',
    alt: 'images',
};

function icon(item) {
    return icons[item.key] || 'alert';
}
</script>

<template>
    <Card :handle="handle" title="Kræver opmærksomhed">
        <p v-if="!items.length" class="dash-empty">
            Alt er ajour — intet kræver handling lige nu.
        </p>

        <component
            v-for="item in items"
            :key="item.key"
            :is="item.url ? 'a' : 'div'"
            :href="item.url || undefined"
            class="dash-row-item"
        >
            <Icon class="dash-icon" :class="'dash-icon--' + item.level" :name="icon(item)" />
            <span class="dash-row-item__body">
                <span class="dash-row-item__title">{{ item.label }}</span>
                <span class="dash-row-item__sub">{{ item.hint }}</span>
            </span>
            <Icon v-if="item.url" class="dash-chevron" name="chevron" />
        </component>
    </Card>
</template>
