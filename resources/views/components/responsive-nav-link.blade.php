@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-[#CA8A04] text-start text-base font-medium text-[#0B0B0F] bg-[#FEF9C3] focus:outline-none transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-[#5B6B7E] hover:text-[#0B0B0F] hover:bg-[#F6F7F9] hover:border-[#D6DEE9] focus:outline-none transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
