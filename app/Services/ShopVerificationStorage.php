<?php

namespace App\Services;

/**
 * The documents a seller attaches to the shop-verification form: PDF, Word or
 * PNG/JPEG, kept private, opened by the admin who reviews the shop.
 */
class ShopVerificationStorage extends PrivateDocumentStorage
{
    protected const DIRECTORY = 'uploads/verification_form';

    protected const EXTENSIONS = ['pdf', 'doc', 'docx', 'png', 'jpg'];

    protected const FIELD = 'verification_file';

    protected const REFUSAL = 'A verification document must be a PDF, Word document or PNG/JPEG image of at most 20 MB.';
}
