// Lazy loading med sløret pladsholder, som i
// https://blog.webdevsimplified.com/2023-05/lazy-load-images/
// Artiklens kode, kørt for hver .blurred-img på siden.
function init() {
  document.querySelectorAll(".blurred-img").forEach(blurredImageDiv => {
    const img = blurredImageDiv.querySelector("img")
    function loaded() {
      blurredImageDiv.classList.add("loaded")
    }

    if (img.complete) {
      loaded()
    } else {
      img.addEventListener("load", loaded)
    }
  })
}

init()

// Live Preview morpher HTML'en og fjerner dermed loaded-klassen, så billedet
// ville stå med opacity: 0. Efter hvert morph køres det samme igen.
window.addEventListener("statamic:preview-updated", init)
