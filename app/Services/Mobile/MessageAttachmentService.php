<?php

namespace App\Services\Mobile;

use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class MessageAttachmentService
{
    /** @var array<int, string> */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/pdf',
        'audio/mpeg',
        'audio/mp3',
        'video/mp4',
        'audio/wav',
        'audio/x-wav',
        'video/webm',
        'audio/webm',
    ];

    private const MAX_BYTES = 10 * 1024 * 1024;

    public function upload(Message $message, UploadedFile $file, User $actor): MessageAttachment
    {
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException('نوع الملف غير مدعوم.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('حجم الملف يتجاوز الحد المسموح (10 ميجابايت).');
        }

        $workspaceId = (int) $message->workspace_id;
        $messageId = (int) $message->id;
        $filename = Str::uuid()->toString().'.'.$file->guessExtension();
        $directory = "mobile-attachments/{$workspaceId}/{$messageId}";
        $path = $file->storeAs($directory, $filename, 'local');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('تعذر حفظ المرفق.');
        }

        return MessageAttachment::query()->create([
            'workspace_id' => $workspaceId,
            'message_id' => $messageId,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size_bytes' => (int) $file->getSize(),
            'kind' => $this->kindFromMime($mime),
        ]);
    }

    public function downloadUrl(MessageAttachment $attachment, int $ttlMinutes = 30): string
    {
        $disk = Storage::disk('local');

        if ($disk->providesTemporaryUrls()) {
            return $disk->temporaryUrl($attachment->path, now()->addMinutes($ttlMinutes));
        }

        return URL::temporarySignedRoute(
            'mobile.v1.attachments.download',
            now()->addMinutes($ttlMinutes),
            ['attachment' => $attachment->id],
        );
    }

    public function stream(MessageAttachment $attachment)
    {
        $disk = Storage::disk($attachment->disk ?: 'local');

        if (! $disk->exists($attachment->path)) {
            throw new RuntimeException('المرفق غير موجود.');
        }

        return $disk->download($attachment->path, $attachment->original_name);
    }

    private function kindFromMime(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }

        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }

        return 'file';
    }
}
