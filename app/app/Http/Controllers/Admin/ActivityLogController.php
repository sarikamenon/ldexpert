<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivityLog\ExportActivityLogsRequest;
use App\Http\Requests\Admin\ActivityLog\IndexActivityLogRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ActivityLogController extends Controller
{
    public function index(IndexActivityLogRequest $request): View
    {
        $query = ActivityLog::with('user')
            ->latest('created_at');

        // Apply filters
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('model_type')) {
            $query->where('model_type', $request->input('model_type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->input('search') . '%');
        }

        $perPage = $request->integer('per_page', 25);
        $logs = $query->paginate($perPage)->withQueryString();

        $users = User::where('role', 'admin')
            ->orderBy('name')
            ->get();

        $actions = ActivityLog::distinct()
            ->pluck('action')
            ->sort()
            ->values();

        $modelTypes = ActivityLog::distinct()
            ->pluck('model_type')
            ->map(fn($type) => class_basename($type))
            ->sort()
            ->values();

        return view('admin.activity-logs.index', [
            'logs' => $logs,
            'users' => $users,
            'actions' => $actions,
            'modelTypes' => $modelTypes,
            'filters' => $request->validated(),
        ]);
    }

    public function export(ExportActivityLogsRequest $request): StreamedResponse
    {
        $query = ActivityLog::with('user')
            ->latest('created_at');

        // Apply same filters as index
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('model_type')) {
            $query->where('model_type', $request->input('model_type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->input('search') . '%');
        }

        $logs = $query->get();
        $filename = sprintf('activity-logs-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($logs): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'User',
                'Action',
                'Model',
                'Description',
                'IP Address',
                'Date/Time',
            ]);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->user?->name ?? 'System',
                    $log->action,
                    class_basename($log->model_type),
                    $log->description,
                    $log->ip_address,
                    $log->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}

