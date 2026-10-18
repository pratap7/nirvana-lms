<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6" id="communication-template-manager">
    <!-- Header -->
    <div class="border-b border-stone-800 pb-5">
        <span class="text-xs font-bold uppercase tracking-wider text-amber-500 font-mono">Section H • Template Studio</span>
        <h1 class="text-2xl font-black text-white mt-1">Multi-Channel Notification Template Manager</h1>
        <p class="text-stone-400 text-xs mt-0.5">Manage domain notification templates, per-language translations (English/Hindi), variables, and preview rendering.</p>
    </div>

    @if($feedbackMessage)
        <div class="p-4 rounded-2xl bg-emerald-950/60 border border-emerald-800/60 text-emerald-300 text-xs flex items-center gap-2 shadow-lg">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ $feedbackMessage }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Sidebar: Template List -->
        <div class="lg:col-span-4 rounded-2xl bg-stone-900 border border-stone-800 overflow-hidden shadow-xl p-4 space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-stone-400 font-mono">Domain Templates</h3>
            <div class="space-y-2">
                @foreach($templates as $tpl)
                    <button wire:click="selectTemplate({{ $tpl->id }})"
                            class="w-full text-left p-3 rounded-xl transition flex flex-col gap-1 {{ $selectedTemplateId === $tpl->id ? 'bg-amber-500/10 border border-amber-500/30' : 'bg-stone-950/60 hover:bg-stone-800 border border-transparent' }}">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-white font-mono">{{ $tpl->key }}</span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $tpl->is_mandatory ? 'bg-rose-950 text-rose-400' : 'bg-stone-800 text-stone-400' }}">
                                {{ $tpl->is_mandatory ? 'MANDATORY' : 'STANDARD' }}
                            </span>
                        </div>
                        <span class="text-[11px] text-stone-400">{{ $tpl->name }} (v{{ $tpl->version }})</span>
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Main Studio: Edit & Preview -->
        <div class="lg:col-span-8 space-y-6">
            @if($selectedTemplate)
                <!-- Controls: Language & Channel Selection -->
                <div class="p-4 rounded-2xl bg-stone-900 border border-stone-800 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-bold text-stone-400 uppercase font-mono">Channel:</span>
                        <select wire:model.live="previewChannel" class="px-3 py-1.5 rounded-xl bg-stone-950 border border-stone-800 text-white text-xs">
                            <option value="mail">Email (Mail)</option>
                            <option value="database">In-App Bell (Database)</option>
                            <option value="whatsapp">WhatsApp Business API</option>
                            <option value="sms">SMS Text</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="text-xs font-bold text-stone-400 uppercase font-mono">Language:</span>
                        <div class="flex items-center rounded-xl bg-stone-950 border border-stone-800 p-1">
                            <button wire:click="$set('previewLocale', 'en')"
                                    class="px-3 py-1 rounded-lg text-xs font-bold transition {{ $previewLocale === 'en' ? 'bg-amber-500 text-stone-950' : 'text-stone-400 hover:text-white' }}">
                                English
                            </button>
                            <button wire:click="$set('previewLocale', 'hi')"
                                    class="px-3 py-1 rounded-lg text-xs font-bold transition {{ $previewLocale === 'hi' ? 'bg-amber-500 text-stone-950' : 'text-stone-400 hover:text-white' }}">
                                हिन्दी (Hindi)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Edit Form -->
                <div class="p-6 rounded-2xl bg-stone-900 border border-stone-800 shadow-xl space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-white uppercase font-mono tracking-wider">
                            Edit Content • {{ strtoupper($previewLocale) }} ({{ strtoupper($previewChannel) }})
                        </h3>
                        <span class="text-[10px] text-stone-400 font-mono">Version {{ $selectedTemplate->version }}</span>
                    </div>

                    @if($previewChannel === 'mail')
                        <div class="space-y-1">
                            <label class="text-xs text-stone-400 block font-mono">Email Subject Line</label>
                            <input type="text" wire:model="editSubject"
                                   placeholder="e.g. Schedule Update: {{ $selectedTemplate->name }} - @{{ session_title }}"
                                   class="w-full px-4 py-2.5 rounded-xl bg-stone-950 border border-stone-800 text-white text-xs">
                        </div>
                    @endif

                    @if($previewChannel === 'whatsapp')
                        <div class="space-y-1">
                            <label class="text-xs text-stone-400 block font-mono">Meta WhatsApp Template Name</label>
                            <input type="text" wire:model="editWhatsappTemplate"
                                   placeholder="e.g. pramana_session_rescheduled_v1"
                                   class="w-full px-4 py-2.5 rounded-xl bg-stone-950 border border-stone-800 text-white text-xs font-mono">
                        </div>
                    @endif

                    <div class="space-y-1">
                        <label class="text-xs text-stone-400 block font-mono">Template Message Body (Supports @{{ variable_name }})</label>
                        <textarea wire:model="editBody" rows="5"
                                  placeholder="Type template message body..."
                                  class="w-full px-4 py-3 rounded-xl bg-stone-950 border border-stone-800 text-white text-xs font-mono leading-relaxed"></textarea>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <span class="text-[11px] text-stone-500 font-mono">
                            Available variables: @{{ student_name }}, @{{ session_title }}, @{{ new_starts_at }}, @{{ starts_at }}, @{{ due_date }}
                        </span>
                        <button wire:click="saveTranslation"
                                class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs shadow-lg transition">
                            Save Translation & Bump Version
                        </button>
                    </div>
                </div>

                <!-- Live Preview Pane -->
                <div class="p-6 rounded-2xl bg-stone-950 border border-stone-800 shadow-xl space-y-3">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-amber-500 font-mono">Rendered Simulation Preview</span>
                    @if(!empty($renderedPreview['subject']))
                        <div class="border-b border-stone-800 pb-2">
                            <span class="text-[10px] text-stone-500 font-mono block">SUBJECT</span>
                            <h4 class="text-sm font-bold text-white">{{ $renderedPreview['subject'] }}</h4>
                        </div>
                    @endif
                    <div>
                        <span class="text-[10px] text-stone-500 font-mono block mb-1">BODY</span>
                        <div class="p-4 rounded-xl bg-stone-900/80 border border-stone-800/80 text-stone-200 text-xs font-mono whitespace-pre-wrap leading-relaxed">
                            {{ $renderedPreview['body'] }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
