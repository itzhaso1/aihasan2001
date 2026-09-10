<?php

namespace App\EInvoicing\QR;

final readonly class QrField
{
    public static function of(int $tag, string $value): self
    {
        return new self(QrTag::fromInt($tag), $value);
    }

    public function __construct(
        public QrTag $tag,
        public string $value,
    ) {
        if ($this->value === '') {
            throw new QrEncodingException(
                'QR field value must not be empty.',
                field: 'tag_'.$this->tag->value(),
                reason: 'empty_value',
            );
        }

        if (! mb_check_encoding($this->value, 'UTF-8')) {
            throw new QrEncodingException(
                'QR field value must be valid UTF-8.',
                field: 'tag_'.$this->tag->value(),
                reason: 'invalid_utf8',
            );
        }

        if (strlen($this->value) > 255) {
            throw new QrEncodingException(
                'QR field UTF-8 length exceeds the official one-byte length.',
                field: 'tag_'.$this->tag->value(),
                reason: 'length_overflow',
            );
        }
    }

    public function utf8Bytes(): string
    {
        return $this->value;
    }

    public function byteLength(): int
    {
        return strlen($this->value);
    }
}
