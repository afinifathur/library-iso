@extends('layouts.iso')

@section('title','Approval Queue')

@section('content')
<div style="max-width:1000px;margin:18px auto;">
  <h2>Approval Queue @if($status) — {{ ucfirst($status) }} @endif</h2>

  @if(session('success'))
    <div style="padding:10px;background:#ecfccb;border:1px solid #d9f99d;margin-bottom:12px;border-radius:8px;color:#25630f;">
      {{ session('success') }}
    </div>
  @endif

  <div style="margin-bottom:12px;">
    <a class="btn" href="{{ route('approval.index', ['status'=>'pending']) }}">Pending</a>
    <a class="btn-muted" href="{{ route('approval.index', ['status'=>'approved']) }}">Approved</a>
    <a class="btn-muted" href="{{ route('approval.index', ['status'=>'rejected']) }}">Rejected</a>
    <a class="btn-muted" href="{{ route('approval.index') }}">All</a>
  </div>

  <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #eef3f8;border-radius:8px;">
    <thead>
      <tr style="text-align:left;color:#555">
        <th style="padding:10px;border-bottom:1px solid #f0f4f8">When</th>
        <th style="padding:10px;border-bottom:1px solid #f0f4f8">Document</th>
        <th style="padding:10px;border-bottom:1px solid #f0f4f8">Version</th>
        <th style="padding:10px;border-bottom:1px solid #f0f4f8">By</th>
        <th style="padding:10px;border-bottom:1px solid #f0f4f8">Status</th>
        <th style="padding:10px;border-bottom:1px solid #f0f4f8">Action</th>
      </tr>
    </thead>
    <tbody>
      @forelse($versions as $v)
      <tr>
        <td style="padding:10px;border-bottom:1px solid #fafafa; width:130px;">
          {{ $v->created_at ? $v->created_at->format('Y-m-d H:i') : '-' }}
        </td>
        <td style="padding:10px;border-bottom:1px solid #fafafa;">
          <a href="{{ route('documents.show', $v->document->id) }}">{{ $v->document->doc_code }} — {{ $v->document->title }}</a>
        </td>
        <td style="padding:10px;border-bottom:1px solid #fafafa;">{{ $v->version_label }}</td>
        <td style="padding:10px;border-bottom:1px solid #fafafa;">{{ optional($v->creator)->name ?? '-' }}</td>
        <td style="padding:10px;border-bottom:1px solid #fafafa;">{{ $v->status }}</td>
        <td style="padding:10px;border-bottom:1px solid #fafafa; width:260px;">
          <a class="btn" href="{{ route('documents.show', $v->document->id) }}" style="margin-right:6px">Open</a>

          {{-- Approve form --}}
          <form action="{{ route('approval.approve', $v->id) }}" method="post" style="display:inline-block;margin-right:6px;">
            @csrf
            <input type="hidden" name="note" value="Approved by {{ auth()->user()->name ?? auth()->user()->email }}">
            <button class="btn" type="submit" onclick="return confirm('Approve version {{ $v->version_label }} ?')">Approve</button>
          </form>

          {{-- Reject: opens small inline form --}}
          <button class="btn-muted" onclick="document.getElementById('reject-form-{{ $v->id }}').style.display='block'">Reject</button>

          <form id="reject-form-{{ $v->id }}" action="{{ route('approval.reject',$v->id) }}" method="post" style="display:none;margin-top:8px;">
            @csrf
            <textarea name="note" rows="2" style="width:100%;padding:6px;border-radius:6px;border:1px solid #e6eef8" placeholder="Rejection note (optional)"></textarea>
            <div style="margin-top:6px">
              <button class="btn" type="submit" onclick="return confirm('Reject this version?')">Submit Reject</button>
              <button class="btn-muted" type="button" onclick="document.getElementById('reject-form-{{ $v->id }}').style.display='none'">Cancel</button>
            </div>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="6" style="padding:12px" class="small-muted">No versions in queue.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div style="margin-top:12px;">
    {{ $versions->links() }}
  </div>
</div>
@endsection
