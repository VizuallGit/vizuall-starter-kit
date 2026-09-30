<script setup>
import { computed, ref } from 'vue';
import Icon from './lib/Icon.vue';
import Card from './lib/Card.vue';

const props = defineProps({
    handle: { type: String, default: '' },
    pages: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({ good: 0, ok: 0, bad: 0 }) },
});

/** Så mange rækker vises, før listen selv beder om plads. */
const PREVIEW = 6;

const openId = ref(null);
const expanded = ref(false);

const labels = {
    good: 'God',
    ok: 'Kan blive bedre',
    bad: 'Mangler',
};

const openPage = computed(() => props.pages.find((page) => page.id === openId.value) || null);

const visible = computed(() => (expanded.value ? props.pages : props.pages.slice(0, PREVIEW)));

const hidden = computed(() => Math.max(0, props.pages.length - PREVIEW));
</script>

<template>
    <Card :handle="handle" :title="openPage ? openPage.title : 'SEO-status'">
        <template #tools>
            <template v-if="openPage">
                <span class="dash-row-item__aside">{{ openPage.score }} / 100</span>
                <button type="button" class="dash-head-btn" @click="openId = null">Tilbage</button>
            </template>
            <span v-else class="dash-list__legend">
                <span><span class="dash-dot dash-dot--good" />{{ counts.good }} gode</span>
                <span><span class="dash-dot dash-dot--ok" />{{ counts.ok }} kan blive bedre</span>
                <span><span class="dash-dot dash-dot--bad" />{{ counts.bad }} mangler</span>
            </span>
        </template>

        <!-- Én side ad gangen: listen viser hvem, detaljen viser hvad. -->
        <template v-if="openPage">
            <div v-for="(check, index) in openPage.checks" :key="index" class="dash-row-item">
                <span class="dash-dot" :class="'dash-dot--' + check.level" />
                <span class="dash-row-item__body">
                    <span class="seo-check">{{ check.hint }}</span>
                </span>
            </div>

            <a v-if="openPage.edit_url" class="dash-row-item" :href="openPage.edit_url">
                <span class="dash-row-item__body">
                    <span class="dash-row-item__title seo-edit-link">Rediger siden</span>
                </span>
                <Icon class="dash-chevron" name="chevron" />
            </a>
        </template>

        <template v-else>
            <p v-if="!pages.length" class="dash-empty">Ingen udgivne sider at score.</p>

            <button
                v-for="page in visible"
                :key="page.id"
                type="button"
                class="dash-row-item"
                @click="openId = page.id"
            >
                <span class="dash-dot" :class="'dash-dot--' + page.status" :title="labels[page.status]" />
                <span class="dash-row-item__body">
                    <span class="dash-row-item__title">{{ page.title }}</span>
                </span>
                <span class="dash-row-item__aside">{{ page.hint }}</span>
                <Icon class="dash-chevron" name="chevron" />
            </button>

            <button v-if="hidden && !expanded" type="button" class="dash-more" @click="expanded = true">
                Vis de {{ hidden }} øvrige sider
            </button>
        </template>
    </Card>
</template>

<style scoped>
.seo-check {
    font-size: 0.85rem;
    line-height: 1.35;
    white-space: normal;
}

.seo-edit-link {
    color: var(--theme-color-primary, #4530d8);
}
</style>
