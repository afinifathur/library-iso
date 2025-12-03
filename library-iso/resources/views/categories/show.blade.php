@extends('layouts.iso')
@section('content')
<h2>Category: {{ $category->name }}</h2>
<p>{{ $category->description }}</p>

<table class="table">
<thead><tr><th>Doc Code</th><th>Title</th><th>Dept</th><th>Latest</th></tr></thead>
<tbody>
@foreach($documents as $d)
<tr>
  <td>{{ $d->doc_code }}</td>
  <td><a href="{{ route('documents.show',$d->id) }}">{{ $d->title }}</a></td>
  <td>{{ $d->department->code ?? '-' }}</td>
  <td>{{ $d->currentVersion->version_label ?? '-' }}</td>
</tr>
@endforeach
</tbody>
</table>

{{ $documents->links() }}
@endsection
