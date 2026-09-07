<x-guest-layout>
    <div class="rounded-2xl border border-slate-200 bg-white p-1 shadow-2xl shadow-slate-200/70">
        <div class="rounded-xl bg-gradient-to-br from-[#06C2A4]/10 via-white to-cyan-50 p-6">
            <div class="mb-5 text-center">
                <h1 class="text-2xl font-extrabold text-slate-900">مرحبًا بك في حاسم</h1>
                <p class="mt-1 text-sm text-slate-500">سجّل الدخول لإدارة أعمالك من لوحة موحدة</p>
            </div>

            <x-auth-session-status class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <x-input-label for="email_or_phone" value="البريد الإلكتروني أو رقم الجوال" />
                    <x-text-input
                        id="email_or_phone"
                        class="mt-1 block w-full rounded-xl border-slate-300 bg-white/90"
                        type="text"
                        name="email_or_phone"
                        :value="old('email_or_phone')"
                        required
                        autofocus
                        autocomplete="username"
                    />
                    <x-input-error :messages="$errors->get('email_or_phone')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password" value="كلمة المرور" />
                    <x-text-input
                        id="password"
                        class="mt-1 block w-full rounded-xl border-slate-300 bg-white/90"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                    />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between gap-2 pt-1">
                    <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-[#06C2A4] shadow-sm focus:ring-[#06C2A4]" name="remember">
                        <span>تذكرني</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="text-sm font-medium text-[#0f7668] hover:text-[#06C2A4]" href="{{ route('password.request') }}">
                            نسيت كلمة المرور؟
                        </a>
                    @endif
                </div>

                <button type="submit" class="w-full rounded-xl bg-[#06C2A4] px-4 py-3 text-sm font-bold text-white transition hover:bg-[#05ab91]">
                    تسجيل الدخول
                </button>
            </form>

            <div class="my-5 flex items-center gap-3">
                <div class="h-px flex-1 bg-slate-200"></div>
                <span class="text-xs font-semibold text-slate-400">أو</span>
                <div class="h-px flex-1 bg-slate-200"></div>
            </div>

            <div class="space-y-2">
                <a href="{{ route('social.redirect', 'google') }}" class="flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    <svg class="h-4 w-4" viewBox="0 0 48 48" aria-hidden="true">
                        <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3 0 5.7 1.1 7.8 3l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.2-.1-2.4-.4-3.5z"/>
                        <path fill="#FF3D00" d="M6.3 14.7l6.6 4.9C14.7 16 19 12 24 12c3 0 5.7 1.1 7.8 3l5.7-5.7C34 6.1 29.3 4 24 4c-7.7 0-14.3 4.3-17.7 10.7z"/>
                        <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.1 35.1 26.7 36 24 36c-5.1 0-9.5-3.3-11.1-8l-6.6 5.1C9.7 39.6 16.4 44 24 44z"/>
                        <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-1.1 3-3.4 5.3-6.1 6.8l6.2 5.2C38.8 36.8 44 31 44 24c0-1.2-.1-2.4-.4-3.5z"/>
                    </svg>
                    المتابعة عبر Google
                </a>

                <a href="{{ route('social.redirect', 'facebook') }}" class="flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#1877F2" d="M24 12a12 12 0 10-13.875 11.85V15.47H7.078V12h3.047V9.356c0-3.007 1.792-4.669 4.533-4.669 1.313 0 2.686.235 2.686.235v2.953h-1.513c-1.49 0-1.956.925-1.956 1.874V12h3.328l-.532 3.47h-2.796v8.38A12.003 12.003 0 0024 12z"/>
                    </svg>
                    المتابعة عبر Facebook
                </a>

                <a href="{{ route('otp.login') }}" class="flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    <svg class="h-4 w-4 text-[#06C2A4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M16 2H8a2 2 0 00-2 2v16a2 2 0 002 2h8a2 2 0 002-2V4a2 2 0 00-2-2zm-4 18h.01"/>
                    </svg>
                    المتابعة عبر رمز التحقق (OTP)
                </a>
            </div>
        </div>
    </div>
</x-guest-layout>
