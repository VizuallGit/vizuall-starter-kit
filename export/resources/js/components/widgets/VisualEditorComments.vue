<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Widget } from '@statamic/cms/ui';

const props = defineProps({
    pages: { type: Array, default: () => [] },
    csrf: { type: String, default: '' },
});

const pages = ref(clone(props.pages));
const filter = ref('open');
const open = ref(null);
const reply = ref('');
const busy = ref(false);
const error = ref('');
const textarea = ref(null);

watch(
    () => props.pages,
    (value) => {
        pages.value = clone(value);
    }
);

const openCount = computed(() =>
    pages.value.reduce((n, page) => n + page.comments.filter((c) => !c.resolved).length, 0)
);

const allCount = computed(() =>
    pages.value.reduce((n, page) => n + page.comments.length, 0)
);

const visiblePages = computed(() =>
    pages.value
        .map((page) => ({
            ...page,
            comments: page.comments.filter((c) => filter.value === 'all' || !c.resolved),
        }))
        .filter((page) => page.comments.length)
);

const thread = computed(() => {
    if (!open.value) {
        return null;
    }

    const page = pages.value.find((item) => item.id === open.value.pageId);

    if (!page) {
        return null;
    }

    const comment = page.comments.find((item) => item.id === open.value.commentId);

    return comment ? { page, comment } : null;
});

function clone(value) {
    return JSON.parse(JSON.stringify(value || []));
}

function timeAgo(iso) {
    const then = Date.parse(iso);

    if (!then) {
        return '';
    }

    const mins = Math.round((Date.now() - then) / 60000);

    if (mins < 1) {
        return 'nu';
    }

    if (mins < 60) {
        return `${mins} min`;
    }

    const hours = Math.round(mins / 60);

    if (hours < 24) {
        return `${hours} t`;
    }

    const date = new Date(then);
    const dd = String(date.getDate()).padStart(2, '0');
    const mm = String(date.getMonth() + 1).padStart(2, '0');

    return `${dd}/${mm}/${date.getFullYear()}`;
}

function csrfToken() {
    return (
        props.csrf ||
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
        window.Statamic?.$config?.get?.('csrfToken') ||
        window.Statamic?.$config?.get?.('csrf_token') ||
        ''
    );
}

