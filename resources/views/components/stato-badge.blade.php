@props(['value'])
@php($s = config('manutenzione.stati.'.$value, ['label' => $value, 'color' => '#78909c']))
<span class="badge" style="background: {{ $s['color'] }}">
    <span class="dot"></span>{{ $s['label'] }}
</span>
