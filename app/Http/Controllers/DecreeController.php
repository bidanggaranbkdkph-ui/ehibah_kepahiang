<?php

namespace App\Http\Controllers;

use App\Models\Decree;
use App\Models\FiscalYear;
use App\Services\AuditService;
use App\Services\ReportService;
use Illuminate\Http\Request;

/** Keputusan Bupati tentang daftar penerima & besaran hibah (Perbup Pasal 7). */
class DecreeController extends Controller
{
    public function index()
    {
        return view('decrees.index', [
            'decrees' => Decree::with('fiscalYear')->withCount('decisions')->orderByDesc('decree_date')->get(),
            'years' => FiscalYear::orderByDesc('year')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $d = $request->validate([
            'fiscal_year_id' => 'required|exists:fiscal_years,id',
            'decree_no' => 'required|string|max:80|unique:decrees,decree_no',
            'decree_date' => 'required|date',
            'title' => 'required|string|max:250',
            'perda_ref' => 'required|string|max:120',
            'perkada_ref' => 'required|string|max:120',
        ], [], ['decree_no' => 'Nomor keputusan', 'perda_ref' => 'Referensi Perda APBD', 'perkada_ref' => 'Referensi Perkada penjabaran']);
        $decree = Decree::create($d + ['created_by' => $request->user()->id]);
        $audit->record('DECREE_CREATED', $decree, null, $d);

        return back()->with('success', 'Keputusan tercatat. Kini dapat dipilih saat penetapan penerima.');
    }

    /** Daftar nominatif (Excel/PDF) — Lampiran penetapan. */
    public function nominatif(Request $request, Decree $decree, ReportService $reports)
    {
        $data = $reports->nominatif($decree);
        $format = $request->input('format', 'xlsx');

        return $format === 'pdf' ? $reports->pdf($data, 'Daftar Nominatif — '.$decree->decree_no) : $reports->excel($data, 'nominatif-'.$decree->id);
    }
}
