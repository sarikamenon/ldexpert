<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TherapistBillPayment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TherapistBillPaymentsListController extends Controller
{
    public function index(Request $request): View
    {
        $query = TherapistBillPayment::query()
            ->with(['therapistBill.therapist', 'recordedBy'])
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at');

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->where('paid_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('paid_at', '<=', $request->to_date);
        }

        // Filter by payment method
        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }

        // Search by reference or therapist name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('therapistBill.therapist', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $payments = $query->paginate(25)->withQueryString();

        $totalAmount = TherapistBillPayment::query()
            ->when($request->filled('from_date'), fn ($q) => $q->where('paid_at', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn ($q) => $q->where('paid_at', '<=', $request->to_date))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->method))
            ->sum('amount');

        return view('admin.payments.therapist-bill-payments.index', compact('payments', 'totalAmount'));
    }
}
