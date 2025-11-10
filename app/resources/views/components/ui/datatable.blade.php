@props(['id' => 'datatable'])

<div class="overflow-hidden border border-border rounded-lg bg-white">
    <table id="{{ $id }}" class="w-full text-left text-sm border-collapse border border-border display">
        <thead class="bg-background/subtle text-foreground">
            {{ $head }}
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
