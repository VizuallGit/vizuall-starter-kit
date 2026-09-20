<script setup>
import { computed, ref } from 'vue';
import { Widget } from '@statamic/cms/ui';

const props = defineProps({
    pages: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({ good: 0, ok: 0, bad: 0 }) },
});

const openId = ref(null);

const labels = {
    good: 'God',
    ok: 'Kan blive bedre',
    bad: 'Mangler',
};

const openPage = computed(() => props.pages.find((page) => page.id === openId.value) || null);
</script>

<template>
    <Widget title="SEO-status">
        <div v-if="openPage" class="ve-seo ve-seo--detail">
            <header>
                <div>
                    <strong>{{ openPage.title }}</strong>
                    <span>{{ openPage.score }} / 100</span>
                </div>
                <button type="button" aria-label="Luk" @click="openId = null">×</button>
            </header>

            <ul class="ve-seo__checks">
                <li v-for="(check, index) in openPage.checks" :key="index">
                    <span class="ve-seo__dot" :class="'is-' + check.level" />
                    <span>{{ check.hint }}</span>
                </li>
            </ul>

            <a v-if="openPage.edit_url" class="ve-seo__edit" :href="openPage.edit_url">
                Rediger siden
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path d="M5 12h14" />
                    <path d="m13 6 6 6-6 6" />
                </svg>
            </a>
        </div>

        <div v-else class="ve-seo">
            <div class="ve-seo__summary">
                <span class="is-good">{{ counts.good }} gode</span>
                <span class="is-ok">{{ counts.ok }} kan blive bedre</span>
                <span class="is-bad">{{ counts.bad }} mangler</span>
            </div>

            <p v-if="!pages.length" class="ve-seo__empty">Ingen udgivne sider at score.</p>

            <ul v-else class="ve-seo__pages">
                <li v-for="page in pages" :key="page.id">
                    <button type="button" @click="openId = page.id">
                        <span class="ve-seo__dot" :class="'is-' + page.status" :title="labels[page.status]" />
                        <span class="ve-seo__title">{{ page.title }}</span>
                        <span class="ve-seo__hint">{{ page.hint }}</span>
                    </button>
                </li>
            </ul>
        </div>
    </Widget>
</template>

<style scoped>
.ve-seo {
    padding: 8px 12px 12px;
    color: inherit;
}
.ve-seo__summary {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 12px;
    font-size: 12px;
}
.ve-seo__summary span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.ve-seo__summary span::before {
    content: '';
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: currentColor;
}
.ve-seo__summary .is-good { color: #22c55e; }
.ve-seo__summary .is-ok { color: #eab308; }
.ve-seo__summary .is-bad { color: #ef4444; }
.ve-seo__empty {
    margin: 0;
    padding: 16px 8px;
    text-align: center;
    opacity: 0.55;
    font-size: 12px;
}
.ve-seo__pages,
.ve-seo__checks {
    list-style: none;
    margin: 0;
    padding: 0;
}
.ve-seo__pages li + li {
    margin-top: 2px;
}
.ve-seo__pages button {
    all: unset;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 7px 4px;
    border-radius: 6px;
    cursor: pointer;
    color: inherit;
    text-align: left;
}
.ve-seo__pages button:hover {
    background: color-mix(in oklab, currentColor 7%, transparent);
}
.ve-seo__dot {
    width: 10px;
    height: 10px;
    flex: 0 0 auto;
    border-radius: 999px;
}
.ve-seo__dot.is-good { background: #22c55e; }
.ve-seo__dot.is-ok { background: #eab308; }
.ve-seo__dot.is-bad { background: #ef4444; }
.ve-seo__title {
    flex: 1 1 auto;
    min-width: 0;
    font-size: 13px;
    font-weight: 650;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ve-seo__hint {
    flex: 0 1 auto;
    font-size: 11px;
    opacity: 0.55;
    white-space: nowrap;
}
.ve-seo--detail header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    padding-bottom: 10px;
    margin-bottom: 8px;
    border-bottom: 1px solid color-mix(in oklab, currentColor 14%, transparent);
}
.ve-seo--detail header strong {
    display: block;
    font-size: 13px;
}
.ve-seo--detail header span {
    display: block;
    margin-top: 2px;
    font-size: 11px;
    opacity: 0.55;
}
.ve-seo--detail header button {
    all: unset;
    cursor: pointer;
    opacity: 0.55;
    font-size: 16px;
    padding: 0 4px;
    line-height: 1;
}
.ve-seo__checks li {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 7px 2px;
    font-size: 13px;
    line-height: 1.35;
}
.ve-seo__checks .ve-seo__dot {
    margin-top: 4px;
}
.ve-seo__edit {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 10px;
    padding: 5px 10px;
    border-radius: 6px;
    background: var(--theme-color-primary, #4530d8);
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.2;
    text-decoration: none;
}
.ve-seo__edit svg {
    flex: 0 0 auto;
}
</style>
