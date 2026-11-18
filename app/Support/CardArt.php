<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * A card's picture: where it lives, how big its window is, and how it fills it.
 *
 * Pictures are files in design/art/<kind>/, beside the JSON that names them,
 * because the design folder is the source of truth and an import deletes
 * whatever it does not have. A card row holds the path and three placement
 * settings, each null for "default", and this class is the one place the
 * defaults are decided. resources/js/cardArt.js is the other half of the same
 * rules, for the editor's preview — change one and change the other.
 */
class CardArt
{
    /** The card kinds that have an editor and so can carry a picture. The setup card has neither. */
    public const KINDS = ['entity', 'board', 'town', 'player', 'character', 'beat', 'tale'];

    /** Percent of the card: width on a landscape entity card, height on a portrait one. */
    public const DEFAULT_SIZE = 40;

    /** Crop to fill the window, or show the whole picture with the tint around it. */
    public const FITS = ['cover', 'contain'];

    public const DEFAULT_FOCUS = [50, 50];

    public const MIMES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    /** Upload cap, in kilobytes. */
    public const MAX_KB = 8192;

    /**
     * The biggest picture this server will really take, in kilobytes. PHP
     * refuses anything over upload_max_filesize or post_max_size before the
     * app sees it, so the cap is the smallest of the three — 2 MB on a PHP
     * left at its defaults, however generous MAX_KB is.
     */
    public static function maxKb(): int
    {
        return min(
            self::MAX_KB,
            self::iniKb(ini_get('upload_max_filesize')),
            self::iniKb(ini_get('post_max_size')),
        );
    }

    /** A php.ini size ("2M", "512K", "1G") in kilobytes; 0 or empty is no limit. */
    public static function iniKb(string|false $value): int
    {
        $value = trim((string) $value);
        $number = (float) $value;

        if ($number <= 0) {
            return self::MAX_KB;
        }

        return (int) match (strtoupper(substr($value, -1))) {
            'G' => $number * 1024 * 1024,
            'M' => $number * 1024,
            'K' => $number,
            default => $number / 1024,
        };
    }

    public static function designPath(): string
    {
        return rtrim((string) config('cardforge.design_path'), '/\\');
    }

    /**
     * How much of the card the window takes, in percent. Every card has one at
     * 40% by default, blank until there is a picture — the designer's call
     * (2026-11-18), so a card looks the way it will once it is drawn for. A
     * size the designer set wins, and 0 means no window at all. $art and
     * $landscape no longer change the default; they stay in the signature so
     * both halves read the same and a per-kind default can come back.
     */
    public static function windowSize(?string $art, ?int $size, bool $landscape): int
    {
        return $size ?? self::DEFAULT_SIZE;
    }

    /**
     * The file on disk a path names, or null when there is none — including
     * any path that would resolve outside the design folder's art directory,
     * which is how a request for "../../.env" gets nothing.
     */
    public static function absolute(?string $art, ?string $designPath = null): ?string
    {
        if ($art === null || $art === '' || ! str_starts_with($art, 'art/')) {
            return null;
        }

        $designPath = rtrim($designPath ?? self::designPath(), '/\\');
        $root = realpath($designPath.'/art');
        $file = realpath($designPath.'/'.$art);

        if ($root === false || $file === false || ! is_file($file)) {
            return null;
        }

        return str_starts_with($file, $root.DIRECTORY_SEPARATOR) ? $file : null;
    }

    /**
     * Store an upload as art/<kind>/<slug>.<ext> and return that path. A name
     * already taken is numbered rather than replaced, because another card may
     * be using the picture that is there.
     */
    public static function store(UploadedFile $file, string $kind, string $name): string
    {
        $slug = Str::slug($name) ?: 'card';
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $directory = self::designPath().'/art/'.$kind;

        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        $candidate = $slug;

        for ($n = 2; is_file("{$directory}/{$candidate}.{$extension}"); $n++) {
            $candidate = "{$slug}-{$n}";
        }

        $file->move($directory, "{$candidate}.{$extension}");

        return "art/{$kind}/{$candidate}.{$extension}";
    }

    /** The class a print sheet draws one picture with, however many cards use it. */
    public static function cssClass(string $art): string
    {
        return 'art-'.substr(sha1($art), 0, 12);
    }

    /** The picture inlined, because the print sheet is rendered from file:// and fetches nothing. */
    public static function dataUri(string $art): ?string
    {
        $file = self::absolute($art);

        if ($file === null) {
            return null;
        }

        $mime = self::MIMES[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($file));
    }

    /** [x, y], each 0–100, whatever the column held. */
    public static function focus(mixed $focus): array
    {
        if (! is_array($focus) || count($focus) !== 2) {
            return self::DEFAULT_FOCUS;
        }

        return array_map(fn ($v) => max(0, min(100, (int) $v)), array_values($focus));
    }

