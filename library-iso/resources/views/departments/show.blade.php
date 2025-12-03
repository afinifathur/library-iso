@extends('layouts.iso')

@section('content')
<div style="max-width:1100px;margin:0 auto;">
  <h2 style="margin-top:0">{{ $department->code }} — {{ $department->name }}</h2>
  <p class="small-muted">Documents grouped by type (from doc_code prefix)</p>

  @foreach($groups as $prefix => $docs)
    <div style="margin-top:18px;">
      <h3 style="margin-bottom:8px">{{ $prefix }}</h3>
      <div style="background:#fff;border:1px solid #eef3f8;border-radius:10px;padding:12px;">
        <table class="table" style="width:100%">
          <thead>
            <tr><th>Code</th><th>Title</th><th style="width:120px">Latest</th><th style="width:100px">Status</th><th style="width:140px">Actions</th></tr>
          </thead>
          <tbody>
            @foreach($docs as $d)
              @php
                $latest = $d->versions->first(); // versions ordered desc
              @endphp
              <tr>
                <td>{{ $d->doc_code }}</td>
                <td><a href="{{ route('documents.show',$d->id) }}">{{ $d->title }}</a></td>
                <td>{{ $latest ? ($latest->version_label . ' — ' . ($latest->created_at ? $latest->created_at->format('Y-m-d') : '-')) : '-' }}</td>
                <td>
                  @if($latest)
                    @if($latest->status == 'approved') <span class="badge badge-success">approved</span>
                    @elseif($latest->status == 'rejected') <span class="badge badge-danger">rejected</span>
                    @else <span class="badge badge-warning">{{ $latest->status }}</span>
                    @endif
                  @else
                    <span class="small-muted">no-version</span>
                  @endif
                </td>
                <td>
                  <a class="btn-muted" href="{{ route('documents.show',$d->id) }}">View</a>
                  <a class="btn" href="{{ route('documents.create', ['document_id' => $d->id]) }}">+ New Version</a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endforeach

  <div style="margin-top:28px;">
    <h3>Dokumen lain yang menyebut dokumen di departemen ini</h3>
    @if($related->isEmpty())
      <div class="small-muted">No related documents found.</div>
    @else
      <div style="background:#fff;border:1px solid #eef3f8;border-radius:10px;padding:12px;">
        <table class="table">
          <thead><tr><th>Doc</th><th>References</th></tr></thead>
          <tbody>
            @foreach($related as $r)
              <tr>
                <td><a href="{{ route('documents.show', $r->doc_id) }}">{{ $r->doc_code }} — {{ $r->title }}</a></td>
                <td>references <a href="{{ route('documents.show', $r->related_to) }}">{{ $r->related_code }}</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
@endsection
