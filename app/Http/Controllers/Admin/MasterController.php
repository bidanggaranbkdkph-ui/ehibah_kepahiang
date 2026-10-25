<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequirement;
use App\Models\FiscalYear;
use App\Models\GrantType;
use App\Models\Organization;
use App\Models\RecipientCategory;
use App\Models\Regulation;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CRUD data master generik, diatur lewat definisi di bawah. Setiap perubahan tercatat di audit (SISTEM). */
class MasterController extends Controller
{
    public function __construct(private AuditService $audit)
    {
    }

    /** @return array<string, array> */
    public static function definitions(): array
    {
        $cat = fn () => RecipientCategory::pluck('name', 'id')->all();

        return [
            'organisasi' => [
                'label' => 'Organisasi (OPD & Lembaga)', 'model' => Organization::class, 'order' => ['type', 'name'],
                'fields' => [
                    'code' => ['Kode', 'text', 'required|string|max:30'], 'name' => ['Nama', 'text', 'required|string|max:200'],
                    'type' => ['Jenis', 'select', 'required|in:OPD,LEMBAGA', ['OPD' => 'OPD', 'LEMBAGA' => 'Lembaga penerima']],
                    'address' => ['Alamat', 'text', 'nullable|string|max:500'], 'contact' => ['Kontak', 'text', 'nullable|string|max:100'],
                    'is_active' => ['Aktif', 'bool', 'boolean'],
                ],
                'unique' => ['code'],
            ],
            'tahun-anggaran' => [
                'label' => 'Tahun anggaran', 'model' => FiscalYear::class, 'order' => ['year'],
                'fields' => [
                    'year' => ['Tahun', 'number', 'required|integer|between:2020,2100'],
                    'status' => ['Status', 'select', 'required|in:RENCANA,BERJALAN,TUTUP', ['RENCANA' => 'Rencana', 'BERJALAN' => 'Berjalan', 'TUTUP' => 'Tutup']],
                    'submission_open' => ['Buka pengajuan', 'date', 'nullable|date'], 'submission_close' => ['Tutup pengajuan', 'date', 'nullable|date|after_or_equal:submission_open'],
                ],
                'unique' => ['year'],
            ],
            'jenis-hibah' => [
                'label' => 'Jenis hibah', 'model' => GrantType::class, 'order' => ['id'],
                'fields' => [
                    'code' => ['Kode', 'text', 'required|string|max:20'], 'name' => ['Nama', 'text', 'required|string|max:200'],
                    'form' => ['Bentuk', 'select', 'required|in:UANG,BARANG', ['UANG' => 'Uang (jalur SP2D)', 'BARANG' => 'Barang (jalur BAST)']],
                    'scope' => ['Lingkup', 'select', 'required|in:HIBAH,BANSOS', ['HIBAH' => 'Hibah', 'BANSOS' => 'Bansos (di luar E-Hibah)']],
                    'is_active' => ['Aktif', 'bool', 'boolean'],
                ],
                'unique' => ['code'],
            ],
            'kategori-penerima' => [
                'label' => 'Kategori penerima', 'model' => RecipientCategory::class, 'order' => ['id'],
                'fields' => [
                    'code' => ['Kode', 'text', 'required|string|max:30'], 'name' => ['Nama', 'text', 'required|string|max:200'],
                    'legal_basis' => ['Dasar (pasal)', 'text', 'nullable|string|max:40'], 'special_rules' => ['Ketentuan khusus', 'text', 'nullable|string'],
                    'is_active' => ['Aktif', 'bool', 'boolean'],
                ],
                'unique' => ['code'],
            ],
            'dokumen-wajib' => [
                'label' => 'Dokumen wajib', 'model' => DocumentRequirement::class, 'order' => ['stage', 'sort_order'],
                'fields' => [
                    'code' => ['Kode', 'text', 'required|string|max:40'], 'name' => ['Nama dokumen', 'text', 'required|string|max:250'],
                    'stage' => ['Tahap', 'select', 'required|in:PENGAJUAN,NPHD,PENCAIRAN,PENYERAHAN,LPJ,ARSIP', ['PENGAJUAN' => 'Pengajuan', 'NPHD' => 'NPHD', 'PENCAIRAN' => 'Pencairan (uang)', 'PENYERAHAN' => 'Penyerahan (barang)', 'LPJ' => 'LPJ', 'ARSIP' => 'Arsip']],
                    'grant_form' => ['Bentuk hibah', 'select', 'nullable|in:UANG,BARANG', ['' => 'Uang & barang', 'UANG' => 'Uang', 'BARANG' => 'Barang']],
                    'recipient_category_id' => ['Kategori penerima', 'select', 'nullable|exists:recipient_categories,id', fn () => ['' => 'Semua kategori'] + $cat()],
                    'is_mandatory' => ['Wajib', 'bool', 'boolean'], 'requires_validity' => ['Punya masa berlaku', 'bool', 'boolean'],
                    'template_ref' => ['Templat/Lampiran', 'text', 'nullable|string|max:30'], 'sort_order' => ['Urut', 'number', 'nullable|integer'],
                    'is_active' => ['Aktif', 'bool', 'boolean'],
                ],
                'unique' => [],
            ],
            'regulasi' => [
                'label' => 'Regulasi (dasar hukum)', 'model' => Regulation::class, 'order' => ['type', 'code'],
                'fields' => [
                    'code' => ['Kode', 'text', 'required|string|max:40'], 'title' => ['Judul', 'text', 'required|string|max:300'],
                    'type' => ['Jenis', 'select', 'required|in:UU,PP,PERMENDAGRI,PERDA,PERBUP,KEPUTUSAN', ['UU' => 'UU', 'PP' => 'PP', 'PERMENDAGRI' => 'Permendagri', 'PERDA' => 'Perda', 'PERBUP' => 'Perbup', 'KEPUTUSAN' => 'Keputusan']],
                    'status' => ['Status', 'select', 'required|in:AKTIF,DICABUT,PERLU_DICEK', ['AKTIF' => 'Aktif', 'DICABUT' => 'Dicabut', 'PERLU_DICEK' => 'Perlu dicek']],
                    'is_grant_basis' => ['Dasar usulan hibah', 'bool', 'boolean'], 'notes' => ['Catatan', 'text', 'nullable|string'],
                ],
                'unique' => ['code'],
            ],
        ];
    }

