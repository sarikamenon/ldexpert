<?php

return [
    'company_name' => env('FINANCE_COMPANY_NAME', 'The LD Expert, LLC'),
    'irs_tax_status' => env('FINANCE_IRS_TAX_STATUS', '1099-NEC'),

    /*
     * The user ID used as the ledger account holder for operating expenses
     * (rent, software, marketing, etc.) that have no school/therapist counterparty.
     * This must be the admin user and must never be hard-deleted.
     */
    'business_account_user_id' => env('FINANCE_BUSINESS_ACCOUNT_USER_ID', 1),
];
