/**
 * Den lokale ydelsesmåling: hvad siden vejer, målt på siden selv.
 *
 * Google kan ikke nå et lokalt domæne, og PageSpeed-kontakten er slået fra som
 * standard. Men spørgsmålet "hvad vejer den her side, og hvor sidder vægten"
 * kan browseren svare på helt selv: kontrolpanelet og det offentlige site har
 * samme origin, så en ramme med siden i kan læses direkte — resource timing,
 * vitals og det hele, uden nøgle og uden at spørge nogen.
 *
 * Den er ærlig om hvad den er: en umålt-hastighedsindlæsning på den maskine du
 * sidder ved. Den svarer ikke på "hvordan føles det på en telefon på 4G" —
 * det kan kun Google, og når den kontakt er tændt, er det dén der tæller.
 *
 * Budgetterne og vægtene herunder er de samme som Visual Editors
 * ydelses-panel bruger (`resources/js/cp/perf/report.js` i addonet), så de to
 * giver samme tal på samme side. Ændrer panelets sig, skal denne følge med —
 * `tests` holder tabellen fast, så det ikke kan ske ved et uheld.
 */

const KB = 1024;

/** [godt, dårligt] — under det første giver 100, over det andet giver 0. */
export const BUDGETS = {
    weight: [1000 * KB, 3000 * KB],
    js: [200 * KB, 700 * KB],
    image: [800 * KB, 2500 * KB],
    requests: [40, 100],
    lcp: [2500, 4000],
    cls: [0.1, 0.25],
};

/** Hvor meget hvert budget tæller. Vægten er dét man faktisk kan lave om. */
const WEIGHTS = { weight: 3, js: 2, image: 2, requests: 1, lcp: 1, cls: 1 };

const VIEWPORT_W = 1440;
const VIEWPORT_H = 900;
const LOAD_TIMEOUT = 25000;
const SETTLE_MS = 700;
const MAX_SCROLL_STEPS = 24;
const SCROLL_WAIT = 140;

