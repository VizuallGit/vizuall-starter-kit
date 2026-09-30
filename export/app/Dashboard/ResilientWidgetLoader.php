<?php

namespace App\Dashboard;

use Illuminate\Support\Facades\Log;
use Statamic\Widgets\Loader;
use Statamic\Widgets\WidgetNotFoundException;

/**
 * Statamics widget-loader, men uden at hele kontrolpanelet vælter over en
 * widget der er væk.
 *
 * Hver bruger har sin egen liste i `users/*.yaml`, og den liste overlever
 * koden: fjernes en widget — eller afinstalleres addonet der havde den —
 * kaster `Loader::load()` en WidgetNotFoundException, og så svarer
 * `/cp/dashboard` 500 for præcis de brugere der havde den valgt. De kan ikke
 * engang nå vælgeren for at fravælge den, fordi vælgeren står på dashboardet.
 *
 * En manglende widget er ikke en fejl i serveren; det er en linje i en gammel
 * liste. Den bliver sprunget over og skrevet i loggen, og resten af
 * dashboardet tegner som før. Den forsvinder helt fra listen, næste gang
 * brugeren gemmer i Tilpas dashboard, fordi `WidgetCatalog::configsFor()`
 * kun gemmer typer der findes.
 */
class ResilientWidgetLoader extends Loader
{
    public function load($name, $config)
    {
        try {
            return parent::load($name, $config);
        } catch (WidgetNotFoundException) {
            Log::warning("Dashboard-widget [{$name}] findes ikke længere og blev sprunget over.");

            return tap(new MissingWidget, fn (MissingWidget $widget) => $widget->setConfig($config));
        }
    }
}
