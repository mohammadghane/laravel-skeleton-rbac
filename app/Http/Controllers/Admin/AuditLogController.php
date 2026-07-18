<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with(['user', 'auditable'])
            ->when($request->filled('filters.auditable_type'), fn ($q) => $q->where('auditable_type', $request->input('filters.auditable_type')))
            ->when($request->filled('filters.action'), fn ($q) => $q->where('action', $request->input('filters.action')))
            ->when($request->filled('filters.user_id'), fn ($q) => $q->where('user_id', $request->input('filters.user_id')))
            ->when($request->filled('filters.from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('filters.from')))
            ->when($request->filled('filters.to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('filters.to')))
            ->latest()
            ->paginate(config('app.page_size', 20))
            ->withQueryString();

        // Distinct model types actually present in the table, for the filter dropdown -
        // avoids hardcoding a model list here that'd go stale as new Auditable models are added.
        $auditableTypes = AuditLog::query()->distinct()->pluck('auditable_type');
        $modelLabels = config('audit.model_labels', []);

        if ($request->ajax() && $request->boolean('rows_only')) {
            return view('admin.audit-logs.partials.table', compact('logs', 'modelLabels'));
        }

        return view('admin.audit-logs.index', compact('logs', 'auditableTypes', 'modelLabels'));
    }
}
