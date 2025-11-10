@extends('layouts.iso')
@section('content')
<h2>Revision History</h2>
<p>Chronological list of all document versions.</p>
<table class="table">
<thead><tr><th>Doc Code</th><th>Title</th><th>Version</th><th>Signed By</th><th>Signed At</th><th>Created</th></tr></thead>
<tbody>
@foreach($history as $h)
<tr>
  <td>{{ $h->document->doc_code }}</td>
  <td>{{ $h->document->title }}</td>
  <td>{{ $h->version_label }}</td>
  <td>{{ $h->signed_by ?? '-' }}</td>
  <td>{{ $h->signed_at ? $h->signed_at->format('Y-m-d') : '-' }}</td>
  <td>{{ $h->created_at->format('Y-m-d') }}</td>
</tr>
@endforeach
</tbody>
</table>
{{ $history->links() }}
@endsection
