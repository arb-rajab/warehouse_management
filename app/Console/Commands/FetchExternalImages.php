<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Upload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use Throwable;

class FetchExternalImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:otajer-photos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch images from external links and store them locally';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $failedCount = 0;

        Upload::query()
            ->select(['id', 'file_name', 'external_link'])
            ->whereNull('file_name')
            ->whereNotNull('external_link')
            ->chunkById(100, function ($uploads) use (&$failedCount): void {
                foreach ($uploads as $upload) {
                    $this->info("Upload ID {$upload->id} processing...");

                    $isUsedByProduct = Product::whereJsonContains('photos', $upload->id)
                        ->orWhereJsonContains('old_photo', $upload->id)
                        ->orWhereJsonContains('thumbnail_img', $upload->id)
                        ->exists();

                    if (! $isUsedByProduct) {
                        $upload->delete();
                        $this->info("Upload ID {$upload->id} does not exist in any product, record deleted.");

                        continue;
                    }

                    $this->info("Upload ID {$upload->id} exists in a product.");

                    if (! $this->storeExternalImage($upload)) {
                        $failedCount++;
                    }
                }
            });

        return $failedCount === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Downloads the upload's external image, stores a reduced-quality copy and
     * points the upload at it.
     */
    private function storeExternalImage(Upload $upload): bool
    {
        $externalLink = $upload->external_link;

        try {
            $response = Http::timeout(30)->get($externalLink);

            if (! $response->successful()) {
                $this->error("Failed to fetch image from: {$externalLink}");

                return false;
            }

            $storagePath = 'uploads/otajer/'.basename($externalLink);

            // Decrease the quality of the image to less than 50%
            Storage::put($storagePath, Image::make($response->body())->encode('jpg', 50));

            $upload->file_name = $storagePath;
            $upload->external_link = null;
            $upload->save();

            $this->info("Successfully fetched and stored: {$externalLink}");

            return true;
        } catch (Throwable $e) {
            $this->error("Error fetching image from: {$externalLink} - {$e->getMessage()}");

            return false;
        }
    }
}
