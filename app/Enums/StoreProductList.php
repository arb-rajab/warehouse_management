<?php

namespace App\Enums;

/**
 * The store back-office product lists that share one Inertia page, keyed
 * by the segment of their legacy URIs (`/products/admin`, `/products/all`).
 * Their row actions name the list they were fired from, so each action only
 * reaches the products that list shows and returns to it.
 */
enum StoreProductList: string
{
    case InHouse = 'admin';
    case All = 'all';
    case Seller = 'seller';
}
