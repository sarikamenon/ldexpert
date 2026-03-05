<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Lead\Services\LeadService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lead\StoreLeadNoteRequest;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;

final class LeadNoteController extends Controller
{
    public function __construct(
        private readonly LeadService $leadService,
    ) {}

    public function store(StoreLeadNoteRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $authorId = (int) $request->user()?->id;
        $note = $this->leadService->addNote($lead, $authorId, $request->validated('note'));

        $note->load('author');

        return response()->json([
            'success' => true,
            'message' => 'Note added successfully.',
            'note' => [
                'id' => $note->id,
                'note' => $note->note,
                'author_name' => $note->author->name ?? 'Unknown',
                'created_at' => $note->created_at?->format('M d, Y h:i A'),
            ],
        ]);
    }
}
