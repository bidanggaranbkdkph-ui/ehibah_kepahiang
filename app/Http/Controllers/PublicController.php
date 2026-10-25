<?php

namespace App\Http\Controllers;

use App\Enums\Status;
use App\Models\FiscalYear;
use App\Models\GrantApplication;
use App\Support\Fmt;
use Illuminate\Http\Request;

/** Portal publik: informasi non-sensitif dan pelacakan status berdasar nomor register. */
class PublicController extends Controller
{
    public function home()
    {
        $fy = FiscalYear::current();
        $base = GrantApplication::query()->when($fy, fn ($q) => $q->where('fiscal_year_id', $fy->id))->where('status', '!=', Status::DRAFT->value);

        $syarat = \App\Models\DocumentRequirement::query()
            ->where('stage', 'PENGAJUAN')->where('is_mandatory', true)->whereNull('recipient_category_id')
            ->orderBy('sort_order')->pluck('name');

        return view('public.home', [
            'fy' => $fy,
            'syarat' => $syarat,
            'stats' => [
                'usulan' => (clone $base)->count(),
                'ditetapkan' => (clone $base)->whereNotNull('amount_approved')->count(),
                'nilai' => (clone $base)->whereNotNull('amount_approved')->sum('amount_approved'),
                'selesai' => (clone $base)->where('status', Status::CLOSED->value)->count(),
            ],
        ]);
    }

    /** Hanya status & tahap yang ditampilkan — tanpa nama lembaga, nilai, atau dokumen. */
    public function status(Request $request)
    {
        $result = null;
        if ($request->filled('reg')) {
            $request->validate(['reg' => 'string|max:40']);
            $app = GrantApplication::query()->where('reg_number', trim($request->input('reg')))->first();
            $result = $app ? [
                'reg' => $app->reg_number, 'status' => $app->status, 'phase' => $app->status->phase(),
                'updated' => Fmt::date($app->status_changed_at),
            ] : false;
        }

        return view('public.status', ['result' => $result, 'reg' => $request->input('reg')]);
    }
}
