@props(['student', 'comments', 'context' => 'admin'])

<div class="space-y-6">
    {{-- Comment Form --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Add Comment</h3>
        <form id="comment-form"
            action="{{ $context === 'admin' ? route('admin.students.comments.store', $student) : route('therapist.students.comments.store', $student) }}"
            method="POST">
            @csrf
            <div>
                <x-input-label for="comment" value="Comment *" />
                <p class="mt-1 text-xs text-foreground/60" id="comment_help">
                    Add a comment about this student. Comments are visible to all admins and therapists assigned to this
                    student.
                </p>
                <textarea id="comment" name="comment" rows="4"
                    class="mt-1 block w-full border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                    aria-describedby="comment_help" required maxlength="5000">{{ old('comment') }}</textarea>
                <x-input-error :messages="$errors->get('comment')" class="mt-2" />
            </div>
            <div class="mt-4">
                <button type="submit" id="submit-comment-btn"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    <span id="submit-text">Add Comment</span>
                    <span id="submit-spinner" class="hidden ml-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                    </span>
                </button>
            </div>
        </form>
    </x-ui::card>

    {{-- Comments List --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Comments</h3>
        <div id="comments-list" class="space-y-4">
            @if ($comments->count() > 0)
                @foreach ($comments as $comment)
                    <div class="border-b border-border pb-4 last:border-b-0 last:pb-0">
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-foreground">{{ $comment->author->name }}</span>
                                <x-ui::badge :variant="$comment->author->role->value === 'admin' ? 'primary' : 'success'">
                                    {{ ucfirst($comment->author->role->value) }}
                                </x-ui::badge>
                            </div>
                            <span class="text-xs text-foreground/60">
                                {{ $comment->created_at->format('M d, Y g:i A') }}
                            </span>
                        </div>
                        <p class="text-sm text-foreground whitespace-pre-wrap">{{ $comment->comment }}</p>
                    </div>
                @endforeach

                {{-- Pagination --}}
                @if ($comments->hasPages())
                    <div class="mt-6">
                        {{ $comments->links() }}
                    </div>
                @endif
            @else
                <div class="text-center py-8">
                    <p class="text-sm text-foreground/60">No comments yet. Be the first to add a comment!</p>
                </div>
            @endif
        </div>
    </x-ui::card>
</div>
