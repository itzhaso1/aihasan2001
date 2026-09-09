@extends('platform.layout')

@section('content')
    <div class="py-8" dir="rtl">
        <div class="mx-auto max-w-7xl px-4 space-y-6">
            @include('platform.partials.nav')
            @include('partials.flash')

            <div>
                <h1 class="text-xl font-bold text-gray-900">إعدادات المنصة</h1>
                <p class="mt-1 text-sm text-gray-600">شعار واسم الموقع يظهران في الصفحة الرئيسية وتسجيل الدخول وتطبيق حاسم.</p>
            </div>

            <form method="POST" action="{{ route('platform.settings.update') }}" enctype="multipart/form-data" class="max-w-xl space-y-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                @csrf
                @method('PUT')

                <div>
                    <label for="site_name" class="mb-1 block text-xs font-semibold text-gray-600">اسم الموقع</label>
                    <input id="site_name" name="site_name" value="{{ old('site_name', $siteName) }}" required maxlength="80" class="w-full rounded-xl border-gray-300 text-sm focus:border-[#06C2A4] focus:ring-[#06C2A4]">
                </div>

                <div>
                    <p class="mb-2 text-xs font-semibold text-gray-600">شعار الموقع</p>
                    <div class="mb-3 flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 p-3">
                        <x-platform-logo class="h-16 w-auto max-w-[220px]" />
                        <span class="text-xs text-gray-500">المعاينة الحالية</span>
                    </div>
                    <input type="file" name="logo" accept="image/*" class="w-full rounded-xl border-gray-300 text-sm">
                    <p class="mt-1 text-[11px] text-gray-500">PNG أو JPG أو WebP — حتى 4MB.</p>
                </div>

                @if($setting?->logo_path)
                    <label class="inline-flex items-center gap-2 text-sm text-rose-700">
                        <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300 text-rose-600">
                        حذف الشعار المرفوع والعودة للشعار الافتراضي
                    </label>
                @endif

                <button class="rounded-xl bg-[#06C2A4] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#05ab91]">حفظ الإعدادات</button>
            </form>
        </div>
    </div>
@endsection
