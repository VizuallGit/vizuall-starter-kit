<?php

namespace App\Widgets;

use App\Dashboard\SiteSnapshot;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

/**
 * Den ene liste over alt der venter: åbne kommentarer, kladder, manglende
 * meta-tekst og billeder uden alt-tekst. Er den tom, siger den det — et tomt
 * kort uden svar ligner en fejl.
 */
class NeedsAttention extends Widget
{
    public function component()
    {
        return VueComponent::render('NeedsAttention', [
            'handle' => static::handle(),
            'items' => app(SiteSnapshot::class)->attention(),
        ]);
    }
}
