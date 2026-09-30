<script setup>
import { computed, ref, watch } from 'vue';
import Icon from './lib/Icon.vue';
import Pager from './lib/Pager.vue';
import { timeAgo } from './lib/time.js';
import { formatBytes, levelOf, measurePage } from './lib/measure.js';
import Card from './lib/Card.vue';

const props = defineProps({
    handle: { type: String, default: '' },
    rows: { type: Array, default: () => [] },
    average: { type: Number, default: null },
    perPage: { type: Number, default: 5 },
    endpoint: { type: String, default: null },
    saveUrl: { type: String, default: '' },
    strategy: { type: String, default: 'mobile' },
});

const rows = ref(props.rows.map((row) => ({ ...row })));
const best = ref(true);
const page = ref(0);
const busy = ref(null);
const bulk = ref(null);
const note = ref('');

watch(() => props.rows, (value) => {
    rows.value = value.map((row) => ({ ...row }));
});

/**
 * Umålte sider står altid nederst.
 *
 * De har ingen score, og en manglende score er ikke en dårlig score — læser
 * man dem som nul, ser et site der ikke er målt ud som et site der er i stykker.
 */
const sorted = computed(() =>
    [...rows.value].sort((a, b) => {
        if (a.score === null && b.score === null) return a.title.localeCompare(b.title, 'da');
        if (a.score === null) return 1;
        if (b.score === null) return -1;

        return best.value ? b.score - a.score : a.score - b.score;
    })
);

const pages = computed(() => Math.max(1, Math.ceil(sorted.value.length / props.perPage)));

const visible = computed(() => sorted.value.slice(page.value * props.perPage, (page.value + 1) * props.perPage));

const measured = computed(() => rows.value.filter((row) => row.score !== null).length);

/**
 * Hvor sitets tal kommer fra.
 *
 * De to kilder svarer på hver sit spørgsmål, så et gennemsnit af begge skal
 * sige det højt i stedet for at lade som om det er ét tal fra ét sted.
 */
const sources = computed(() => {
    const seen = new Set(rows.value.filter((row) => row.score !== null).map((row) => row.source || 'google'));

    if (seen.size === 0) return '';
    if (seen.size > 1) return 'blandet';

    return seen.has('google') ? 'Google' : 'lokal';
});

const sourceLabel = { google: 'Google', local: 'Lokal' };

const siteScore = ref(props.average);

function level(score) {
    if (score === null || score === undefined) return 'none';
    if (score >= 90) return 'good';
    if (score >= 50) return 'ok';

    return 'bad';
}

function metrics(row) {
    if (!row.lab?.length) {
        return row.score === null ? 'Ikke målt endnu' : '';
    }

    return row.lab.map((item) => `${item.label}: ${item.value}`).join(' · ');
}

function csrfToken() {
    return (
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
        window.Statamic?.$config?.get?.('csrfToken') ||
        ''
    );
}

/**
 * Mål hele listen, én side ad gangen.
 *
 * Aldrig parallelt: to sider der indlæses samtidig, konkurrerer om den samme
 * forbindelse og den samme CPU, og så måler man ikke siderne — man måler at
 * man målte to ting på én gang. Sider der allerede har et tal, springes over,
 * med mindre alle har et; så er det en genmåling, man beder om.
 */
async function measureAll() {
    if (busy.value) {
        return;
    }

    const pending = rows.value.filter((row) => row.score === null);
    const queue = pending.length ? pending : [...rows.value];

    note.value = '';

    for (const [index, row] of queue.entries()) {
        bulk.value = `${index + 1} af ${queue.length}`;

        await measure(row);

        // En fejl på den første side er en fejl på dem alle — som regel er det
        // adressen eller rettighederne, ikke siden.
        if (note.value && index === 0) {
            break;
        }
    }

    bulk.value = null;
}

/**
 * Mål én side.
 *
 * Google spørges først, hvis addonet er der: det er den eneste af de to der
 * kan svare på, hvordan siden opfører sig på en telefon på et rigtigt net.
 * Svarer den ikke — kontakten er slukket, adressen er lokal, kvoten er brugt —
 * så måles der her i browseren i stedet. Det er et andet spørgsmål (hvad vejer
 * siden), men et svar man kan handle på, og det er bedre end en tom streg.
 */
async function measure(row) {
    if (busy.value) {
        return;
    }

    busy.value = row.id;
    note.value = '';

    try {
        const google = await fromGoogle(row);

        if (google) {
            await save(row, google.score, google.lab, 'google');

            return;
        }

        const local = await measurePage(row.url);

        await save(row, local.score, labOf(local.totals), 'local');
    } catch (error) {
        note.value = error.message || 'Målingen kunne ikke gennemføres.';
    } finally {
        busy.value = null;
    }
}

