@extends('layouts.iso')

@section('title', ($document->doc_code ?? '') . ' - ' . ($version->version_label ?? 'Version'))

@section('content')
<div style="max-width:1000px;margin:auto;">
  <h2>{{ $document->doc_code }} — {{ $document->title }}</h2>
  <p class="small-muted">Version: <strong>{{ $version->version_label }}</strong> — Status: <strong>{{ $version->status }}</strong></p>

  <div style="display:flex;gap:16px;">
    <div style="flex:1">
      <div style="background:#fff;border:1px solid #eef3f8;border-radius:8px;padding:12px;margin-bottom:12px;">
        <h3>Version details</h3>
        <p><strong>Change note:</strong> {{ $version->change_note ?? '-' }}</p>
        <p><strong>Created by:</strong> {{ optional($version->creator)->name ?? $version->created_by }}</p>
        <p><strong>Signed by:</strong> {{ $version->signed_by ?? '-' }} @if($version->signed_at) ({{ \Carbon\Carbon::parse($version->signed_at)->format('Y-m-d') }}) @endif</p>
        <p><strong>Checksum:</strong> <code style="font-size:12px">{{ $version->checksum }}</code></p>

        @if($version->file_path)
          <div style="margin-top:10px;">
            <strong>File:</strong><br>
            <a href="{{ url('/storage/' . $version->file_path) }}" target="_blank">Download file</a>
          </div>
        @else
          <div style="margin-top:10px;">
            <strong>Text content</strong>
            <pre style="white-space:pre-wrap;background:#f8fafc;padding:10px;border-radius:6px;">{{ $version->pasted_text ?? $version->plain_text ?? '-' }}</pre>
          </div>
        @endif

        <div style="margin-top:10px;">
          <a class="btn" href="{{ route('documents.show', $document->id) }}">Back to Document</a>
          @if(auth()->check() && (auth()->user()->hasAnyRole(['kabag','mr','admin','director']) || auth()->user()->id == $version->created_by))
            <a class="btn" href="{{ route('versions.edit', $version->id) }}">Edit Version</a>
          @endif
        </div>
      </div>
    </div>

    <div style="width:260px;">
      <div style="background:#fff;border:1px solid #eef3f8;border-radius:8px;padding:12px;">
        <h4>Other versions</h4>
        <ul style="list-style:none;padding:0;margin:0">
          @foreach($otherVersions as $ov)
            <li style="padding:6px 0;border-bottom:1px solid #f1f5f9">
              <a href="{{ route('versions.show', $ov->id) }}">{{ $ov->version_label }}</a><br>
              <small class="small-muted">{{ $ov->status }} — {{ $ov->created_at ? $ov->created_at->format('Y-m-d') : '-' }}</small>
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection
