<div class="overflow-hidden border border-border rounded-lg bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-background/subtle text-foreground">
            {{ $head }}
        </thead>
        <tbody class="divide-y divide-border">
            {{ $slot }}
        </tbody>
    </table>
</div>
