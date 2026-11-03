{{ $appName ?: config('app.name') }}
{{ $appFooter }}
----------------------------------------

{{ $title }}

@foreach($paragraphs as $paragraph)
{{ $paragraph }}

@endforeach
@if($highlight)
{{ $highlight }}

@endif
@if($actionUrl)
{{ $actionText ?: 'Buka tautan' }}:
{{ $actionUrl }}

@endif
@if($note)
{{ $note }}

@endif
--
Email otomatis dari sistem {{ $appName ?: config('app.name') }}. Mohon jangan membalas email ini.
