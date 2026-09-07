<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">Channels</h2>
            <p class="mt-1 text-xs text-slate-500">إدارة وربط قنوات التواصل في مكان واحد.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        @include('workspace.partials.nav')
        @include('partials.flash')

        <div class="rounded-2xl border border-[#BDEFE5] bg-[#F3FCFA] px-5 py-4 text-sm text-slate-700">
            <p class="font-semibold text-[#067e6b]">قنوات التواصل</p>
            <p class="mt-1">
                حالة كل قناة مرتبطة ببيانات النظام الفعلية (واتساب من حسابات WhatsApp Business، والبريد من حسابات البريد المضافة).
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach($channels as $channel)
                @php
                    $connected = (bool) $channel['connected'];
                @endphp
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl
                                {{ $connected ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' }}">
                                @switch($channel['icon'])
                                    @case('whatsapp')
                                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="currentColor" aria-hidden="true">
                                            <path d="M17.5 6.5A7.42 7.42 0 0 0 12.2 4a7.7 7.7 0 0 0-6.66 11.52L4 20l4.63-1.5A7.7 7.7 0 0 0 12.2 20h.01a7.8 7.8 0 0 0 7.78-7.77A7.7 7.7 0 0 0 17.5 6.5Zm-5.29 12.2a6.42 6.42 0 0 1-3.27-.9l-.24-.14-2.74.89.9-2.67-.15-.27a6.42 6.42 0 1 1 5.5 3.09Zm3.54-4.8c-.2-.1-1.18-.58-1.36-.65-.18-.06-.31-.1-.45.1-.13.2-.51.65-.63.78-.11.13-.23.15-.42.05-.2-.1-.82-.3-1.56-.95a5.8 5.8 0 0 1-1.08-1.34c-.11-.2-.01-.3.08-.4.09-.09.2-.23.3-.34.1-.12.13-.2.2-.33.07-.13.03-.25-.02-.35-.05-.1-.45-1.08-.62-1.48-.16-.39-.32-.33-.45-.34h-.38a.73.73 0 0 0-.53.25c-.18.2-.68.67-.68 1.64 0 .96.7 1.89.8 2.02.1.13 1.37 2.1 3.33 2.95.46.2.83.33 1.11.42.47.15.9.13 1.24.08.38-.06 1.18-.48 1.35-.95.17-.47.17-.87.12-.95-.05-.08-.18-.13-.38-.24Z" />
                                        </svg>
                                        @break
                                    @case('messenger')
                                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="currentColor" aria-hidden="true">
                                            <path d="M12 2C6.47 2 2 6.15 2 11.3c0 2.94 1.46 5.56 3.74 7.26V22l3.18-1.75c.99.27 2.03.42 3.08.42 5.53 0 10-4.15 10-9.3S17.53 2 12 2Zm.96 12.48-2.54-2.7-4.85 2.7 5.34-5.67 2.67 2.69 4.71-2.69-5.33 5.67Z" />
                                        </svg>
                                        @break
                                    @case('instagram')
                                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="currentColor" aria-hidden="true">
                                            <path d="M7.5 2h9A5.5 5.5 0 0 1 22 7.5v9a5.5 5.5 0 0 1-5.5 5.5h-9A5.5 5.5 0 0 1 2 16.5v-9A5.5 5.5 0 0 1 7.5 2Zm0 1.8A3.7 3.7 0 0 0 3.8 7.5v9a3.7 3.7 0 0 0 3.7 3.7h9a3.7 3.7 0 0 0 3.7-3.7v-9a3.7 3.7 0 0 0-3.7-3.7h-9Zm9.55 1.35a1.1 1.1 0 1 1 0 2.2 1.1 1.1 0 0 1 0-2.2ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 1.8a3.2 3.2 0 1 0 0 6.4 3.2 3.2 0 0 0 0-6.4Z" />
                                        </svg>
                                        @break
                                    @default
                                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3 6.75A1.75 1.75 0 0 1 4.75 5h14.5A1.75 1.75 0 0 1 21 6.75v10.5A1.75 1.75 0 0 1 19.25 19H4.75A1.75 1.75 0 0 1 3 17.25V6.75Zm1.75.35L12 12l7.25-4.9" />
                                        </svg>
                                @endswitch
                            </span>
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">{{ $channel['name'] }}</h3>
                                <p class="text-xs text-slate-500">{{ $channel['hint'] }}</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold
                            {{ $connected ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            <span class="h-2 w-2 rounded-full {{ $connected ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                            {{ $channel['status_text'] }}
                        </span>
                    </div>

                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        @if($channel['key'] === 'whatsapp')
                            <button
                                type="button"
                                data-whatsapp-connect
                                data-meta-app-id="{{ $metaAppId }}"
                                data-meta-config-id="{{ $metaConfigId }}"
                                data-graph-version="{{ $graphApiVersion }}"
                                data-connect-url="{{ route('workspace.channels.whatsapp.connect') }}"
                                class="inline-flex items-center rounded-xl bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#04a98e]"
                            >
                                {{ $channel['primary_action'] }}
                            </button>
                            <a href="{{ $channel['manage_url'] }}" class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                إدارة القناة
                            </a>
                            <p data-whatsapp-feedback class="w-full text-xs text-slate-500"></p>
                        @else
                            <a href="{{ $channel['manage_url'] }}" class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                {{ $channel['primary_action'] }}
                            </a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>

    <script>
        (function () {
            const connectButton = document.querySelector('[data-whatsapp-connect]');
            if (!connectButton) {
                return;
            }

            const feedbackEl = document.querySelector('[data-whatsapp-feedback]');
            const metaAppId = connectButton.dataset.metaAppId || '';
            const metaConfigId = connectButton.dataset.metaConfigId || '';
            const graphVersion = connectButton.dataset.graphVersion || 'v20.0';
            const connectUrl = connectButton.dataset.connectUrl || '';
            let sdkReady = false;
            let signupSessionInfo = {};

            function setFeedback(message, tone) {
                if (!feedbackEl) {
                    return;
                }
                feedbackEl.textContent = message;
                feedbackEl.classList.remove('text-emerald-600', 'text-rose-600', 'text-slate-500');
                feedbackEl.classList.add(tone === 'success' ? 'text-emerald-600' : tone === 'error' ? 'text-rose-600' : 'text-slate-500');
            }

            function setButtonLoading(loading) {
                connectButton.disabled = loading;
                connectButton.classList.toggle('opacity-60', loading);
                connectButton.classList.toggle('cursor-not-allowed', loading);
            }

            if (!metaAppId || !metaConfigId || !connectUrl) {
                connectButton.disabled = true;
                connectButton.classList.add('opacity-60', 'cursor-not-allowed');
                setFeedback('Meta App configuration is missing. Please set META_APP_ID and WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID.', 'error');
                return;
            }

            window.addEventListener('message', function (event) {
                if (!['https://www.facebook.com', 'https://web.facebook.com'].includes(event.origin)) {
                    return;
                }

                let payload = event.data;
                if (typeof payload === 'string') {
                    try {
                        payload = JSON.parse(payload);
                    } catch (error) {
                        return;
                    }
                }

                if (!payload || payload.type !== 'WA_EMBEDDED_SIGNUP') {
                    return;
                }

                if (payload.event === 'FINISH' && payload.data) {
                    signupSessionInfo = payload.data;
                }

                if (payload.event === 'CANCEL') {
                    setFeedback('تم إلغاء عملية ربط واتساب من نافذة Meta.', 'error');
                }
            });

            window.fbAsyncInit = function () {
                window.FB.init({
                    appId: metaAppId,
                    cookie: true,
                    xfbml: false,
                    version: graphVersion,
                });
                sdkReady = true;
                setFeedback('Meta SDK جاهز. يمكنك بدء ربط واتساب.', 'info');
            };

            const sdkScript = document.createElement('script');
            sdkScript.async = true;
            sdkScript.defer = true;
            sdkScript.src = 'https://connect.facebook.net/en_US/sdk.js';
            sdkScript.onerror = function () {
                setFeedback('تعذر تحميل Meta SDK. تحقق من الشبكة أو حظر السكربتات.', 'error');
            };
            document.head.appendChild(sdkScript);

            async function sendCodeToBackend(code) {
                setButtonLoading(true);
                setFeedback('جاري إتمام الربط مع Meta...', 'info');

                try {
                    const response = await fetch(connectUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify({
                            code: code,
                            session_info: signupSessionInfo,
                        }),
                    });

                    const payload = await response.json();
                    if (!response.ok) {
                        throw new Error(payload.message || 'Meta connection failed.');
                    }

                    setFeedback('تم ربط واتساب بنجاح. جارٍ تحديث الحالة...', 'success');
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 900);
                } catch (error) {
                    setFeedback(error.message || 'فشل ربط واتساب. حاول مرة أخرى.', 'error');
                } finally {
                    setButtonLoading(false);
                }
            }

            connectButton.addEventListener('click', function () {
                if (!sdkReady || typeof window.FB?.login !== 'function') {
                    setFeedback('Meta SDK غير جاهز بعد. أعد المحاولة خلال ثوانٍ.', 'error');
                    return;
                }

                window.FB.login(function (response) {
                    const code = response?.authResponse?.code;
                    if (!code) {
                        setFeedback('لم تكتمل عملية الربط أو تم إلغاؤها.', 'error');
                        return;
                    }

                    sendCodeToBackend(code);
                }, {
                    config_id: metaConfigId,
                    response_type: 'code',
                    override_default_response_type: true,
                    extras: {
                        setup: {},
                    },
                });
            });
        })();
    </script>
</x-app-layout>
