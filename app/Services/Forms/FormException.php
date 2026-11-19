<?php

namespace App\Services\Forms;

/** A rule of the forms, approvals or batch module was broken. The code is one of the error codes of the spec (section 4). */
class FormException extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function render($request)
    {
        if ($this->status === 403) {
            abort(403, $this->getMessage());   // not in the plan, not your call: the same 403 page as everywhere else
        }
        if ($request->expectsJson()) {
            return response()->json(['error' => ['code' => $this->errorCode, 'message' => $this->getMessage()]], $this->status);
        }

        return back()->withInput()->with('error', $this->getMessage())->withErrors(['identifier' => $this->getMessage()]);
    }
}
