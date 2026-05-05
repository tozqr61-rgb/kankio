<?php

namespace App\Services;

use App\Models\AdminAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuditService
{
    public function record(Request $request, string $action, ?string $targetType = null, int|string|null $targetId = null, array $payload = []): void
    {
        AdminAction::create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId !== null ? (string) $targetId : null,
            'payload' => $payload,
            'ip_address' => $request->ip(),
        ]);
    }
}
