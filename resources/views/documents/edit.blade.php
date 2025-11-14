{{-- resources/views/documents/edit.blade.php --}}
@extends('layouts.iso')

@section('content')
<div class="container-narrow">
    <h2>Edit Document — {{ $document->doc_code ?? $document->title }}</h2>

    @php
        // Action menuju updateCombined (sesuai controller milikmu)
        $action = route('documents.updateCombined', $document->id);
        $method = 'PUT';
        $submitLabel = 'Save Changes';
    @endphp

    {{-- pastikan controller edit() mengirim: $document, $departments, $categories --}}
    @include('documents._form', compact('action','method','document','departments','categories','submitLabel'))
</div>
@endsection
