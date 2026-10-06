<?php

namespace App\Frontend;

/**
 * Om den færdige HTML har billeder med sløret forhåndsbillede.
 *
 * components/picture sætter data-blur-load, medmindre kaldet siger
 * blur_load="false". Uden attributten hentes scriptet ikke.
 */
class BlurLoadMarkup
{
    private const PATTERN = '/(?:^|[\s<])data-blur-load(?![\w-])/';

    public static function usesBlurLoad(string $html): bool
    {
        return preg_match(self::PATTERN, $html) === 1;
    }
}
