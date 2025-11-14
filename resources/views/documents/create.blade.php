{{-- resources/views/documents/create.blade.php --}}
@extends('layouts.iso')

@section('content')
<div class="container-narrow">
    <h2>New Document (Upload Baseline)</h2>

    @php
        $action = route('documents.store');
        $method = 'POST';
        $submitLabel = 'Save baseline (v1) & Publish';
    @endphp

    @include('documents._form', compact('action','method','departments','categories','submitLabel'))
</div>
@endsection
