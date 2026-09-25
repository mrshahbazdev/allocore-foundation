<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-white border border-[#D6DEE9] rounded-lg font-medium text-sm text-[#1A2433] shadow-sm hover:bg-[#F6F7F9] focus:outline-none focus:ring-2 focus:ring-[#B07C34]/40 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
