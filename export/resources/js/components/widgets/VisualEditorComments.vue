<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import Card from './lib/Card.vue';

const props = defineProps({
    handle: { type: String, default: '' },
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
    <Card :handle="handle" :title="thread ? thread.page.title : 'Kommentarer'">
        <template #tools>
            <button v-if="thread" type="button" class="dash-head-btn" @click="closeThread">Tilbage</button>
            <span v-else class="ve-tabs">
                <button type="button" :class="{ 'is-active': filter === 'open' }" @click="filter = 'open'">
                    Åbne ({{ openCount }})
                </button>
                <button type="button" :class="{ 'is-active': filter === 'all' }" @click="filter = 'all'">
                    Alle ({{ allCount }})
                </button>
            </span>
        </template>

        <!-- Én tråd ad gangen: listen viser hvem der har skrevet, tråden viser hvad. -->
        <div v-if="thread" class="ve-thread">
            <p class="ve-thread__where">{{ thread.comment.section }}</p>

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

        <template v-else>
            <p v-if="!visiblePages.length" class="dash-empty">
                {{ filter === 'open' ? 'Ingen åbne kommentarer.' : 'Ingen kommentarer endnu.' }}
            </p>

            <template v-for="page in visiblePages" :key="page.id">
                <button
                    v-for="comment in page.comments"
                    :key="comment.id"
                    type="button"
                    class="dash-row-item"
                    :class="{ 'is-resolved': comment.resolved }"
                    @click="openThread(page, comment)"
                >
                    <span class="dash-dot" :class="comment.resolved ? 'dash-dot--good' : 'dash-dot--ok'" />
                    <span class="dash-row-item__body">
                        <span class="dash-row-item__title">{{ comment.body }}</span>
                        <span class="dash-row-item__sub">
                            {{ page.title }} · {{ comment.section }} · {{ comment.author }}
                        </span>
                    </span>
                    <span class="dash-row-item__aside">{{ timeAgo(comment.created_at) }}</span>
                </button>
            </template>
        </template>
    </Card>
</template>

<style scoped>
.ve-tabs {
    display: flex;
    gap: 0.25rem;
}

.ve-tabs button {
    margin: 0;
    padding: 0.1875rem 0.625rem;
    border: 0;
    border-radius: 999px;
    background: var(--dash-field);
    color: var(--dash-muted);
    font: inherit;
    font-size: 0.6875rem;
    font-weight: 500;
    cursor: pointer;
}

.ve-tabs button.is-active {
    background: var(--theme-color-primary, #4530d8);
    color: #fff;
}

.dash-row-item.is-resolved {
    opacity: 0.6;
}

.ve-thread {
    padding: 0.75rem 1.125rem 1.125rem;
    border-top: 1px solid var(--dash-line);
}

.ve-thread__where {
    margin: 0 0 0.625rem;
    font-size: 0.75rem;
    color: var(--dash-sub);
}

.ve-thread__messages {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    max-height: 15rem;
    overflow: auto;
}

.ve-thread__who {
    display: flex;
    align-items: baseline;
    gap: 0.375rem;
}

.ve-thread__who span:first-child {
    font-size: 0.75rem;
    font-weight: 600;
}

.ve-thread__who span:last-child {
    font-size: 0.6875rem;
    color: var(--dash-sub);
}

.ve-thread__messages p {
    margin: 0.1875rem 0 0;
    font-size: 0.8125rem;
    line-height: 1.4;
    white-space: pre-wrap;
    word-break: break-word;
}

.ve-thread__form {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--dash-line);
}

.ve-thread__form textarea {
    width: 100%;
    min-height: 4rem;
    padding: 0.5rem;
    resize: vertical;
    border: 1px solid var(--dash-ring);
    border-radius: 0.5rem;
    font: inherit;
    font-size: 0.8125rem;
    color: inherit;
    background: var(--dash-field);
}

.ve-thread__form textarea:focus {
    outline: none;
    border-color: var(--theme-color-primary, #4530d8);
}

.ve-thread__error {
    margin: 0;
    font-size: 0.75rem;
    color: var(--dash-bad);
}

.ve-thread__actions {
    display: flex;
    gap: 0.375rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.ve-thread__form button {
    margin: 0;
    padding: 0.375rem 0.75rem;
    border: 0;
    border-radius: 0.5rem;
    background: var(--dash-field);
    color: inherit;
    font: inherit;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
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
