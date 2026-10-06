<?php

namespace App\Frontend;

/**
 * Om den færdige HTML bruger Alpine.
 *
 * Kilden er markupken, der allerede er rendret — header, sektioner og footer
 * i samme dokument. En side med x-data i headeren får scriptet. En side uden
 * direktiver får det ikke.
 */
class AlpineMarkup
{
    /**
     * Alpine-attributter, plus @click="…" og lignende. @media og @scope i CSS
     * har ikke = efter navnet, så de tæller ikke.
     */
    private const PATTERN = '/(?:^|[\s<])(?:x-(?:data|init|show|bind|on|text|html|model|modelable|for|if|transition|effect|ref|ignore|teleport|intersect|collapse|trap|cloak|id|mask|anchor|sort)(?![\w-])|@[a-zA-Z][\w:.$+\-]*?=)/';

    public static function usesAlpine(string $html): bool
    {
        return preg_match(self::PATTERN, $html) === 1;
    }
}
