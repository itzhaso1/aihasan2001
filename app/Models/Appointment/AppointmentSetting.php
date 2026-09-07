<?php

namespace App\Models\Appointment;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;

#[Fillable([
    'workspace_id',
    'business_type',
    'business_label',
    'timezone',
    'slot_interval_minutes',
    'start_hour',
    'end_hour',
    'allow_walk_in',
    'automation_mode',
    'auto_confirm_after_payment',
    'reminder_offsets',
    'metadata',
])]
class AppointmentSetting extends WorkspaceScopedModel
{
    use BelongsToWorkspace;

    protected function casts(): array
    {
        return [
            'slot_interval_minutes' => 'integer',
            'allow_walk_in' => 'boolean',
            'auto_confirm_after_payment' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * Always expose reminder offsets as a list of positive integers.
     * Legacy rows may store a CSV string or a JSON-encoded string.
     *
     * @return Attribute<array<int, int>, array<int, int>|string|null>
     */
    protected function reminderOffsets(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): array {
                return $this->normalizeReminderOffsets($value);
            },
            set: function (mixed $value): string {
                return json_encode($this->normalizeReminderOffsets($value));
            },
        );
    }

    /**
     * @return array<int, int>
     */
    private function normalizeReminderOffsets(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = explode(',', $value);
            }
        }

        if (! is_array($value)) {
            return [1440, 120];
        }

        $normalized = collect($value)
            ->map(fn ($item) => (int) (is_string($item) ? trim($item) : $item))
            ->filter(fn (int $item): bool => $item > 0)
            ->unique()
            ->values()
            ->all();

        return $normalized === [] ? [1440, 120] : $normalized;
    }
}
