<?php

namespace App\Models;

use App\Enums\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class GrantApplication extends Model
{
    protected $guarded = ['id', 'lock_version'];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'activity_start' => 'date',
            'activity_end' => 'date',
            'activity_completed_at' => 'date',
            'lpj_due_date' => 'date',
            'bpk_audit_date' => 'date',
            'status_changed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'closed_at' => 'datetime',
            'full_exception_at' => 'datetime',
            'is_recurring' => 'boolean',
            'funding_declaration' => 'boolean',
            'lpj_overdue' => 'boolean',
            'committee' => 'array',
            'amount_requested' => 'integer',
            'amount_recommended' => 'integer',
            'amount_approved' => 'integer',
        ];
    }

    // ---- relasi --------------------------------------------------------
    public function fiscalYear(): BelongsTo { return $this->belongsTo(FiscalYear::class); }
    public function applicant(): BelongsTo { return $this->belongsTo(Organization::class, 'applicant_org_id'); }
    public function opd(): BelongsTo { return $this->belongsTo(Organization::class, 'opd_id'); }
    public function grantType(): BelongsTo { return $this->belongsTo(GrantType::class); }
    public function category(): BelongsTo { return $this->belongsTo(RecipientCategory::class, 'recipient_category_id'); }
    public function regulation(): BelongsTo { return $this->belongsTo(Regulation::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany { return $this->hasMany(ApplicationBudgetItem::class, 'application_id')->orderBy('sort_order'); }
    public function documents(): HasMany { return $this->hasMany(ApplicationDocument::class, 'application_id'); }
    public function verifications(): HasMany { return $this->hasMany(Verification::class, 'application_id')->orderBy('id'); }
    public function recommendation(): HasOne { return $this->hasOne(Recommendation::class, 'application_id'); }
    public function tapdReview(): HasOne { return $this->hasOne(TapdReview::class, 'application_id'); }
    public function decision(): HasOne { return $this->hasOne(Decision::class, 'application_id'); }
    public function allocation(): HasOne { return $this->hasOne(BudgetAllocation::class, 'application_id'); }
    public function nphds(): HasMany { return $this->hasMany(NphdDocument::class, 'application_id')->orderByDesc('version'); }
    public function disbursements(): HasMany { return $this->hasMany(Disbursement::class, 'application_id')->orderBy('stage'); }
    public function goodsDelivery(): HasOne { return $this->hasOne(GoodsDelivery::class, 'application_id'); }
    public function reports(): HasMany { return $this->hasMany(AccountabilityReport::class, 'application_id')->orderBy('id'); }
    public function fundReturns(): HasMany { return $this->hasMany(FundReturn::class, 'application_id'); }
    public function warnings(): HasMany { return $this->hasMany(WarningLetter::class, 'application_id'); }
    public function monitoring(): HasMany { return $this->hasMany(MonitoringRecord::class, 'application_id')->orderByDesc('visited_at'); }
    public function histories(): HasMany { return $this->hasMany(StatusHistory::class, 'application_id')->orderBy('id'); }
    public function corrections(): HasMany { return $this->hasMany(DataCorrection::class, 'application_id')->orderByDesc('id'); }

    // ---- helper --------------------------------------------------------
    public function isMoney(): bool
    {
        return $this->grantType->form === 'UANG';
    }

    public function isParpol(): bool
    {
        return (bool) $this->category?->is_parpol;
    }

    /** NPHD aktif (bukan BATAL) versi terbaru. */
    public function activeNphd(): ?NphdDocument
    {
        return $this->nphds->firstWhere(fn ($n) => $n->status !== 'BATAL');
    }

    public function disbursedTotal(): int
    {
        return (int) $this->disbursements()->where('status', 'DISALURKAN')->sum('amount');
    }

    public function realizationTotal(): int
    {
        return (int) $this->reports()->where('review_status', 'DITERIMA')->sum('total_realization');
    }

    public function returnedTotal(): int
    {
        return (int) $this->fundReturns()->sum('amount');
    }

    public function setStatus(Status $s): void
    {
        $this->status = $s;
        $this->status_changed_at = now();
    }

    // ---- cakupan data per peran (row-level security) -----------------------
    /**
     * Satu-satunya sumber kebenaran "siapa boleh melihat usulan apa".
     * - Pemohon: usulan lembaganya sendiri.
     * - OPD: usulan di bawah OPD-nya.
     * - Tim Verifikasi: lintas OPD, sejak usulan masuk verifikasi administrasi.
     * - TAPD/Bupati: sejak direkomendasikan. BPKAD: sejak pertimbangan TAPD.
     * - Inspektorat: semua usulan yang telah diajukan.
     * - Admin Sistem: tidak punya akses data permohonan (Blueprint Bab 6).
     */
    public function scopeVisibleTo(Builder $q, User $u): Builder
    {
        $reached = fn (array $statuses) => fn (Builder $w) => $w->whereExists(
            fn ($s) => $s->select(DB::raw(1))->from('status_histories')
                ->whereColumn('status_histories.application_id', 'grant_applications.id')
                ->whereIn('status_histories.to_status', $statuses)
        );

        $roles = $u->roleCodes();

        return $q->where(function (Builder $w) use ($u, $roles, $reached) {
            $w->whereRaw('1 = 0');
            if (in_array('ADMIN', $roles, true)) {
                $w->orWhere('status', '!=', Status::DRAFT->value); // Admin: lihat semua usulan (kecuali draf)
            }
            if (in_array('PEMOHON', $roles, true) && $u->organization_id) {
                $w->orWhere('applicant_org_id', $u->organization_id);
            }
            if (in_array('OPD', $roles, true) && $u->organization_id) {
                $w->orWhere(fn ($x) => $x->where('opd_id', $u->organization_id)->where('status', '!=', Status::DRAFT->value));
            }
            if (in_array('VERIFIKATOR', $roles, true)) {
                $w->orWhere(fn ($x) => $x->where($reached(['ADMIN_CHECK'])));
            }
            if (array_intersect(['TAPD', 'BUPATI'], $roles)) {
                $w->orWhere(fn ($x) => $x->where($reached(['RECOMMENDED'])));
            }
            if (in_array('BPKAD', $roles, true)) {
                $w->orWhere(fn ($x) => $x->where($reached(['TAPD_REVIEW'])));
            }
            if (in_array('INSPEKTORAT', $roles, true)) {
                $w->orWhere('status', '!=', Status::DRAFT->value);
            }
        });
    }
}
