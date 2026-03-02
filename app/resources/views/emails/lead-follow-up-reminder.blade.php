<x-mail::message>
    # Lead Follow-Up Reminder

    Hello {{ $recipientName }},

    You have a follow-up scheduled for today with the following lead:

    <x-mail::panel>
        **Lead Details**

        * **Name:** {{ $lead->full_name }}
        * **Email:** {{ $lead->email ?? 'Not provided' }}
        * **Status:** {{ $lead->status->label() }}
        * **School:** {{ $lead->school?->display_name ?? 'Not assigned' }}
        * **Follow-up Date:** {{ $lead->follow_up_date?->format('M d, Y') }}
    </x-mail::panel>

    @if ($lead->follow_up_notes)
        **Follow-up Notes:**
        {{ $lead->follow_up_notes }}
    @endif

    <x-mail::button :url="$leadUrl">
        View Lead
    </x-mail::button>

    Warm regards,<br>
    {{ config('app.name') }}
</x-mail::message>