    private function def(string $entity): array
    {
        return self::definitions()[$entity] ?? abort(404);
    }

    public function index(string $entity)
    {
        $def = $this->def($entity);
        $q = $def['model']::query();
        foreach ($def['order'] as $col) {
            $q->orderBy($col);
        }
        $options = [];
        foreach ($def['fields'] as $k => $f) {
            if ($f[1] === 'select') {
                $options[$k] = is_callable($f[3]) ? ($f[3])() : $f[3];
            }
        }

        return view('admin.master', ['entity' => $entity, 'def' => $def, 'rows' => $q->get(), 'options' => $options, 'all' => self::definitions()]);
    }

    public function store(Request $request, string $entity)
    {
        $def = $this->def($entity);
        $data = $this->validated($request, $def);
        foreach ($def['unique'] as $col) {
            if ($def['model']::where($col, $data[$col])->exists()) {
                return back()->withInput()->withErrors([$col => 'Nilai sudah dipakai.']);
            }
        }
        $row = $def['model']::create($data);
        $this->audit->system('MASTER_CREATED', $row, null, $data);

        return back()->with('success', 'Data ditambahkan.');
    }

    public function update(Request $request, string $entity, int $id)
    {
        $def = $this->def($entity);
        $row = $def['model']::findOrFail($id);
        $data = $this->validated($request, $def);
        foreach ($def['unique'] as $col) {
            if ($def['model']::where($col, $data[$col])->where('id', '!=', $id)->exists()) {
                return back()->withInput()->withErrors([$col => 'Nilai sudah dipakai.']);
            }
        }
        $old = $row->only(array_keys($data));
        $row->update($data);
        $this->audit->system('MASTER_UPDATED', $row, $old, $data);

        return back()->with('success', 'Data diperbarui.');
    }

    private function validated(Request $request, array $def): array
    {
        $rules = collect($def['fields'])->map(fn ($f) => $f[2])->all();
        $data = $request->validate($rules);
        foreach ($def['fields'] as $k => $f) {
            if ($f[1] === 'bool') {
                $data[$k] = $request->boolean($k);
            }
            if (array_key_exists($k, $data) && $data[$k] === '') {
                $data[$k] = null;
            }
        }

        return $data;
    }
}
