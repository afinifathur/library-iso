{{-- resources/views/documents/edit.blade.php --}}
@extends('layouts.iso')

@section('title', 'Edit Document — '.($document->doc_code ?: $document->title))

@section('content')
<div class="container-narrow">
    <h2>Edit Document — {{ $document->doc_code ?? $document->title }}</h2>

    @php
        // Action menuju updateCombined (sesuai DocumentController@updateCombined)
        $action       = route('documents.updateCombined', $document->id);
        $method       = 'PUT';
        $submitLabel  = 'Save Changes';
        // supaya link "Open Drafts" tetap muncul (opsional, karena default di _form juga true)
        $showDraftLink = true;
    @endphp

    {{-- Controller edit() harus mengirim: $document, $departments, $categories --}}
    @include('documents._form', compact('action', 'method', 'document', 'departments', 'categories', 'submitLabel', 'showDraftLink'))
</div>
@endsection
