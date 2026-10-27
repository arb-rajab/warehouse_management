<?php

namespace App\Services;

use Intervention\Image\ImageManagerStatic as Image;
use InvalidArgumentException;
use Throwable;

class ImageOptimizer
{
    public const MAX_INPUT_BYTES = 20 * 1024 * 1024;

    public const MAX_PIXELS = 40_000_000;

    public const MAX_OUTPUT_BYTES = 1024 * 1024;

    public const MAX_SVG_BYTES = 512 * 1024;

    /**
     * Always re-encode untrusted images to strip metadata and appended content.
     * Legacy clients retain their format; admin uploads may request WebP.
     *
     * @return array{0: string, 1: string}
     */
    public function optimise(string $contents, ?string $format = null, int $maxDimension = 1500): array
    {
        if (strlen($contents) > self::MAX_INPUT_BYTES) {
            throw new InvalidArgumentException('The image is too large.');
        }

        $metadata = [];
        $info = @getimagesizefromstring($contents, $metadata);
        $formats = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if ($info === false || ! isset($formats[$info['mime']])) {
            throw new InvalidArgumentException('The image is not valid.');
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new InvalidArgumentException('The image is too large.');
        }
        if ($this->isAnimated($contents, $info['mime'])) {
            throw new InvalidArgumentException('Animated images are not supported. Please upload a still image.');
        }

        $format ??= $formats[$info['mime']];
        if ($format === 'webp' && ! function_exists('imagewebp')) {
            $format = $formats[$info['mime']];
        }
        $image = null;
        try {
            $image = Image::make($contents);
            match ($this->orientation($metadata['APP1'] ?? '')) {
                2 => $image->flip(),
                3 => $image->rotate(180),
                4 => $image->rotate(180)->flip(),
                5 => $image->rotate(270)->flip(),
                6 => $image->rotate(270),
                7 => $image->rotate(90)->flip(),
                8 => $image->rotate(90),
                default => null,
            };
            $image->resize($maxDimension, $maxDimension, function ($constraint): void {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            for ($attempt = 0; $attempt < 10; $attempt++) {
                $encoded = (string) $image->encode($format, 82);
                if ($encoded !== '' && strlen($encoded) <= self::MAX_OUTPUT_BYTES) {
                    return [$encoded, $format];
                }
                $image->resize(max(1, (int) ($image->width() * 0.8)), max(1, (int) ($image->height() * 0.8)));
            }
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('The image could not be compressed.', previous: $exception);
        } finally {
            $image?->destroy();
        }

        throw new InvalidArgumentException('The image could not be compressed.');
    }

    /** Read only the TIFF orientation tag, including on hosts without ext-exif. */
    private function orientation(string $app1): int
    {
        if (! str_starts_with($app1, "Exif\0\0")) {
            return 1;
        }
        $tiff = substr($app1, 6);
        $littleEndian = substr($tiff, 0, 2) === 'II';
        if (! $littleEndian && substr($tiff, 0, 2) !== 'MM') {
            return 1;
        }
        $read = static function (int $offset, int $size) use ($tiff, $littleEndian): int {
            if ($offset < 0 || $offset + $size > strlen($tiff)) {
                return 0;
            }
            $format = $size === 2 ? ($littleEndian ? 'v' : 'n') : ($littleEndian ? 'V' : 'N');
            $value = unpack($format.'value', substr($tiff, $offset, $size));

            return $value === false ? 0 : $value['value'];
        };
        if ($read(2, 2) !== 42) {
            return 1;
        }
        $directory = $read(4, 4);
        $count = min($read($directory, 2), 4096);
        for ($index = 0; $index < $count; $index++) {
            $entry = $directory + 2 + $index * 12;
            if ($read($entry, 2) === 0x0112 && $read($entry + 2, 2) === 3 && $read($entry + 4, 4) === 1) {
                return $read($entry + 8, 2);
            }
        }

        return 1;
    }

    /** Detect animation before GD could silently flatten it. */
    private function isAnimated(string $contents, string $mime): bool
    {
        if ($mime === 'image/webp') {
            return substr($contents, 12, 4) === 'VP8X' && (ord($contents[20] ?? "\0") & 2) !== 0;
        }
        if ($mime === 'image/png') {
            $offset = 8;
            $length = strlen($contents);
            while ($offset + 12 <= $length) {
                if (substr($contents, $offset + 4, 4) === 'acTL') {
                    return true;
                }
                $chunk = unpack('Nlength', substr($contents, $offset, 4));
                if ($chunk === false) {
                    break;
                }
                $offset += 12 + $chunk['length'];
            }

            return false;
        }
        if ($mime !== 'image/gif') {
            return false;
        }

        $length = strlen($contents);
        $offset = 13 + ((ord($contents[10] ?? "\0") & 128) ? 3 * (2 << (ord($contents[10]) & 7)) : 0);
        $frames = 0;
        while ($offset < $length) {
            $block = ord($contents[$offset++]);
            if ($block === 0x3B) {
                break;
            }
            if ($block === 0x2C) {
                if (++$frames > 1) {
                    return true;
                }
                if ($offset + 9 > $length) {
                    break;
                }
                $packed = ord($contents[$offset + 8]);
                $offset += 9 + (($packed & 128) ? 3 * (2 << ($packed & 7)) : 0) + 1;
            } elseif ($block === 0x21) {
                $offset++;
            } else {
                break;
            }
            while ($offset < $length && ($size = ord($contents[$offset++])) > 0) {
                $offset += $size;
            }
        }

        return false;
    }
}
