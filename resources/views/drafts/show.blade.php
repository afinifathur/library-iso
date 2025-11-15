{{-- resources/views/drafts/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Draft Detail')

@section('content')
@php
  $user = auth()->user();
  $hasRoles = fn(array $roles) => $user && method_exists($user, 'hasAnyRole') ? $user->hasAnyRole($roles) : false;
  $isFinal  = in_array($version->status, ['approved','rejected'], true);
  // who can moderate (approve/reject)
  $canModerate = $hasRoles(['mr','admin','director']);
  // who can delete
  $canDelete   = $hasRoles(['mr','admin']);
  // who can reopen (owner or admin/mr)
  $canReopen   = $canDelete || ($user && (int)$user->id === (int)$version->created_by);
@endphp

<div style="max-width:1000px;margin:18px auto;">

  {{-- Flash & errors --}}
  @if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
  @endif
  @if(session('warning'))
    <div class="alert alert-warning" role="status">{{ session('warning') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger" role="alert">
      <ul style="margin:0;padding-left:18px;">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <h2 style="margin-bottom:10px;">
    Draft: {{ $version->document->doc_code ?? '-' }}
    — {{ $version->document->title ?? '-' }}
    ({{ $version->version_label }})
  </h2>

  <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;">
    {{-- LEFT: content --}}
    <div style="flex:1;min-width:280px;background:#fff;padding:12px;border-radius:8px;">
      <p style="margin:0 0 6px 0;">
        <strong>Status:</strong> {{ $version->status }}
        — <strong>Stage:</strong> {{ $version->approval_stage ?? 'KABAG' }}
      </p>
      <p style="margin:0 0 12px 0;">
        <strong>Created by:</strong> {{ $version->creator?->name ?? $version->created_by }}
      </p>

      @if(!empty($version->change_note))
        <p style="margin:0 0 16px 0;"><strong>Change note:</strong> {{ $version->change_note }}</p>
      @endif

      <h4 style="margin-top:12px;margin-bottom:8px;">Content</h4>

      @if(!empty($version->pasted_text) || !empty($version->plain_text))
        <pre style="white-space:pre-wrap;margin:0;background:#fafafa;padding:10px;border-radius:6px;overflow:auto;">
{!! nl2br(e($version->pasted_text ?? $version->plain_text)) !!}
        </pre>
      @elseif(!empty($version->file_path))
        <p style="margin:0;">
          File attached. <a href="{{ route('documents.versions.download', $version->id) }}">Download</a>
        </p>
      @else
        <p class="small-muted" style="margin:0;">No content</p>
      @endif
    </div>

    {{-- RIGHT: actions --}}
    <div style="width:320px;min-width:220px;">
      <div style="background:#fff;padding:12px;border-radius:8px;">
        <h4 style="margin-top:0;margin-bottom:8px;">Actions</h4>

        {{-- APPROVE (only if canModerate && not final) --}}
        @if($canModerate && ! $isFinal)
          <form method="POST" action="{{ route('approval.approve', $version->id) }}" style="margin-bottom:10px;">
            @csrf
            <button class="btn" type="submit" onclick="return confirm('Yakin approve versi ini?')">Approve</button>
          </form>

          {{-- REJECT: require note --}}
          <form method="POST" action="{{ route('approval.reject', $version->id) }}" style="margin-bottom:6px;">
            @csrf
            <div style="margin-bottom:8px;">
              <label for="reject-note-{{ $version->id }}" style="display:block;font-size:12px;margin-bottom:4px;">
                Reason for rejection <span style="color:#d00;">(required)</span>
              </label>
              <textarea id="reject-note-{{ $version->id }}" name="note" rows="3" required
                        placeholder="Tuliskan alasan penolakan"
                        style="width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:6px;"></textarea>
            </div>
            <button class="btn-muted" type="submit" onclick="return confirm('Kirim penolakan dan catatan?')">Reject</button>
          </form>
        @endif

        {{-- DELETE (only MR/Admin) --}}
        @if($canDelete && ! $isFinal)
          <form method="POST" action="{{ route('drafts.destroy', $version->id) }}" style="margin-top:12px;" onsubmit="return confirm('Hapus draft ini? Tindakan ini tidak dapat dibatalkan.')">
            @csrf
            <button class="btn-small btn-danger" type="submit">Delete draft</button>
          </form>
        @endif

        {{-- REOPEN --}}
        @if($canReopen && ! in_array($version->status, ['approved'], true))
          <form method="POST" action="{{ route('drafts.reopen', $version->id) }}" style="margin-top:12px;">
            @csrf
            <button class="btn-small" type="submit" onclick="return confirm('Reopen versi ini menjadi draft?')">Reopen as Draft</button>
          </form>
        @endif

        {{-- Fallback actions for owner when final (view only) --}}
        @if($isFinal)
          <div style="margin-top:10px;color:#6b7280;font-size:13px;">This version is final ({{ $version->status }}).</div>
        @endif

      </div>
    </div>
  </div>
</div>
@endsection
