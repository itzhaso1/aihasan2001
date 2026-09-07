<section class="mx-auto max-w-4xl px-4 py-14">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-900">Contact</h1>
        <p class="mt-2 text-sm text-slate-500">تواصل معنا عبر القنوات التالية.</p>

        <div class="mt-6 space-y-3 text-sm text-slate-700">
            <p><span class="font-semibold">Business:</span> {{ $settings['business_name'] ?? $website->name }}</p>
            <p><span class="font-semibold">Phone:</span> {{ $settings['contact_phone'] ?? '-' }}</p>
            <p><span class="font-semibold">Email:</span> {{ $settings['contact_email'] ?? '-' }}</p>
            <p><span class="font-semibold">Address:</span> {{ $settings['contact_address'] ?? '-' }}</p>
        </div>
    </div>
</section>
