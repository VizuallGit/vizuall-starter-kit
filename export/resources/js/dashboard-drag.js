/**
 * Træk widgets rundt på dashboardet.
 *
 * Kontrolpanelets dashboard er Statamics egen Vue-side; vi ejer kortene, ikke
 * gitteret. Derfor flyttes der ikke DOM-noder her — det ville rive noder ud
 * under Vue, som stadig regner med dem. I stedet sættes `order` på gitterets
 * flex-børn, og layoutet flytter sig selv. Vue ser ingen forskel, og
 * rækkefølgen kan trækkes hvor som helst hen uden at noget går i stykker.
 *
 * Rækkefølgen gemmes på brugeren gennem den samme rute som "Tilpas dashboard"
 * bruger, så de to ikke har hver sin sandhed.
 */

const CELL_DRAGGING = 'dash-cell-dragging';
const GRID_DRAGGING = 'is-dragging';

let cleanup = null;

function cpRoot() {
    return String(window.Statamic?.$config?.get?.('cpRoot') || '/cp').replace(/\/+$/, '');
}

function isDashboard() {
    const path = window.location.pathname.replace(/\/+$/, '') || '/';
    const root = cpRoot();

    return path === root || path === `${root}/dashboard`;
}

function csrfToken() {
    return (
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
        window.Statamic?.$config?.get?.('csrfToken') ||
        ''
    );
}

/** Gitterets direkte børn — én pr. widget — i den rækkefølge de står nu. */
function cellsOf(grid) {
    return [...grid.children].filter((cell) => cell.querySelector?.('[data-widget]'));
}

function cellFor(grid, node) {
    let el = node;

    while (el && el.parentElement !== grid) {
        el = el.parentElement;
    }

    return el && el.parentElement === grid ? el : null;
}

function apply(list) {
    list.forEach((cell, index) => {
        cell.style.order = String(index);
    });
}

function typesOf(list) {
    return list
        .map((cell) => cell.querySelector('[data-widget]')?.dataset.widget)
        .filter(Boolean);
}

async function persist(list) {
    const config = window.Statamic?.$config?.get?.('dashboardWidgets');
    const url = config?.urls?.save;
    const types = typesOf(list);

    if (!url || !types.length) {
        return;
    }

    try {
        await fetch(url, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ types }),
        });
    } catch {
        // Rækkefølgen står rigtigt på skærmen; kunne den ikke gemmes, er den
        // tilbage ved næste indlæsning. Det er en bedre fejl end en dialog.
    }
}

function start(grid, handle, event) {
    const dragged = cellFor(grid, handle);

    if (!dragged) {
        return;
    }

    event.preventDefault();

    const list = cellsOf(grid).sort((a, b) => (Number(a.style.order) || 0) - (Number(b.style.order) || 0));

    apply(list);
    grid.classList.add(GRID_DRAGGING);
    dragged.classList.add(CELL_DRAGGING);

    try {
        handle.setPointerCapture(event.pointerId);
    } catch {
        //
    }

    /** Hvilken kasse peger musen på nu — og skal den trukne ligge før eller efter den? */
    function move(moveEvent) {
        const under = document.elementFromPoint(moveEvent.clientX, moveEvent.clientY);
        const target = under && cellFor(grid, under);

        if (!target || target === dragged) {
            return;
        }

        const from = list.indexOf(dragged);
        const to = list.indexOf(target);

        if (from === -1 || to === -1) {
            return;
        }

        list.splice(from, 1);
        list.splice(to, 0, dragged);
        apply(list);
    }

    function end() {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', end);
        window.removeEventListener('pointercancel', end);

        grid.classList.remove(GRID_DRAGGING);
        dragged.classList.remove(CELL_DRAGGING);

        persist(list);
    }

    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', end);
    window.addEventListener('pointercancel', end);
}

function mount() {
    cleanup?.();
    cleanup = null;

    if (!isDashboard()) {
        return;
    }

    const grid = document.querySelector('.widgets');

    if (!grid || !cellsOf(grid).length) {
        return;
    }

    apply(cellsOf(grid));

    const onPointerDown = (event) => {
        // Kun venstre knap, og kun på selve grebet.
        if (event.button !== 0) {
            return;
        }

        const handle = event.target.closest?.('[data-dash-drag]');

        if (handle && grid.contains(handle)) {
            start(grid, handle, event);
        }
    };

    grid.addEventListener('pointerdown', onPointerDown);

    cleanup = () => grid.removeEventListener('pointerdown', onPointerDown);
}

/**
 * Dashboardet kommer både som en frisk indlæsning og som et Inertia-skift.
 * Begge skal ramme, og en widget der tegnes bagefter, skal også kunne trækkes
 * — derfor et ekstra forsøg i næste frame.
 */
function schedule() {
    requestAnimationFrame(() => {
        mount();
        requestAnimationFrame(mount);
    });
}

if (window.Statamic?.booting) {
    window.Statamic.booting(() => {
        schedule();
        window.Statamic.booted?.(schedule);
    });
} else {
    schedule();
}

['inertia:success', 'inertia:navigate'].forEach((event) => {
    document.addEventListener(event, schedule);
});
