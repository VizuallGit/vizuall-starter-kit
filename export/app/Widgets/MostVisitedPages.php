<?php

namespace App\Widgets;

use App\Dashboard\PageViewStore;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

class MostVisitedPages extends Widget
{
    public function component()
    {
        $limit = (int) $this->config('limit', 8);

        return VueComponent::render('MostVisitedPages', [
            'pages' => app(PageViewStore::class)->top(max(1, $limit)),
        ]);
    }
}
