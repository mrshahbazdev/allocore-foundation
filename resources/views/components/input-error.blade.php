@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-[13px] text-[#A6362E] space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
