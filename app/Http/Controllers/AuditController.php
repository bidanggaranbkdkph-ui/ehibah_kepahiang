<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AuditService;
use App\Support\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    /** Kategori yang boleh dilihat: audit trail proses (BPKAD/Inspektorat), log sistem (Admin/Inspektorat). */
    private function categories(): array
    {
        $u = auth()->user();
        $c = [];
        if (Permission::allows($u, 'audit_trail', 'V')) {
            $c = ['PROSES', 'AKSES'];
        }
        if (Permission::allows($u, 'log_sistem', 'V')) {
            $c = array_merge($c, ['SISTEM', 'AKSES']);
        }
        if (! $c) {
            throw new AuthorizationException('Tidak berwenang melihat audit trail.');
        }

        return array_values(array_unique($c));
    }

    public function index(Request $request)
    {
        $cats = $this->categories();
        $logs = AuditLog::query()->whereIn('category', $cats)
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', '%'.strtoupper($request->input('action')).'%'))
            ->when($request->filled('user'), fn ($q) => $q->whereRaw('lower(user_name) like ?', ['%'.mb_strtolower($request->input('user')).'%']))
            ->when($request->filled('app'), fn ($q) => $q->where('application_id', (int) $request->input('app')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->orderByDesc('id')->paginate(30)->withQueryString();

        return view('audit.index', ['logs' => $logs, 'cats' => $cats, 'canVerify' => Permission::allows($request->user(), 'audit_trail', 'V') || Permission::allows($request->user(), 'log_sistem', 'V')]);
    }

    public function verify(AuditService $audit)
    {
        $this->categories();
        $broken = $audit->verifyChain();
        $audit->access('AUDIT_CHAIN_VERIFIED', auth()->user(), ['result' => $broken ? 'RUSAK' : 'UTUH']);

        return back()->with($broken ? 'error' : 'success', $broken
            ? "Rantai audit RUSAK pada baris #{$broken['id']}: {$broken['reason']}."
            : 'Rantai audit utuh: tidak ada baris yang diubah, disisipkan, atau dihapus.');
    }
}
