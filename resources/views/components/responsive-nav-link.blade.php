@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-white text-start text-base font-medium text-white bg-white/20 focus:outline-none focus:text-white focus:bg-white/30 focus:border-white transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-white/80 hover:text-white hover:bg-white/10 hover:border-white/50 focus:outline-none focus:text-white focus:bg-white/10 focus:border-white/50 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
