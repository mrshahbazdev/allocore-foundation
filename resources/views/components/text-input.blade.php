@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-[#D6DEE9] focus:border-[#CA8A04] focus:ring-[#CA8A04]/30 rounded-lg shadow-sm text-sm placeholder:text-[#9AA9BD]']) }}>
