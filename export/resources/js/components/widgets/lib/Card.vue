<script setup>
/**
 * Kortet alle dashboardets widgets deles om.
 *
 * Det ejer tre ting, som ellers ville blive skrevet af syv gange: rammen,
 * hovedet med titel og værktøjer, og grebet man trækker i. `data-widget`
 * er dét, træk-koden læser for at vide hvilken widget en kasse er — uden den
 * kan rækkefølgen ikke gemmes bagefter.
 */
defineProps({
    handle: { type: String, default: '' },
    title: { type: String, required: true },
});
</script>

<template>
    <div class="dash-scope" :data-widget="handle">
        <div class="dash-card dash-list">
            <div class="dash-list__head">
                <span class="dash-list__title">
                    <span class="dash-grip" data-dash-drag title="Træk for at flytte" aria-hidden="true">
                        <svg viewBox="0 0 10 16" fill="currentColor">
                            <circle cx="2.5" cy="3" r="1.3" />
                            <circle cx="7.5" cy="3" r="1.3" />
                            <circle cx="2.5" cy="8" r="1.3" />
                            <circle cx="7.5" cy="8" r="1.3" />
                            <circle cx="2.5" cy="13" r="1.3" />
                            <circle cx="7.5" cy="13" r="1.3" />
                        </svg>
                    </span>
                    {{ title }}
                </span>

                <span v-if="$slots.tools" class="dash-head-tools">
                    <slot name="tools" />
                </span>
            </div>

            <slot />
        </div>
    </div>
</template>
