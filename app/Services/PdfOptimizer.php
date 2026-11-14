<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Best-effort PDF compression through Ghostscript (`pdfwrite`, the `/ebook`
 * preset: images at 150 dpi, still legible for a scanned bill). Never fails
 * the caller: when compression is turned off, the binary is missing, times
 * out or fails, or the result is not a smaller, well-formed PDF, the original
 * bytes come back unchanged (and a warning is logged when it was attempted).
 *
 * The command is an argument list (no shell), and the only paths it names are
 * temporary files of our own in the system temp dir, removed afterwards.
 * Configured by `services.ghostscript` (binary, timeout, on/off).
 */
class PdfOptimizer
{
    public function optimise(string $contents): string
    {
        if (! config('services.ghostscript.compress_pdfs')) {
            return $contents;
        }

        $input = $output = false;
        try {
            $input = tempnam(sys_get_temp_dir(), 'pdf-in-');
            $output = tempnam(sys_get_temp_dir(), 'pdf-out-');
            if ($input === false || $output === false || file_put_contents($input, $contents) !== strlen($contents)) {
                return $this->keepOriginal($contents, 'no temporary file could be written');
            }

            $result = Process::timeout(max(1, (int) config('services.ghostscript.timeout')))->run([
                (string) config('services.ghostscript.binary'),
                '-dSAFER', '-dBATCH', '-dNOPAUSE', '-dQUIET',
                '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.5', '-dPDFSETTINGS=/ebook',
                '-sOutputFile='.$output,
                $input,
            ]);

            if ($result->failed()) {
                return $this->keepOriginal($contents, 'Ghostscript exited with code '.$result->exitCode(), [
                    'error' => mb_substr(trim($result->errorOutput()), 0, 500),
                ]);
            }

            $compressed = (string) file_get_contents($output);
            if (! $this->isPdf($compressed)) {
                return $this->keepOriginal($contents, 'Ghostscript wrote no valid PDF');
            }
            if (strlen($compressed) >= strlen($contents)) {
                return $this->keepOriginal($contents, 'the compressed PDF was not smaller', ['compressed_bytes' => strlen($compressed)]);
            }

            return $compressed;
        } catch (Throwable $exception) {
            return $this->keepOriginal($contents, $exception::class.': '.$exception->getMessage());
        } finally {
            foreach ([$input, $output] as $path) {
                if (is_string($path) && is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    /** A PDF header at the start and an end-of-file marker near the end. */
    private function isPdf(string $contents): bool
    {
        return str_starts_with($contents, '%PDF-') && str_contains(substr($contents, -1024), '%%EOF');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function keepOriginal(string $contents, string $reason, array $context = []): string
    {
        Log::warning('PDF compression skipped; the original PDF is kept: '.$reason, [
            'original_bytes' => strlen($contents),
            ...$context,
        ]);

        return $contents;
    }
}
