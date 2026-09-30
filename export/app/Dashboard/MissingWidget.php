<?php

namespace App\Dashboard;

use Statamic\Widgets\Widget;

/**
 * Pladsholderen for en widget der ikke findes længere.
 *
 * Den tegner ingenting: hverken `html()` eller `component()` svarer, og
 * dashboardets egen controller fravælger netop de widgets der ikke gør nogen
 * af delene. Resultatet er, at den forsvundne widget bare ikke er der.
 *
 * Ligger i App\Dashboard og ikke i App\Widgets med vilje — Statamic
 * registrerer alt i app/Widgets som en type man kan vælge, og en pladsholder
 * hører ikke hjemme i vælgeren.
 */
class MissingWidget extends Widget
{
    //
}
