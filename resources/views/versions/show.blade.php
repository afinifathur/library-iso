{{-- resources/views/versions/show.blade.php --}}
@extends('layouts.iso')

@section('title', ($document->doc_code ?? '') . ' - ' . ($version->version_label ?? 'Version'))

@section('content')
<div style="max-width:1000px;margin:auto;">
  <h2>{{ $document->doc_code }} — {{ $document->title }}</h2>
  <p class="small-muted">
    Version: <strong>{{ $version->version_label }}</strong> —
    Status: <strong>{{ $version->status }}</strong>
  </p>

  {{-- ACTIONS --}}
  <div style="margin-bottom:12px;display:flex;gap:8px;align-items:center;">
    <a class="btn btn-muted" href="{{ route('documents.show', $document->id) }}">Back to Document</a>

    {{-- Compare button: support both route name variants --}}
    @if(Route::has('versions.chooseCompare'))
      <a class="btn btn-outline" href="{{ route('versions.chooseCompare', $version->id) }}" target="_blank" rel="noopener">Compare</a>
    @elseif(Route::has('versions.choose_compare'))
      <a class="btn btn-outline" href="{{ route('versions.choose_compare', $version->id) }}" target="_blank" rel="noopener">Compare</a>
    @elseif(Route::has('documents.compare'))
      <a class="btn btn-outline" href="{{ route('documents.compare', $document->id) }}?v2={{ $version->id }}" target="_blank" rel="noopener">Compare</a>
    @endif

    {{-- Edit button (if allowed) --}}
    @php
      $canEdit = false;
      if (auth()->check()) {
          $u = auth()->user();
          if (method_exists($u, 'hasAnyRole') && $u->hasAnyRole(['kabag','mr','admin','director'])) {
              $canEdit = true;
          } elseif (method_exists($u, 'roles')) {
              try {
                  $names = $u->roles()->pluck('name')->map(fn($n) => strtolower($n))->toArray();
                  foreach (['kabag','mr','admin','director'] as $r) {
                      if (in_array($r, $names, true)) { $canEdit = true; break; }
                  }
              } catch (\Throwable $e) { /* ignore */ }
          } elseif (isset($u->roles) && is_iterable($u->roles)) {
              $names = collect($u->roles)->pluck('name')->map(fn($n) => strtolower($n))->toArray();
              foreach (['kabag','mr','admin','director'] as $r) {
                  if (in_array($r, $names, true)) { $canEdit = true; break; }
              }
          }
          if ($u->id === $version->created_by) { $canEdit = true; }
      }
    @endphp

    @if($canEdit)
      <a class="btn" href="{{ route('versions.edit', $version->id) }}">Edit Version</a>
    @endif
  </div>

  <div style="display:flex;gap:16px;">
    <div style="flex:1">
      <div style="background:#fff;border:1px solid #eef3f8;border-radius:8px;padding:12px;margin-bottom:12px;">
        <h3>Version details</h3>
        <p><strong>Change note:</strong> {{ $version->change_note ?? '-' }}</p>
        <p><strong>Created by:</strong> {{ optional($version->creator)->name ?? $version->created_by }}</p>
        <p>
          <strong>Signed by:</strong> {{ $version->signed_by ?? '-' }}
          @if($version->signed_at)
            ({{ \Carbon\Carbon::parse($version->signed_at)->format('Y-m-d') }})
          @endif
        </p>
        <p><strong>Checksum:</strong> <code style="font-size:12px">{{ $version->checksum }}</code></p>

        @if($version->file_path)
          <div style="margin-top:10px;">
            <strong>File:</strong><br>
            <a href="{{ url('/storage/' . ltrim($version->file_path, '/')) }}" target="_blank" rel="noopener">Download file</a>
          </div>
        @else
          <div style="margin-top:10px;">
            <strong>Text content</strong>
            <pre style="white-space:pre-wrap;background:#f8fafc;padding:10px;border-radius:6px;">{{ $version->pasted_text ?? $version->plain_text ?? '-' }}</pre>
          </div>
        @endif

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

{{-- Inline script: mark viewed when MR/admin/director opens page --}}
@php
  // compute permission to mark viewed (re-use logic like in controller)
  $canMarkViewed = false;
  if (auth()->check()) {
      $u = auth()->user();
      if (method_exists($u, 'hasAnyRole') && $u->hasAnyRole(['mr','admin','director'])) {
          $canMarkViewed = true;
      } elseif (method_exists($u, 'roles')) {
          try {
              $names = $u->roles()->pluck('name')->map(fn($n) => strtolower($n))->toArray();
              foreach (['mr','admin','director'] as $r) {
                  if (in_array($r, $names, true)) { $canMarkViewed = true; break; }
              }
          } catch (\Throwable $e) { /* ignore */ }
      } elseif (isset($u->roles) && is_iterable($u->roles)) {
          $names = collect($u->roles)->pluck('name')->map(fn($n) => strtolower($n))->toArray();
          foreach (['mr','admin','director'] as $r) {
              if (in_array($r, $names, true)) { $canMarkViewed = true; break; }
          }
      }
  }
@endphp

@if ($canMarkViewed && Route::has('versions.markViewed'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    const versionId = @json($version->id ?? null);
    if (!versionId) return;

    // CSRF token: prefer meta tag, fallback to blade token
    const meta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = meta ? meta.getAttribute('content') : @json(csrf_token());

    // build url from named route
    const url = @json(route('versions.markViewed', ['version' => $version->id]));

    // fire-and-forget POST; keepalive helps during navigation
    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({}),
        keepalive: true
    }).then(response => {
        // optional: you can inspect response and update UI
        if (!response.ok) {
            // Non-fatal; silently ignore
            console.debug('markViewed response not ok', response.status);
        }
        return response.json().catch(() => null);
    }).then(json => {
        if (json && json.mr_viewed_at) {
            console.debug('marked viewed at', json.mr_viewed_at);
            // optional: show a small indicator in UI
        }
    }).catch(err => {
        console.debug('markViewed failed', err);
    });
});
</script>
@endif

@endsection
