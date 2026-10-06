<?php

namespace App\Frontend;

/**
 * Om den færdige HTML bruger auto-kontrast.
 *
 * Samme kilde som Alpine: markupken der allerede er rendret. data-auto-contrast
 * og data-auto-contrast-hover tæller. Uden dem hentes scriptet ikke.
 */
class ContrastMarkup
{
    private const PATTERN = '/(?:^|[\s<])data-auto-contrast(?:-hover)?(?![\w-])/';

    public static function usesContrast(string $html): bool
    {
        return preg_match(self::PATTERN, $html) === 1;
    }
}
