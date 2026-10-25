<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting as SettingModel;
use App\Services\AuditService;
use App\Support\Setting;
use Illuminate\Http\Request;

/** Parameter aturan bisnis & keamanan yang dapat diubah tanpa ubah kode. */
class SettingController extends Controller
{
    /** key => [label, tipe, bantuan, rules] */
    public const META = [
        'disbursement.stage1_percent' => ['Persentase penyaluran tahap I (%)', 'number', 'Perbup Pasal 9: 70% lalu 30%.', 'required|integer|between:1,99'],
        'lpj.months_after_activity' => ['Tenggat LPJ (bulan setelah kegiatan selesai)', 'number', 'Pasal 19: 1 bulan.', 'required|integer|between:0,12'],
        'lpj.cap_enabled' => ['Terapkan batas akhir tanggal tetap', 'bool', 'Pasal 19: paling lambat 10 Januari tahun berikutnya. Matikan bila Bagian Hukum menafsirkan lain.', 'boolean'],
        'lpj.cap_day' => ['Batas akhir — tanggal', 'number', '', 'required|integer|between:1,31'],
        'lpj.cap_month' => ['Batas akhir — bulan', 'number', '', 'required|integer|between:1,12'],
        'lpj.reminder_days' => ['Pengingat LPJ (hari sebelum tenggat, pisahkan koma)', 'list', 'Contoh: 7,3,1', 'required|string'],
        'lpj.warning_interval_days' => ['Jeda surat peringatan berikutnya (hari)', 'number', 'Setelah tenggat lewat.', 'required|integer|between:1,60'],
        'tech.min_score' => ['Skor minimal verifikasi teknis (0–100)', 'number', 'Rata-rata skor di bawah nilai ini = tidak layak.', 'required|integer|between:0,100'],
        'upload.max_mb' => ['Ukuran maksimum berkas (MB)', 'number', '', 'required|integer|between:1,50'],
        'close.require_monitoring' => ['Penutupan wajib ada monitoring & evaluasi', 'bool', 'Pasal 21.', 'boolean'],
        'security.max_login_attempts' => ['Maks. percobaan login gagal', 'number', '', 'required|integer|between:3,20'],
        'security.lockout_minutes' => ['Lama kunci akun (menit)', 'number', '', 'required|integer|between:1,1440'],
        'security.password_min' => ['Panjang minimal kata sandi', 'number', '', 'required|integer|between:8,64'],
        'security.mfa_enforced' => ['Wajibkan MFA untuk peran istimewa', 'bool', 'Admin, BPKAD, Bupati, TAPD, Inspektorat.', 'boolean'],
        'backup.retention_days' => ['Retensi backup (hari)', 'number', '', 'required|integer|between:1,3650'],
    ];

    public static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public function index()
    {
        $values = [];
        foreach (self::META as $k => $m) {
            $v = Setting::get($k);
            $values[$k] = is_array($v) ? implode(',', $v) : $v;
        }

        return view('admin.settings', ['meta' => self::META, 'values' => $values]);
    }

    public function update(Request $request, AuditService $audit)
    {
        // nama input memakai "__" sebagai pengganti titik (PHP/validator memperlakukan titik sebagai nested)
        $rules = [];
        foreach (self::META as $k => $m) {
            $rules[self::field($k)] = $m[3];
        }
        $request->validate($rules);
        $changes = [];

        foreach (self::META as $k => [$label, $type]) {
            $dataKey = self::field($k);
            $raw = $request->input($dataKey);
            $new = match ($type) {
                'bool' => $request->boolean($dataKey),
                'number' => (int) $raw,
                'list' => array_values(array_filter(array_map('intval', explode(',', (string) $raw)), fn ($n) => $n > 0)),
                default => $raw,
            };
            $old = Setting::get($k);
            if ($old !== $new) {
                $changes[$k] = ['old' => $old, 'new' => $new];
                SettingModel::updateOrCreate(['key' => $k], ['value' => json_encode($new), 'description' => $label, 'updated_by' => $request->user()->id]);
            }
        }
        Setting::forget();
        if ($changes) {
            $audit->system('SETTINGS_CHANGED', null, collect($changes)->map->old->all(), collect($changes)->map->new->all());
        }

        return back()->with('success', $changes ? count($changes).' pengaturan diubah.' : 'Tidak ada perubahan.');
    }
}