async function request(path, options = {}) {
    const response = await fetch(path, {
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
        ...options,
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(data.message || 'Kommentaren kunne ikke gemmes.');
    }

    return data;
}

function fromApi(api, existing) {
    const messages = (api.messages || []).map((message) => ({
        id: message.id || '',
        author: message.author_name || message.author || '',
        body: String(message.body || '').trim(),
        created_at: message.created_at || null,
    }));
    const first = messages[0] || {};

    return {
        ...existing,
        id: api.id || existing.id,
        resolved: !!api.resolved,
        author: first.author || existing.author,
        body: first.body || '',
        created_at: api.created_at || first.created_at || existing.created_at,
        messages,
    };
}

function patchComment(pageId, commentId, next) {
    pages.value = pages.value.map((page) => {
        if (page.id !== pageId) {
            return page;
        }

        return {
            ...page,
            comments: page.comments.map((comment) => (comment.id === commentId ? next : comment)),
        };
    });
}

function removeComment(pageId, commentId) {
    pages.value = pages.value
        .map((page) => {
            if (page.id !== pageId) {
                return page;
            }

            return {
                ...page,
                comments: page.comments.filter((comment) => comment.id !== commentId),
            };
        })
        .filter((page) => page.comments.length);
}

async function openThread(page, comment) {
    open.value = { pageId: page.id, commentId: comment.id };
    reply.value = '';
    error.value = '';
    await nextTick();
    textarea.value?.focus();
}

function closeThread() {
    open.value = null;
    reply.value = '';
    error.value = '';
}

async function sendReply() {
    const current = thread.value;

    if (!current || busy.value) {
        return;
    }

    const body = reply.value.trim();

    if (!body) {
        return;
    }

    busy.value = true;
    error.value = '';

    try {
        const result = await request(
            `/!/sve/comments/${encodeURIComponent(current.page.id)}/${encodeURIComponent(current.comment.id)}/replies`,
            { method: 'POST', body: JSON.stringify({ body }) }
        );
        patchComment(current.page.id, current.comment.id, fromApi(result.comment, current.comment));
        reply.value = '';
    } catch (err) {
        error.value = err.message;
    } finally {
        busy.value = false;
    }
}

async function toggleResolved() {
    const current = thread.value;

    if (!current || busy.value) {
        return;
    }

    busy.value = true;
    error.value = '';

    try {
        const result = await request(
            `/!/sve/comments/${encodeURIComponent(current.page.id)}/${encodeURIComponent(current.comment.id)}`,
            { method: 'PATCH', body: JSON.stringify({ resolved: !current.comment.resolved }) }
        );
        patchComment(current.page.id, current.comment.id, fromApi(result.comment, current.comment));
    } catch (err) {
        error.value = err.message;
    } finally {
        busy.value = false;
    }
}

async function deleteThread() {
    const current = thread.value;

    if (!current || busy.value) {
        return;
    }

    if (!window.confirm('Slet denne kommentar?')) {
        return;
    }

    busy.value = true;
    error.value = '';

    try {
        await request(
            `/!/sve/comments/${encodeURIComponent(current.page.id)}/${encodeURIComponent(current.comment.id)}`,
            { method: 'DELETE' }
        );
        removeComment(current.page.id, current.comment.id);
        closeThread();
    } catch (err) {
        error.value = err.message;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Widget title="Kommentarer">
        <div v-if="thread" class="ve-thread">
            <header>
                <div>
                    <strong>Kommentar</strong>
                    <span>{{ thread.page.title }}</span>
                </div>
                <button type="button" aria-label="Luk" @click="closeThread">×</button>
            </header>

            <div class="ve-thread__section">
                <label>Sektion</label>
                <div>{{ thread.comment.section }}</div>
            </div>

            <div class="ve-thread__messages">
                <div v-for="message in thread.comment.messages" :key="message.id || message.created_at">
                    <div class="ve-thread__who">
                        <span>{{ message.author }}</span>
                        <span>{{ timeAgo(message.created_at) }}</span>
                    </div>
                    <p>{{ message.body }}</p>
                </div>
            </div>

            <form class="ve-thread__form" @submit.prevent="sendReply">
                <textarea
                    ref="textarea"
                    v-model="reply"
                    placeholder="Skriv et svar..."
                    required
                    :disabled="busy"
                />
                <p v-if="error" class="ve-thread__error">{{ error }}</p>
                <div class="ve-thread__actions">
                    <button type="submit" class="is-primary" :disabled="busy || !reply.trim()">Svar</button>
                    <button type="button" :disabled="busy" @click="toggleResolved">
                        {{ thread.comment.resolved ? 'Marker som åben' : 'Marker som løst' }}
                    </button>
                    <button type="button" :disabled="busy" @click="deleteThread">Slet</button>
                </div>
            </form>
        </div>

        <div v-else class="ve-comments">
            <div class="ve-comments__tabs">
                <button type="button" :class="{ 'is-active': filter === 'open' }" @click="filter = 'open'">
                    Åbne ({{ openCount }})
                </button>
                <button type="button" :class="{ 'is-active': filter === 'all' }" @click="filter = 'all'">
                    Alle ({{ allCount }})
                </button>
            </div>

            <div v-if="!visiblePages.length" class="ve-comments__empty">
                {{ filter === 'open' ? 'Ingen åbne kommentarer.' : 'Ingen kommentarer endnu.' }}
            </div>

            <section v-for="page in visiblePages" :key="page.id" class="ve-comments__page">
                <h3>{{ page.title }}</h3>
                <button
                    v-for="comment in page.comments"
                    :key="comment.id"
                    type="button"
                    class="ve-comments__row"
                    :class="{ 'is-resolved': comment.resolved }"
                    @click="openThread(page, comment)"
                >
                    <div class="ve-comments__meta">
                        <span>{{ comment.author }}</span>
                        <span>{{ timeAgo(comment.created_at) }}</span>
                    </div>
                    <div class="ve-comments__where">{{ comment.section }}</div>
                    <div class="ve-comments__body">{{ comment.body }}</div>
                </button>
            </section>
        </div>
    </Widget>
</template>

<style scoped>
.ve-comments,
.ve-thread {
    padding: 8px 12px 12px;
    color: inherit;
}
.ve-comments__tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 12px;
}
.ve-comments__tabs button {
    all: unset;
    cursor: pointer;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 500;
    background: color-mix(in oklab, currentColor 14%, transparent);
}
.ve-comments__tabs button.is-active {
    font-weight: 600;
    background: var(--theme-color-primary, #4530d8);
    color: #fff;
}
.ve-comments__empty {
    padding: 20px 8px;
    text-align: center;
    opacity: 0.55;
    font-size: 12px;
    line-height: 1.45;
}
.ve-comments__page + .ve-comments__page {
    margin-top: 16px;
}
.ve-comments__page h3 {
    margin: 0 0 8px;
    font-size: 13px;
    font-weight: 650;
}
.ve-comments__row {
    all: unset;
    box-sizing: border-box;
    display: block;
    width: 100%;
    cursor: pointer;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid color-mix(in oklab, currentColor 16%, transparent);
    background: color-mix(in oklab, currentColor 7%, transparent);
    color: inherit;
    text-align: left;
}
.ve-comments__row + .ve-comments__row {
    margin-top: 8px;
}
.ve-comments__row.is-resolved {
    opacity: 0.62;
}
.ve-comments__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;
}
.ve-comments__meta span:first-child {
    font-size: 11px;
    font-weight: 650;
}
.ve-comments__meta span:last-child {
    font-size: 10px;
    opacity: 0.55;
}
.ve-comments__where {
    font-size: 10px;
    opacity: 0.55;
    margin-bottom: 4px;
}
.ve-comments__body {
    font-size: 13px;
    line-height: 1.4;
    white-space: pre-wrap;
    word-break: break-word;
}
.ve-thread header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid color-mix(in oklab, currentColor 14%, transparent);
}
.ve-thread header strong {
    display: block;
    font-size: 13px;
}
.ve-thread header span {
    display: block;
    margin-top: 2px;
    font-size: 11px;
    opacity: 0.55;
}
.ve-thread header button {
    all: unset;
    cursor: pointer;
    opacity: 0.55;
    font-size: 16px;
    padding: 0 4px;
    line-height: 1;
}
.ve-thread__section {
    padding: 10px 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
    border-bottom: 1px solid color-mix(in oklab, currentColor 14%, transparent);
}
.ve-thread__section label {
    font-size: 10px;
    font-weight: 650;
    opacity: 0.55;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}
.ve-thread__section div {
    font-size: 12px;
}
.ve-thread__messages {
    padding: 10px 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 240px;
    overflow: auto;
}
.ve-thread__who {
    display: flex;
    align-items: baseline;
}
.ve-thread__who span:first-child {
    font-size: 11px;
    font-weight: 650;
}
.ve-thread__who span:last-child {
    font-size: 10px;
    opacity: 0.55;
    margin-left: 6px;
}
.ve-thread__messages p {
    margin: 3px 0 0;
    font-size: 13px;
    line-height: 1.4;
    white-space: pre-wrap;
    word-break: break-word;
}
.ve-thread__form {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding-top: 10px;
    border-top: 1px solid color-mix(in oklab, currentColor 14%, transparent);
}
.ve-thread__form textarea {
    width: 100%;
    min-height: 64px;
    resize: vertical;
    border: 1px solid color-mix(in oklab, currentColor 22%, transparent);
    border-radius: 8px;
    padding: 8px;
    font: inherit;
    font-size: 13px;
    box-sizing: border-box;
    color: inherit;
    background: color-mix(in oklab, currentColor 6%, transparent);
}
.ve-thread__form textarea:focus {
    outline: none;
    border-color: var(--theme-color-primary, #4530d8);
}
.ve-thread__error {
    margin: 0;
    font-size: 12px;
    color: #e5484d;
}
.ve-thread__actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: flex-end;
}
.ve-thread__form button {
    cursor: pointer;
    font: inherit;
    font-size: 12px;
    font-weight: 650;
    padding: 6px 10px;
    border: 0;
    border-radius: 8px;
    color: inherit;
    background: color-mix(in oklab, currentColor 12%, transparent);
}
.ve-thread__form button:disabled {
    opacity: 0.45;
    cursor: default;
}
.ve-thread__form button.is-primary {
    background: var(--theme-color-primary, #4530d8);
    color: #fff;
}
</style>
