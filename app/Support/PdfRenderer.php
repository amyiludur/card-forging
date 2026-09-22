<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;

/**
 * An HTML page rendered to a PDF by headless Chromium.
 *
 * The page is written to disk and opened as a file:// URL, which is why every
 * print view carries its CSS inline and draws its icons as inline SVG: a
 * <link>, an @font-face URL or an <img src> would not load there. Both print
 * views — the card sheet and the rulebook — go through this one place, so the
 * browser is found and the flags are passed the same way for each.
 */
class PdfRenderer
{
    private const NO_BROWSER = 'No Chromium binary found. Open the print preview and use the browser\'s "Save as PDF" instead.';

    /**
     * The rendered file, or the reason there isn't one. Reported rather than
     * thrown: a missing browser is something the designer can work around in
     * their own browser, and the page says so.
     *
     * @return array{path: ?string, error: ?string}
     */
    public function render(string $html): array
    {
        $chromium = $this->binary();

        if ($chromium === null) {
            return ['path' => null, 'error' => self::NO_BROWSER];
        }

        $work = storage_path('app/print/'.uniqid('sheet_', true));
        @mkdir($work, 0o755, true);

        $source = "{$work}/sheet.html";
        $pdf = "{$work}/sheet.pdf";

        file_put_contents($source, $html);

        $result = Process::timeout(120)->run([
            $chromium,
            '--headless',
            '--disable-gpu',
            '--no-sandbox',
            '--no-pdf-header-footer',
            '--print-to-pdf-no-header',
            "--print-to-pdf={$pdf}",
            'file://'.$source,
        ]);

        if (! $result->successful() || ! is_file($pdf)) {
            @unlink($source);

            return [
                'path' => null,
                'error' => 'Chromium could not render the PDF: '.trim($result->errorOutput() ?: 'unknown error'),
            ];
        }

        return ['path' => $pdf, 'error' => null];
    }

    public function binary(): ?string
    {
        $candidates = array_filter([
            env('CHROMIUM_BINARY'),
            '/opt/pw-browsers/chromium',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/bin/google-chrome',
        ]);

        foreach ($candidates as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }
}
