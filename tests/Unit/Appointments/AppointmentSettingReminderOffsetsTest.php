<?php

namespace Tests\Unit\Appointments;

use App\Models\Appointment\AppointmentSetting;
use Tests\TestCase;

class AppointmentSettingReminderOffsetsTest extends TestCase
{
    public function test_reminder_offsets_accessor_normalizes_csv_string(): void
    {
        $setting = new AppointmentSetting();
        $setting->setRawAttributes([
            'reminder_offsets' => '1440,120',
        ], true);

        $this->assertSame([1440, 120], $setting->reminder_offsets);
    }

    public function test_reminder_offsets_accessor_normalizes_json_array_string(): void
    {
        $setting = new AppointmentSetting();
        $setting->setRawAttributes([
            'reminder_offsets' => '[60,30]',
        ], true);

        $this->assertSame([60, 30], $setting->reminder_offsets);
    }

    public function test_reminder_offsets_mutator_stores_json_array(): void
    {
        $setting = new AppointmentSetting();
        $setting->reminder_offsets = '90, 45, 45';

        $this->assertSame('[90,45]', $setting->getAttributes()['reminder_offsets']);
        $this->assertSame([90, 45], $setting->reminder_offsets);
    }
}
