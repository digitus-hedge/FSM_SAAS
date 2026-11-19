<?php

namespace App\Services\Currency;

use Illuminate\Http\Request;

/**
 * A refused currency action. $errorCode is one of the codes of the spec (CURRENCY_IN_USE, RATE_LOCKED, ...).
 * When it is not caught, Laravel calls render(): JSON callers get {code, message} with the status of section 8,
 * web forms go back with the message under the currency field and the code in the session.
 */
class CurrencyException extends \DomainException
{
    private const STATUS = [
        'RATE_LOCKED'          => 409,
        'CURRENCY_IN_USE'      => 409,
        'CURRENCY_NOT_ENABLED' => 422,
        'VALIDATION_FAILED'    => 422,
        'FEATURE_NOT_IN_PLAN'  => 403,
        'FORBIDDEN'            => 403,
        'NOT_FOUND'            => 404,
    ];

    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return self::STATUS[$this->errorCode] ?? 422;
    }

    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['code' => $this->errorCode, 'message' => $this->getMessage()], $this->status());
        }

        return back()->withInput()->withErrors(['currency_code' => $this->getMessage()])->with('error_code', $this->errorCode);
    }
}
