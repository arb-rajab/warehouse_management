<?php

namespace App\Services;

/**
 * A customer's trade licence: a PDF, Word document or PNG/JPEG image, kept
 * private. The admin download and the customer's own are the only way out.
 */
class TradeLicenseStorage extends PrivateDocumentStorage
{
    protected const DIRECTORY = 'uploads/licenses';

    protected const EXTENSIONS = ['pdf', 'doc', 'docx', 'png', 'jpg'];

    protected const FIELD = 'trade_license';

    protected const REFUSAL = 'storefront.upload.trade_license_refusal';
}
