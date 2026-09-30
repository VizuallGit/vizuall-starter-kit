<script setup>
import { computed, ref } from 'vue';
import Icon from './lib/Icon.vue';
import Pager from './lib/Pager.vue';
import Card from './lib/Card.vue';

const props = defineProps({
    handle: { type: String, default: '' },
    rows: { type: Array, default: () => [] },
    average: { type: Number, default: null },
    perPage: { type: Number, default: 5 },
    counts: { type: Object, default: () => ({ good: 0, ok: 0, bad: 0 }) },
});

const best = ref(false);
const page = ref(0);
const openId = ref(null);

const openPage = computed(() => props.rows.find((row) => row.id === openId.value) || null);

const sorted = computed(() =>
    [...props.rows].sort((a, b) => (best.value ? b.score - a.score : a.score - b.score))
);

const pages = computed(() => Math.max(1, Math.ceil(sorted.value.length / props.perPage)));

const visible = computed(() => sorted.value.slice(page.value * props.perPage, (page.value + 1) * props.perPage));

function level(score) {
    if (score === null || score === undefined) return 'none';
    if (score >= 90) return 'good';
    if (score >= 60) return 'ok';

    return 'bad';
}
</script>

<template>
    <Card :handle="handle" :title="openPage ? openPage.title : 'Tilgængelighed'">
        <template #tools>
            <template v-if="openPage">
                <span class="dash-score" :class="'dash-score--' + level(openPage.score)">{{ openPage.score }}</span>
                <button type="button" class="dash-head-btn" @click="openId = null">Tilbage</button>
            </template>
            <template v-else>
                <span class="dash-list__legend">
                    <span><span class="dash-dot dash-dot--good" />{{ counts.good }}</span>
                    <span><span class="dash-dot dash-dot--ok" />{{ counts.ok }}</span>
                    <span><span class="dash-dot dash-dot--bad" />{{ counts.bad }}</span>
                </span>
                <span class="dash-head-total">
                    Sitet
                    <span class="dash-score" :class="'dash-score--' + level(average)">{{ average ?? '–' }}</span>
                </span>
                <button type="button" class="dash-head-btn" @click="best = !best">
                    <Icon name="sort" />
                    {{ best ? 'Bedste først' : 'Dårligste først' }}
                </button>
            </template>
        </template>

        <!-- Én side ad gangen: listen viser hvem, detaljen viser hvad. -->
        <template v-if="openPage">
            <div v-for="(check, index) in openPage.checks" :key="index" class="dash-row-item">
                <span class="dash-dot" :class="'dash-dot--' + check.level" />
                <span class="dash-row-item__body">
                    <span class="a11y-check">{{ check.hint }}</span>
                </span>
            </div>

            <a v-if="openPage.edit_url" class="dash-row-item" :href="openPage.edit_url">
                <span class="dash-row-item__body">
                    <span class="dash-row-item__title a11y-edit">Rediger siden</span>
                </span>
                <Icon class="dash-chevron" name="chevron" />
            </a>
        </template>

        <template v-else>
            <p v-if="!rows.length" class="dash-empty">Ingen udgivne sider at tjekke.</p>

            <button
                v-for="row in visible"
                :key="row.id"
                type="button"
                class="dash-row-item"
                @click="openId = row.id"
            >
                <span class="dash-score" :class="'dash-score--' + level(row.score)">{{ row.score }}</span>
                <span class="dash-row-item__body">
                    <span class="dash-row-item__title">{{ row.title }}</span>
                    <span class="dash-row-item__sub">{{ row.hint }}</span>
                </span>
                <Icon class="dash-chevron" name="chevron" />
            </button>

            <Pager :page="page" :pages="pages" @go="page = $event" />

            <p class="dash-note">
                Læst i sidens indhold: overskrifter, billeder og links. Kontrast, fokusrækkefølge og
                skabelonens egen markup måles i Tilgængelighed-panelet i Live Preview.
            </p>
        </template>
    </Card>
</template>

<style scoped>
.a11y-check {
    font-size: 0.85rem;
    line-height: 1.35;
    white-space: normal;
}

.a11y-edit {
    color: var(--theme-color-primary, #4530d8);
}
</style>
