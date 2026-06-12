<?php

namespace App\Fieldtypes;

use Statamic\Fields\Fieldtype;

class FontUploader extends Fieldtype
{
    protected static $handle = 'font_uploader';

    public function component(): string
    {
        return 'font-uploader';
    }

    public function preload(): array
    {
        return [];
    }

    public function process($value)
    {
        if (!$value || !str_starts_with((string) $value, '{')) {
            return $value;
        }

        $decoded = json_decode($value, true);
        if (!$decoded || empty($decoded['filename']) || empty($decoded['data'])) {
            return null;
        }

        $original = basename($decoded['filename']);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if (!in_array($ext, ['woff2', 'woff', 'ttf', 'otf'])) {
            return null;
        }

        $stem = pathinfo($original, PATHINFO_FILENAME);
        $stem = preg_replace('/[^A-Za-z0-9\-_]/', '-', $stem);
        $stem = preg_replace('/-+/', '-', $stem);
        $stem = trim($stem, '-');
        $filename = $stem . '.' . $ext;

        [, $base64] = explode(',', $decoded['data'], 2);
        file_put_contents(public_path('fonts/' . $filename), base64_decode($base64));

        return $filename;
    }
}
