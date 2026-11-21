<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform contact details
    |--------------------------------------------------------------------------
    |
    | Shown on the "Powered by Amsterdam Digital" footer of generated documents
    | (invoices, proformas). Phone and address are optional — the footer
    | only prints the ones that are actually set.
    |
    */
    'support_email' => env('HUB_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS')),
    'support_phone' => env('HUB_SUPPORT_PHONE'),
    'support_address' => env('HUB_SUPPORT_ADDRESS'),

    /*
    |--------------------------------------------------------------------------
    | Catalogue owner
    |--------------------------------------------------------------------------
    |
    | Producer code the products of Amsterdam's own catalogue
    | (database/data/amsterdam-catalogue.json) are shown under ("by
    | Amsterdam Group"), whoever makes them. Workbook products keep their
    | own brand. Empty keeps each catalogue product's maker.
    |
    */
    'catalogue_producer' => env('HUB_CATALOGUE_PRODUCER', 'BR-AMS'),

    /*
    | Day-old chicks: the seller that sells every hatchery's chicks and
    | manages their hatch dates (Amsterdam Group), the box size customers
    | book in, and how many days before a hatch date booking closes.
    */
    'chick_seller' => env('HUB_CHICK_SELLER', 'SEL-AMS'),
    'chick_seller_name' => env('HUB_CHICK_SELLER_NAME', 'Amsterdam Group'),
    'chick_box_size' => (int) env('HUB_CHICK_BOX_SIZE', 50),
    'chick_order_cutoff_days' => (int) env('HUB_CHICK_ORDER_CUTOFF_DAYS', 3),

    /*
    | A partner range given its own block on the marketplace home page
    | ("Silverlands feeds"). Empty hides the block.
    */
    'partner_producer' => env('HUB_PARTNER_PRODUCER', 'BR-SIL'),

];
