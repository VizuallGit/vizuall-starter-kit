/**
 * Søgbart mappefilter på Fieldsets-listen.
 * Ligger på document.body — overskriver ikke Statamics view eller Vue-træ.
 */
const HOST_ID = 'fs-folder-filter';
const HIDE_CLASS = 'fs-ff-hide';

let query = '';
let listOpen = false;
let folderKeys = [];
let started = false;

function isFieldsetsIndex() {
    const path = window.location.pathname.replace(/\/+$/, '') || '/';

    return /\/fields\/fieldsets$/.test(path);
}

function sync() {
    try {
        if (!isFieldsetsIndex()) {
            teardown();
            return;
        }

        ensureHost();
        positionHost();
        applyFilter();
        renderList();
    } catch (error) {
        console.warn('Fieldsets folder filter', error);
    }
}

function teardown() {
    try {
        document.getElementById(HOST_ID)?.remove();
        restoreGroups();
        listOpen = false;
    } catch (_) {
        // Aldrig vælte CP.
    }
}

function ensureHost() {
    if (document.getElementById(HOST_ID)) {
        return;
    }

    const host = document.createElement('div');
    host.id = HOST_ID;

    const input = document.createElement('input');
    input.type = 'search';
    input.className = 'cp-input fs-folder-filter__input';
    input.placeholder = 'Filtrer mapper…';
    input.autocomplete = 'off';
    input.setAttribute('aria-label', 'Filtrer fieldset-mapper');
    input.value = query;
    input.addEventListener('input', () => {
        query = input.value;
        listOpen = true;
        applyFilter();
        renderList();
    });
    input.addEventListener('focus', () => {
        listOpen = true;
        collectFolders();
        renderList();
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            listOpen = false;
            renderList();
            input.blur();
        }
    });

    const list = document.createElement('ul');
    list.className = 'fs-folder-filter__list';
    list.hidden = true;
    list.style.backgroundColor = document.documentElement.classList.contains('dark')
        || document.body.classList.contains('dark')
        ? '#1a1a1a'
        : '#ffffff';

    host.append(input, list);
    document.body.append(host);
}

function createButton() {
    return [...document.querySelectorAll('a, button')].find((el) => {
        const text = el.textContent.replace(/\s+/g, ' ').trim();

        return /^create fieldset$/i.test(text) || /^opret fieldset$/i.test(text);
    }) || null;
}

function positionHost() {
    const host = document.getElementById(HOST_ID);

    if (!host) {
        return;
    }

    const button = createButton();

    if (!button) {
        host.style.top = '';
        host.style.left = '';
        host.style.right = '';
        return;
    }

    const rect = button.getBoundingClientRect();
    const width = host.offsetWidth || 224;
    const height = host.offsetHeight || 36;

    host.style.right = 'auto';
    host.style.top = `${Math.round(rect.top + (rect.height - height) / 2)}px`;
    host.style.left = `${Math.max(16, Math.round(rect.left - 12 - width))}px`;
}

function collectFolders() {
    const fromDom = groupSections()
        .map(groupName)
        .filter(Boolean);

    if (fromDom.length) {
        folderKeys = [...new Set(fromDom)];
        return folderKeys;
    }

    return folderKeys;
}

function groupSections() {
    const wrappers = [
        ...document.querySelectorAll('[data-max-width-wrapper] section > div'),
        ...document.querySelectorAll('section.space-y-6 > div'),
    ];

    return [...new Set(wrappers)].filter((el) => groupName(el));
}

function groupName(section) {
    const heading = section.querySelector('[data-ui-subheading], h2, h3');

    return heading?.textContent.trim() || '';
}

function applyFilter() {
    const needle = query.trim().toLowerCase();
    const groups = groupSections();

    if (groups.length) {
        groups.forEach((section) => {
            const name = groupName(section);
            section.classList.toggle(HIDE_CLASS, Boolean(needle) && !name.toLowerCase().includes(needle));
        });
        return;
    }

    folderKeys.forEach((name) => {
        const show = !needle || name.toLowerCase().includes(needle);
        headingFor(name)?.closest('div')?.classList.toggle(HIDE_CLASS, !show);
    });
}

function headingFor(name) {
    return [...document.querySelectorAll('[data-ui-subheading], h2, h3')].find((el) => (
        el.textContent.trim() === name
    ));
}

function restoreGroups() {
    document.querySelectorAll(`.${HIDE_CLASS}`).forEach((section) => {
        section.classList.remove(HIDE_CLASS);
    });
}

function renderList() {
    const host = document.getElementById(HOST_ID);
    const list = host?.querySelector('ul');

    if (!list) {
        return;
    }

    const needle = query.trim().toLowerCase();
    const names = collectFolders().filter((name) => !needle || name.toLowerCase().includes(needle));

    list.replaceChildren();

    if (!listOpen || !names.length) {
        list.hidden = true;
        return;
    }

    names.forEach((name) => {
        const item = document.createElement('li');
        item.textContent = name;
        item.addEventListener('mousedown', (event) => {
            event.preventDefault();
            query = name;
            const input = host.querySelector('input');
            if (input) {
                input.value = name;
            }
            listOpen = false;
            applyFilter();
            renderList();
        });
        list.append(item);
    });

    list.hidden = false;
}

function rememberInertiaFolders(event) {
    try {
        const page = event.detail?.page;
        const fieldsets = page?.props?.fieldsets;

        if (page?.component === 'fieldsets/Index' && fieldsets && typeof fieldsets === 'object') {
            folderKeys = Object.keys(fieldsets);
        }
    } catch (_) {
        // Ignore.
    }
}

function onDocumentMouseDown(event) {
    const host = document.getElementById(HOST_ID);

    if (!host || host.contains(event.target)) {
        return;
    }

    listOpen = false;
    renderList();
}

function start() {
    if (started) {
        return;
    }

    started = true;

    const kick = () => {
        rememberInertiaFolders({ detail: {} });
        sync();
    };

    document.addEventListener('inertia:success', (event) => {
        rememberInertiaFolders(event);
        requestAnimationFrame(sync);
    });
    document.addEventListener('inertia:navigate', () => requestAnimationFrame(sync));
    window.addEventListener('scroll', () => {
        if (isFieldsetsIndex()) {
            positionHost();
        }
    }, true);
    window.addEventListener('resize', () => {
        if (isFieldsetsIndex()) {
            positionHost();
        }
    });
    document.addEventListener('mousedown', onDocumentMouseDown);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', kick);
    } else {
        kick();
    }

    setTimeout(sync, 300);
    setTimeout(sync, 1000);
}

start();
