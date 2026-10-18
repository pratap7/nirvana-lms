<div class="max-w-2xl mx-auto py-12 px-4 sm:px-6" id="certificate-verification-page">
    @if(!$isFound)
        <!-- Certificate Not Found Card -->
        <div class="p-8 sm:p-12 rounded-3xl bg-stone-900 border border-stone-800 text-center space-y-4 shadow-2xl">
            <div class="w-14 h-14 rounded-2xl bg-rose-950/60 border border-rose-800/50 text-rose-400 mx-auto flex items-center justify-center">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h1 class="text-2xl font-bold text-white">Certificate Record Not Found</h1>
            <p class="text-stone-400 text-sm max-w-md mx-auto">
                No certificate was found for token <code class="text-amber-400 font-mono">{{ $publicToken }}</code>.
                Please ensure you have scanned a genuine QR code or entered the full URL correctly.
            </p>
        </div>
    @else
        <!-- Verified Certificate Card -->
        <div class="p-8 sm:p-12 rounded-3xl bg-stone-900 border {{ $certificate->isValid() ? 'border-amber-500/30' : ($certificate->isRevoked() ? 'border-rose-600/40' : 'border-amber-600/40') }} shadow-2xl space-y-8 relative overflow-hidden">
            <!-- Header with Status Badge -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-white/5 pb-6">
                <div>
                    <span class="text-[10px] uppercase font-bold tracking-widest text-amber-500 block">Pramana Institute Official Registry</span>
                    <h1 class="text-2xl font-black text-white mt-1">Credential Verification</h1>
                </div>

                <!-- Status Badge (Valid, Revoked, Reissued) -->
                <div>
                    @if($certificate->isValid())
                        <span class="px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-950 text-emerald-400 border border-emerald-800 flex items-center gap-1.5 shadow-lg shadow-emerald-950/50">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>VALID</span>
                        </span>
                    @elseif($certificate->isRevoked())
                        <span class="px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider bg-rose-950 text-rose-400 border border-rose-800 flex items-center gap-1.5 shadow-lg shadow-rose-950/50">
                            <svg class="w-4 h-4 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>REVOKED</span>
                        </span>
                    @else
                        <span class="px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-950 text-amber-400 border border-amber-800 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            <span>REISSUED</span>
                        </span>
                    @endif
                </div>
            </div>

            <!-- Revocation / Reissue Notification Banner if not valid -->
            @if($certificate->isRevoked())
                <div class="p-4 rounded-2xl bg-rose-950/60 border border-rose-800/60 text-rose-200 text-xs space-y-1">
                    <div class="flex items-center gap-2 font-bold uppercase tracking-wider text-rose-300">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>Official Revocation Notice</span>
                    </div>
                    <p>This certificate was revoked on <strong class="font-mono text-white">{{ $certificate->revoked_at?->format('F d, Y') }}</strong>.</p>
                    @if($certificate->revocation_reason)
                        <p class="font-mono text-stone-300">Reason: {{ $certificate->revocation_reason }}</p>
                    @endif
                </div>
            @elseif($certificate->isReissued())
                <div class="p-4 rounded-2xl bg-amber-950/60 border border-amber-800/60 text-amber-200 text-xs space-y-1">
                    <div class="flex items-center gap-2 font-bold uppercase tracking-wider text-amber-300">
                        <span>Certificate Superseded (Notice of Replacement)</span>
                    </div>
                    <p>This credential has been superseded and reissued under a new certificate number.</p>
                    @if($certificate->reissuedCertificate)
                        <a href="{{ $certificate->reissuedCertificate->getVerificationUrl() }}" class="text-amber-400 underline font-mono font-bold block mt-1">
                            View Reissued Certificate ({{ $certificate->reissuedCertificate->certificate_number }}) &rarr;
                        </a>
                    @endif
                </div>
            @endif

            @if($certificate->original_certificate_id)
                <div class="p-3 rounded-xl bg-stone-800/80 border border-stone-700 text-stone-300 text-xs flex items-center gap-2">
                    <span class="text-amber-400 font-bold">Reissued Certificate:</span>
                    <span>This credential is an updated replacement for an earlier certificate.</span>
                </div>
            @endif

            <!-- Restricted Public Details (ONLY name, course, issue date, status) -->
            <div class="space-y-6">
                <div>
                    <span class="text-[11px] uppercase tracking-wider font-bold text-stone-400 block font-mono">Conferred Recipient</span>
                    <h2 class="text-2xl font-black text-white mt-1">{{ $certificate->user?->full_name }}</h2>
                </div>

                <div>
                    <span class="text-[11px] uppercase tracking-wider font-bold text-stone-400 block font-mono">Certified Program / Course</span>
                    <h3 class="text-lg font-bold text-amber-400 mt-1">{{ $certificate->getTargetTitle() }}</h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-white/5 font-mono text-xs">
                    <div>
                        <span class="text-stone-500 block uppercase font-sans text-[10px]">Date of Conferment</span>
                        <span class="text-white font-bold text-sm block mt-0.5">{{ $certificate->issue_date->format('F d, Y') }}</span>
                    </div>
                    <div>
                        <span class="text-stone-500 block uppercase font-sans text-[10px]">Certificate Identifier</span>
                        <span class="text-stone-300 font-bold text-sm block mt-0.5">{{ $certificate->certificate_number }}</span>
                    </div>
                </div>
            </div>

            <!-- Verification QR Code & Badges Link -->
            <div class="pt-6 border-t border-white/5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                <!-- Share & Open Badges Buttons -->
                <div class="flex flex-wrap items-center gap-3">
                    @if($certificate->isValid())
                        <a href="{{ $certificate->getLinkedInShareUrl() }}" target="_blank" rel="noopener noreferrer"
                           class="px-4 py-2 rounded-xl bg-[#0a66c2] hover:bg-[#004182] text-white font-bold text-xs flex items-center gap-2 shadow-lg transition">
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                            <span>Add to LinkedIn</span>
                        </a>
                    @endif

                    <a href="{{ route('certificates.badge', ['publicToken' => $certificate->public_token]) }}" target="_blank"
                       class="px-4 py-2 rounded-xl bg-stone-800 hover:bg-stone-700 text-stone-300 font-semibold text-xs border border-stone-700 flex items-center gap-1.5 transition">
                        <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                        <span>Open Badges JSON</span>
                    </a>
                </div>

                <!-- Verification QR Code -->
                <div class="flex items-center gap-3 bg-white p-3 rounded-2xl">
                    @if($certificate->qr_code_svg)
                        <div class="w-16 h-16 flex items-center justify-center">
                            {!! $certificate->qr_code_svg !!}
                        </div>
                    @endif
                    <div class="text-[10px] font-mono text-stone-900 leading-tight">
                        <strong>Official QR</strong><br>
                        Pramana Registry
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
