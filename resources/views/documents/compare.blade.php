@extends('layouts.iso')

@section('title', 'Compare Versions: ' . $doc->doc_code)

@section('content')
<div class="container mt-4">
  <h2 class="mb-2 text-xl font-bold">Perbandingan Dokumen: {{ $doc->doc_code }}</h2>
  <div class="text-sm text-gray-600 mb-4">
    <div>Versi <strong>{{ $ver1->version_label }}</strong> ({{ $ver1->created_at->format('d M Y') }})
      vs <strong>{{ $ver2->version_label }}</strong> ({{ $ver2->created_at->format('d M Y') }})</div>
    <div>Diupload oleh: <em>{{ optional($ver2->creator)->name ?? 'Tidak diketahui' }}</em></div>
  </div>

  <div class="bg-white border rounded-lg shadow-sm p-3 overflow-auto">
    <style>
      ins { background-color: #a3e6a1; text-decoration: none; }
      del { background-color: #f5a5a5; text-decoration: none; }
      pre { white-space: pre-wrap; word-wrap: break-word; font-family: monospace; }
    </style>

    <pre>{!! $diff !!}</pre>
  </div>

  <div class="mt-4">
    <a href="{{ route('documents.show', $doc->id) }}" class="btn btn-secondary">← Kembali ke dokumen</a>
  </div>
</div>
@endsection
