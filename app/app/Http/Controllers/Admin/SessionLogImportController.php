<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\SessionLogImportRowTransformer;
use App\Domain\SessionLog\Services\SessionLogImportListService;
use App\Domain\SessionLog\Services\SessionLogImportService;
use App\DTOs\StoreSessionLogImportDTO;
use App\Enums\SessionLogImportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SessionLog\ImportSessionLogsRequest;
use App\Http\Requests\Admin\SessionLog\SessionLogImportDataRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\SessionLogImport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SessionLogImportController extends Controller
{
    use DataTablesResponse;

    /**
     * @var array<int, string>
     */
    private const ORDER_WHITELIST = [
        0 => 'session_log_imports.id',
        1 => 'session_log_imports.type',
        2 => 'session_log_imports.file_name',
        4 => 'session_log_imports.status',
        6 => 'session_log_imports.created_at',
    ];

    public function __construct(
        private readonly SessionLogImportService $importService,
        private readonly SessionLogImportListService $importListService,
    ) {}

    public function showImportForm(): View
    {
        $this->authorize('create', SessionLogImport::class);

        $type = SessionLogImportType::RSM;
        $template = $this->importService->getTemplate($type);

        return view('admin.session-log-imports.import-form', [
            'importTypes' => SessionLogImportType::cases(),
            'requiredColumns' => $template['required_columns'] ?? [],
            'optionalColumns' => $template['optional_columns'] ?? [],
        ]);
    }

    public function import(ImportSessionLogsRequest $request): JsonResponse
    {
        $this->authorize('create', SessionLogImport::class);

        $validated = $request->validated();
        $type = SessionLogImportType::from($validated['type'] ?? SessionLogImportType::RSM->value);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $dto = StoreSessionLogImportDTO::fromArray([
            'file' => $request->file('file'),
            'user_id' => $user->id,
            'type' => $type->value,
        ]);

        $import = $this->importService->storeImportRequest($dto);

        return response()->json([
            'success' => true,
            'message' => 'Import queued successfully. You will receive an email notification when it completes.',
            'data' => [
                'import_id' => $import->id,
                'status' => $import->status->value,
            ],
        ]);
    }

    public function showImportStatus(Request $request, SessionLogImport $import): View|JsonResponse
    {
        $this->authorize('view', $import);

        $import->load(['rows.sessionLog', 'user']);

        $stats = [
            'total' => $import->total_rows,
            'processed' => $import->processed_rows,
            'success' => $import->rows()->where('status', 'done')->count(),
            'duplicates' => $import->rows()->where('status', 'duplicate')->count(),
            'errors' => $import->rows()->where('status', 'validation_error')->count(),
            'skipped' => $import->rows()->where('status', 'skipped')->count(),
            'pending' => $import->rows()->where('status', 'pending')->count(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'status' => $import->status->value,
                'stats' => $stats,
            ]);
        }

        return view('admin.session-log-imports.import-status', [
            'import' => $import,
            'stats' => $stats,
        ]);
    }

    public function importHistory(): View
    {
        $this->authorize('viewAny', SessionLogImport::class);

        return view('admin.session-log-imports.import-history', [
            'datatableUrl' => route('admin.session-logs.imports.data'),
        ]);
    }

    public function importHistoryData(SessionLogImportDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', SessionLogImport::class);

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
        $result = $this->importListService->listForDataTables($params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn (SessionLogImport $import): array => SessionLogImportRowTransformer::transform($import),
        );
    }

    public function downloadTemplate(Request $request): StreamedResponse
    {
        $this->authorize('create', SessionLogImport::class);

        $type = SessionLogImportType::from($request->query('type', SessionLogImportType::RSM->value));
        $template = $this->importService->getTemplate($type);

        $requiredColumns = $template['required_columns'] ?? [];
        $optionalColumns = $template['optional_columns'] ?? [];
        $allColumns = array_merge($requiredColumns, $optionalColumns);

        $filename = sprintf('session-log-import-template-%s-%s.csv', strtolower($type->value), now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($allColumns): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fputcsv($handle, $allColumns);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function downloadImported(Request $request, SessionLogImport $import): StreamedResponse|RedirectResponse
    {
        $this->authorize('viewAny', SessionLogImport::class);

        try {
            return \Illuminate\Support\Facades\Storage::download(
                $import->file_path,
                $import->file_name,
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Session log import file download failed', [
                'import_id' => $import->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'The original import file could not be found.');
        }
    }
}
