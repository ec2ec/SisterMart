<?php

declare(strict_types=1);

namespace app\support;

use RuntimeException;

class ApiException extends RuntimeException
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public readonly string $errorName,
        string $message,
        public readonly int $status = 422,
        public readonly int $businessCode = 42200,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
