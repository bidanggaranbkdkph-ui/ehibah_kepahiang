@extends('layouts.app')
@section('title', 'Antrean tindakan')
@section('heading', 'Antrean Tindakan')
@section('content')
<div class="page-head"><div><h1>Antrean tindakan</h1><p>{{ $total }} usulan menunggu tindakan sesuai peran Anda. Diurutkan dari yang paling lama menunggu.</p></div></div>
@forelse ($apps as $status => $list)
    <div class="card" style="margin-bottom:1rem">
        <div class="card-h"><x-status :status="$status"/><h3>{{ $list->count() }} usulan</h3></div>
        <div class="table-wrap"><table class="responsive table-plain">
            <thead><tr><th>No. Register</th><th>Usulan</th><th>Pemohon</th><th>OPD</th><th class="num">Nilai</th><th>Menunggu sejak</th><th></th></tr></thead>
            <tbody>
            @foreach ($list as $a)
                <tr>
                    <td data-label="No. Register" class="mono nowrap">{{ $a->reg_number }}</td>
                    <td data-label="Usulan"><b>{{ \Illuminate\Support\Str::limit($a->title, 60) }}</b></td>
                    <td data-label="Pemohon">{{ $a->applicant->name }}</td><td data-label="OPD">{{ $a->opd->name }}</td>
                    <td data-label="Nilai" class="num">{{ rupiah($a->amount_requested) }}</td>
                    <td data-label="Menunggu sejak" class="nowrap">{{ tgl($a->status_changed_at, true) }} <span class="muted small">({{ $a->status_changed_at?->diffForHumans() }})</span></td>
                    <td><a class="btn sm primary" href="{{ route('applications.show', $a) }}">Proses</a></td>
                </tr>
            @endforeach
            </tbody></table></div>
    </div>
@empty
    <div class="card"><div class="empty"><svg class="i i-lg"><use href="#i-check"/></svg><p>Tidak ada usulan yang menunggu tindakan Anda. 🎉</p></div></div>
@endforelse
@endsection
