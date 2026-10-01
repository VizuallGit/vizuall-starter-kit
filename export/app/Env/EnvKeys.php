<?php

namespace App\Env;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * The handful of .env values the Control Panel may set.
 *
 * Why .env and not a settings file: these are secrets. Every YAML under
 * resources/ is committed, so a key pasted into a normal settings field ends
 * up on GitHub and in every site built from the starter kit. .env is
 * gitignored, which is exactly the property a key needs — the only thing
 * wrong with it was that reaching it meant a trip to Ploi.
 *
 * An allow-list, deliberately short. APP_ENV, APP_DEBUG, APP_KEY and the
 * database credentials are NOT here and should not be added:
 *
 *   - APP_ENV and APP_DEBUG decide whether a visitor sees a stack trace with
 *     those database credentials in it. One wrong click on a live site is a
 *     breach, and nothing in the Control Panel is worth that.
 *   - APP_KEY re-encrypts nothing when it changes; it just invalidates every
 *     session and encrypted value already stored.
 *
 * Values are write-only from the browser's point of view: the screen is told
 * whether a key is set, never what it says.
 */
class EnvKeys
{
    /** handle => [label, hint]. Add a row here to put a key on the screen. */
    public const KEYS = [
        'CURSOR_API_KEY' => [
            'Cursor API-nøgle',
            'Hentes på cursor.com/dashboard/api. Bruges af AI-panelet og AI-tekst.',
        ],
    ];

    public function allowed(string $key): bool
    {
        return array_key_exists($key, static::KEYS);
    }

    /** Is there a value? Never what it is. */
    public function isSet(string $key): bool
    {
        return $this->allowed($key) && trim((string) env($key, '')) !== '';
    }

    /**
     * The rows the screen draws.
     *
     * @return list<array{key: string, label: string, hint: string, set: bool}>
     */
    public function rows(): array
    {
        $out = [];

        foreach (static::KEYS as $key => [$label, $hint]) {
            $out[] = ['key' => $key, 'label' => $label, 'hint' => $hint, 'set' => $this->isSet($key)];
        }

        return $out;
    }

    public function writable(): bool
    {
        return is_file($this->path()) && is_writable($this->path());
    }

    /**
     * Write one key. An empty value removes the line.
     *
     * The file is rewritten to a temporary neighbour and renamed into place,
     * so a crash half way through cannot leave a site with a broken .env —
     * which is a site that does not boot at all.
     *
     * @return bool whether anything was written
     */
    public function set(string $key, string $value): bool
    {
        if (! $this->allowed($key) || ! $this->writable()) {
            return false;
        }

        $value = trim($value);

        // A newline would turn one value into two settings.
        if (preg_match('/[\r\n]/', $value)) {
            return false;
        }

        $lines = preg_split('/\R/', File::get($this->path())) ?: [];
        $line = $value === '' ? null : $key.'='.$this->quote($value);
        $found = false;

        foreach ($lines as $i => $existing) {
            if (! preg_match('/^\s*'.preg_quote($key, '/').'\s*=/', $existing)) {
                continue;
            }

            if ($found || $line === null) {
                unset($lines[$i]);

                continue;
            }

            $lines[$i] = $line;
            $found = true;
        }

        if (! $found && $line !== null) {
            $lines[] = $line;
        }

        $this->put(implode("\n", array_values($lines)));

        // Production caches the config, so the new value would otherwise not
        // be read until the next deploy.
        Artisan::call('config:clear');

        return true;
    }

    /** Quote only when the value needs it, so the file stays readable by hand. */
    protected function quote(string $value): string
    {
        return preg_match('/[\s#"\'=]/', $value)
            ? '"'.str_replace(['\\', '"'], ['\\\\', '\"'], $value).'"'
            : $value;
    }

    protected function put(string $contents): void
    {
        $path = $this->path();
        $temp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        File::put($temp, rtrim($contents, "\n")."\n");
        @chmod($temp, 0644);

        if (! @rename($temp, $path)) {
            @unlink($temp);
        }
    }

    public function path(): string
    {
        return base_path('.env');
    }
}