    /**
     * What every card face needs to draw the window, for CardPresenter. The
     * raw size travels beside the resolved one so the editor can tell "left at
     * the default" from "set to 40".
     *
     * @return array<string, mixed>
     */
    public static function present(Model $card, bool $landscape): array
    {
        $art = $card->art !== null && $card->art !== '' ? $card->art : null;
        $found = $art !== null && self::absolute($art) !== null;

        return [
            'art' => $art,
            'art_size' => $card->art_size,
            'art_window' => self::windowSize($art, $card->art_size, $landscape),
            'art_fit' => in_array($card->art_fit, self::FITS, true) ? $card->art_fit : 'cover',
            'art_focus' => self::focus($card->art_focus),
            // Reported, never cleared: the card keeps the path it was given.
            'art_missing' => $art !== null && ! $found,
            'art_class' => $found ? self::cssClass($art) : null,
        ];
    }

    /**
     * The "art" key a design file writes, or nothing at all when the card has
     * no picture, so a folder nobody has drawn for exports byte for byte. Just
     * the path when every setting is at its default; otherwise an object of
     * the path and whichever settings differ from the default.
     *
     * @return array<string, string|array<string, mixed>>
     */
    public static function forDesign(Model $card): array
    {
        $file = $card->art !== null && $card->art !== '' ? $card->art : null;

        $settings = array_filter([
            'size' => $card->art_size,
            'fit' => $card->art_fit !== null && $card->art_fit !== 'cover' ? $card->art_fit : null,
            'focus' => $card->art_focus !== null && self::focus($card->art_focus) !== self::DEFAULT_FOCUS
                ? self::focus($card->art_focus)
                : null,
        ], fn ($v) => $v !== null);

        // A window can be sized with no picture in it yet — 0 hides an entity
        // card's — so settings are written even without a file. Nothing set
        // at all writes nothing, so a folder nobody has drawn for is unchanged.
        if ($file === null) {
            return $settings === [] ? [] : ['art' => $settings];
        }

        return ['art' => $settings === [] ? $file : ['file' => $file, ...$settings]];
    }

    /**
     * The four columns a design file's "art" key means, either form of it. A
     * file without the key clears them, the same way the folder clears
     * anything else it no longer says.
     *
     * @return array{art: ?string, art_size: ?int, art_fit: ?string, art_focus: ?array}
     */
    public static function fromDesign(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            return ['art' => $value, 'art_size' => null, 'art_fit' => null, 'art_focus' => null];
        }

        if (! is_array($value)) {
            return ['art' => null, 'art_size' => null, 'art_fit' => null, 'art_focus' => null];
        }

        return [
            // Absent when the window is sized before there is a picture.
            'art' => is_string($value['file'] ?? null) && $value['file'] !== '' ? $value['file'] : null,
            'art_size' => isset($value['size']) ? (int) $value['size'] : null,
            'art_fit' => $value['fit'] ?? null,
            'art_focus' => isset($value['focus']) ? self::focus($value['focus']) : null,
        ];
    }

    /** Every card model that can carry a picture. */
    public const MODELS = [
        \App\Models\EntityCard::class,
        \App\Models\BoardCard::class,
        \App\Models\TownAction::class,
        \App\Models\PlayerCard::class,
        \App\Models\Character::class,
        \App\Models\StoryBeat::class,
        \App\Models\TaleCard::class,
    ];

    /**
     * Pictures in the design folder that no card names, as art/… paths. Taking
     * a picture off a card never deletes it — a duplicate may share it — so
     * this is how the leftovers are found. Reported, never removed.
     *
     * @return list<string>
     */
    public static function orphans(?string $designPath = null): array
    {
        $designPath = rtrim($designPath ?? self::designPath(), '/\\');
        $root = $designPath.'/art';

        if (! is_dir($root)) {
            return [];
        }

        $used = collect(self::MODELS)
            ->flatMap(fn (string $model) => $model::query()->whereNotNull('art')->pluck('art'))
            ->flip();

        $files = [];
        $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($walk as $file) {
            if (! $file->isFile() || ! isset(self::MIMES[strtolower($file->getExtension())])) {
                continue;
            }

            $path = 'art/'.str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            if (! isset($used[$path])) {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }

    /** The four fields as every card editor validates them. */
    public static function rules(): array
    {
        return [
            'art' => ['nullable', 'string', 'max:255', 'starts_with:art/', 'not_regex:#(^|/)\.\.(/|$)#'],
            'art_size' => ['nullable', 'integer', 'min:0', 'max:90'],
            'art_fit' => ['nullable', 'in:'.implode(',', self::FITS)],
            'art_focus' => ['nullable', 'array', 'size:2'],
            'art_focus.*' => ['integer', 'min:0', 'max:100'],
        ];
    }
}
