<script setup>
import { Widget } from '@statamic/cms/ui';

defineProps({
    pages: { type: Array, default: () => [] },
});
</script>

<template>
    <Widget title="Mest besøgte sider">
        <div class="ve-views">
            <p v-if="!pages.length" class="ve-views__empty">
                Ingen visninger endnu. Åbn en offentlig side, så dukker den op her.
            </p>
            <ol v-else>
                <li v-for="(page, index) in pages" :key="page.id">
                    <div class="ve-views__row">
                        <span class="ve-views__rank">{{ index + 1 }}</span>
                        <span class="ve-views__title">{{ page.title }}</span>
                        <span class="ve-views__count">{{ page.views.toLocaleString('da-DK') }} visninger</span>
                    </div>
                    <div class="ve-views__track" aria-hidden="true">
                        <span :style="{ width: page.percent + '%' }" />
                    </div>
                </li>
            </ol>
        </div>
    </Widget>
</template>

<style scoped>
.ve-views {
    padding: 4px 12px 12px;
    color: inherit;
}
.ve-views__empty {
    margin: 0;
    padding: 20px 8px;
    text-align: center;
    opacity: 0.55;
    font-size: 12px;
    line-height: 1.45;
}
ol {
    list-style: none;
    margin: 0;
    padding: 0;
}
li + li {
    margin-top: 10px;
}
.ve-views__row {
    display: flex;
    align-items: baseline;
    gap: 10px;
}
.ve-views__rank {
    width: 1.1rem;
    flex: 0 0 auto;
    font-size: 11px;
    opacity: 0.45;
    font-variant-numeric: tabular-nums;
}
.ve-views__title {
    flex: 1 1 auto;
    min-width: 0;
    font-size: 13px;
    font-weight: 650;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ve-views__count {
    flex: 0 0 auto;
    font-size: 11px;
    opacity: 0.55;
    font-variant-numeric: tabular-nums;
}
.ve-views__track {
    margin: 6px 0 0 1.7rem;
    height: 4px;
    border-radius: 999px;
    background: color-mix(in oklab, currentColor 12%, transparent);
    overflow: hidden;
}
.ve-views__track span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: var(--theme-color-primary, #4530d8);
}
</style>
