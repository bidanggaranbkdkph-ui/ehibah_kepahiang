@extends('layouts.app')

@section('title', 'Jadwal & Batas Waktu Tahapan Hibah')

@section('content')
<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
    <div>
        <h1 style="margin:0; font-size:1.5rem;">Jadwal & Batas Waktu Tahapan Bisnis</h1>
        <p style="margin:0.25rem 0 0; color:var(--text-muted, #64748b);">
            Atur tenggat waktu penyampaian, buka/tutup tahapan secara manual, dan sesuaikan dengan regulasi daerah.
        </p>
    </div>

    <form method="GET" action="{{ route('admin.schedules.index') }}" style="display:flex; gap:0.5rem; align-items:center;">
        <label for="fiscal_year_id" style="font-weight:600;">Tahun Anggaran:</label>
        <select name="fiscal_year_id" id="fiscal_year_id" onchange="this.form.submit()" class="form-control">
            @foreach($fiscalYears as $fy)
                <option value="{{ $fy->id }}" @selected($fy->id === $selectedYearId)>
                    TA {{ $fy->year }} ({{ strtoupper($fy->status) }})
                </option>
            @endforeach
        </select>
    </form>
</div>

<div class="card" style="overflow-x:auto;">
    <table class="table" style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="text-align:left; border-bottom:2px solid #e2e8f0;">
                <th style="padding:0.75rem;">No</th>
                <th style="padding:0.75rem;">Tahapan Prosedur Bisnis & Regulasi</th>
                <th style="padding:0.75rem;">Status Saat Ini</th>
                <th style="padding:0.75rem;">Pengaturan Jadwal & Mode</th>
                <th style="padding:0.75rem;">Aksi Cepat (Buka/Tutup)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($schedules as $item)
                @php
                    $isOpen = $item->isOpen();
                @endphp
                <tr style="border-bottom:1px solid #e2e8f0; vertical-align:top;">
                    <td style="padding:0.75rem; font-weight:700;">{{ $item->sequence }}</td>
                    <td style="padding:0.75rem; max-width:300px;">
                        <div style="font-weight:700; color:#0f172a;">{{ $item->stage_name }}</div>
                        <div style="font-size:0.8rem; color:#475569; margin-top:0.25rem;">
                            <strong>Kode:</strong> <code>{{ $item->stage_code }}</code>
                        </div>
                        <div style="font-size:0.8rem; color:#64748b; margin-top:0.25rem;">
                            <strong>Regulasi:</strong> {{ $item->regulation_ref ?? '-' }}
                        </div>
                    </td>
                    <td style="padding:0.75rem;">
                        <span style="display:inline-block; padding:0.25rem 0.6rem; border-radius:999px; font-size:0.75rem; font-weight:700; background:{{ $isOpen ? '#dcfce7' : '#fee2e2' }}; color:{{ $isOpen ? '#166534' : '#991b1b' }};">
                            {{ $isOpen ? 'TERBUKA' : 'TERTUTUP' }}
                        </span>
                        <div style="font-size:0.8rem; color:#475569; margin-top:0.35rem;">
                            {{ $item->statusMessage() }}
                        </div>
                    </td>
                    <td style="padding:0.75rem;">
                        <form method="POST" action="{{ route('admin.schedules.update', $item) }}" style="display:grid; gap:0.5rem;">
                            @csrf
                            @method('PUT')
                            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                                <div>
                                    <label style="font-size:0.75rem; display:block;">Mulai Dibuka:</label>
                                    <input type="datetime-local" name="start_date"
                                           value="{{ $item->start_date?->format('Y-m-d\TH:i') }}"
                                           class="form-control" style="font-size:0.85rem;">
                                </div>
                                <div>
                                    <label style="font-size:0.75rem; display:block;">Batas Akhir (Tutup):</label>
                                    <input type="datetime-local" name="end_date"
                                           value="{{ $item->end_date?->format('Y-m-d\TH:i') }}"
                                           class="form-control" style="font-size:0.85rem;">
                                </div>
                                <div>
                                    <label style="font-size:0.75rem; display:block;">Mode Kontrol:</label>
                                    <select name="control_mode" class="form-control" style="font-size:0.85rem;">
                                        <option value="AUTO" @selected($item->control_mode === 'AUTO')>Otomatis (Ikut Tanggal)</option>
                                        <option value="FORCE_OPEN" @selected($item->control_mode === 'FORCE_OPEN')>Buka Paksa (Override)</option>
                                        <option value="FORCE_CLOSED" @selected($item->control_mode === 'FORCE_CLOSED')>Tutup Paksa (Override)</option>
                                    </select>
                                </div>
                            </div>
                            <div style="display:flex; gap:0.5rem;">
                                <input type="text" name="notes" value="{{ $item->notes }}"
                                       placeholder="Catatan/alasan perpanjangan atau penutupan..."
                                       class="form-control" style="font-size:0.85rem; flex:1;">
                                <button type="submit" class="btn btn-primary" style="font-size:0.85rem;">Simpan</button>
                            </div>
                        </form>
                    </td>
                    <td style="padding:0.75rem;">
                        <div style="display:flex; flex-direction:column; gap:0.35rem;">
                            <form method="POST" action="{{ route('admin.schedules.toggle', $item) }}">
                                @csrf
                                <input type="hidden" name="mode" value="FORCE_OPEN">
                                <button type="submit" class="btn btn-sm" style="width:100%; background:#16a34a; color:#fff; border:none; padding:0.35rem 0.5rem; border-radius:4px; cursor:pointer;">
                                    Buka Tahapan Sekarang
                                </button>
                            </form>

                            <form method="POST" action="{{ route('admin.schedules.toggle', $item) }}">
                                @csrf
                                <input type="hidden" name="mode" value="FORCE_CLOSED">
                                <button type="submit" class="btn btn-sm" style="width:100%; background:#dc2626; color:#fff; border:none; padding:0.35rem 0.5rem; border-radius:4px; cursor:pointer;">
                                    Tutup Tahapan Sekarang
                                </button>
                            </form>

                            @if($item->control_mode !== 'AUTO')
                                <form method="POST" action="{{ route('admin.schedules.toggle', $item) }}">
                                    @csrf
                                    <input type="hidden" name="mode" value="AUTO">
                                    <button type="submit" class="btn btn-sm" style="width:100%; background:#475569; color:#fff; border:none; padding:0.35rem 0.5rem; border-radius:4px; cursor:pointer;">
                                        Ikuti Jadwal Otomatis
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection