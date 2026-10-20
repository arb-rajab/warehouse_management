{{-- $schemas: list of schema.org arrays. Printed only through StructuredData::encode(), whose \u escapes keep any value from closing the <script> or breaking the JSON. --}}
@foreach ($schemas as $schema)
    <script type="application/ld+json">{!! \App\Support\StructuredData::encode($schema) !!}</script>
@endforeach
