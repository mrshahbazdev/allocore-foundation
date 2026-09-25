<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-[#A6362E] border border-transparent rounded-lg font-semibold text-sm text-white hover:bg-[#8F2E27] active:bg-[#8F2E27] focus:outline-none focus:ring-2 focus:ring-[#A6362E]/40 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
