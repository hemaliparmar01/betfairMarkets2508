@extends('users.layout.main')

@section('title', __('Notifications · BF Markets'))

@section('content')
    @php
        $telegramButtonClasses = [
            'connect' => 'btn-tg btn-tg-connect inline-flex items-center [gap:8px] [padding:10px_20px] [border-radius:60px] [font-size:13.5px] font-bold border-0 cursor-pointer [transition:transform_0.2s,_box-shadow_0.2s] [text-decoration:none] [font-family:inherit] [background:linear-gradient(135deg,_var(--surface-brand-15),_var(--surface-primary))] text-white [box-shadow:0_4px_12px_var(--shadow-brand-15)] [&:hover]:[transform:translateY(-2px)] [&:hover]:[box-shadow:0_8px_20px_var(--shadow-brand-15-2)]',
            'disconnect' => 'btn-tg btn-tg-disconnect inline-flex items-center [gap:8px] [padding:10px_20px] [border-radius:60px] [font-size:13.5px] font-bold border-0 cursor-pointer [transition:transform_0.2s,_box-shadow_0.2s] [text-decoration:none] [font-family:inherit] bg-transparent [color:var(--text-danger)] [border:1px_solid_var(--border-danger-5)] dark:[color:rgb(var(--danger))] dark:[border-color:var(--status-danger-border)] [&:hover]:[background:var(--surface-neutral-0)] [&:hover]:[border-color:var(--border-danger-10)] dark:[&:hover]:[background:var(--status-danger-surface)]',
        ];
    @endphp

    <div id="notification-settings" data-status-url="{{ route('notifications.status') }}" data-connect-url="{{ route('notifications.connect') }}" data-disconnect-url="{{ route('notifications.disconnect') }}" data-test-url="{{ route('notifications.test') }}">
    <script id="notification-state" type="application/json">{!! json_encode($notificationState, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <p id="notification-feedback" role="status" class="my-3 text-sm text-[rgb(var(--primary))] dark:text-[rgb(var(--text-soft))]"></p>

    <!-- ===== PAGE HEADER ===== -->
    <div class="page-header [display:flex] [align-items:center] [justify-content:space-between] [flex-wrap:wrap] [gap:12px] [margin:18px_0_14px] [margin:28px_0_8px]">
        <h1 class="page-title [font-size:24px] [font-weight:800] [color:rgb(var(--text-main))] [display:flex] [align-items:center] [gap:10px] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[font-size:22px] max-[768px]:[font-size:18px] max-[768px]:[&_i]:[font-size:18px] max-[480px]:[font-size:16px] [font-size:32px] [font-weight:700] [letter-spacing:-0.5px] [gap:12px] [&_i]:[font-size:28px] max-[768px]:[font-size:24px] max-[480px]:[font-size:20px] max-[480px]:[gap:8px] max-[480px]:[&_i]:[font-size:20px] max-[768px]:[font-size:22px] max-[768px]:[gap:10px] max-[768px]:[&_i]:[font-size:22px] max-[480px]:[font-size:19px] max-[1024px]:[font-size:28px] max-[480px]:[font-size:21px]"><i class="fab fa-telegram"></i> {{ __('Notifications') }}</h1>
        <p class="page-subtitle [font-size:14px] [color:rgb(var(--text-muted))] [margin-top:6px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))] max-[768px]:[font-size:13px] max-[768px]:[font-size:14px]">{{ __('Connect the Telegram bot and receive real-time sports exchange signals.') }}</p>
    </div>

    <!-- ===== TELEGRAM CONNECT CARD ===== -->
    <div class="section">
        <div class="tg-card [background:linear-gradient(135deg,_var(--surface-neutral-0-2),_var(--surface-neutral-0-3))] [border:1px_solid_var(--border-brand-5)] [border-radius:20px] [padding:24px_28px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-color:rgb(var(--border))] max-[768px]:[padding:18px_18px]">
            <div class="tg-card-header flex items-center [gap:16px] [margin-bottom:18px] max-[768px]:[gap:12px] max-[480px]:[flex-direction:column] max-[480px]:[align-items:flex-start]">
                <div class="tg-logo [width:56px] [height:56px] [border-radius:14px] [background:linear-gradient(135deg,_var(--surface-brand-15),_var(--surface-primary))] flex items-center justify-center text-white [font-size:28px] shrink-0 [box-shadow:0_8px_20px_var(--shadow-brand-15)] max-[768px]:[width:48px] max-[768px]:[height:48px] max-[768px]:[font-size:24px]"><i class="fab fa-telegram-plane"></i></div>
                <div class="tg-info [flex:1]">
                    <div class="tg-title [font-size:17px] font-bold [color:rgb(var(--text-main))] [margin-bottom:4px] [transition:color_0.3s] dark:[color:var(--text-on-primary)]">{{ __('Telegram Notifications') }}</div>
                    <div class="tg-desc [font-size:13px] [color:rgb(var(--text-muted))] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('Our official bot:') }} <strong>@BFMarketsAlertsBot</strong></div>
                </div>
            </div>

            <!-- Not Connected State -->
            <div class="tg-status-bar flex items-center justify-between [gap:12px] flex-wrap [padding:14px_18px] [border-radius:14px] bg-[rgb(var(--surface))] [border:1px_solid_var(--border-brand-5)] [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] max-[768px]:[padding:12px_14px] max-[480px]:[flex-direction:column] max-[480px]:[align-items:stretch]" id="tgStatusDisconnected">
                <div class="tg-status-info flex items-center [gap:10px] [font-size:14px] font-semibold">
                    <span class="tg-status-dot disconnected [width:10px] [height:10px] [border-radius:50%] shrink-0 [&.connected]:[background:var(--surface-success)] [&.connected]:[box-shadow:0_0_0_4px_var(--shadow-success-40)] [&.connected]:[animation:pulseGreen_2s_infinite] [&.disconnected]:[background:var(--surface-danger)] [&.disconnected]:[box-shadow:0_0_0_4px_var(--shadow-danger-20)] [&.error]:[background:var(--surface-warning)] [&.error]:[box-shadow:0_0_0_4px_var(--shadow-warning-10)]"></span>
                    <span class="tg-status-text disconnected [&.connected]:[color:var(--text-success)] [&.disconnected]:[color:var(--text-danger)] [&.error]:[color:var(--text-warning-30)]">{{ __('Not connected') }}</span>
                </div>
                <div class="tg-status-actions flex items-center [gap:8px] flex-wrap max-[480px]:[width:100%] max-[480px]:[&_.btn-tg]:[flex:1] max-[480px]:[&_.btn-tg]:[justify-content:center]">
                    <button type="button" class="{{ $telegramButtonClasses[$notificationState['connected'] ? 'disconnect' : 'connect'] }}" data-connect-class="{{ $telegramButtonClasses['connect'] }}" data-disconnect-class="{{ $telegramButtonClasses['disconnect'] }}" id="btnConnectTelegram">
                        <i class="fas fa-link"></i>
                        <span data-telegram-action-label>{{ __('Connect Telegram') }}</span>
                    </button>
                </div>
            </div>

            <!-- Connected State -->
            <div class="tg-status-bar flex items-center justify-between [gap:12px] flex-wrap [padding:14px_18px] [border-radius:14px] bg-[rgb(var(--surface))] [border:1px_solid_var(--border-brand-5)] [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] max-[768px]:[padding:12px_14px] max-[480px]:[flex-direction:column] max-[480px]:[align-items:stretch]" id="tgStatusConnected" style="display:none;">
                <div class="tg-status-info flex items-center [gap:10px] [font-size:14px] font-semibold">
                    <span class="tg-status-dot connected [width:10px] [height:10px] [border-radius:50%] shrink-0 [&.connected]:[background:var(--surface-success)] [&.connected]:[box-shadow:0_0_0_4px_var(--shadow-success-40)] [&.connected]:[animation:pulseGreen_2s_infinite] [&.disconnected]:[background:var(--surface-danger)] [&.disconnected]:[box-shadow:0_0_0_4px_var(--shadow-danger-20)] [&.error]:[background:var(--surface-warning)] [&.error]:[box-shadow:0_0_0_4px_var(--shadow-warning-10)]"></span>
                    <span class="tg-status-text connected [&.connected]:[color:var(--text-success)] [&.disconnected]:[color:var(--text-danger)] [&.error]:[color:var(--text-warning-30)]">{{ __('Connected as') }} <strong id="tgUsername">@username</strong></span>
                </div>
                <div class="tg-status-actions flex items-center [gap:8px] flex-wrap max-[480px]:[width:100%] max-[480px]:[&_.btn-tg]:[flex:1] max-[480px]:[&_.btn-tg]:[justify-content:center]">
                    <button class="btn-tg btn-tg-test inline-flex items-center [gap:8px] [padding:10px_20px] [border-radius:60px] [font-size:13.5px] font-bold border-0 cursor-pointer [transition:transform_0.2s,_box-shadow_0.2s] [text-decoration:none] [font-family:inherit] bg-transparent [color:rgb(var(--primary))] [border:1px_solid_var(--border-brand-5-2)] dark:[color:rgb(var(--primary))] dark:[border-color:rgb(var(--border))] [&:hover]:[background:var(--surface-hover)] [&:hover]:[border-color:var(--border-primary)] dark:[&:hover]:[background:rgb(var(--surface-elevated))] dark:[&:hover]:[border-color:rgb(var(--border))]" id="btnTestNotification">
                        <i class="fas fa-paper-plane"></i>
                        {{ __('Send Test Notification') }}
                    </button>
                </div>
            </div>

            <!-- Error State -->
            <div class="tg-status-bar flex items-center justify-between [gap:12px] flex-wrap [padding:14px_18px] [border-radius:14px] bg-[rgb(var(--surface))] [border:1px_solid_var(--border-brand-5)] [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] max-[768px]:[padding:12px_14px] max-[480px]:[flex-direction:column] max-[480px]:[align-items:stretch]" id="tgStatusError" style="display:none;">
                <div class="tg-status-info flex items-center [gap:10px] [font-size:14px] font-semibold">
                    <span class="tg-status-dot error [width:10px] [height:10px] [border-radius:50%] shrink-0 [&.connected]:[background:var(--surface-success)] [&.connected]:[box-shadow:0_0_0_4px_var(--shadow-success-40)] [&.connected]:[animation:pulseGreen_2s_infinite] [&.disconnected]:[background:var(--surface-danger)] [&.disconnected]:[box-shadow:0_0_0_4px_var(--shadow-danger-20)] [&.error]:[background:var(--surface-warning)] [&.error]:[box-shadow:0_0_0_4px_var(--shadow-warning-10)]"></span>
                    <span class="tg-status-text error [&.connected]:[color:var(--text-success)] [&.disconnected]:[color:var(--text-danger)] [&.error]:[color:var(--text-warning-30)]" id="tgErrorText">{{ __('Telegram connection failed') }}</span>
                </div>
                <div class="tg-status-actions flex items-center [gap:8px] flex-wrap max-[480px]:[width:100%] max-[480px]:[&_.btn-tg]:[flex:1] max-[480px]:[&_.btn-tg]:[justify-content:center]">
                </div>
            </div>
        </div>

        <div class="info-box flex items-start [gap:12px] [padding:14px_18px] [border-radius:12px] [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [font-size:13px] [color:var(--text-secondary-strong)] [margin-top:16px] [transition:0.3s] [line-height:1.6] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[color:rgb(var(--text-soft))] [&_i]:[color:rgb(var(--primary))] [&_i]:[font-size:16px] [&_i]:[margin-top:2px] [&_i]:[flex-shrink:0] dark:[&_i]:[color:rgb(var(--primary))]">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>{{ __('How does it work?') }}</strong> {{ __('Click "Connect Telegram" — our official bot will open. Press') }}
                <strong>{{ __('/start') }}</strong> {{ __('and your account will be linked automatically.') }}
                <strong>{{ __('You will receive all signals') }}</strong> {{ __('directly in Telegram.') }}
            </div>
        </div>
    </div>

    <label class="my-6 flex cursor-pointer items-center gap-3 rounded-xl border border-[var(--border-brand-5)] bg-[var(--surface-neutral-0-2)] p-5 dark:bg-[rgb(var(--surface-soft))] dark:text-white">
        <input id="popup-enabled" type="checkbox" @checked($notificationState['popup_enabled']) class="size-5 accent-[rgb(var(--primary))]">
        <span><strong>{{ __('Platform Live Pop-up Alerts') }}</strong><span class="mt-1 block text-sm text-[rgb(var(--text-muted))]">{{ __('Enable Live Pop-up alerts') }} {{ __('· LTD + Goal · EN / KA') }}</span></span>
    </label>

    <!-- ===== LIVE ALERTS GROUP ===== -->
    <div class="notif-group [margin-bottom:28px]">
        <div class="notif-group-header live flex items-center [gap:12px] [padding:12px_18px] [border-radius:12px] [margin:14px_0px] [transition:0.3s] [&.live]:[background:linear-gradient(135deg,_var(--surface-neutral-0),_var(--surface-neutral-0-4))] [&.live]:[border-left:4px_solid_var(--border-danger)] dark:[&.live]:[background:linear-gradient(135deg,_var(--status-danger-surface),_var(--status-accent-surface))] [&.prematch]:[background:linear-gradient(135deg,_var(--surface-neutral-0-5),_var(--surface-warning-0))] [&.prematch]:[border-left:4px_solid_var(--border-warning)] dark:[&.prematch]:[background:linear-gradient(135deg,_var(--status-warning-surface),_var(--status-warning-surface))]">
            <span class="section-icon live"><i class="fas fa-circle"></i></span>
            <div>
                <div class="notif-group-title live [font-size:14px] font-extrabold uppercase [letter-spacing:0.5px] flex items-center [gap:8px] [&.live]:[color:var(--text-danger)] dark:[&.live]:[color:rgb(var(--danger))] [&.prematch]:[color:var(--text-warning-30)] dark:[&.prematch]:[color:rgb(var(--warning))]">🔴 {{ __('Live Alerts') }}</div>
                <div class="section-subtitle">{{ __('Real-time signals from in-play matches') }}</div>
            </div>
        </div>

        <div class="notif-list flex flex-col [gap:10px]">
            <!-- 1. HT 0-0 -->
            <div class="notif-item flex items-start [gap:14px] [padding:16px_18px] [border-radius:14px] bg-[rgb(var(--surface))] [border:2px_solid_var(--border-neutral-5)] cursor-pointer [transition:0.25s] dark:[background:rgb(var(--surface-soft))] dark:[border-color:rgb(var(--border))] [&:hover]:[border-color:var(--border-brand-5-2)] [&:hover]:[transform:translateY(-2px)] [&:hover]:[box-shadow:0_6px_16px_var(--shadow-brand-40-3)] dark:[&:hover]:[border-color:rgb(var(--border))] dark:[&:hover]:[box-shadow:0_6px_16px_rgb(0 0 0 / 0.12)] max-[768px]:[padding:14px_14px] max-[768px]:[gap:12px] max-[480px]:[flex-direction:row]" data-filter="ht00">
                <input type="checkbox" aria-label="{{ __('HT 0-0') }}" data-notification-filter="ht00" @checked($notificationState['preferences']['ht00']) class="notif-checkbox mt-0.5 size-[22px] shrink-0 cursor-pointer rounded-md accent-[rgb(var(--primary))]">
                <div class="notif-content [flex:1]">
                    <div class="notif-title [font-size:15px] font-bold [color:rgb(var(--text-main))] [margin-bottom:4px] flex items-center [gap:8px] flex-wrap [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_.notif-badge]:[font-size:10.5px] [&_.notif-badge]:[font-weight:800] [&_.notif-badge]:[padding:2px_8px] [&_.notif-badge]:[border-radius:20px] [&_.notif-badge]:[text-transform:uppercase] [&_.notif-badge]:[letter-spacing:0.3px] max-[768px]:[font-size:14px] max-[480px]:[font-size:13.5px]">{{ __('HT 0-0 (Half Time 0-0)') }} <span class="notif-badge hot [&.hot]:[background:linear-gradient(135deg,_var(--surface-neutral-0),_var(--surface-neutral-0-4))] [&.hot]:[color:var(--text-danger)] dark:[&.hot]:[background:var(--status-danger-surface)] dark:[&.hot]:[color:rgb(var(--danger))] [&.new]:[background:linear-gradient(135deg,_var(--surface-neutral-5),_var(--surface-neutral-5-2))] [&.new]:[color:var(--text-success)] dark:[&.new]:[background:var(--status-success-surface)] dark:[&.new]:[color:rgb(var(--success))]">{{ __('LIVE') }}</span></div>
                    <div class="notif-desc [font-size:13px] [color:rgb(var(--text-muted))] [line-height:1.5] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))] max-[768px]:[font-size:12.5px] max-[480px]:[font-size:12px]">{{ __('Goalless at half-time — ideal for 2nd-half goal strategies.') }}</div>
                </div>
            </div>

            <!-- 2. 0-0 @ 70' -->
            <div class="notif-item flex items-start [gap:14px] [padding:16px_18px] [border-radius:14px] bg-[rgb(var(--surface))] [border:2px_solid_var(--border-neutral-5)] cursor-pointer [transition:0.25s] dark:[background:rgb(var(--surface-soft))] dark:[border-color:rgb(var(--border))] [&:hover]:[border-color:var(--border-brand-5-2)] [&:hover]:[transform:translateY(-2px)] [&:hover]:[box-shadow:0_6px_16px_var(--shadow-brand-40-3)] dark:[&:hover]:[border-color:rgb(var(--border))] dark:[&:hover]:[box-shadow:0_6px_16px_rgb(0 0 0 / 0.12)] max-[768px]:[padding:14px_14px] max-[768px]:[gap:12px] max-[480px]:[flex-direction:row]" data-filter="00_70">
                <input type="checkbox" aria-label="0-0 @ 70'" data-notification-filter="00_70" @checked($notificationState['preferences']['00_70']) class="notif-checkbox mt-0.5 size-[22px] shrink-0 cursor-pointer rounded-md accent-[rgb(var(--primary))]">
                <div class="notif-content [flex:1]">
                    <div class="notif-title [font-size:15px] font-bold [color:rgb(var(--text-main))] [margin-bottom:4px] flex items-center [gap:8px] flex-wrap [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_.notif-badge]:[font-size:10.5px] [&_.notif-badge]:[font-weight:800] [&_.notif-badge]:[padding:2px_8px] [&_.notif-badge]:[border-radius:20px] [&_.notif-badge]:[text-transform:uppercase] [&_.notif-badge]:[letter-spacing:0.3px] max-[768px]:[font-size:14px] max-[480px]:[font-size:13.5px]">{{ __("0-0 @ 70' (Late Goal Radar)") }} <span class="notif-badge hot [&.hot]:[background:linear-gradient(135deg,_var(--surface-neutral-0),_var(--surface-neutral-0-4))] [&.hot]:[color:var(--text-danger)] dark:[&.hot]:[background:var(--status-danger-surface)] dark:[&.hot]:[color:rgb(var(--danger))] [&.new]:[background:linear-gradient(135deg,_var(--surface-neutral-5),_var(--surface-neutral-5-2))] [&.new]:[color:var(--text-success)] dark:[&.new]:[background:var(--status-success-surface)] dark:[&.new]:[color:rgb(var(--success))]">{{ __('LIVE') }}</span></div>
                    <div class="notif-desc [font-size:13px] [color:rgb(var(--text-muted))] [line-height:1.5] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))] max-[768px]:[font-size:12.5px] max-[480px]:[font-size:12px]">{{ __('Still 0-0 at or after the 70th minute — late goal opportunity.') }}</div>
                </div>
            </div>

            <!-- 3. Fav Losing -->
            <div class="notif-item flex items-start [gap:14px] [padding:16px_18px] [border-radius:14px] bg-[rgb(var(--surface))] [border:2px_solid_var(--border-neutral-5)] cursor-pointer [transition:0.25s] dark:[background:rgb(var(--surface-soft))] dark:[border-color:rgb(var(--border))] [&:hover]:[border-color:var(--border-brand-5-2)] [&:hover]:[transform:translateY(-2px)] [&:hover]:[box-shadow:0_6px_16px_var(--shadow-brand-40-3)] dark:[&:hover]:[border-color:rgb(var(--border))] dark:[&:hover]:[box-shadow:0_6px_16px_rgb(0 0 0 / 0.12)] max-[768px]:[padding:14px_14px] max-[768px]:[gap:12px] max-[480px]:[flex-direction:row]" data-filter="fav_losing">
                <input type="checkbox" aria-label="{{ __('Fav Losing') }}" data-notification-filter="fav_losing" @checked($notificationState['preferences']['fav_losing']) class="notif-checkbox mt-0.5 size-[22px] shrink-0 cursor-pointer rounded-md accent-[rgb(var(--primary))]">
                <div class="notif-content [flex:1]">
                    <div class="notif-title [font-size:15px] font-bold [color:rgb(var(--text-main))] [margin-bottom:4px] flex items-center [gap:8px] flex-wrap [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_.notif-badge]:[font-size:10.5px] [&_.notif-badge]:[font-weight:800] [&_.notif-badge]:[padding:2px_8px] [&_.notif-badge]:[border-radius:20px] [&_.notif-badge]:[text-transform:uppercase] [&_.notif-badge]:[letter-spacing:0.3px] max-[768px]:[font-size:14px] max-[480px]:[font-size:13.5px]">{{ __('Fav Losing (Favorite Losing)') }} <span class="notif-badge hot [&.hot]:[background:linear-gradient(135deg,_var(--surface-neutral-0),_var(--surface-neutral-0-4))] [&.hot]:[color:var(--text-danger)] dark:[&.hot]:[background:var(--status-danger-surface)] dark:[&.hot]:[color:rgb(var(--danger))] [&.new]:[background:linear-gradient(135deg,_var(--surface-neutral-5),_var(--surface-neutral-5-2))] [&.new]:[color:var(--text-success)] dark:[&.new]:[background:var(--status-success-surface)] dark:[&.new]:[color:rgb(var(--success))]">{{ __('LIVE') }}</span></div>
                    <div class="notif-desc [font-size:13px] [color:rgb(var(--text-muted))] [line-height:1.5] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))] max-[768px]:[font-size:12.5px] max-[480px]:[font-size:12px]">{{ __('Pre-match favorite is currently trailing in-play — potential comeback situation.') }}
                    </div>
                </div>
            </div>

            <!-- 4. Strategy: 1st Half LTD -->
            <div class="notif-item flex items-start [gap:14px] [padding:16px_18px] [border-radius:14px] bg-[rgb(var(--surface))] [border:2px_solid_var(--border-neutral-5)] cursor-pointer [transition:0.25s] dark:[background:rgb(var(--surface-soft))] dark:[border-color:rgb(var(--border))] [&:hover]:[border-color:var(--border-brand-5-2)] [&:hover]:[transform:translateY(-2px)] [&:hover]:[box-shadow:0_6px_16px_var(--shadow-brand-40-3)] dark:[&:hover]:[border-color:rgb(var(--border))] dark:[&:hover]:[box-shadow:0_6px_16px_rgb(0 0 0 / 0.12)] max-[768px]:[padding:14px_14px] max-[768px]:[gap:12px] max-[480px]:[flex-direction:row]" data-filter="ltd">
                <input type="checkbox" aria-label="{{ \App\Services\AlertService::STRATEGY[app()->getLocale() === 'ka' ? 'ka' : 'en'] }}" data-notification-filter="ltd" @checked($notificationState['preferences']['ltd']) class="notif-checkbox mt-0.5 size-[22px] shrink-0 cursor-pointer rounded-md accent-[rgb(var(--primary))]">
                <div class="notif-content [flex:1]">
                    <div class="notif-title [font-size:15px] font-bold [color:rgb(var(--text-main))] [margin-bottom:4px] flex items-center [gap:8px] flex-wrap [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_.notif-badge]:[font-size:10.5px] [&_.notif-badge]:[font-weight:800] [&_.notif-badge]:[padding:2px_8px] [&_.notif-badge]:[border-radius:20px] [&_.notif-badge]:[text-transform:uppercase] [&_.notif-badge]:[letter-spacing:0.3px] max-[768px]:[font-size:14px] max-[480px]:[font-size:13.5px]">{{ \App\Services\AlertService::STRATEGY[app()->getLocale() === 'ka' ? 'ka' : 'en'] }} <span class="notif-badge new [&.hot]:[background:linear-gradient(135deg,_var(--surface-neutral-0),_var(--surface-neutral-0-4))] [&.hot]:[color:var(--text-danger)] dark:[&.hot]:[background:var(--status-danger-surface)] dark:[&.hot]:[color:rgb(var(--danger))] [&.new]:[background:linear-gradient(135deg,_var(--surface-neutral-5),_var(--surface-neutral-5-2))] [&.new]:[color:var(--text-success)] dark:[&.new]:[background:var(--status-success-surface)] dark:[&.new]:[color:rgb(var(--success))]">{{ __('NEW') }}</span></div>
                    <div class="notif-desc [font-size:13px] [color:rgb(var(--text-muted))] [line-height:1.5] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))] max-[768px]:[font-size:12.5px] max-[480px]:[font-size:12px]">{{ \App\Services\AlertService::STRATEGY['en'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== PREMATCH ALERTS GROUP ===== -->
    <div class="notif-group [margin-bottom:28px]">
        <div class="notif-group-header prematch flex items-center [gap:12px] [padding:12px_18px] [border-radius:12px] [margin:14px_0px] [transition:0.3s] [&.live]:[background:linear-gradient(135deg,_var(--surface-neutral-0),_var(--surface-neutral-0-4))] [&.live]:[border-left:4px_solid_var(--border-danger)] dark:[&.live]:[background:linear-gradient(135deg,_var(--status-danger-surface),_var(--status-accent-surface))] [&.prematch]:[background:linear-gradient(135deg,_var(--surface-neutral-0-5),_var(--surface-warning-0))] [&.prematch]:[border-left:4px_solid_var(--border-warning)] dark:[&.prematch]:[background:linear-gradient(135deg,_var(--status-warning-surface),_var(--status-warning-surface))]">
            <span class="section-icon prematch"><i class="fas fa-clock"></i></span>
            <div>
                <div class="notif-group-title prematch [font-size:14px] font-extrabold uppercase [letter-spacing:0.5px] flex items-center [gap:8px] [&.live]:[color:var(--text-danger)] dark:[&.live]:[color:rgb(var(--danger))] [&.prematch]:[color:var(--text-warning-30)] dark:[&.prematch]:[color:rgb(var(--warning))]">🟡 {{ __('Prematch Alerts') }}</div>
                <div class="section-subtitle">{{ __('Pre-match market movement signals') }}</div>
            </div>
        </div>

        <div class="notif-list flex flex-col [gap:10px]">
            <div class="notif-item flex items-start [gap:14px] [padding:16px_18px] [border-radius:14px] bg-[rgb(var(--surface))] [border:2px_solid_var(--border-neutral-5)] cursor-pointer [transition:0.25s] dark:[background:rgb(var(--surface-soft))] dark:[border-color:rgb(var(--border))] [&:hover]:[border-color:var(--border-brand-5-2)] [&:hover]:[transform:translateY(-2px)] [&:hover]:[box-shadow:0_6px_16px_var(--shadow-brand-40-3)] dark:[&:hover]:[border-color:rgb(var(--border))] dark:[&:hover]:[box-shadow:0_6px_16px_rgb(0 0 0 / 0.12)] max-[768px]:[padding:14px_14px] max-[768px]:[gap:12px] max-[480px]:[flex-direction:row]" data-filter="prematch_drops">
                <input type="checkbox" aria-label="{{ __('Prematch Drops') }}" data-notification-filter="prematch_drops" @checked($notificationState['preferences']['prematch_drops']) class="notif-checkbox mt-0.5 size-[22px] shrink-0 cursor-pointer rounded-md accent-[rgb(var(--primary))]">
                <div class="notif-content [flex:1]">
                    <div class="notif-title [font-size:15px] font-bold [color:rgb(var(--text-main))] [margin-bottom:4px] flex items-center [gap:8px] flex-wrap [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_.notif-badge]:[font-size:10.5px] [&_.notif-badge]:[font-weight:800] [&_.notif-badge]:[padding:2px_8px] [&_.notif-badge]:[border-radius:20px] [&_.notif-badge]:[text-transform:uppercase] [&_.notif-badge]:[letter-spacing:0.3px] max-[768px]:[font-size:14px] max-[480px]:[font-size:13.5px]">{{ __('Prematch Drops') }}</div>
                    <div class="notif-desc [font-size:13px] [color:rgb(var(--text-muted))] [line-height:1.5] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))] max-[768px]:[font-size:12.5px] max-[480px]:[font-size:12px]">{{ __('Early warning for significant pre-match odds movement.') }}</div>
                </div>
            </div>

            <!-- 6. Market Shock Detector -->
            <div class="notif-item flex items-start [gap:14px] [padding:16px_18px] [border-radius:14px] bg-[rgb(var(--surface))] [border:2px_solid_var(--border-neutral-5)] cursor-pointer [transition:0.25s] dark:[background:rgb(var(--surface-soft))] dark:[border-color:rgb(var(--border))] [&:hover]:[border-color:var(--border-brand-5-2)] [&:hover]:[transform:translateY(-2px)] [&:hover]:[box-shadow:0_6px_16px_var(--shadow-brand-40-3)] dark:[&:hover]:[border-color:rgb(var(--border))] dark:[&:hover]:[box-shadow:0_6px_16px_rgb(0 0 0 / 0.12)] max-[768px]:[padding:14px_14px] max-[768px]:[gap:12px] max-[480px]:[flex-direction:row]" data-filter="market_shock">
                <input type="checkbox" aria-label="{{ __('Market Shock Detector') }}" data-notification-filter="market_shock" @checked($notificationState['preferences']['market_shock']) class="notif-checkbox mt-0.5 size-[22px] shrink-0 cursor-pointer rounded-md accent-[rgb(var(--primary))]">
                <div class="notif-content [flex:1]">
                    <div class="notif-title [font-size:15px] font-bold [color:rgb(var(--text-main))] [margin-bottom:4px] flex items-center [gap:8px] flex-wrap [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_.notif-badge]:[font-size:10.5px] [&_.notif-badge]:[font-weight:800] [&_.notif-badge]:[padding:2px_8px] [&_.notif-badge]:[border-radius:20px] [&_.notif-badge]:[text-transform:uppercase] [&_.notif-badge]:[letter-spacing:0.3px] max-[768px]:[font-size:14px] max-[480px]:[font-size:13.5px]">{{ __('Market Shock Detector') }} <span class="notif-badge hot [&.hot]:[background:linear-gradient(135deg,_var(--surface-neutral-0),_var(--surface-neutral-0-4))] [&.hot]:[color:var(--text-danger)] dark:[&.hot]:[background:var(--status-danger-surface)] dark:[&.hot]:[color:rgb(var(--danger))] [&.new]:[background:linear-gradient(135deg,_var(--surface-neutral-5),_var(--surface-neutral-5-2))] [&.new]:[color:var(--text-success)] dark:[&.new]:[background:var(--status-success-surface)] dark:[&.new]:[color:rgb(var(--success))]">{{ __('HOT') }}</span></div>
                    <div class="notif-desc [font-size:13px] [color:rgb(var(--text-muted))] [line-height:1.5] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))] max-[768px]:[font-size:12.5px] max-[480px]:[font-size:12px]">{{ __('Sharp market movement within 1–3 minutes — rapid change detector.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SAVE BAR ===== -->
    <div class="save-bar [position:sticky] [bottom:0] flex items-center justify-between [gap:12px] flex-wrap [padding:16px_20px] [margin-top:24px] bg-[rgb(var(--surface))] [border-top:1px_solid_var(--border-subtle)] [border-radius:16px] [box-shadow:0_-8px_24px_var(--shadow-brand-85)] [transition:0.3s] dark:[background:rgb(var(--surface-soft))] dark:[border-top-color:rgb(var(--border))] dark:[box-shadow:0_-8px_24px_rgb(0 0 0 / 0.2)] max-[768px]:[padding:12px_14px] max-[480px]:[flex-direction:column] max-[480px]:[align-items:stretch]">
        <div class="save-info [font-size:13px] [color:rgb(var(--text-muted))] flex items-center [gap:8px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))] [&_i]:[color:rgb(var(--primary))] dark:[&_i]:[color:rgb(var(--primary))]">
            <i class="fas fa-shield-alt"></i>
            <span>{{ __('Your preferences are saved securely') }}</span>
        </div>
        <button class="btn-save inline-flex items-center [gap:8px] [padding:11px_28px] [border-radius:60px] [font-size:14px] font-bold [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white border-0 cursor-pointer [transition:transform_0.2s,_box-shadow_0.2s] [font-family:inherit] dark:[background:linear-gradient(135deg,_var(--button-primary),_var(--button-primary))] [&:hover]:[transform:translateY(-2px)] [&:hover]:[box-shadow:0_8px_20px_var(--shadow-brand-40-2)] [&:disabled]:[opacity:0.5] [&:disabled]:[cursor:not-allowed] [&:disabled]:[transform:none] [&.saved]:[background:linear-gradient(135deg,_var(--surface-success),_var(--surface-success-35))] dark:[&.saved]:[background:linear-gradient(135deg,_var(--status-success-fill),_var(--status-success-fill))] max-[768px]:[padding:10px_22px] max-[768px]:[font-size:13px] max-[480px]:[width:100%] max-[480px]:[justify-content:center]" id="btnSavePreferences">
            <i class="fas fa-save"></i>
            {{ __('Save Changes') }}
        </button>
    </div>
    </div>
@endsection
