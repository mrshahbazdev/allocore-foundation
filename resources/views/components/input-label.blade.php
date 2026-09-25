@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-[13px] text-[#42536A]']) }}>
    {{ $value ?? $slot }}
</label>
