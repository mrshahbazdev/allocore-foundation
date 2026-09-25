<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2.5 bg-[#0B0B0F] border border-transparent rounded-lg font-semibold text-sm text-white hover:bg-[#1A1A1F] focus:bg-[#1A1A1F] active:bg-[#0B0B0F] focus:outline-none focus:ring-2 focus:ring-[#CA8A04] focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
