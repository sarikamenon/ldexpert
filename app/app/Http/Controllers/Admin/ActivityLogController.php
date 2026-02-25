<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\ActivityLogRowTransformer;
use App\Domain\ActivityLog\Repositories\ActivityLogRepositoryInterface;
use App\Domain\User\Services\UserService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivityLog\ActivityLogDataRequest;
use App\Http\Requests\Admin\ActivityLog\ExportActivityLogsRequest;
use App\Http\Requests\Admin\ActivityLog\IndexActivityLogRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\ActivityLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ActivityLogController extends Controller
{
    use DataTablesResponse;

    /**
     * @var array<int, string>
     */
    private const ORDER_WHITELIST = [
        0 => 'activity_logs.id',
        1 => 'users.name',
        2 => 'activity_logs.action',
        3 => 'activity_logs.model_type',
        4 => 'activity_logs.description',
        5 => 'activity_logs.ip_address',
        6 => 'activity_logs.created_at',
    ];

    public function __construct(
        private readonly ActivityLogRepositoryInterface $activityLogs,
        private readonly UserService $userService,
    ) {}

    public function index(IndexActivityLogRequest $request): View
    {
        $filters = $request->validated();
        $users = $this->userService->listAdmins();
        $actions = $this->activityLogs->distinctActions();
        $modelTypes = $this->activityLogs->distinctModelTypes();

        return view('admin.activity-logs.index', [
            'logs' => collect(),
            'users' => $users,
            'actions' => $actions,
            'modelTypes' => $modelTypes,
            'filters' => $filters,
            'datatableUrl' => route('admin.activity-logs.data'),
        ]);
    }

    public function data(ActivityLogDataRequest $request): JsonResponse
    {
        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
        $filters = [
            'user_id' => $request->input('filter_user_id'),
            'action' => $request->input('filter_action'),
            'model_type' => $request->input('filter_model_type'),
            'date_from' => $request->input('filter_date_from'),
            'date_to' => $request->input('filter_date_to'),
            'search' => $request->input('filter_search'),
        ];
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');

        $result = $this->activityLogs->listForDataTables($filters, $params);

        $rows = $result['rows']->withUserTimezone($request->user());

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $rows,
            static fn (ActivityLog $log): array => ActivityLogRowTransformer::transform($log),
        );
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
