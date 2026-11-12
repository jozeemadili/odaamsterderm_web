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
    | Producer code every imported catalogue product is shown under ("by
    | Amsterdam Group"), whatever brand the workbook gives it. Empty keeps
    | each product's own brand.
    |
    */
    'catalogue_producer' => env('HUB_CATALOGUE_PRODUCER', 'BR-AMS'),

];
