<?php

namespace Tests\Feature\Feature\Platform;

use App\Models\PlatformAdmin;
use App\Models\PlatformSetting;
use App\Services\Platform\PlatformBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformSiteLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_platform_settings(): void
    {
        $this->get(route('platform.settings.edit'))
            ->assertRedirect(route('platform.login'));
    }

    public function test_platform_admin_can_upload_site_logo_and_see_it_on_welcome(): void
    {
        Storage::fake('public');
        $admin = PlatformAdmin::factory()->create();

        $this->actingAs($admin, 'platform_admin')
            ->get(route('platform.settings.edit'))
            ->assertOk()
            ->assertSee('إعدادات المنصة', false)
            ->assertSee('شعار الموقع', false);

        $this->actingAs($admin, 'platform_admin')
            ->put(route('platform.settings.update'), [
                'site_name' => 'متجري',
                'logo' => UploadedFile::fake()->image('my-logo.png', 120, 80),
            ])
            ->assertRedirect(route('platform.settings.edit'));

        $setting = PlatformSetting::query()->firstOrFail();
        $this->assertSame('متجري', $setting->site_name);
        $this->assertNotEmpty($setting->logo_path);
        Storage::disk('public')->assertExists($setting->logo_path);

        $this->get('/')
            ->assertOk()
            ->assertSee('متجري', false)
            ->assertSee($setting->logo_path, false);

        $this->post(route('platform.logout'));
        $this->get(route('platform.login'))
            ->assertOk()
            ->assertSee('متجري', false)
            ->assertSee($setting->logo_path, false);
    }

    public function test_platform_admin_can_remove_uploaded_logo(): void
    {
        Storage::fake('public');
        $admin = PlatformAdmin::factory()->create();
        $path = UploadedFile::fake()->image('old.png')->store('platform/branding', 'public');
        PlatformSetting::query()->first()?->update([
            'site_name' => 'حاسم',
            'logo_path' => $path,
        ]);
        app(PlatformBranding::class)->forgetCache();

        $this->actingAs($admin, 'platform_admin')
            ->put(route('platform.settings.update'), [
                'site_name' => 'حاسم',
                'remove_logo' => '1',
            ])
            ->assertRedirect(route('platform.settings.edit'));

        $this->assertNull(PlatformSetting::query()->first()?->logo_path);
        Storage::disk('public')->assertMissing($path);
    }
}
