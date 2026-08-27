@props(['value'])
@php($p = config('manutenzione.priorita.'.$value, ['short' => $value, 'color' => '#78909c']))
<span class="badge" style="background: {{ $p['color'] }}">{{ $p['short'] }}</span>
