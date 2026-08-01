<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MemberContactConsent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Keamanan & Audit (PRD §5.5, Task 5.5). "Manajer/Bendahara terbatas,
 * Pengawas penuh" is implemented via the existing permission split:
 * `keamanan_audit.read` (view only) is held by Manajer/Bendahara/Pengawas
 * alike, but `keamanan_audit.export` (this controller's export() action)
 * is Pengawas-only per RolePermissionSeeder — no separate access-control
 * logic needed here beyond gating each action on its own permission.
 */
class SecurityAuditController extends Controller
{
    public function index(): View
    {
        $this->authorize('keamanan_audit.read');

        return view('admin.keamanan-audit.index', [
            'roles' => Role::query()->with('permissions')->get(),
            'users' => User::query()->with(['roles', 'branchScope.singleBranch', 'branchScope.branches'])->get(),
        ]);
    }

    public function auditLog(Request $request): View
    {
        $this->authorize('keamanan_audit.read');

        return view('admin.keamanan-audit.log', [
            'logs' => AuditLog::query()
                ->with('actor')
                ->when($request->filled('auditable_type'), fn ($query) => $query->where('auditable_type', $request->input('auditable_type')))
                ->when($request->filled('action'), fn ($query) => $query->where('action', $request->input('action')))
                ->when($request->filled('actor_id'), fn ($query) => $query->where('actor_id', $request->input('actor_id')))
                ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('date_from')))
                ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('date_to')))
                ->latest()
                ->limit(100)
                ->get(),
            'filters' => $request->only(['auditable_type', 'action', 'actor_id', 'date_from', 'date_to']),
            'actors' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function consentLog(): View
    {
        $this->authorize('keamanan_audit.read');

        return view('admin.keamanan-audit.consent', [
            'consents' => MemberContactConsent::query()->with('member')->latest()->limit(100)->get(),
        ]);
    }

    public function export(): StreamedResponse
    {
        $this->authorize('keamanan_audit.export');

        $logs = AuditLog::query()->with('actor')->latest()->get();

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Waktu', 'Actor', 'Role', 'Cabang', 'Aksi', 'Model', 'ID']);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->created_at->toDateTimeString(),
                    $log->actor?->name ?? '-',
                    $log->actor_role ?? '-',
                    $log->branch_id ?? '-',
                    $log->action,
                    $log->auditable_type,
                    $log->auditable_id,
                ]);
            }

            fclose($handle);
        }, 'audit-log-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