const FONT_RE = /\.(woff2?|ttf|otf|eot)(\?|#|$)/i;
const IMAGE_RE = /\.(png|jpe?g|gif|webp|avif|svg|ico|bmp)(\?|#|$)/i;
const SCRIPT_RE = /\.(m?js|jsx)(\?|#|$)/i;
const STYLE_RE = /\.css(\?|#|$)/i;

/**
 * Hvilken kasse en forespørgsel hører i.
 *
 * `initiatorType` spørges, men ikke alene: en webfont hentet af et stylesheet
 * melder sig som `css`, og så ligner seks fontfiler et stort CSS-problem.
 */
function kindOf(entry) {
    const url = entry.name || '';

    if (FONT_RE.test(url)) return 'font';
    if (IMAGE_RE.test(url)) return 'image';

    switch (entry.initiatorType) {
        case 'script': return 'js';
        case 'css':
        case 'link': return STYLE_RE.test(url) ? 'css' : 'other';
        case 'img':
        case 'image':
        case 'imageset': return 'image';
        default: break;
    }

    if (SCRIPT_RE.test(url)) return 'js';
    if (STYLE_RE.test(url)) return 'css';

    return 'other';
}

function sleep(ms) {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
}

/**
 * Rammen er gennemsigtig — ikke skjult.
 *
 * `display:none` og en placering uden for skærmen stopper native lazy loading
 * i nogensinde at gå i gang, og en måling uden de dovne billeder er en måling
 * af en side ingen besøger.
 */
function createFrame(url) {
    const el = document.createElement('iframe');

    el.setAttribute('aria-hidden', 'true');
    el.setAttribute('tabindex', '-1');
    el.style.cssText =
        'position:fixed;top:0;left:0;border:0;opacity:0;pointer-events:none;z-index:-1;' +
        `width:${VIEWPORT_W}px;height:${VIEWPORT_H}px;`;
    el.src = url;
    document.body.appendChild(el);

    return el;
}

function loaded(frame) {
    return new Promise((resolve, reject) => {
        const timer = window.setTimeout(() => reject(new Error('Siden svarede ikke i tide.')), LOAD_TIMEOUT);

        frame.addEventListener('load', () => {
            window.clearTimeout(timer);
            resolve();
        }, { once: true });
    });
}

/**
 * Vitals, samlet fra denne side af rammen.
 *
 * `buffered: true` er dét der gør det muligt bagefter: observatøren oprettes
 * når rammen er indlæst og får stadig alt, browseren nåede at registrere før
 * den fandtes.
 */
function observeVitals(frameWin) {
    const state = { lcp: 0, cls: 0 };
    const observers = [];

    const on = (type, handle) => {
        try {
            const observer = new frameWin.PerformanceObserver((list) => list.getEntries().forEach(handle));

            observer.observe({ type, buffered: true });
            observers.push(observer);
        } catch {
            /* browseren kender ikke denne type */
        }
    };

    on('largest-contentful-paint', (entry) => { state.lcp = entry.startTime; });
    on('layout-shift', (entry) => { if (!entry.hadRecentInput) state.cls += entry.value; });

    return { state, stop: () => observers.forEach((o) => o.disconnect()) };
}

/** Ned gennem siden, så de dovne billeder vågner, og op igen. */
async function scrollThrough(frameWin, doc) {
    const height = Math.max(doc.body?.scrollHeight || 0, doc.documentElement?.scrollHeight || 0);
    const steps = Math.min(MAX_SCROLL_STEPS, Math.ceil(height / VIEWPORT_H));

    for (let step = 1; step <= steps; step += 1) {
        frameWin.scrollTo(0, step * VIEWPORT_H);
        await sleep(SCROLL_WAIT);
    }

    frameWin.scrollTo(0, 0);
    await sleep(SCROLL_WAIT);
}

function budgetScore(value, [good, poor]) {
    if (value <= good) return 100;
    if (value >= poor) return 0;

    return Math.round(((poor - value) / (poor - good)) * 100);
}

export function scoreOf(totals) {
    let sum = 0;
    let weight = 0;

    Object.entries(WEIGHTS).forEach(([key, factor]) => {
        // En vital browseren aldrig meldte, er ikke et nul — det er et
        // spørgsmål den her browser ikke kan svare på, og at score det som
        // fejl ville straffe siden for måleinstrumentet.
        if ((key === 'lcp' || key === 'cls') && !totals.lcp) {
            return;
        }

        sum += budgetScore(totals[key], BUDGETS[key]) * factor;
        weight += factor;
    });

    return weight ? Math.round(sum / weight) : 0;
}

export function levelOf(value, key) {
    const [good, poor] = BUDGETS[key];

    return value <= good ? 'good' : value >= poor ? 'bad' : 'ok';
}

export function formatBytes(value) {
    if (!value) return '0 kB';
    if (value < 1000 * KB) return `${Math.round(value / KB).toLocaleString('da-DK')} kB`;

    return `${(value / KB / KB).toLocaleString('da-DK', { maximumFractionDigits: 1 })} MB`;
}

/**
 * Indlæs siden, tag tallene af den, og ryd rammen væk uanset hvad.
 *
 * En side der bliver liggende i kontrolpanelet, beholder sine timere, sin
 * video og sine animationer kørende bag et panel der for længst er lukket.
 */
export async function measurePage(url) {
    const frame = createFrame(url);

    try {
        await loaded(frame);

        const frameWin = frame.contentWindow;
        const doc = frame.contentDocument;

        if (!frameWin || !doc?.body) {
            throw new Error('Siden kunne ikke læses.');
        }

        const vitals = observeVitals(frameWin);

        await sleep(SETTLE_MS);

        try {
            await doc.fonts?.ready;
        } catch {
            /* ingen fontmanager her */
        }

        await scrollThrough(frameWin, doc);
        await sleep(SETTLE_MS);

        const perf = frameWin.performance;
        const nav = perf.getEntriesByType('navigation')[0] || null;
        const seen = new Map();

        perf.getEntriesByType('resource').forEach((entry) => {
            if (entry.name.startsWith('data:') || entry.name.startsWith('blob:')) {
                return;
            }

            // `decodedBodySize` frem for `transferSize`: en anden måling på en
            // varm cache overfører næsten intet og ville melde en side der vejer nul.
            const bytes = entry.decodedBodySize || entry.transferSize || 0;
            const prior = seen.get(entry.name);

            if (!prior || bytes > prior.bytes) {
                seen.set(entry.name, { kind: kindOf(entry), bytes });
            }
        });

        const rows = [...seen.values()];
        const sum = (kind) => rows.filter((row) => row.kind === kind).reduce((n, row) => n + row.bytes, 0);

        const totals = {
            weight: rows.reduce((n, row) => n + row.bytes, 0) + (nav?.decodedBodySize || 0),
            js: sum('js'),
            image: sum('image'),
            requests: rows.length + 1,
            lcp: Math.round(vitals.state.lcp),
            cls: Math.round(vitals.state.cls * 1000) / 1000,
        };

        vitals.stop();

        return { ok: true, totals, score: scoreOf(totals) };
    } finally {
        frame.remove();
    }
}
