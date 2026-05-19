{{--
    Shared sub-coverage form fields (reason + invitee picker).
    Used by both the create form and the edit-no-request inline form.

    Variables expected (all passed via @include):
      string  $reasonFieldName
      string  $reasonFieldId
      string  $reasonValue
      string  $pickerRootId
      string  $pickerTriggerId
      string  $pickerDropdownId
      string  $pickerSearchId
      string  $pickerListId
      string  $pickerPlaceholderId
      string  $hiddenInputsId
      string  $eligibleSubsUrl
      array   $reasonErrors
      array   $inviteeErrors
      array   $inviteeStarErrors
--}}
<div class="space-y-2">
    <x-input-label :for="$reasonFieldId" value="Reason *" />
    <p class="text-xs text-foreground/60" id="{{ $reasonFieldId }}_help">
        Briefly explain why you need coverage. This will be shared with the therapists you invite.
    </p>
    <textarea name="{{ $reasonFieldName }}" id="{{ $reasonFieldId }}" rows="3"
        aria-describedby="{{ $reasonFieldId }}_help"
        class="w-full border border-border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30"
        placeholder="e.g. Vacation, conflict, illness…">{{ $reasonValue }}</textarea>
    <x-input-error :messages="$reasonErrors" class="mt-2" />
</div>

<div class="space-y-1">
    <x-input-label value="Invite substitute therapists" />
    <p class="text-xs text-foreground/60" id="{{ $pickerRootId }}_help">
        Select one or more eligible therapists to invite. Only therapists in your position with an active contract for this service are shown.
    </p>

    <div id="{{ $pickerRootId }}"
        data-eligible-subs-url="{{ $eligibleSubsUrl }}"
        class="relative"
        aria-describedby="{{ $pickerRootId }}_help">
        <div id="{{ $pickerTriggerId }}"
            class="min-h-[2.5rem] w-full flex flex-wrap gap-1.5 items-center border border-border rounded-lg px-3 py-2 bg-background cursor-pointer focus-within:ring-2 focus-within:ring-primary/30"
            tabindex="0" role="combobox" aria-expanded="false" aria-haspopup="listbox">
            <span class="picker-placeholder text-sm text-foreground/40" id="{{ $pickerPlaceholderId }}">
                @if (str_contains($eligibleSubsUrl, 'eligible-subs') && !$isEdit)
                    Select a service and date above first.
                @else
                    Loading eligible therapists…
                @endif
            </span>
        </div>

        <div id="{{ $pickerDropdownId }}"
            class="hidden absolute z-20 mt-1 w-full bg-background border border-border rounded-lg shadow-lg max-h-56 overflow-y-auto"
            role="listbox">
            <div class="p-2 border-b border-border">
                <input type="text" id="{{ $pickerSearchId }}"
                    class="w-full text-sm px-2 py-1.5 rounded border border-border bg-background focus:outline-none focus:ring-1 focus:ring-primary/30"
                    placeholder="Search therapists…" autocomplete="off" />
            </div>
            <div id="{{ $pickerListId }}" class="p-1"></div>
        </div>
    </div>

    <div id="{{ $hiddenInputsId }}"></div>
    <x-input-error :messages="$inviteeErrors" class="mt-2" />
    <x-input-error :messages="$inviteeStarErrors" class="mt-1" />
</div>
