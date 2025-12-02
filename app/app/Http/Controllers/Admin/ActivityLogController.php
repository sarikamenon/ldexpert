<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\ActivityLog\Repositories\ActivityLogRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivityLog\ExportActivityLogsRequest;
use App\Http\Requests\Admin\ActivityLog\IndexActivityLogRequest;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ActivityLogController extends Controller
{
    public function __construct(
        private readonly ActivityLogRepositoryInterface $activityLogs,
    ) {}

    public function index(IndexActivityLogRequest $request): View
    {
        $filters = $request->validated();
        $perPage = $request->integer('per_page', 25);
        /** @var LengthAwarePaginator $logs */
        $logs = $this->activityLogs->paginate($filters, $perPage);

        $logs->setCollection(
            $logs->getCollection()
                ->withUserTimezone($request->user())
        );

        $users = User::where('role', 'admin')
            ->orderBy('name')
            ->get();

        $actions = $this->activityLogs->distinctActions();
        $modelTypes = $this->activityLogs->distinctModelTypes();

        $logs->getCollection()->transform(function ($log) {
            $actionKey = $log->action ?? 'activity';
            $log->action_label = Str::headline($actionKey);
            $log->action_variant = match (true) {
                str_contains($actionKey, 'created') => 'success',
                str_contains($actionKey, 'updated') => 'primary',
                str_contains($actionKey, 'deleted') => 'danger',
                str_contains($actionKey, 'status') => 'warning',
                default => 'secondary',
            };

            return $log;
        });

        return view('admin.activity-logs.index', [
            'logs' => $logs,
            'users' => $users,
            'actions' => $actions,
            'modelTypes' => $modelTypes,
            'filters' => $filters,
        ]);
    }

    public function export(ExportActivityLogsRequest $request): StreamedResponse
    {
        $logs = $this->activityLogs
            ->all($request->validated())
            ->withUserTimezone($request->user());
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
                    $log->created_at_local?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
