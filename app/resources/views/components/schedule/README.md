# Schedule Components

Reusable Blade components for schedule/calendar functionality.

## Components

### `<x-schedule::calendar>`
Calendar widget component with month navigation and date selection.

**Props:**
- `selectedDate` (Carbon|null): Currently selected date
- `showTodayButton` (bool): Show "TODAY'S VIEW" button (default: true)
- `onDateSelect` (string|null): JavaScript function name to call on date select

**Usage:**
```blade
<x-schedule::calendar :selected-date="$selectedDate" />
```

### `<x-schedule::schedule-card>`
Displays a single schedule item with details and actions.

**Props:**
- `schedule` (array): Schedule data with keys:
  - `start_time`, `end_time`
  - `school`, `school_url`
  - `student` or `therapist`
  - `session_type`
  - `notes`
  - `id`, `edit_url`, `bill_url`
- `showActions` (bool): Show edit/bill buttons (default: true)
- `showNotes` (bool): Show notes field (default: true)

**Usage:**
```blade
<x-schedule::schedule-card :schedule="$schedule" />
```

### `<x-schedule::schedule-filters>`
Filter form for schools and students.

**Props:**
- `schools` (Collection): List of schools
- `students` (Collection): List of students
- `selectedSchoolId` (int|null): Currently selected school ID
- `selectedStudentId` (int|null): Currently selected student ID
- `formAction` (string|null): Form action URL (default: current URL)
- `formMethod` (string): Form method (default: 'GET')

**Usage:**
```blade
<x-schedule::schedule-filters 
    :schools="$schools" 
    :students="$students"
    :selected-school-id="$selectedSchoolId"
    :selected-student-id="$selectedStudentId"
/>
```

### `<x-schedule::pending-banner>`
Warning banner for pending sessions.

**Props:**
- `count` (int): Number of pending sessions
- `pendingUrl` (string|null): URL to pending schedules page

**Usage:**
```blade
<x-schedule::pending-banner :count="270" :pending-url="route('therapist.schedule.pending')" />
```

### `<x-schedule::schedule-list>`
List of schedules with empty state handling.

**Props:**
- `schedules` (array): Array of schedule data
- `selectedDate` (Carbon|null): Selected date for empty message
- `emptyMessage` (string|null): Custom empty message
- `showAddButton` (bool): Show add button in empty state (default: true)
- `addButtonUrl` (string): URL for add button (default: '#')
- `addButtonText` (string): Add button text (default: '+ ADD NEW SCHEDULE')

**Usage:**
```blade
<x-schedule::schedule-list 
    :schedules="$schedules" 
    :selected-date="$selectedDate"
    :add-button-url="route('therapist.schedule.create')"
/>
```

### `<x-schedule::schedule-header>`
Page header with title and add button.

**Props:**
- `title` (string): Page title (default: 'Schedule')
- `addButtonUrl` (string): URL for add button (default: '#')
- `addButtonText` (string): Add button text (default: '+ ADD NEW SCHEDULE')
- `showAddButton` (bool): Show add button (default: true)

**Usage:**
```blade
<x-schedule::schedule-header />
```

### `<x-schedule::schedule-details-header>`
Header for schedule details section with date display.

**Props:**
- `selectedDate` (Carbon|null): Selected date to display
- `timezone` (string): Timezone string (default: 'US/Central')
- `showDropdown` (bool): Show dropdown arrow (default: false)

**Usage:**
```blade
<x-schedule::schedule-details-header :selected-date="$selectedDate" />
```

## Example: Complete Schedule Page

```blade
<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <x-schedule::schedule-header />
            <x-schedule::pending-banner :count="$pendingCount" :pending-url="route('therapist.schedule.pending')" />
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1 space-y-6">
                    <x-schedule::calendar :selected-date="$selectedDate" />
                    <x-schedule::schedule-filters 
                        :schools="$schools" 
                        :students="$students"
                    />
                </div>
                
                <div class="lg:col-span-2">
                    <x-ui::card class="p-6">
                        <x-schedule::schedule-details-header :selected-date="$selectedDate" />
                        <x-schedule::schedule-list 
                            :schedules="$schedules" 
                            :selected-date="$selectedDate"
                        />
                    </x-ui::card>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

