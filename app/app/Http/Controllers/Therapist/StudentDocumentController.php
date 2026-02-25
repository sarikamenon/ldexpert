<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Domain\Student\Services\StudentDocumentService;
use App\DTOs\CreateStudentDocumentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\StoreStudentDocumentRequest;
use App\Models\StudentDocument;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StudentDocumentController extends Controller
{
    public function __construct(
        private readonly StudentDocumentService $documentService,
    ) {}

    public function store(StoreStudentDocumentRequest $request, User $student): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        /** @var \App\Models\User $user */
        $user = $request->user();
        $dto = CreateStudentDocumentDTO::fromArray([
            'documentable_type' => User::class,
            'documentable_id' => $student->id,
            'uploaded_by_id' => $user->id,
            'document_type' => $validated['document_type'],
            'file' => $request->file('file'),
            'description' => $validated['description'] ?? null,
        ]);

        $document = $this->documentService->create($dto);
        $document->load(['uploadedBy']);

        if ($request->expectsJson()) {
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

        return redirect()
            ->route('therapist.students.show', ['student' => $student, 'tab' => 'documents'])
            ->with('success', 'Document uploaded successfully.');
    }

    public function download(StudentDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);

        return $this->documentService->download($document);
    }

    public function destroy(StudentDocument $document): JsonResponse
    {
        $this->authorize('delete', $document);

        $this->documentService->delete($document);

        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully.',
        ]);
    }
}
