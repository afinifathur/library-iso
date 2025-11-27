{{-- resources/views/versions/show.blade.php --}}
@extends('layouts.iso')

@section('title', ($document->doc_code ?? '') . ' - ' . ($version->version_label ?? 'Version'))

@section('content')
@php
    use Illuminate\Support\Facades\Storage;

    // defensive guards
    $document = $document ?? null;
    $version = $version ?? null;
    $otherVersions = $otherVersions ?? collect();
    $user = auth()->user();
@endphp

<div style="max-width:1000px;margin:auto;">
  <h2>{{ $document->doc_code ?? '-' }} — {{ $document->title ?? '-' }}</h2>
  <p class="small-muted">
    Version: <strong>{{ $version->version_label ?? '-' }}</strong>
    — Status: <strong>{{ $version->status ?? '-' }}</strong>
  </p>

  <div style="display:flex;gap:16px;">
    <div style="flex:1">
      <div style="background:#fff;border:1px solid #eef3f8;border-radius:8px;padding:12px;margin-bottom:12px;">
        <h3>Version details</h3>

        <p><strong>Change note:</strong> {{ $version->change_note ?? '-' }}</p>
        <p><strong>Created by:</strong> {{ optional($version->creator)->name ?? $version->created_by ?? '-' }}</p>
        <p>
          <strong>Signed by:</strong>
          {{ $version->signed_by ?? '-' }}
          @if(!empty($version->signed_at))
            ({{ \Carbon\Carbon::parse($version->signed_at)->format('Y-m-d') }})
          @endif
        </p>
        <p><strong>Checksum:</strong>
          <code style="font-size:12px">{{ $version->checksum ?? '-' }}</code>
        </p>

        @php
          // file URL handling (prefer public disk; adjust if you use 'documents' disk)
          $fileUrl = null;
          if (!empty($version->file_path)) {
              try {
                  $fileUrl = Storage::disk('public')->url(ltrim($version->file_path, '/'));
              } catch (\Throwable $e) {
                  // fallback to asset if storage URL fails
                  $fileUrl = asset('storage/' . ltrim($version->file_path, '/'));
              }
          }
        @endphp

        @if(!empty($fileUrl))
          <div style="margin-top:10px;">
            <strong>File:</strong><br>
            <a href="{{ $fileUrl }}" target="_blank" rel="noopener noreferrer">Download file</a>
          </div>
        @else
          <div style="margin-top:10px;">
            <strong>Text content</strong>
            <pre style="white-space:pre-wrap;background:#f8fafc;padding:10px;border-radius:6px;">
{{ $version->pasted_text ?? $version->plain_text ?? '-' }}
            </pre>
          </div>
        @endif

        <div style="margin-top:10px;">
          <a class="btn" href="{{ route('documents.show', $document->id ?? 0) }}">Back to Document</a>

          @php
            $canEdit = false;
            if ($user) {
                if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['kabag','mr','admin','director'])) {
                    $canEdit = true;
                } elseif (isset($version->created_by) && $user->id == $version->created_by) {
                    $canEdit = true;
                }
            }
          @endphp

          @if($canEdit)
            <a class="btn" href="{{ route('versions.edit', $version->id) }}">Edit Version</a>
          @endif
        </div>
      </div>
    </div>

    <div style="width:260px;">
      <div style="background:#fff;border:1px solid #eef3f8;border-radius:8px;padding:12px;">
        <h4>Other versions</h4>
        <ul style="list-style:none;padding:0;margin:0">
          @forelse($otherVersions as $ov)
            <li style="padding:6px 0;border-bottom:1px solid #f1f5f9">
              <a href="{{ route('versions.show', $ov->id) }}">{{ $ov->version_label }}</a><br>
              <small class="small-muted">{{ $ov->status ?? '-' }} — {{ $ov->created_at ? $ov->created_at->format('Y-m-d') : '-' }}</small>
            </li>
          @empty
            <li style="padding:6px 0;color:#6b7280">No other versions.</li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>
</div>

{{-- Optional: notify opener window (useful when opening version from queue) --}}
<script>
(function(){
  try {
    var vid = "{{ $version->id ?? '' }}";
    if (vid && window.opener && !window.opener.closed) {
      try {
        window.opener.postMessage({ iso_action: 'version_opened', version_id: vid }, '*');
      } catch(e){}
      try { localStorage.setItem('iso_opened_version_' + vid, '1'); } catch(e){}
    }
  } catch(e){/* ignore errors */ }
})();
</script>
@endsection
