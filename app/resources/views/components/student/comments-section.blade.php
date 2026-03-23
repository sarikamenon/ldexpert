@props(['student', 'comments', 'context' => 'admin'])

<div>
    <x-ui::card class="p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-foreground">Comments</h3>
            @if ($comments->hasPages() && $comments->currentPage() < $comments->lastPage())
                <a href="{{ $comments->url($comments->lastPage()) }}" class="text-xs text-primary hover:underline">
                    View latest messages
                </a>
            @endif
        </div>
        <div class="rounded-2xl border border-border bg-background/50 p-4 md:p-5">
            <div id="comments-scroll" class="max-h-[520px] overflow-y-auto pr-2">
                <div id="comments-list" class="space-y-6">
                    @if ($comments->count() > 0)
                        @foreach ($comments as $comment)
                            @php
                                $isOwnComment = $comment->author_id === auth()->id();
                            @endphp
                            <div class="flex {{ $isOwnComment ? 'justify-end' : 'justify-start' }}">
                                <div
                                    class="max-w-[75%] rounded-[28px] border px-5 py-3 text-sm shadow-sm {{ $isOwnComment ? 'bg-primary/10 border-primary/20' : 'bg-background border-border' }}">
                                    <div
                                        class="flex items-center gap-2 text-xs text-foreground/70 {{ $isOwnComment ? 'justify-end' : 'justify-start' }}">
                                        <span class="font-semibold text-foreground">{{ $comment->author->name }}</span>
                                        <x-ui::badge :variant="$comment->author->role->value === 'admin' ? 'primary' : 'success'">
                                            {{ ucfirst($comment->author->role->value) }}
                                        </x-ui::badge>
                                        <span class="text-foreground/50">
                                            {{ $comment->created_at->format('M d, Y g:i A') }}
                                        </span>
                                    </div>
                                    <p class="mt-2 whitespace-pre-wrap text-foreground">{{ $comment->comment }}</p>
                                </div>
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
            </div>
            <form id="comment-form" class="mt-6 pt-6"
                action="{{ $context === 'admin' ? route('admin.students.comments.store', $student) : route('therapist.students.comments.store', $student) }}"
                method="POST">
                @csrf
                <div>
                    <x-input-label for="comment" value="Comment *" />
                    <p class="mt-1 text-xs text-foreground/60" id="comment_help">
                        Add a comment about this student. Comments are visible to all admins and therapists assigned to
                        this
                        student.
                    </p>
                    <div class="relative mt-1">
                        <textarea id="comment" name="comment" rows="3"
                            class="block w-full border border-border rounded-2xl px-4 py-3 pr-24 pb-12 text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                            aria-describedby="comment_help" required maxlength="5000">{{ old('comment') }}</textarea>
                        <button type="submit" id="submit-comment-btn"
                            class="absolute bottom-3 right-3 inline-flex items-center px-4 py-2 bg-primary text-white rounded-full hover:bg-primary/90 text-sm font-medium shadow-sm">
                            <span id="submit-text">Send</span>
                            <span id="submit-spinner" class="hidden ml-2">
                                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                            </span>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('comment')" class="mt-2" />
                </div>
            </form>
        </div>
    </x-ui::card>
</div>