/** Google, hvis den både kan og vil. Ellers null, og så måler vi selv. */
async function fromGoogle(row) {
    if (!props.endpoint) {
        return null;
    }

    const url = `${props.endpoint}?url=${encodeURIComponent(row.url)}&strategy=${props.strategy}&fresh=1`;
    let report;

    try {
        const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });

        // 403 er "kontakten er slukket". Alt andet der ikke er et svar, er
        // heller ikke et svar — begge dele er grund til at måle selv i stedet
        // for at stoppe med en fejl brugeren ikke kan gøre noget ved.
        if (response.status === 403) {
            return null;
        }

        report = await response.json();
    } catch {
        return null;
    }

    if (!report.ok) {
        // Kvoten er den ene fejl der er værd at sige højt: den går væk af sig selv
        // eller med en nøgle, mens en lokal adresse aldrig bliver til noget andet.
        if (report.reason !== 'local' && report.message) {
            note.value = `Google svarede ikke (${report.message}) — målt lokalt i stedet.`;
        }

        return null;
    }

    return {
        score: report.score,
        lab: (report.lab || []).slice(0, 3).map((item) => ({
            label: shortLabel(item),
            value: item.value,
            level: item.level,
        })),
    };
}

/** De tre tal fra den lokale måling der siger mest om hvor vægten sidder. */
function labOf(totals) {
    const rows = [
        { label: 'Vægt', value: formatBytes(totals.weight), level: levelOf(totals.weight, 'weight') },
    ];

    if (totals.lcp) {
        rows.push({
            label: 'LCP',
            value: `${(totals.lcp / 1000).toLocaleString('da-DK', { maximumFractionDigits: 1 })} s`,
            level: levelOf(totals.lcp, 'lcp'),
        });
    } else {
        rows.push({ label: 'JS', value: formatBytes(totals.js), level: levelOf(totals.js, 'js') });
    }

    rows.push({
        label: 'Forespørgsler',
        value: String(totals.requests),
        level: levelOf(totals.requests, 'requests'),
    });

    return rows;
}

async function save(row, score, lab, source) {
    const response = await fetch(props.saveUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ id: row.id, score, lab, source, strategy: props.strategy }),
    });

    if (!response.ok) {
        note.value = 'Målingen kom hjem, men kunne ikke gemmes.';

        return;
    }

    const saved = await response.json();

    row.score = score;
    row.lab = lab;
    row.source = source;
    row.measured_at = new Date().toISOString();
    siteScore.value = saved.average ?? siteScore.value;
}

/**
 * "Largest Contentful Paint" fylder en hel række alene.
 *
 * Der forkortes efter Lighthouses nøgle og ikke efter titlen: Google svarer i
 * redaktørens eget sprog, så titlen er dansk hos os og noget andet hos den
 * næste — nøglen er den samme overalt.
 */
function shortLabel(item) {
    const map = {
        'largest-contentful-paint': 'LCP',
        'total-blocking-time': 'TBT',
        'cumulative-layout-shift': 'CLS',
        'first-contentful-paint': 'FCP',
        'speed-index': 'SI',
    };

    return map[item.key] || item.label;
}
</script>

<template>
    <Card :handle="handle" title="Ydelse">
        <template #tools>
            <span class="dash-head-total">
                Sitet<template v-if="sources"> · {{ sources }}</template>
                <span class="dash-score" :class="'dash-score--' + level(siteScore)">
                    {{ siteScore ?? '–' }}
                </span>
            </span>
            <button type="button" class="dash-head-btn" :disabled="busy !== null" @click="measureAll">
                <Icon name="gauge" />
                {{ bulk ? `Måler ${bulk} …` : (measured === rows.length && rows.length ? 'Mål alle igen' : 'Mål alle') }}
            </button>
            <button type="button" class="dash-head-btn" @click="best = !best">
                <Icon name="sort" />
                {{ best ? 'Bedste først' : 'Dårligste først' }}
            </button>
        </template>

        <p v-if="!rows.length" class="dash-empty">
            Ingen udgivne sider med en offentlig adresse at måle.
        </p>

        <div v-for="row in visible" :key="row.id" class="dash-row-item">
            <span class="dash-score" :class="'dash-score--' + level(row.score)">
                {{ row.score ?? '–' }}
            </span>
            <span class="dash-row-item__body">
                <span class="dash-row-item__title">{{ row.title }}</span>
                <span class="dash-row-item__sub">
                    <template v-if="row.source">{{ sourceLabel[row.source] }} · </template>{{ metrics(row) }}
                    <template v-if="row.measured_at"> · målt {{ timeAgo(row.measured_at) }}</template>
                </span>
            </span>
            <button type="button" class="dash-head-btn" :disabled="busy !== null" @click="measure(row)">
                <Icon :name="row.score === null ? 'gauge' : 'refresh'" />
                {{ busy === row.id ? 'Måler …' : (row.score === null ? 'Mål' : 'Mål igen') }}
            </button>
        </div>

        <Pager :page="page" :pages="pages" @go="page = $event" />

        <p v-if="note" class="dash-note">{{ note }}</p>

        <p v-else-if="rows.length" class="dash-note">
            {{ measured }} af {{ rows.length }} sider er målt. Er Google PageSpeed slået til i Visual Editor,
            måler den; ellers vejes siden her i browseren.
        </p>
    </Card>
</template>
