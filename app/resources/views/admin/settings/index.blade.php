<x-admin.layouts.app>
    <x-page-title title="System Settings" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        <!-- General Settings -->
        <x-ui::card class="p-6 mb-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">General Settings</h3>
            
            <div class="space-y-4">
                <div>
                    <label for="site_name" class="block text-sm font-medium text-foreground/70 mb-1">Site Name</label>
                    <x-text-input 
                        type="text" 
                        name="site_name" 
                        id="site_name" 
                        value="{{ old('site_name', $settings['general']['site_name'] ?? '') }}" 
                        class="w-full" 
                    />
                    @error('site_name')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="support_email" class="block text-sm font-medium text-foreground/70 mb-1">Support Email</label>
                    <x-text-input 
                        type="email" 
                        name="support_email" 
                        id="support_email" 
                        value="{{ old('support_email', $settings['general']['support_email'] ?? '') }}" 
                        class="w-full" 
                    />
                    @error('support_email')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="records_per_page" class="block text-sm font-medium text-foreground/70 mb-1">Records Per Page</label>
                    <x-text-input 
                        type="number" 
                        name="records_per_page" 
                        id="records_per_page" 
                        value="{{ old('records_per_page', $settings['general']['records_per_page'] ?? 25) }}" 
                        min="10" 
                        max="100" 
                        class="w-full" 
                    />
                    @error('records_per_page')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </x-ui::card>

        <!-- System Settings -->
        <x-ui::card class="p-6 mb-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">System Settings</h3>
            
            <div class="space-y-4">
                <div>
                    <x-ui-checkbox
                        name="maintenance_mode"
                        id="maintenance_mode"
                        value="1"
                        @checked(old('maintenance_mode', $settings['system']['maintenance_mode'] ?? false))
                        label="Enable Maintenance Mode"
                    />
                </div>
                <p class="text-xs text-foreground/60">When enabled, only admins can access the system.</p>
            </div>
        </x-ui::card>

        <!-- Email Settings -->
        <x-ui::card class="p-6 mb-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">Email Settings</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="smtp_host" class="block text-sm font-medium text-foreground/70 mb-1">SMTP Host</label>
                    <x-text-input 
                        type="text" 
                        name="smtp_host" 
                        id="smtp_host" 
                        value="{{ old('smtp_host', $settings['email']['smtp_host'] ?? '') }}" 
                        class="w-full" 
                    />
                </div>

                <div>
                    <label for="smtp_port" class="block text-sm font-medium text-foreground/70 mb-1">SMTP Port</label>
                    <x-text-input 
                        type="number" 
                        name="smtp_port" 
                        id="smtp_port" 
                        value="{{ old('smtp_port', $settings['email']['smtp_port'] ?? 587) }}" 
                        class="w-full" 
                    />
                </div>

                <div>
                    <label for="smtp_username" class="block text-sm font-medium text-foreground/70 mb-1">SMTP Username</label>
                    <x-text-input 
                        type="text" 
                        name="smtp_username" 
                        id="smtp_username" 
                        value="{{ old('smtp_username', $settings['email']['smtp_username'] ?? '') }}" 
                        class="w-full" 
                    />
                </div>

                <div>
                    <label for="smtp_password" class="block text-sm font-medium text-foreground/70 mb-1">SMTP Password</label>
                    <x-text-input 
                        type="password" 
                        name="smtp_password" 
                        id="smtp_password" 
                        placeholder="Leave blank to keep current password" 
                        class="w-full" 
                    />
                </div>
            </div>
        </x-ui::card>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                Save Settings
            </button>
        </div>
    </form>
</x-admin.layouts.app>

