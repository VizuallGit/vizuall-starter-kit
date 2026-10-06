// Blur-load: components/picture og components/image viser et sløret
// forhåndsbillede (data-blur-load) mens billedet hentes. På <picture> sidder
// attributten på indpakningen, på image-komponenten på selve <img>. Kun
// scriptet sætter is-loading/is-loaded, så et billede aldrig står skjult
// uden det.
for (const el of document.querySelectorAll('[data-blur-load]')) {
    const img = el instanceof HTMLImageElement ? el : el.querySelector(':scope > img')

    if (!img || img.complete) continue

    el.classList.add('is-loading')

    img.addEventListener('load', () => el.classList.replace('is-loading', 'is-loaded'), { once: true })
    img.addEventListener('error', () => el.classList.remove('is-loading'), { once: true })
}
