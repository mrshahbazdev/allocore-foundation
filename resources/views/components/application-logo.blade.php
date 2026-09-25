@props(['dark' => false])
<span {{ $attributes->merge(['class' => 'inline-flex items-center']) }}>
    <img src="{{ asset('logo-mark.png') }}" alt="ALLOCORE" class="h-9 w-auto">
    <span class="ml-2.5 font-semibold tracking-tight text-[17px] leading-none">
        <span class="{{ $dark ? 'text-white' : 'text-[#0B0B0F]' }}">ALLO</span><span class="text-[#FACC15]">CORE</span>
    </span>
</span>
