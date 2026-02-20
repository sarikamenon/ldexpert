<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;

class InvoicePaymentPolicy
{
    /**
     * Determine whether the user can view any invoice payments.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }

    /**
     * Determine whether the user can view the invoice payment.
     */
    public function view(User $user, InvoicePayment $invoicePayment): bool
    {
        return $user->role === Role::ADMIN;
    }

    /**
     * Determine whether the user can record a payment for an invoice.
     */
    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $user->role === Role::ADMIN;
    }

    /**
     * Determine whether the user can delete the invoice payment.
     */
    public function delete(User $user, InvoicePayment $invoicePayment): bool
    {
        return $user->role === Role::ADMIN;
    }
}
