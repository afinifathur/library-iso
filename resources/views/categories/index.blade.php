@extends('layouts.iso')
@section('content')
<h2>Categories</h2>
<table class="table">
<thead><tr><th>Category</th><th>Description</th><th>Docs</th></tr></thead>
<tbody>
@foreach($categories as $c)
<tr>
  <td><a href="{{ route('categories.show',$c->id) }}">{{ $c->name }}</a></td>
  <td>{{ $c->description }}</td>
  <td>{{ $c->documents_count }}</td>
</tr>
@endforeach
</tbody>
</table>
@endsection
