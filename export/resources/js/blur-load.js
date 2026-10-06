// Blur-load: components/picture lægger et sløret forhåndsbillede bag
// billedet (data-blur-load). Billeder der ikke er hentet endnu skjules og
// toner frem når de er. Kun scriptet sætter is-loading, så et billede aldrig
// står skjult uden det.
for (const picture of document.querySelectorAll('[data-blur-load]')) {
    const img = picture.querySelector(':scope > img')

    if (!img || img.complete) continue

    picture.classList.add('is-loading')

    const done = () => picture.classList.remove('is-loading')
    img.addEventListener('load', done, { once: true })
    img.addEventListener('error', done, { once: true })
}
