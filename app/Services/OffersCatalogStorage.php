<?php

namespace App\Services;

/**
 * The offers catalogue the shop hands to customers: one PDF, kept private, sent
 * through the catalogue download routes.
 */
class OffersCatalogStorage extends PrivateDocumentStorage
{
    protected const DIRECTORY = 'uploads/offers/catalogs';

    protected const EXTENSIONS = ['pdf'];

    protected const FIELD = 'catalog';

    protected const REFUSAL = 'storefront.upload.catalog_refusal';

    protected const MAX_BYTES = 50 * 1024 * 1024;
}
