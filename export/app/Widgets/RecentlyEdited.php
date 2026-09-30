<?php

namespace App\Widgets;

use App\Dashboard\SiteSnapshot;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

/** Hvor man slap — på tværs af sider, sektioner og skabeloner. */
class RecentlyEdited extends Widget
{
    public function component()
    {
        return VueComponent::render('RecentlyEdited', [
            'handle' => static::handle(),
            'rows' => app(SiteSnapshot::class)->recent((int) $this->config('limit', 6)),
        ]);
    }
}
