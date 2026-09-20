<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    widgets: { type: Array, default: () => [] },
    usingDefault: { type: Boolean, default: true },
    canManageDefault: { type: Boolean, default: false },
    urls: { type: Object, default: () => ({}) },
});

const open = ref(false);
const busy = ref(false);
const error = ref('');
const items = ref(props.widgets.map((widget) => ({ ...widget })));
const root = ref(null);

const selectedTypes = computed(() =>
    items.value.filter((widget) => widget.enabled).map((widget) => widget.type)
);

const dirty = computed(() =>
    items.value.some((widget, index) => widget.enabled !== props.widgets[index]?.enabled)
);

function csrfToken() {
    return (
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
        window.Statamic?.$config?.get?.('csrfToken') ||
        ''
    );
}

async function request(url, method, body) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (!response.ok) {
        throw new Error('Kunne ikke gemme. Prøv igen.');
    }
}

async function save() {
    await persist('save', { types: selectedTypes.value });
}

async function saveDefault() {
    await persist('saveDefault', { types: selectedTypes.value });
}

async function reset() {
    await persist('reset');
}

async function persist(kind, body) {
    const url = props.urls[kind];

    if (!url) {
        return;
    }

    busy.value = true;
    error.value = '';

    try {
        await request(url, kind === 'reset' ? 'DELETE' : 'PUT', body);
        window.location.reload();
    } catch (e) {
        error.value = e.message || 'Kunne ikke gemme.';
        busy.value = false;
    }
}

function onDocumentClick(event) {
    if (open.value && root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div ref="root" class="ve-dash-picker">
        <button
            type="button"
            class="ve-dash-picker__toggle"
            :aria-expanded="open ? 'true' : 'false'"
            @click="open = !open"
        >
            Tilpas dashboard
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                <path d="M6 9l6 6 6-6" />
            </svg>
        </button>

        <div v-if="open" class="ve-dash-picker__panel" role="dialog" aria-label="Tilpas dashboard">
            <p class="ve-dash-picker__status">
                {{ usingDefault ? 'Du ser standarden. Dine valg overskriver kun for dig.' : 'Du har dine egne widgets. Andre brugere ser stadig standarden.' }}
            </p>

            <ul>
                <li v-for="widget in items" :key="widget.type">
                    <label>
                        <input v-model="widget.enabled" type="checkbox">
                        <span>
                            <strong>{{ widget.label }}</strong>
                            <small v-if="widget.description">{{ widget.description }}</small>
                        </span>
                    </label>
                </li>
            </ul>

            <p v-if="error" class="ve-dash-picker__error">{{ error }}</p>

            <div class="ve-dash-picker__actions">
                <button
                    v-if="!usingDefault"
                    type="button"
                    class="ve-dash-picker__ghost"
                    :disabled="busy"
                    @click="reset"
                >
                    Gendan standard
                </button>
                <button
                    type="button"
                    class="ve-dash-picker__save"
                    :disabled="busy || !dirty"
                    @click="save"
                >
                    Gem mine widgets
                </button>
            </div>

            <button
                v-if="canManageDefault"
                type="button"
                class="ve-dash-picker__default"
                :disabled="busy"
                @click="saveDefault"
            >
                Gem som standard for alle
            </button>
        </div>
    </div>
</template>

<style scoped>
.ve-dash-picker {
    position: relative;
    display: flex;
    justify-content: flex-end;
    margin: 0 12px 16px;
}
.ve-dash-picker__toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    padding: 6px 10px;
    border: 1px solid color-mix(in srgb, currentColor 18%, transparent);
    border-radius: 8px;
    background: transparent;
    color: inherit;
    font: inherit;
    font-size: 13px;
    cursor: pointer;
}
.ve-dash-picker__toggle:hover {
    background: color-mix(in srgb, currentColor 6%, transparent);
}
.ve-dash-picker__panel {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    z-index: 40;
    width: min(360px, calc(100vw - 32px));
    padding: 14px;
    border: 1px solid color-mix(in srgb, currentColor 16%, transparent);
    border-radius: 12px;
    background: var(--theme-color-bg, Canvas);
    color: inherit;
    box-shadow: 0 12px 32px color-mix(in srgb, currentColor 16%, transparent);
}
.ve-dash-picker__status {
    margin: 0 0 12px;
    font-size: 12px;
    line-height: 1.45;
    opacity: 0.7;
}
ul {
    list-style: none;
    margin: 0;
    padding: 0;
}
li + li {
    margin-top: 8px;
}
label {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    cursor: pointer;
}
input {
    margin: 4px 0 0;
}
strong {
    display: block;
    font-size: 13px;
    font-weight: 650;
}
small {
    display: block;
    margin-top: 2px;
    font-size: 12px;
    line-height: 1.4;
    opacity: 0.6;
}
.ve-dash-picker__error {
    margin: 10px 0 0;
    font-size: 12px;
    color: var(--theme-color-red, #c2410c);
}
.ve-dash-picker__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 14px;
}
.ve-dash-picker__save,
.ve-dash-picker__ghost,
.ve-dash-picker__default {
    margin: 0;
    padding: 6px 10px;
    border-radius: 8px;
    font: inherit;
    font-size: 12px;
    cursor: pointer;
}
.ve-dash-picker__save {
    border: 0;
    background: var(--theme-color-primary, #3b82f6);
    color: #fff;
}
.ve-dash-picker__ghost,
.ve-dash-picker__default {
    border: 1px solid color-mix(in srgb, currentColor 18%, transparent);
    background: transparent;
    color: inherit;
}
.ve-dash-picker__default {
    width: 100%;
    margin-top: 8px;
}
.ve-dash-picker__save:disabled,
.ve-dash-picker__ghost:disabled,
.ve-dash-picker__default:disabled {
    opacity: 0.45;
    cursor: default;
}
</style>
