<?php

namespace App\Http\Controllers;

use App\Models\Setting as SettingModel;
use App\Services\AuditService;
use App\Support\Permission;
use App\Support\Setting;
use Illuminate\Http\Request;

class MatrixController extends Controller
{
    public function index(Request $request)
    {
        return view('matrix', [
            'matrix' => Permission::matrix(),
            'roles' => config('ehibah.roles'),
            'canEdit' => $request->user()->hasRole('ADMIN'),
        ]);
    }

    /** Admin Sistem mengatur tingkat akses setiap peran pada setiap modul. */
    public function update(Request $request, AuditService $audit)
    {
        $roles = array_keys(config('ehibah.roles'));
        $features = array_keys(config('ehibah.features'));
        $input = (array) $request->input('level', []);

        $old = Setting::get('permissions.overrides', []);
        $new = [];
        foreach ($features as $feature) {
            foreach ($roles as $role) {
                $lvl = $input[$feature][$role] ?? null;
                if (! is_string($lvl) || ! isset(Permission::RANK[$lvl]) || Permission::isLocked($feature, $role)) {
                    continue;
                }
                if ($lvl !== Permission::defaultLevel($role, $feature)) {
                    $new[$feature][$role] = $lvl;
                }
            }
        }

        $this->store($new, $request->user()->id);
        $audit->system('PERMISSIONS_CHANGED', null, $old ?: null, $new ?: null);

        return back()->with('success', 'Hak akses diperbarui. Berlaku segera untuk semua pengguna.');
    }

    /** Kembalikan seluruh matriks ke bawaan sistem. */
    public function reset(Request $request, AuditService $audit)
    {
        $old = Setting::get('permissions.overrides', []);
        $this->store([], $request->user()->id);
        $audit->system('PERMISSIONS_RESET', null, $old ?: null, null);

        return back()->with('success', 'Hak akses dikembalikan ke pengaturan bawaan.');
    }

    private function store(array $overrides, int $userId): void
    {
        SettingModel::updateOrCreate(['key' => 'permissions.overrides'], [
            'value' => json_encode($overrides),
            'description' => 'Penimpaan matriks hak akses (peran × modul)',
            'updated_by' => $userId,
        ]);
        Setting::forget();
    }
}