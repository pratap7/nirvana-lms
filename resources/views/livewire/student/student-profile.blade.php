<div class="space-y-10 max-w-4xl" id="student-profile-view">
    <!-- Header -->
    <div class="border-b border-white/5 pb-6">
        <h1 class="text-3xl font-bold tracking-tight text-white">Profile & Preferences</h1>
        <p class="text-stone-400 text-sm mt-1">Manage personal contact, emergency information, multi-channel notification alerts, and data privacy.</p>
    </div>

    @if($feedbackMessage)
        <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-700/50 text-emerald-200 text-sm flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ $feedbackMessage }}</span>
            </div>
            <button wire:click="$set('feedbackMessage', null)" class="text-emerald-400 hover:text-white text-xs">Dismiss</button>
        </div>
    @endif

    <!-- Profile Information Form -->
    <form wire:submit="saveProfile" class="rounded-2xl bg-stone-900/60 border border-stone-800 p-6 md:p-8 space-y-6 shadow-xl">
        <h2 class="text-lg font-bold text-white flex items-center gap-2 border-b border-white/5 pb-3">
            <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            Personal Information
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="first_name" class="block text-xs font-semibold uppercase tracking-wider text-stone-400 mb-1">First Name</label>
                <input wire:model="first_name" id="first_name" type="text"
                    class="w-full bg-stone-950 border border-stone-800 rounded-xl px-4 py-2.5 text-white text-sm focus:border-amber-500 focus:outline-none" required />
                @error('first_name') <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="last_name" class="block text-xs font-semibold uppercase tracking-wider text-stone-400 mb-1">Last Name</label>
                <input wire:model="last_name" id="last_name" type="text"
                    class="w-full bg-stone-950 border border-stone-800 rounded-xl px-4 py-2.5 text-white text-sm focus:border-amber-500 focus:outline-none" required />
                @error('last_name') <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="phone" class="block text-xs font-semibold uppercase tracking-wider text-stone-400 mb-1">Mobile / WhatsApp Number</label>
                <input wire:model="phone" id="phone" type="text" placeholder="+91 9876543210"
                    class="w-full bg-stone-950 border border-stone-800 rounded-xl px-4 py-2.5 text-white text-sm focus:border-amber-500 focus:outline-none" />
                @error('phone') <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="timezone" class="block text-xs font-semibold uppercase tracking-wider text-stone-400 mb-1">Timezone</label>
                <select wire:model="timezone" id="timezone"
                    class="w-full bg-stone-950 border border-stone-800 rounded-xl px-4 py-2.5 text-white text-sm focus:border-amber-500 focus:outline-none">
                    <option value="Asia/Kolkata">Asia/Kolkata (IST +05:30)</option>
                    <option value="UTC">UTC (GMT +00:00)</option>
                    <option value="America/New_York">America/New_York (EST)</option>
                    <option value="America/Los_Angeles">America/Los_Angeles (PST)</option>
                    <option value="Europe/London">Europe/London (GMT/BST)</option>
                    <option value="Asia/Dubai">Asia/Dubai (GST +04:00)</option>
                    <option value="Asia/Singapore">Asia/Singapore (SGT +08:00)</option>
                </select>
                @error('timezone') <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="locale" class="block text-xs font-semibold uppercase tracking-wider text-stone-400 mb-1">Preferred Language</label>
                <select wire:model="locale" id="locale"
                    class="w-full bg-stone-950 border border-stone-800 rounded-xl px-4 py-2.5 text-white text-sm focus:border-amber-500 focus:outline-none">
                    <option value="en">English</option>
                    <option value="hi">हिंदी (Hindi)</option>
                    <option value="sa">संस्कृतम् (Sanskrit)</option>
                    <option value="ta">தமிழ் (Tamil)</option>
                    <option value="te">తెలుగు (Telugu)</option>
                </select>
                @error('locale') <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        <!-- Emergency Contact Section -->
        <div class="pt-6 border-t border-white/5 space-y-4">
            <div>
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Emergency Contact</h3>
                <p class="text-xs text-stone-400">Encrypted at rest for safety during residential and retreat programs.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="emergency_contact_name" class="block text-xs text-stone-400 mb-1">Contact Name</label>
                    <input wire:model="emergency_contact_name" id="emergency_contact_name" type="text"
                        class="w-full bg-stone-950 border border-stone-800 rounded-xl px-4 py-2 text-white text-sm focus:border-amber-500 focus:outline-none" />
                </div>
                <div>
                    <label for="emergency_contact_relationship" class="block text-xs text-stone-400 mb-1">Relationship</label>
                    <input wire:model="emergency_contact_relationship" id="emergency_contact_relationship" type="text" placeholder="Parent / Spouse / Friend"
                        class="w-full bg-stone-950 border border-stone-800 rounded-xl px-4 py-2 text-white text-sm focus:border-amber-500 focus:outline-none" />
                </div>
                <div>
                    <label for="emergency_contact_phone" class="block text-xs text-stone-400 mb-1">Phone Number</label>
                    <input wire:model="emergency_contact_phone" id="emergency_contact_phone" type="text"
                        class="w-full bg-stone-950 border border-stone-800 rounded-xl px-4 py-2 text-white text-sm focus:border-amber-500 focus:outline-none" />
                </div>
            </div>
        </div>

        <!-- Medical & Health Declarations -->
        <div class="pt-6 border-t border-white/5 space-y-4">
            <div>
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Health & Dietary Declarations</h3>
                <p class="text-xs text-stone-400">Confidential info for yoga instructors and retreat organizers.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="medical_conditions" class="block text-xs text-stone-400 mb-1">Medical Conditions</label>
                    <input wire:model="medical_conditions" id="medical_conditions" type="text" placeholder="e.g. Lower back pain, asthma"
                        class="w-full bg-stone-950 border border-stone-800 rounded-xl px-4 py-2 text-white text-sm focus:border-amber-500 focus:outline-none" />
                </div>
                <div>
                    <label for="allergies" class="block text-xs text-stone-400 mb-1">Allergies</label>
                    <input wire:model="allergies" id="allergies" type="text" placeholder="e.g. Dust, nuts"
                        class="w-full bg-stone-950 border border-stone-800 rounded-xl px-4 py-2 text-white text-sm focus:border-amber-500 focus:outline-none" />
                </div>
                <div>
                    <label for="dietary_requirements" class="block text-xs text-stone-400 mb-1">Dietary Requirements</label>
                    <input wire:model="dietary_requirements" id="dietary_requirements" type="text" placeholder="e.g. Sattvic, vegan, Jain"
                        class="w-full bg-stone-950 border border-stone-800 rounded-xl px-4 py-2 text-white text-sm focus:border-amber-500 focus:outline-none" />
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-4">
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-amber-500 text-stone-950 font-bold hover:bg-amber-400 text-sm shadow-lg shadow-amber-500/20">
                Save Profile
            </button>
        </div>
    </form>

    <!-- Notification Preferences -->
    <div class="rounded-2xl bg-stone-900/60 border border-stone-800 p-6 md:p-8 space-y-6 shadow-xl">
        <div class="flex items-center justify-between border-b border-white/5 pb-3">
            <div>
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    Notification Preferences by Channel
                </h2>
                <p class="text-xs text-stone-400 mt-0.5">Control where and when you receive automated alerts.</p>
            </div>
            <button wire:click="saveNotificationPreferences" type="button" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs">
                Save Preferences
            </button>
        </div>

        <div class="space-y-4">
            @php
                $channels = [
                    'email' => 'Email Notifications',
                    'whatsapp' => 'WhatsApp Updates',
                    'sms' => 'SMS Alerts',
                ];
                $types = [
                    'class_reminder' => 'Upcoming Session Reminders (1 hour before)',
                    'announcement' => 'Batch & Course Announcements',
                    'assignment_due' => 'Assignment Deadlines & Teacher Feedback',
                    'payment_reminder' => 'Installment Due & Payment Receipts',
                ];
            @endphp

            @foreach($channels as $chanKey => $chanLabel)
                <div class="p-4 rounded-xl bg-stone-950/60 border border-stone-800 space-y-3">
                    <h3 class="text-sm font-bold text-white">{{ $chanLabel }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($types as $typeKey => $typeLabel)
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" wire:model="notifications.{{ $chanKey }}.{{ $typeKey }}"
                                    class="rounded bg-stone-900 border-stone-700 text-amber-500 focus:ring-amber-500 focus:ring-offset-stone-950" />
                                <span class="text-xs text-stone-300">{{ $typeLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Privacy & Data Export -->
    <div class="rounded-2xl bg-stone-900/60 border border-stone-800 p-6 md:p-8 space-y-4 shadow-xl">
        <h2 class="text-lg font-bold text-white flex items-center gap-2 border-b border-white/5 pb-3">
            <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            Privacy & GDPR Data Rights
        </h2>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 rounded-xl bg-stone-950/60 border border-stone-800">
            <div>
                <h3 class="text-sm font-semibold text-white">Download Personal Data Archive</h3>
                <p class="text-xs text-stone-400">Request a full export of your personal information, enrollment history, attendance records, and quiz scores.</p>
                @if($exportRequestedAt)
                    <p class="text-xs text-amber-400 mt-1 font-mono">Last requested on: {{ $exportRequestedAt }} (Processing)</p>
                @endif
            </div>
            <button wire:click="requestDataExport" type="button"
                class="px-4 py-2 rounded-xl bg-stone-800 hover:bg-stone-700 text-stone-200 text-xs font-semibold shrink-0 transition">
                Request Data Export
            </button>
        </div>
    </div>
</div>
