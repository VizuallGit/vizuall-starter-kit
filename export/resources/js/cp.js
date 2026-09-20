// Responsive felter, blokbredde, ikon-knapgruppe, kun-én-af-hver, låste rækker
// og foldede sektioner indlæses nu af visual editor-addonet (resources/js/addon.js).

import { createApp } from 'vue';
import MostVisitedPages from './components/widgets/MostVisitedPages.vue';
import VisualEditorComments from './components/widgets/VisualEditorComments.vue';
import SeoScore from './components/widgets/SeoScore.vue';
import DashboardWidgetsPicker from './components/widgets/DashboardWidgetsPicker.vue';
import './fieldsets-folder-filter.js';

(function () {
    'use strict';

    // ── Komponent Eksport utility ─────────────────────────────────────────────
    // Komponent Eksport-komponenten er flyttet til
    // statamic-addon/component-exporter og indlæses derfra.
    // Skjul felter for ikke-admin brugere via custom conditions
    Statamic.booting(() => {
        Statamic.$components.register('MostVisitedPages', MostVisitedPages);
        Statamic.$components.register('VisualEditorComments', VisualEditorComments);
        Statamic.$components.register('SeoScore', SeoScore);

        mountDashboardWidgetsPicker();
        Statamic.booted(() => {
            mountDashboardWidgetsPicker();
            requestAnimationFrame(mountDashboardWidgetsPicker);
        });
        ['inertia:success', 'inertia:navigate'].forEach((event) => {
            document.addEventListener(event, () => requestAnimationFrame(mountDashboardWidgetsPicker));
        });

        Statamic.$conditions.add('isAdmin', function () {
            return Statamic.$permissions.has('super');
        });

        // Bruges på settings-grupper der styres af en show_settings revealer
        Statamic.$conditions.add('adminSettingsVisible', function ({ values }) {
            if (!Statamic.$permissions.has('super')) return false;
            return values?.show_settings === true;
        });

        // Skjul "Developer" replicator-gruppe for ikke-admins via MutationObserver
        if (!Statamic.$permissions.has('super')) {
            const observer = new MutationObserver(() => {
                document.querySelectorAll('.replicator-set-picker-group').forEach(group => {
                    const heading = group.querySelector('.replicator-set-picker-group-heading, [class*="group-heading"], h6, strong, span');
                    if (heading && heading.textContent.trim() === 'Developer') {
                        group.style.display = 'none';
                    }
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    });


    let pickerApp = null;

    function cpRoot() {
        return String(window.Statamic?.$config?.get?.('cpRoot') || '/cp').replace(/\/+$/, '');
    }

    function isDashboard() {
        const path = window.location.pathname.replace(/\/+$/, '') || '/';
        const root = cpRoot();

        return path === root || path === `${root}/dashboard`;
    }

    function unmountDashboardWidgetsPicker() {
        if (pickerApp) {
            pickerApp.unmount();
            pickerApp = null;
        }

        document.getElementById('dashboard-widgets-picker-host')?.remove();
    }

    function mountDashboardWidgetsPicker() {
        if (!isDashboard()) {
            unmountDashboardWidgetsPicker();
            return;
        }

        if (document.getElementById('dashboard-widgets-picker-host')) {
            return;
        }

        const config = window.Statamic?.$config?.get?.('dashboardWidgets');

        if (!config?.widgets?.length) {
            return;
        }

        const anchor = dashboardAnchor();

        if (!anchor) {
            return;
        }

        const host = document.createElement('div');
        host.id = 'dashboard-widgets-picker-host';
        anchor.parent.insertBefore(host, anchor.before);

        pickerApp = createApp(DashboardWidgetsPicker, config);
        pickerApp.mount(host);
    }

    function dashboardAnchor() {
        const widgets = document.querySelector('.widgets');

        if (widgets?.parentNode) {
            return { parent: widgets.parentNode, before: widgets };
        }

        const headers = [...document.querySelectorAll('h1')];
        const title = headers.find((el) => el.textContent.trim() === 'Dashboard');

        if (title?.parentNode) {
            return { parent: title.parentNode, before: title.nextSibling };
        }

        return null;
    }
}());
