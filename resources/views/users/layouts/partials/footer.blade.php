<style>
    .social-icon {
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background-color: rgb(var(--surface));
    border: 1px solid rgb(var(--border));
    box-shadow: 0 2px 8px rgb(var(--shadow-soft) / 0.05);
}

.social-icon:hover {
    transform: translateY(-3px);
    border-color: rgb(var(--primary));
    box-shadow: 0 8px 20px rgb(var(--shadow-primary) / 0.2);
}

.social-icon:hover svg {
    color: rgb(var(--primary));
}

/* WhatsApp special - stays green */
.whatsapp-icon {
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background-color: rgb(var(--success));
    color: rgb(var(--surface));
    box-shadow: 0 2px 8px rgb(var(--success) / 0.3);
}

.whatsapp-icon:hover {
    transform: translateY(-3px);
    background-color: rgb(var(--success));
    box-shadow: 0 8px 20px rgb(var(--success) / 0.4);
}

</style>
<footer class="border-t theme-border theme-bg-surface">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col items-center text-center">

            <div class="flex items-center justify-center gap-3">
                <a href="https://x.com/Betfair_Markets" target="_blank" rel="noopener noreferrer" class="social-icon inline-flex items-center justify-center w-12 h-12 rounded-full bg-[rgb(var(--surface))] border border-[rgb(var(--border))] shadow-sm hover:border-[rgb(var(--primary))] hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                    <svg class="w-6 h-6 text-[rgb(var(--text-soft))] hover:text-[rgb(var(--primary))] transition-colors" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
                    </svg>
                </a>


                <a href="#" target="_blank" class="social-icon inline-flex items-center justify-center w-12 h-12 rounded-full bg-[rgb(var(--surface))] border border-[rgb(var(--border))] shadow-sm hover:border-[rgb(var(--primary))] hover:shadow-lg transition-all duration-300 hover:-translate-y-1" aria-label="Facebook">
                    <svg class="w-6 h-6 text-[rgb(var(--text-soft))] hover:text-[rgb(var(--primary))] transition-colors" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                </a>

                <a href="#" target="_blank"
                    class="social-icon inline-flex items-center justify-center w-12 h-12 rounded-full bg-[rgb(var(--surface))] border border-[rgb(var(--border))] shadow-sm hover:border-[rgb(var(--primary))] hover:shadow-lg transition-all duration-300 hover:-translate-y-1"
                    aria-label="YouTube">
                    <svg class="w-6 h-6 text-[rgb(var(--text-soft))] hover:text-[rgb(var(--primary))] transition-colors" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.376.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.376-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                    </svg>
                </a>

                <a href="https://discord.com/invite/AMPT65mG" target="_blank"
                    class="social-icon inline-flex items-center justify-center w-12 h-12 rounded-full bg-[rgb(var(--surface))] border border-[rgb(var(--border))] shadow-sm hover:border-[rgb(var(--primary))] hover:shadow-lg transition-all duration-300 hover:-translate-y-1"
                    aria-label="Discord">
                    <svg class="w-6 h-6 text-[rgb(var(--text-soft))] hover:text-[rgb(var(--primary))] transition-colors" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0a12.64 12.64 0 0 0-.617-1.25a.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057a19.9 19.9 0 0 0 5.993 3.03a.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106a13.107 13.107 0 0 1-1.872-.892a.077.077 0 0 1-.008-.128c.126-.094.252-.192.372-.291a.074.074 0 0 1 .078-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .079.01c.12.099.245.198.372.291a.077.077 0 0 1-.006.128a12.299 12.299 0 0 1-1.873.892a.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028a19.839 19.839 0 0 0 6.002-3.03a.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.956-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.955-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.946 2.418-2.157 2.418z"/>
                    </svg>
                </a>

                <a href="https://forum.ge/?f=27&showtopic=35275431&st=0" target="_blank" rel="noopener noreferrer" class="social-icon inline-flex items-center justify-center w-12 h-12 rounded-full bg-[rgb(var(--surface))] border border-[rgb(var(--border))] shadow-sm hover:border-[rgb(var(--primary))] hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                    <svg class="w-6 h-6 text-[rgb(var(--text-soft))] hover:text-[rgb(var(--primary))] transition-colors" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                </a>

                <a href="https://wa.me/995511705508" target="_blank"
                    class="social-icon inline-flex items-center justify-center w-12 h-12 rounded-full bg-[rgb(var(--surface))] border border-[rgb(var(--border))] shadow-sm hover:border-[rgb(var(--primary))] hover:shadow-lg transition-all duration-300 hover:-translate-y-1"
                    aria-label="WhatsApp">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12.032 2.001c-5.514 0-9.998 4.484-9.998 9.998 0 1.77.462 3.492 1.337 5.006L2 21.865l5.01-1.344a9.964 9.964 0 0 0 4.982 1.348c5.514 0 9.998-4.484 9.998-9.998S17.546 2.001 12.032 2.001zm0 18.5a8.504 8.504 0 0 1-4.282-1.152l-.307-.182-3.002.805.802-2.927-.2-.325a8.498 8.498 0 0 1-1.104-3.321 8.509 8.509 0 0 1 2.393-6.6 8.498 8.498 0 0 1 6.009-2.492 8.512 8.512 0 0 1 8.509 8.51 8.514 8.514 0 0 1-8.509 8.509zm4.684-6.374c-.253-.126-1.498-.739-1.73-.824-.232-.085-.401-.126-.571.127-.17.253-.66.824-.809.993-.149.17-.299.19-.552.064-.253-.126-1.068-.393-2.034-1.255-.751-.67-1.259-1.497-1.406-1.75-.149-.253-.015-.39.112-.516.113-.113.253-.295.38-.443.127-.148.169-.253.254-.422.085-.169.043-.317-.021-.444-.064-.127-.57-1.374-.78-1.882-.206-.495-.415-.427-.571-.435-.148-.008-.317-.008-.486-.008s-.444.063-.677.317c-.233.253-.887.867-.887 2.116 0 1.249.909 2.455 1.035 2.624.127.17 1.787 2.729 4.33 3.827.604.261 1.077.417 1.446.535.607.191 1.16.164 1.597.099.487-.073 1.498-.613 1.709-1.205.211-.592.211-1.099.148-1.205-.063-.106-.232-.17-.485-.296z"/>
                    </svg>
                </a>
            </div>

            <div class="mt-8">
                <a href="https://www.trustpilot.com/review/betfairmarkets.com" target="_blank"
                    class="theme-footer-trust-link inline-flex items-center gap-1 rounded px-3 py-1 text-[14px] font-semibold">
                    {{ __('Review us on') }}
                    <span class="theme-footer-trust-brand">★ </span>
                    {{ __('Trustpilot') }}
                </a>
            </div>

            <div class="mt-8 flex flex-wrap items-center justify-center text-xs text-[rgb(var(--text-muted))] gap-4 gap-y-2 text-[13px]">
                <a href="{{ route('privacy.policy') }}" class="theme-text-muted transition hover:opacity-80">
                    Privacy Policy
                </a>

                <a href="{{ route('terms.conditions') }}" class="theme-text-muted transition hover:opacity-80">
                    Terms of Use
                </a>

                <a href="{{ route('disclaimer') }}" class="theme-text-muted transition hover:opacity-80">
                    ⚠️ Disclaimer
                </a>

                <a href="{{ route('refund.policy') }}" class="theme-text-muted transition hover:opacity-80">
                    ↩️ Refund Policy
                </a>

                <a href="{{ route('faq') }}" class="theme-text-muted transition hover:opacity-80">
                    FAQ
                </a>

                <a href="{{ route('manifesto') }}" class="theme-text-muted transition hover:opacity-80">
                    BF Markets Manifesto
                </a>

                <a href="{{ route('filter.guide') }}" class="theme-text-muted transition hover:opacity-80">
                    {{ __('Filter Guide') }}
                </a>

                <a href="{{ route('feature.request') }}" class="theme-text-muted transition hover:opacity-80">
                    {{ __('Feature Request') }}
                </a>
            </div>
            {{-- <div class="mt-8 w-full border-t theme-border"></div> --}}

            {{-- <div class=" border-b-5"></div> --}}
            {{-- <p class="mt-5 text-[11px] theme-text-muted">
                © {{ now()->year }} Digital Analytics Group LLC. {{ __('All rights reserved.') }}
            </p> --}}
            <div class="mt-6 pt-4 border-t border-[rgb(var(--border))] text-center text-xs text-[rgb(var(--text-muted))]">
                © 2026 Digital Analytics Group LLC. All rights reserved.
            </div>
        </div>
    </div>

    {{-- <div class="theme-footer-highlight">
        <div class="mx-auto max-w-7xl px-4 py-5 text-center sm:px-6 lg:px-8">
            <p class="theme-footer-meta text-sm">
                The BF Markets platform is owned, developed, and operated by Digital Analytics Group LLC.
            </p>

            <a href="https://digitalanalyticsgroup.com" target="_blank" class="theme-link-accent mt-1 inline-block text-sm">
                https://digitalanalyticsgroup.com
            </a>

            <div class="theme-footer-meta-soft mt-6 flex flex-wrap items-center justify-center gap-x-10 gap-y-3 text-xs">
                <div class="flex items-center gap-1">
                    <span>🔒</span>
                    <span>GDPR compliant</span>
                </div>

                <div class="flex items-center gap-1">
                    <span>⚡</span>
                    <span>Real-time infrastructure</span>
                </div>

                <div class="flex items-center gap-1">
                    <span>🏢</span>
                    <span>Digital Analytics Group LLC</span>
                </div>

                <div class="flex items-center gap-1">
                    <span>📈</span>
                    <span>SLA: 99.9% uptime</span>
                </div>
            </div>
        </div>
    </div> --}}
</footer>
