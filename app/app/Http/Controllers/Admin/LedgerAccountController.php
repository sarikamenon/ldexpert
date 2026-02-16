<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LedgerAccountController extends Controller
{
    public function index(Request $request): View
    {
        $accountType = $request->get('type', 'schools');
        $search = $request->get('search');

        if ($accountType === 'schools') {
            $accounts = $this->getSchoolAccounts($search);
        } else {
            $accounts = $this->getTherapistAccounts($search);
        }

        return view('admin.ledger.accounts.index', compact('accounts', 'accountType'));
    }

    public function show(Request $request, string $type, int $id): View
    {
        if ($type === 'school') {
            $account = School::findOrFail($id);
            $accountName = $account->name;
            $accountType = 'School';
        } else {
            $account = User::where('role', Role::THERAPIST)->findOrFail($id);
            $accountName = $account->name;
            $accountType = 'Therapist';
        }

        // Get ledger entries
        $ledgerEntries = $account->ledgerEntries()
            ->with(['reference', 'recordedBy'])
            ->orderByDesc('created_at')
            ->paginate(50);

        // Calculate stats
        $stats = $this->calculateAccountStats($account, $type);

        return view('admin.ledger.accounts.show', compact(
            'account',
            'accountName',
            'accountType',
            'ledgerEntries',
            'stats',
            'type',
            'id'
        ));
    }

    private function getSchoolAccounts(?string $search)
    {
        $query = School::query()
            ->select([
                'schools.id',
                'schools.full_name',
                'schools.display_name',
                'schools.contact_email',
                'schools.contact_phone',
                'schools.created_at',
            ])
            ->withCount('invoices')
            ->leftJoin('invoices', 'schools.id', '=', 'invoices.school_id')
            ->groupBy('schools.id', 'schools.full_name', 'schools.display_name', 'schools.contact_email', 'schools.contact_phone', 'schools.created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('schools.full_name', 'like', "%{$search}%")
                    ->orWhere('schools.display_name', 'like', "%{$search}%")
                    ->orWhere('schools.contact_email', 'like', "%{$search}%");
            });
        }

        $accounts = $query->get()->map(function ($school) {
            // Get detailed stats
            $totalInvoiced = $school->invoices()->sum('total');
            $totalPaid = DB::table('invoice_payment_allocations')
                ->join('invoices', 'invoice_payment_allocations.invoice_id', '=', 'invoices.id')
                ->where('invoices.school_id', $school->id)
                ->sum('invoice_payment_allocations.allocated_amount');

            // Get current balance from ledger
            $lastEntry = $school->ledgerEntries()->latest('created_at')->first();
            $currentBalance = $lastEntry ? $lastEntry->balance_after : 0;

            $school->total_invoiced = $totalInvoiced;
            $school->total_paid = $totalPaid;
            $school->outstanding = $totalInvoiced - $totalPaid;
            $school->current_balance = $currentBalance;
            $school->transaction_count = $school->ledgerEntries()->count();

            return $school;
        });

        return $accounts->sortByDesc('outstanding');
    }

    private function getTherapistAccounts(?string $search)
    {
        $query = User::query()
            ->where('role', Role::THERAPIST)
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.created_at',
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        $accounts = $query->get()->map(function ($therapist) {
            // Get detailed stats
            $totalBilled = $therapist->therapistBills()->sum('total_due');
            $totalPaid = DB::table('therapist_bill_payment_allocations')
                ->join('therapist_bills', 'therapist_bill_payment_allocations.therapist_bill_id', '=', 'therapist_bills.id')
                ->where('therapist_bills.therapist_id', $therapist->id)
                ->sum('therapist_bill_payment_allocations.allocated_amount');

            // Get current balance from ledger
            $lastEntry = $therapist->ledgerEntries()->latest('created_at')->first();
            $currentBalance = $lastEntry ? $lastEntry->balance_after : 0;

            $therapist->total_billed = $totalBilled;
            $therapist->total_paid = $totalPaid;
            $therapist->outstanding = $totalBilled - $totalPaid;
            $therapist->current_balance = $currentBalance;
            $therapist->transaction_count = $therapist->ledgerEntries()->count();
            $therapist->bills_count = $therapist->therapistBills()->count();

            return $therapist;
        });

        return $accounts->sortByDesc('outstanding');
    }

    private function calculateAccountStats($account, string $type): array
    {
        if ($type === 'school') {
            $totalInvoiced = $account->invoices()->sum('total');
            $totalPaid = DB::table('invoice_payment_allocations')
                ->join('invoices', 'invoice_payment_allocations.invoice_id', '=', 'invoices.id')
                ->where('invoices.school_id', $account->id)
                ->sum('invoice_payment_allocations.allocated_amount');
            $invoiceCount = $account->invoices()->count();
            $paymentCount = DB::table('invoice_payment_allocations')
                ->join('invoices', 'invoice_payment_allocations.invoice_id', '=', 'invoices.id')
                ->where('invoices.school_id', $account->id)
                ->count();

            return [
                'total_invoiced' => $totalInvoiced,
                'total_paid' => $totalPaid,
                'outstanding' => $totalInvoiced - $totalPaid,
                'invoice_count' => $invoiceCount,
                'payment_count' => $paymentCount,
            ];
        } else {
            $totalBilled = $account->therapistBills()->sum('total_due');
            $totalPaid = DB::table('therapist_bill_payment_allocations')
                ->join('therapist_bills', 'therapist_bill_payment_allocations.therapist_bill_id', '=', 'therapist_bills.id')
                ->where('therapist_bills.therapist_id', $account->id)
                ->sum('therapist_bill_payment_allocations.allocated_amount');
            $billCount = $account->therapistBills()->count();
            $paymentCount = DB::table('therapist_bill_payment_allocations')
                ->join('therapist_bills', 'therapist_bill_payment_allocations.therapist_bill_id', '=', 'therapist_bills.id')
                ->where('therapist_bills.therapist_id', $account->id)
                ->count();

            return [
                'total_billed' => $totalBilled,
                'total_paid' => $totalPaid,
                'outstanding' => $totalBilled - $totalPaid,
                'bill_count' => $billCount,
                'payment_count' => $paymentCount,
            ];
        }
    }
}
