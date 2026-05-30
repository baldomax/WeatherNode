@props([
    'name',
    'size' => 'md',
    'class' => '',
    'alt' => '',
])

@php
$sizes = [
    'xs' => 'w-3 h-3',
    'sm' => 'w-4 h-4',
    'md' => 'w-6 h-6',
    'lg' => 'w-8 h-8',
    'xl' => 'w-10 h-10',
    '2xl' => 'w-12 h-12',
    '3xl' => 'w-16 h-16',
    '4xl' => 'w-20 h-20',
    '5xl' => 'w-24 h-24',
];
$sizeClass = $sizes[$size] ?? $size;
@endphp

<img
    src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/' . $name . '.svg') }}"
    alt="{{ $alt ?: str_replace('-', ' ', $name) }}"
    {{ $attributes->merge(['class' => $sizeClass . ' ' . $class . ' inline-block']) }}
    loading="lazy"
/>
