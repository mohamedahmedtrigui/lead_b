<?php

namespace App\Domain\Shared\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Raised when an action violates a business rule (e.g. NRP attempted too early).
 * Rendered as a 422 JSON response so the SPA can display the message.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, protected string $errorCode = 'business_rule')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], 422);
    }
}
