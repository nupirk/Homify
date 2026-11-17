<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

class AuditService
{
    /**
     * Record an audit log entry (best-effort, never throws).
     */
    public static function record(
        ?int $userId,
        string $action,
        string $entityType,
        string|int|null $entityId,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ?AuditLog {
        try {
            // Capture request context when there is one. Audit rows used to
            // leave these NULL, which made "who did this" unanswerable.
            $request = request();
            $ipAddress = null;
            $userAgent = null;

            if ($request) {
                $ipAddress = $request->ip();
                $userAgent = $request->userAgent()
                    ? substr((string) $request->userAgent(), 0, 500)
                    : null;
            }

            $entry = AuditLog::create([
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => (string) $entityId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            Log::info('Audit log entry recorded', [
                'log_id' => $entry->log_id,
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);

            return $entry;
        } catch (\Exception $e) {
            Log::warning('Failed to record audit log entry', [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
