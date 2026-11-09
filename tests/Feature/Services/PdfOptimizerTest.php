<?php

use App\Services\PdfOptimizer;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\PendingProcess;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process as SymfonyProcess;

/** The PDF every case sends: small, but what Ghostscript gets is only checked by the fakes. */
function sentPdf(): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

beforeEach(function () {
    config([
        'services.ghostscript.compress_pdfs' => true,
        'services.ghostscript.binary' => 'gs',
        'services.ghostscript.timeout' => 30,
    ]);
    Log::spy();
});

/**
 * Fake Ghostscript: record the temp paths it was handed and answer with
 * `$respond` after writing `$writes` (if any) to its output file.
 *
 * @param  array{input?: string, output?: string}  $paths
 */
function fakeGhostscript(?string $writes, Closure $respond, array &$paths): void
{
    Process::fake(function (PendingProcess $process) use ($writes, $respond, &$paths) {
        $output = substr((string) collect($process->command)->first(fn (string $argument) => str_starts_with($argument, '-sOutputFile=')), strlen('-sOutputFile='));
        $paths = ['input' => (string) last($process->command), 'output' => $output];
        if ($writes !== null) {
            file_put_contents($output, $writes);
        }

        return $respond();
    });
}

function expectPdfKeptWithWarning(string $reason): void
{
    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context) => str_contains($message, 'the original PDF is kept') && str_contains($message, $reason)
            && $context['original_bytes'] === strlen(sentPdf()),
    );
}

test('a smaller, well-formed Ghostscript result replaces the PDF, through an argument list on temp files it removes', function () {
    $paths = [];
    fakeGhostscript("%PDF-1.5\nsmall\n%%EOF\n", fn () => Process::result(), $paths);

    expect(app(PdfOptimizer::class)->optimise(sentPdf()))->toBe("%PDF-1.5\nsmall\n%%EOF\n");

    Process::assertRan(fn (PendingProcess $process) => $process->command === [
        'gs', '-dSAFER', '-dBATCH', '-dNOPAUSE', '-dQUIET',
        '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.5', '-dPDFSETTINGS=/ebook',
        '-sOutputFile='.$paths['output'], $paths['input'],
    ] && $process->timeout === 30);
    expect(dirname($paths['input']))->toBe(sys_get_temp_dir())
        ->and(dirname($paths['output']))->toBe(sys_get_temp_dir())
        ->and(file_exists($paths['input']))->toBeFalse()
        ->and(file_exists($paths['output']))->toBeFalse();
    Log::shouldNotHaveReceived('warning');
});

test('the configured binary and timeout are used', function () {
    config(['services.ghostscript.binary' => '/opt/ghostscript/bin/gs', 'services.ghostscript.timeout' => 12]);
    $paths = [];
    fakeGhostscript("%PDF-1.5\n%%EOF", fn () => Process::result(), $paths);

    app(PdfOptimizer::class)->optimise(sentPdf());

    Process::assertRan(fn (PendingProcess $process) => $process->command[0] === '/opt/ghostscript/bin/gs' && $process->timeout === 12);
});

test('turned off, the PDF is kept as sent without running anything or warning', function () {
    config(['services.ghostscript.compress_pdfs' => false]);
    Process::fake();

    expect(app(PdfOptimizer::class)->optimise(sentPdf()))->toBe(sentPdf());

    Process::assertNothingRan();
    Log::shouldNotHaveReceived('warning');
});

test('a missing binary keeps the PDF as sent and logs a warning', function () {
    config(['services.ghostscript.binary' => '/nonexistent/ghostscript-'.Str::random(8)]);

    expect(app(PdfOptimizer::class)->optimise(sentPdf()))->toBe(sentPdf());

    Log::shouldHaveReceived('warning')->once();
});

test('the PDF is kept as sent, with a warning, when Ghostscript cannot make it smaller and well-formed', function (?string $writes, Closure $respond, string $reason) {
    $paths = [];
    fakeGhostscript($writes, $respond, $paths);

    expect(app(PdfOptimizer::class)->optimise(sentPdf()))->toBe(sentPdf());

    expectPdfKeptWithWarning($reason);
    expect(file_exists($paths['input']))->toBeFalse()
        ->and(file_exists($paths['output']))->toBeFalse();
})->with([
    'the binary is not found' => [null, fn () => Process::result(errorOutput: 'gs: not found', exitCode: 127), 'exited with code 127'],
    'a non-zero exit' => ["%PDF-1.5\n%%EOF", fn () => Process::result(errorOutput: 'Unrecoverable error', exitCode: 1), 'exited with code 1'],
    'a timeout' => [null, function () {
        $process = new SymfonyProcess(['gs']);

        throw new ProcessTimedOutException(new SymfonyProcessTimedOutException($process, SymfonyProcessTimedOutException::TYPE_GENERAL), new ProcessResult($process));
    }, ProcessTimedOutException::class],
    'no output' => [null, fn () => Process::result(), 'no valid PDF'],
    'an output that is not a PDF' => ['garbage', fn () => Process::result(), 'no valid PDF'],
    'a truncated PDF' => ['%PDF-1.5 cut', fn () => Process::result(), 'no valid PDF'],
    'a larger output' => ["%PDF-1.5\n".str_repeat('x', strlen(sentPdf()))."\n%%EOF", fn () => Process::result(), 'not smaller'],
    'an output of the same size' => [str_pad('%PDF-1.5', strlen(sentPdf()) - 6).'%%EOF'."\n", fn () => Process::result(), 'not smaller'],
]);

test('a real Ghostscript compresses a PDF holding an uncompressed scan', function () {
    $binary = (new ExecutableFinder)->find('gs');
    if ($binary === null) {
        $this->markTestSkipped('Ghostscript (gs) is not installed here; the faked runs above cover the behaviour.');
    }
    config(['services.ghostscript.binary' => $binary]);

    // One page showing a 600x600 RGB image stored raw, as some scanners write it.
    $pixels = '';
    for ($i = 0; $i < 600 * 600; $i++) {
        $pixels .= chr(200 + $i % 40).chr(200 + $i % 31).chr(190 + $i % 23);
    }
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 600 600] /Resources << /XObject << /Im1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /XObject /Subtype /Image /Width 600 /Height 600 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length '.strlen($pixels)." >>\nstream\n{$pixels}\nendstream",
        "<< /Length 30 >>\nstream\nq 600 0 0 600 0 0 cm /Im1 Do Q\nendstream",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= 'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

    $compressed = app(PdfOptimizer::class)->optimise($pdf);

    expect($compressed)->toStartWith('%PDF-')
        ->and(strlen($compressed))->toBeLessThan(intdiv(strlen($pdf), 4));
    Log::shouldNotHaveReceived('warning');
});
