<?php

namespace App\Services\Platform;

use App\Models\PlatformSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PlatformBranding
{
    public const CACHE_KEY = 'platform.branding.v1';

    /**
     * @return array{site_name: string, logo_url: ?string, logo_path: ?string}
     */
    public function snapshot(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function (): array {
            $setting = $this->current();

            return [
                'site_name' => $setting?->site_name ?: 'حاسم',
                'logo_path' => $setting?->logo_path,
                'logo_url' => $this->resolveLogoUrl($setting?->logo_path),
            ];
        });
    }

    public function current(): ?PlatformSetting
    {
        if (! Schema::hasTable('platform_settings')) {
            return null;
        }

        return PlatformSetting::query()->orderBy('id')->first();
    }

    public function siteName(): string
    {
        return (string) ($this->snapshot()['site_name'] ?? 'حاسم');
    }

    public function logoUrl(): ?string
    {
        $url = $this->snapshot()['logo_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function update(string $siteName, ?UploadedFile $logo = null, bool $removeLogo = false): PlatformSetting
    {
        $setting = $this->current() ?? PlatformSetting::query()->create([
            'site_name' => 'حاسم',
        ]);

        $payload = ['site_name' => $siteName];

        if ($removeLogo && $setting->logo_path) {
            Storage::disk('public')->delete($setting->logo_path);
            $payload['logo_path'] = null;
        }

        if ($logo !== null) {
            $previous = $setting->logo_path;
            $payload['logo_path'] = $logo->store('platform/branding', 'public');
            if ($previous && $previous !== $payload['logo_path']) {
                Storage::disk('public')->delete($previous);
            }
        }

        $setting->update($payload);
        $this->forgetCache();

        return $setting->fresh() ?? $setting;
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function resolveLogoUrl(?string $logoPath): ?string
    {
        if (is_string($logoPath) && $logoPath !== '' && Storage::disk('public')->exists($logoPath)) {
            return Storage::disk('public')->url($logoPath);
        }

        $fallback = public_path('images/hasim-logo.png');
        if (File::exists($fallback)) {
            return asset('images/hasim-logo.png');
        }

        return null;
    }
}
