{{-- Storefront Arabic/English toggle: a plain CSRF form, no theme JavaScript.
     It names the other language in its own script; the stored choice is
     applied by the Language middleware (session, then the store_locale cookie). --}}
@php
    $toggleLocale = \App\Support\StoreLocale::toggleTarget();
    $toggleName = $toggleLocale === 'ar' ? 'العربية' : 'English';
@endphp
<form method="POST" action="{{ route('language.toggle') }}" class="{{ $class ?? 'd-inline-block' }}" data-language-toggle>
    @csrf
    <input type="hidden" name="locale" value="{{ $toggleLocale }}">
    <input type="hidden" name="redirect" value="{{ request()->getRequestUri() }}">
    <button type="submit" class="{{ $buttonClass ?? 'btn btn-link p-0 text-secondary fs-12' }}"
        aria-label="{{ __('storefront.navigation.switch_language', ['language' => $toggleName]) }}">
        <i class="las la-globe" aria-hidden="true"></i>
        <span lang="{{ $toggleLocale }}" dir="{{ $toggleLocale === 'ar' ? 'rtl' : 'ltr' }}">{{ $toggleName }}</span>
    </button>
</form>
