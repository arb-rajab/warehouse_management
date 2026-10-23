<?php

namespace App\Http\Controllers;

use App\Services\Sitemap;
use Illuminate\Http\Response;

/**
 * The storefront's sitemap index and its files (App\Services\Sitemap).
 */
class SitemapController extends Controller
{
    public function index(Sitemap $sitemap): Response
    {
        return $this->xml($sitemap->index());
    }

    public function show(Sitemap $sitemap, string $source, int $page): Response
    {
        $xml = $sitemap->urlset($source, $page);

        abort_if($xml === null, 404);

        return $this->xml($xml);
    }

    private function xml(string $document): Response
    {
        return response($document, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age='.Sitemap::TTL,
        ]);
    }
}
