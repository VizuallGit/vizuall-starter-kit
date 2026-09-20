import Alpine from 'alpinejs'
// Kontrastberegningen bor i color-scheme-addonet. Importeret fra pakken frem
// for kopieret herind, så den følger med når addonet opdateres.
import '../../vendor/statamic-addon/color-scheme/resources/js/auto-contrast.js'

window.Alpine = Alpine
Alpine.start()
