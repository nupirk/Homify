<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use Illuminate\Http\Request;

/**
 * Admin portal for the crash/error reports the client apps send to
 * `POST /api/v1/errors`.
 *
 * Ingest already de-duplicates by fingerprint (a repeat hit bumps
 * `occurrences` instead of inserting), so this is a list of distinct problems
 * rather than raw traffic.
 */
class ErrorReportController extends Controller
{
    public function index(Request $request)
    {
        $query = ErrorLog::query()->with('user');

        if ($request->filled('level')) {
            $query->where('level', $request->query('level'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', $search)
                    ->orWhere('exception_class', 'like', $search)
                    ->orWhere('screen', 'like', $search);
            });
        }

        $reports = $query->orderByDesc('last_seen_at')->paginate(25)->withQueryString();

        $levels = ErrorLog::query()
            ->select('level')
            ->whereNotNull('level')
            ->distinct()
            ->orderBy('level')
            ->pluck('level');

        return view('admin.error-reports.index', compact('reports', 'levels'));
    }

    public function show(ErrorLog $errorReport)
    {
        $errorReport->load('user');

        return view('admin.error-reports.show', ['report' => $errorReport]);
    }
}
