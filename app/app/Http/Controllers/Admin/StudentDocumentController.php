<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Student\Services\StudentDocumentService;
use App\DTOs\CreateStudentDocumentDTO;
use App\DTOs\StudentDocumentFilterDTO;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Student\StoreStudentDocumentRequest;
use App\Http\Requests\Admin\Student\StudentDocumentIndexRequest;
use App\Models\StudentDocument;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StudentDocumentController extends Controller
{
    public function __construct(
        private readonly StudentDocumentService $documentService,
    ) {}

    public function index(StudentDocumentIndexRequest $request): View
    {
        $this->authorize('viewAny', StudentDocument::class);

        $filters = StudentDocumentFilterDTO::fromRequest($request->validated());
        $documents = $this->documentService->list($filters);

        // Get reference data for filters
        $students = $this->getStudentsForFilter();
        $documentTypes = DocumentType::cases();
        $uploadedByUsers = $this->getUploadedByUsersForFilter();

        return view('admin.student-documents.index', [
            'documents' => $documents,
            'filters' => $request->validated(),
            'students' => $students,
            'documentTypes' => $documentTypes,
            'uploadedByUsers' => $uploadedByUsers,
        ]);
    }

    public function store(StoreStudentDocumentRequest $request, User $student): JsonResponse|RedirectResponse
    {
        $this->authorize('create', [StudentDocument::class, $student]);

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
                    'uploaded_by_name' => $document->uploadedBy?->name,
                    'created_at' => $document->created_at?->toIso8601String(),
                ],
            ]);
        }

        return redirect()
            ->route('admin.students.show', ['student' => $student, 'tab' => 'documents'])
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

    /** @return array<int, array{id: int, name: string}> */
    private function getStudentsForFilter(): array
    {
        // Get all students who have documents
        $studentIds = StudentDocument::query()
            ->where('documentable_type', User::class)
            ->distinct()
            ->pluck('documentable_id')
            ->toArray();

        // Also get students from session log documents
        $sessionLogStudentIds = \App\Models\SessionLog::query()
            ->whereHas('documents')
            ->distinct()
            ->pluck('student_id')
            ->toArray();

        $allStudentIds = array_unique(array_merge($studentIds, $sessionLogStudentIds));

        if (empty($allStudentIds)) {
            return [];
        }

        return User::query()
            ->whereIn('id', $allStudentIds)
            ->where('role', \App\Enums\Role::STUDENT->value)
            ->orderBy('name')
            ->get()
            ->map(fn ($student) => [
                'id' => $student->id,
                'name' => $student->name,
            ])
            ->toArray();
    }

    /** @return array<int, array{id: int, name: string}> */
    private function getUploadedByUsersForFilter(): array
    {
        $userIds = StudentDocument::query()
            ->distinct()
            ->pluck('uploaded_by_id')
            ->toArray();

        if (empty($userIds)) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->toArray();
    }
}
