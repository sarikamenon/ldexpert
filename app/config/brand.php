<?php

return [

    /*
     * The public-facing brand name used in email subjects and footers.
     * Override via BRAND_NAME in .env.
     */
    'name' => env('BRAND_NAME', 'LD Expert Services'),

    /*
     * Full legal / copyright name shown in email footers.
     * Override via BRAND_COPYRIGHT_NAME in .env.
     */
    'copyright_name' => env('BRAND_COPYRIGHT_NAME', 'NOVA - Neuroaffirming Operations & Virtual Administration'),

    /*
     * Support / contact email shown to recipients in automated emails.
     * Override via BRAND_SUPPORT_EMAIL in .env.
     */
    'support_email' => env('BRAND_SUPPORT_EMAIL', 'info@LDexpert.org'),

];
