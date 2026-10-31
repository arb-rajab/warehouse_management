@php
    $mailLocale = $array['locale'];
@endphp
{!! $array['subject'] ?? '' !!}

@if(($array['view'] ?? '') === 'shop.emails.newsletter' && !isset($array['content_key']))
{!! html_entity_decode(strip_tags($array['content'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8') !!}
@else
{!! $array['content'] ?? '' !!}
@endif
@if(isset($array['sender']))

{{ __('delivery.mail.sender', [], $mailLocale) }}: {!! $array['sender'] !!}
@endif
@if(isset($array['details']))

{{ __('delivery.mail.details', [], $mailLocale) }}:
@if($array['details_are_html'] ?? false)
{!! html_entity_decode(strip_tags(clean_html($array['details'])), ENT_QUOTES | ENT_HTML5, 'UTF-8') !!}
@else
{!! $array['details'] !!}
@endif
@endif
@if($array['include_terms'] ?? false)

{!! html_entity_decode(strip_tags(get_setting('terms') ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8') !!}
@endif
@if(!empty($array['link']))

{{ __($array['action_key'] ?? 'delivery.mail.see_details', [], $mailLocale) }}:
{!! $array['link'] !!}
@endif
