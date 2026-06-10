<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Invoice Email "From"
    |--------------------------------------------------------------------------
    |
    | The sender shown on invoice emails. Non-sensitive, so kept in config
    | rather than env, and kept independent of the global MAIL_FROM_* keys so
    | invoices always send as LD Expert regardless of the generic mail sender.
    | Override per-environment via the INVOICE_FROM_* env keys if ever required.
    |
    */

    'from_address' => env('INVOICE_FROM_ADDRESS', 'info@ldexpert.org'),
    'from_name' => env('INVOICE_FROM_NAME', 'LD Expert'),

    /*
    |--------------------------------------------------------------------------
    | Invoice Payment Contact & Methods
    |--------------------------------------------------------------------------
    |
    | Shown in the invoice email body. Edit here to change the contact email,
    | Venmo handle, or the mailing address for check payments.
    |
    */

    'contact_email' => 'info@ldexpert.org',

    'venmo_handle' => '@StephanieTsapakis',

    'check_mailing_address' => '706 Mesa Ridge, San Antonio, TX 78258',

];
