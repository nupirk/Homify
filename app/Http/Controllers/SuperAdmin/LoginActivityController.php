<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Admin login activity: who signed in, from which IP, with what client, and
 * every rejected attempt. Rows come from the audit trail written by
 * Admin\AuthController.
 */
class LoginActivityController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user:user_id,first_name,last_name,email,user_type')
            ->where('entity_type', 'admin_auth')
            ->orderByDesc('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('entity_id', 'like', $search)
                    ->orWhere('ip_address', 'like', $search);
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $entries = $query->paginate(50)->withQueryString();

        $actions = AuditLog::where('entity_type', 'admin_auth')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $since = now()->subDay();

        $summary = [
            'logins_24h' => AuditLog::where('entity_type', 'admin_auth')
                ->where('action', 'admin_login')
                ->where('created_at', '>=', $since)
                ->count(),
            'failed_24h' => AuditLog::where('entity_type', 'admin_auth')
                ->where('action', 'admin_login_failed')
                ->where('created_at', '>=', $since)
                ->count(),
            'distinct_ips_24h' => AuditLog::where('entity_type', 'admin_auth')
                ->where('action', 'admin_login_failed')
                ->where('created_at', '>=', $since)
                ->distinct()
                ->count('ip_address'),
        ];

        return view('admin.login-activity.index', compact('entries', 'actions', 'summary'));
    }
}
