<?php

namespace App\Frontend;

/**
 * Om den færdige HTML har billeder med sløret pladsholder (.blurred-img).
 *
 * components/picture og components/image lægger billedet i en
 * <div class="blurred-img">, medmindre kaldet siger blur_load="false". Uden
 * klassen hentes scriptet ikke.
 */
class BlurredImgMarkup
{
    private const PATTERN = '/\sclass=(["\'])[^"\']*(?<![\w-])blurred-img(?![\w-])/';

    public static function usesBlurredImg(string $html): bool
    {
        return preg_match(self::PATTERN, $html) === 1;
    }
}
