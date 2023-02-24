@component('mail::layout')
    {{-- Header --}}
    @slot('header')
        @component('mail::header', ['url' => lp_config('app.url')])
            {{ lp_config('app.name') }}
        @endcomponent
    @endslot

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        @slot('subcopy')
            @component('mail::subcopy')
                {{ $subcopy }}
            @endcomponent
        @endslot
    @endisset

    {{-- Footer --}}
    @slot('footer')
        @component('mail::footer')
            © {{ date('Y') }} {{ lp_config('app.name') }}. @lang('All rights reserved.')
        @endcomponent
    @endslot
@endcomponent
