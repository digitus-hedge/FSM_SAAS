<?php
namespace App\Services\Audit;

/**
 * Minimal stub so the container can resolve it.
 * Real implementation (append-only activity_logs with hash chain) lands with slice 0.
 * For now: writes to the Laravel log, nothing persistent.
 */
class AuditLogger
{
    public function log(string $action, array $context = []): void
    {
        logger()->info("audit.{$action}", $context);
    }
}