// Lazy loading med sløret pladsholder, som i
// https://blog.webdevsimplified.com/2023-05/lazy-load-images/
// Artiklens kode (fadeIn), kørt for hver .blurred-img på siden.
//
// Ud over artiklen: det fulde billede hentes først, når det er 100 px fra
// skærmen. loading="lazy" henter 1250–2500 px før, så skabelonen skriver det
// fulde billede i data-src/data-srcset, og src er en gennemsigtig SVG med
// billedets mål; her flyttes det over, når IntersectionObserver ser billedet.

function fadeIn(blurredImageDiv) {
  const img = blurredImageDiv.querySelector("img")
  function loaded() {
    blurredImageDiv.classList.add("loaded")
  }

  if (img.complete) {
    loaded()
  } else {
    img.addEventListener("load", loaded)
  }
}

// Venter billedet stadig på at komme på skærmen? Efter et Live Preview-morph
// står src igen på SVG'en, og så venter det igen.
function waiting(img) {
  return img.dataset.src !== undefined && img.getAttribute("src") !== img.dataset.src
}

function show(blurredImageDiv) {
  blurredImageDiv.querySelectorAll("[data-srcset]").forEach(el => {
    el.setAttribute("srcset", el.dataset.srcset)
  })
  const img = blurredImageDiv.querySelector("img")
  // Det er tid nu — loading="lazy" ville ellers vente på Chromes egen afstand.
  img.loading = "eager"
  img.setAttribute("src", img.dataset.src)
  fadeIn(blurredImageDiv)
}

const observer = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (!entry.isIntersecting) return
    observer.unobserve(entry.target)
    show(entry.target)
  })
}, { rootMargin: "100px 0px" })

function init() {
  document.querySelectorAll(".blurred-img").forEach(blurredImageDiv => {
    const img = blurredImageDiv.querySelector("img")

    if (!waiting(img)) {
      fadeIn(blurredImageDiv)
    } else if (navigator.webdriver) {
      // Screenshots (Visual Editors Patterns-billeder) skal have alle billeder.
      show(blurredImageDiv)
    } else {
      observer.observe(blurredImageDiv)
    }
  })
}

init()

// Live Preview morpher HTML'en og fjerner dermed loaded-klassen og det src,
// scriptet har sat. Efter hvert morph køres det samme igen.
window.addEventListener("statamic:preview-updated", init)
