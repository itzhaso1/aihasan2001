<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(PlatformBranding $branding): View
    {
        return view('platform.settings.edit', [
            'setting' => $branding->current(),
            'logoUrl' => $branding->logoUrl(),
            'siteName' => $branding->siteName(),
        ]);
    }

    public function update(Request $request, PlatformBranding $branding): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:80'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $branding->update(
            siteName: $validated['site_name'],
            logo: $request->file('logo'),
            removeLogo: $request->boolean('remove_logo'),
        );

        return redirect()
            ->route('platform.settings.edit')
            ->with('success', 'تم حفظ شعار واسم موقع المنصة.');
    }
}
