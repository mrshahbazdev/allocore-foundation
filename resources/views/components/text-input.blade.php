@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-[#D6DEE9] focus:border-[#B07C34] focus:ring-[#B07C34]/30 rounded-lg shadow-sm text-sm placeholder:text-[#9AA9BD]']) }}>
