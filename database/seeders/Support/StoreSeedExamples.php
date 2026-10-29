<?php

namespace Database\Seeders\Support;

/** Stable, fictional business examples; keys remain internal ownership identifiers. */
class StoreSeedExamples
{
    public const int ADMIN_ROWS = 21;

    public const int STOREFRONT_ROWS = 121;

    public static function number(string $key): int
    {
        return (int) sprintf('%u', crc32($key));
    }

    public static function person(string $key): string
    {
        $first = ['Amir', 'Layla', 'Omar', 'Sara', 'Youssef', 'Nour', 'Adam', 'Lina', 'Samir', 'Maya', 'Khaled', 'Hana', 'Rami', 'Salma', 'Karim', 'Dalia', 'Tarek', 'Yasmin', 'Nabil', 'Reem'];
        $last = ['Hassan', 'Mansour', 'Saleh', 'Khalil', 'Nasser', 'Farouk', 'Hamdan', 'Darwish', 'Abbas', 'Najjar', 'Saeed', 'Rahman', 'Karimi', 'Bakri', 'Osman', 'Mahmoud', 'Hakim', 'Jaber', 'Sabbagh', 'Haddad'];
        $number = self::number($key);

        return $first[$number % count($first)].' '.$last[intdiv($number, count($first)) % count($last)];
    }

    public static function company(string $key): string
    {
        $names = ['Cedar', 'Olive Grove', 'Golden Palm', 'Atlas', 'Orchard', 'Saffron', 'Green Valley', 'Harvest', 'Levant', 'North Sea', 'Sunrise', 'Amber', 'Jasmine', 'Royal Date', 'Medina', 'Basil', 'Silk Road', 'Garden', 'Coastal', 'Market Square', 'Grand Bazaar'];
        $types = ['Foods', 'Market', 'Grocers', 'Trading', 'Delicatessen', 'Wholesale'];
        $number = self::number($key);

        return $names[$number % count($names)].' '.$types[intdiv($number, count($names)) % count($types)];
    }

    public static function product(string $key): string
    {
        $products = ['Extra Virgin Olive Oil 1 L', 'Basmati Rice 5 kg', 'Chickpeas 400 g', 'Red Lentils 1 kg', 'Medjool Dates 500 g', 'Tahini Sesame Paste 300 g', 'Bulgur Wheat 1 kg', 'Ground Cumin 100 g', 'Za’atar Spice Blend 150 g', 'Pomegranate Molasses 250 ml', 'Roasted Pistachios 250 g', 'Rose Water 250 ml', 'Turkish Coffee 250 g', 'Green Olives 700 g', 'Tomato Paste 800 g', 'Halva with Pistachios 350 g', 'Dried Apricots 500 g', 'Mint Tea 100 g', 'White Beans 1 kg', 'Grape Leaves 500 g', 'Freekeh Wheat 1 kg', 'Sumac Spice 100 g', 'Orange Blossom Water 250 ml', 'Sesame Seeds 500 g'];

        return $products[self::number($key) % count($products)];
    }

    public static function code(string $prefix, string $key): string
    {
        return $prefix.'-'.strtoupper(substr(hash('sha256', $key), 0, 12));
    }

    public static function price(string $key): int
    {
        return 20 + self::number($key) % 11;
    }
}
