@props(['user', 'size' => 'h-9 w-9'])
<img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" loading="lazy"
     {{ $attributes->merge(['class' => "$size shrink-0 rounded-full object-cover bg-ink-800 ring-1 ring-ink-700"]) }}>
