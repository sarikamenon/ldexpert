<x-mail::message>
    # Upcoming Session Reminder

    Hello {{ $recipientName }},

    This is a friendly reminder about an upcoming **{{ $schedule->service->name ?? 'Therapy' }}** session.

    We know how important consistency is for progress, and we look forward to seeing you.

    <x-mail::panel>
        **Session Details**

        * **Therapist:** {{ $schedule->therapist->name }}
        * **Student:** {{ $schedule->student->name }}
        * **Date:** {{ $scheduleDate }}
        * **Time:** {{ $startTime }} - {{ $endTime }} ({{ $timezone }})
        * **Location:** {{ $schedule->location_details ?? 'No specific location details' }}
    </x-mail::panel>

    @if ($schedule->notes)
        **Notes:**
        {{ $schedule->notes }}
    @endif

    If you need to reschedule or have any questions, please contact the therapist directly at
    {{ $schedule->therapist->email }} or {{ $schedule->therapist->therapistProfile->phone ?? 'N/A' }}.

    Warm regards,<br>
    {{ config('app.name') }}
</x-mail::message>
