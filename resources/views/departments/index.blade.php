@extends('layouts.iso')
@section('content')
<h2>Departments</h2>
<table class="table">
<thead><tr><th>Code</th><th>Name</th><th>Documents</th></tr></thead>
<tbody>
@foreach($depts as $d)
<tr>
  <td><a href="{{ route('departments.show',$d->id) }}">{{ $d->code }}</a></td>
  <td>{{ $d->name }}</td>
  <td>{{ $d->documents_count }}</td>
</tr>
@endforeach
</tbody>
</table>
@endsection
