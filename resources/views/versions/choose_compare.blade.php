{{-- resources/views/versions/choose_compare.blade.php --}}
@extends('layouts.app')

@section('title', 'Choose baseline to compare')

@section('content')
<div class="container-narrow">
  <h3>Compare: Candidate for {{ $document->doc_code ?? ($document->title ?? 'Document') }}</h3>
  <p>Comparing <strong>this version</strong>: {{ $version->version_label }} — choose one (or more) previous approved versions to compare against.</p>

  <form method="GET" action="{{ route('documents.compare', $document->id) }}">
    {{-- include current/new version as first selected item --}}
    <input type="hidden" name="versions[]" value="{{ $version->id }}">

    <div style="margin-bottom:12px;">
      <strong>Choose baseline versions (approved/current):</strong>
    </div>

    <div style="border:1px solid #e6eef6; padding:12px; border-radius:8px; background:#fff;">
      @if($candidates->isEmpty())
        <div class="alert alert-info">Tidak ada versi baseline yang disetujui sebelumnya — ini berarti dokumen baru.</div>
      @else
        <ul style="list-style:none; padding:0; margin:0;">
        @foreach($candidates as $cand)
          <li style="padding:8px 4px; border-bottom:1px solid #f1f5f9;">
            <label style="display:flex; align-items:center; gap:10px;">
              <input type="checkbox" name="versions[]" value="{{ $cand->id }}">
              <div>
                <div><strong>{{ $cand->version_label }}</strong> — {{ $cand->status }} — {{ $cand->created_at ? $cand->created_at->format('Y-m-d') : '' }}</div>
                <div style="font-size:13px;color:#6b7280;">{{ Str::limit($cand->change_note ?? $cand->plain_text ?? '', 180) }}</div>
              </div>
            </label>
          </li>
        @endforeach
        </ul>
      @endif
    </div>

    <div style="margin-top:12px;">
      <button type="submit" class="btn btn-primary">Compare selected</button>
      <a href="#" onclick="window.close(); return false;" class="btn btn-muted">Close</a>
    </div>
  </form>
</div>
@endsection
