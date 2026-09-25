<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2.5 bg-[#0C1622] border border-transparent rounded-lg font-semibold text-sm text-white hover:bg-[#16283F] focus:bg-[#16283F] active:bg-[#0C1622] focus:outline-none focus:ring-2 focus:ring-[#B07C34] focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
