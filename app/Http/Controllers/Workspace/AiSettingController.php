<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Models\AiSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiSettingController extends Controller
{
    use InteractsWithWorkspace;

    public function edit(): View
    {
        $setting = AiSetting::query()->first();

        return view('workspace.ai-settings.edit', [
            'setting' => $setting,
            'rulesJson' => json_encode($setting?->rules ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'businessInfoJson' => json_encode($setting?->business_information ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'tone' => ['nullable', 'string', 'max:100'],
            'reply_style' => ['nullable', 'string', 'max:100'],
            'provider' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'max_tokens' => ['required', 'integer', 'min:50', 'max:4096'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:1.5'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $setting = AiSetting::query()->firstOrNew();
        $setting->fill([
            ...$validated,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'rules' => $this->parseJsonField($request, 'rules_json', $setting->rules ?? []),
            'business_information' => $this->parseJsonField($request, 'business_information_json', $setting->business_information ?? []),
        ]);
        $setting->save();

        return redirect()->route('workspace.ai-settings.edit')->with('success', 'تم تحديث إعدادات الذكاء الاصطناعي.');
    }
}
