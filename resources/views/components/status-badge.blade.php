@props(['status'])
@php
    $map = [
        'active' => 'badge-green', 'approved' => 'badge-green', 'running' => 'badge-green',
        'pending' => 'badge-gold', 'upcoming' => 'badge-blue',
        'blocked' => 'badge-red', 'rejected' => 'badge-red',
        'completed' => 'badge-gray',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'badge '.($map[$status] ?? 'badge-gray')]) }}>{{ ucfirst($status) }}</span>
