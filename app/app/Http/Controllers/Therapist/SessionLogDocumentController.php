<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Domain\Student\Services\StudentDocumentService;
use App\DTOs\CreateStudentDocumentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\StoreSessionLogDocumentRequest;
use App\Models\SessionLog;
use App\Models\StudentDocument;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SessionLogDocumentController extends Controller
{
    public function __construct(
        private readonly StudentDocumentService $documentService,
    ) {}

    public function store(StoreSessionLogDocumentRequest $request, SessionLog $sessionLog): JsonResponse
    {
        $this->authorize('create', [StudentDocument::class, $sessionLog]);

        $validated = $request->validated();

        $dto = CreateStudentDocumentDTO::fromArray([
            'documentable_type' => SessionLog::class,
            'documentable_id' => $sessionLog->id,
            'uploaded_by_id' => $request->user()->id,
            'document_type' => $validated['document_type'],
            'file' => $request->file('file'),
            'description' => $validated['description'] ?? null,
        ]);

        $document = $this->documentService->create($dto);
        $document->load(['uploadedBy']);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully.',
            'document' => [
                'id' => $document->id,
                'file_name' => $document->file_name,
                'document_type' => $document->document_type->label(),
                'uploaded_by_name' => $document->uploadedBy->name,
                'created_at' => $document->created_at->toIso8601String(),
            ],
        ]);
    }

    public function download(SessionLog $sessionLog, StudentDocument $document): StreamedResponse
    {
        if (! $document->isForSessionLog() || $document->documentable_id !== $sessionLog->id) {
            abort(404);
        }

        $this->authorize('view', $document);

        return $this->documentService->download($document);
    }

    public function destroy(SessionLog $sessionLog, StudentDocument $document): JsonResponse
    {
        if (! $document->isForSessionLog() || $document->documentable_id !== $sessionLog->id) {
            abort(404);
        }

        $this->authorize('delete', $document);

        $this->documentService->delete($document);

        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully.',
        ]);
    }
}
