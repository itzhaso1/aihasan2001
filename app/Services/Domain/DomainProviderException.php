<?php

namespace App\Services\Domain;

use RuntimeException;

class DomainProviderException extends RuntimeException
{
    /**
     * @param  array<int, array{number: string, message: string}>  $errors
     */
    public function __construct(
        string $message,
        private readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<int, array{number: string, message: string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
