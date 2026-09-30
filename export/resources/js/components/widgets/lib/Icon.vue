<script setup>
/**
 * Dashboardets ikoner, tegnet her i stedet for hentet fra et ikonsæt.
 *
 * Statamics egne ikoner registreres i kontrolpanelets Vue-app; en widget er
 * monteret som sin egen komponent og kan ikke regne med at have dem. Ti streger
 * SVG vejer mindre end den afhængighed.
 */
defineProps({
    name: { type: String, required: true },
});

const paths = {
    search: ['M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14Z', 'm20 20-4-4'],
    plus: ['M12 5v14', 'M5 12h14'],
    preview: ['M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z', 'M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z'],
    sections: ['M4 5h16v5H4z', 'M4 14h16v5H4z'],
    templates: ['M4 4h16v4H4z', 'M4 12h6v8H4z', 'M14 12h6v8h-6z'],
    images: ['M4 5h16v14H4z', 'm5 16 4.5-5 3.5 4 2.5-3 3.5 4', 'M9 9.5a1 1 0 1 0 0-.001Z'],
    chevron: ['m9 6 6 6-6 6'],
    caret: ['m6 9 6 6 6-6'],
    'caret-left': ['m15 6-6 6 6 6'],
    gauge: ['M12 13.5 16 9', 'M3.5 17a9 9 0 1 1 17 0'],
    eye: ['M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z', 'M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z'],
    sort: ['M7 4v16', 'm4 17 3 3 3-3', 'M17 20V4', 'm14 7 3-3 3 3'],
    refresh: ['M20 11a8 8 0 1 0-2.3 6', 'M20 5v6h-6'],
    check: ['m5 12.5 4.5 4.5L19 7'],
    alert: ['M12 4v10', 'M12 18.5v.5'],
    comment: ['M4 5h16v11H9l-5 4V5Z'],
    draft: ['M5 4h9l5 5v11H5z', 'M14 4v5h5'],
    tag: ['M4 4h7l9 9-7 7-9-9V4Z', 'M8.5 8.5v.01'],
};
</script>

<template>
    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.7"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path v-for="(d, index) in paths[name] || []" :key="index" :d="d" />
    </svg>
</template>
