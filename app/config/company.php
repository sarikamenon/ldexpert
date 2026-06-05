<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company / billing entity details
    |--------------------------------------------------------------------------
    |
    | Default company information used on invoices, bills, and PDFs when no
    | matching value has been configured in the `settings` table. Admin-set
    | settings (`company.*`) always take precedence; these are the fallbacks.
    |
    */

    'name' => 'The LD Expert, LLC',

    'address' => "706 Mesa Ridge\nSan Antonio, TX 78258",

    'phone' => '',

    'email' => '',

    'tax_id' => null,

];
