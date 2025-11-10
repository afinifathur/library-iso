@extends('layouts.iso')

@section('content')
<h2 style="margin-top:0">
  {{ $doc->doc_code }} — {{ $doc->title }}
</h2>

<p style="color:var(--muted);margin-top:4px;">
  Department: {{ $doc->department->code ?? '-' }} — {{ $doc->department->name ?? '' }}
</p>

<h4 style="margin-top:16px;">Versions</h4>

{{-- Compare selected (pilih 2 baris) --}}
<div style="margin-bottom:12px;">
  <button id="compare-selected" class="btn" disabled>Compare selected</button>
</div>

<table class="table">
  <thead>
    <tr>
      <th style="width:36px"></th>
      <th>Version</th>
      <th>Status</th>
      <th>Text</th>
      <th>Signed By</th>
      <th>Uploaded</th>
      <th>Download</th>
      <th>Compare</th>
    </tr>
  </thead>

  <tbody>
  @forelse($doc->versions as $v)
    <tr>
      {{-- Checkbox pilih --}}
      <td>
        <input type="checkbox" class="version-choose" value="{{ $v->id }}">
      </td>

      {{-- Version Label --}}
      <td>{{ $v->version_label }}</td>

      {{-- Status Badge --}}
      <td>
        @switch($v->status)
          @case('approved') <span class="badge badge-success">approved</span> @break
          @case('rejected') <span class="badge badge-danger">rejected</span> @break
          @default          <span class="badge badge-warning">{{ $v->status ?? 'unknown' }}</span>
        @endswitch
      </td>

      {{-- Text Availability --}}
      <td>
        @if(!empty($v->pasted_text))
          <span class="badge badge-info">pasted</span>
        @elseif(!empty($v->plain_text))
          <span class="badge badge-success">indexed</span>
        @else
          <span class="badge badge-warning">no-text</span>
        @endif
      </td>

      {{-- Signed by --}}
      <td>{{ $v->signed_by ?? '-' }}</td>

      {{-- Uploaded (safe date) --}}
      <td>{{ optional($v->created_at)->format('Y-m-d') ?? '-' }}</td>

      {{-- Download PDF --}}
      <td>
        <a class="btn-muted" href="{{ route('versions.download', $v->id) }}">Download</a>
      </td>

      {{-- Compare per-baris (dengan prevVersion jika ada) --}}
      <td>
        <a href="{{ route('documents.compare', [
              'document' => $doc->id,
              'v1'       => optional($v->prevVersion)->id,
              'v2'       => $v->id
          ]) }}"
          class="btn btn-sm btn-outline-primary"
          @if(!$v->prevVersion)
            style="opacity:0.5;pointer-events:none;"
          @endif
        >
          Compare
        </a>
      </td>
    </tr>

  @empty
    <tr>
      <td colspan="8" style="text-align:center;color:var(--muted);padding:14px;">
        No versions found.
      </td>
    </tr>
  @endforelse
  </tbody>
</table>

{{-- JS: aktifkan tombol & navigate ke compare --}}
<script>
document.addEventListener('DOMContentLoaded', function(){
  const btn = document.getElementById('compare-selected');
  const checkboxes = Array.from(document.querySelectorAll('.version-choose'));

  function updateBtn(){
    const checked = checkboxes.filter(c => c.checked).map(c => c.value);
    btn.disabled = checked.length !== 2;
    btn.dataset.ids = checked.join(',');
  }

  checkboxes.forEach(cb => cb.addEventListener('change', updateBtn));
  updateBtn();

  btn.addEventListener('click', function(){
    const ids = this.dataset.ids;
    if(!ids) return;
    const parts = ids.split(',');
    if(parts.length !== 2) return;
    const v1 = parts[0];
    const v2 = parts[1];
    const base = @json(route('documents.compare', ['document' => $doc->id]));
    const url = `${base}?v1=${encodeURIComponent(v1)}&v2=${encodeURIComponent(v2)}`;
    window.location.href = url;
  });
});
</script>
@endsection
