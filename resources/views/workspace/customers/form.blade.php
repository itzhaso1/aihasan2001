@php($customer = $customer ?? null)
<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm">الاسم</label>
        <input name="name" required value="{{ old('name', $customer?->name) }}" class="w-full rounded-lg border-gray-300" />
    </div>
    <div>
        <label class="mb-1 block text-sm">الهاتف</label>
        <input name="phone" required value="{{ old('phone', $customer?->phone) }}" class="w-full rounded-lg border-gray-300" />
    </div>
    <div>
        <label class="mb-1 block text-sm">واتساب</label>
        <input name="whatsapp" value="{{ old('whatsapp', $customer?->whatsapp) }}" class="w-full rounded-lg border-gray-300" />
    </div>
    <div>
        <label class="mb-1 block text-sm">البريد الإلكتروني</label>
        <input type="email" name="email" value="{{ old('email', $customer?->email) }}" class="w-full rounded-lg border-gray-300" />
    </div>
</div>
<div>
    <label class="mb-1 block text-sm">ملاحظات</label>
    <textarea name="notes" rows="3" class="w-full rounded-lg border-gray-300">{{ old('notes', $customer?->notes) }}</textarea>
</div>
<div>
    <label class="mb-1 block text-sm">Metadata JSON</label>
    <textarea name="metadata_json" rows="5" class="w-full rounded-lg border-gray-300">{{ old('metadata_json', $metadataJson ?? json_encode($customer?->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
</div>
