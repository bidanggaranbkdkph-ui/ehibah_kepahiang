@php
    $tone = fn ($to) => in_array($to, ['REJECTED', 'CANCELLED']) ? 'bad' : (in_array($to, ['CLOSED', 'APPROVED']) ? 'ok' : '');
    $audits = \App\Models\AuditLog::where('application_id', $app->id)->orderByDesc('id')->limit(40)->get();
    $canAudit = \App\Support\Permission::allows(auth()->user(), 'audit_trail', 'V');
@endphp
<div class="grid g2" style="align-items:start">
    <div class="card">
        <div class="card-h"><h3>Riwayat status</h3></div>
        <ul class="timeline">
            @foreach ($app->histories->reverse() as $h)
                <li class="{{ $tone($h->to_status) }}"><b><x-status :status="$h->to_status"/></b>
                    <div class="small">{{ $h->user?->name ?? 'Sistem' }} <span class="muted">({{ config('ehibah.roles.'.$h->role_code, $h->role_code) }})</span></div>
                    @if ($h->note)<div class="small">“{{ $h->note }}”</div>@endif
                    <small>{{ tgljam($h->created_at) }}</small></li>
            @endforeach
        </ul>
    </div>
    <div class="card">
        <div class="card-h"><h3>Jejak audit usulan</h3>@if ($canAudit)<span class="sp"></span><a class="btn sm" href="{{ route('audit.index', ['app' => $app->id]) }}">Selengkapnya</a>@endif</div>
        @if ($canAudit || auth()->user()->hasRole('OPD'))
            <div class="table-wrap"><table class="table-plain"><thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th></tr></thead><tbody>
                @foreach ($audits as $a)<tr><td class="nowrap small">{{ tgljam($a->created_at) }}</td><td class="small">{{ $a->user_name ?? 'Sistem' }}</td><td class="small mono">{{ $a->action }}</td></tr>@endforeach
            </tbody></table></div>
        @else <div class="empty small">Jejak audit lengkap tersedia untuk BPKAD dan Inspektorat.</div> @endif
    </div>
</div>
