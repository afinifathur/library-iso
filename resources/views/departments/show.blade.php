@extends('layouts.iso')
@section('content')
<h2>Department: {{ $department->code }} — {{ $department->name }}</h2>
<table class="table">
<thead><tr><th>Doc Code</th><th>Title</th><th>Latest</th></tr></thead>
<tbody>
@foreach($documents as $d)
<tr>
  <td>{{ $d->doc_code }}</td>
  <td><a href="{{ route('documents.show',$d->id) }}">{{ $d->title }}</a></td>
  <td>{{ $d->currentVersion->version_label ?? '-' }}</td>
</tr>
@endforeach
</tbody>
</table>
{{ $documents->links() }}
@endsection
