@props(['status'])
@php $s = $status instanceof \App\Enums\Status ? $status : \App\Enums\Status::from($status); @endphp
<span class="badge {{ $s->tone() }}"><i class="dotc"></i>{{ $s->label() }}</span>
