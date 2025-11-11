@extends('layouts.iso')

@section('title', 'Draft Detail')

@section('content')
@php
  $user = auth()->user();
  $hasRoles = fn(array $roles) => $user && method_exists($user, 'hasAnyRole') ? $user->hasAnyRole($roles) : false;
  $isFinal  = in_array($version->status, ['approved','rejected'], true);
  $canModerate = $hasRoles(['mr','admin','director']); // yang bisa approve/reject
  $canDelete   = $hasRoles(['mr','admin']);            // hapus draft
  $canReopen   = $canDelete || ($user && (int)$user->id === (int)$version->created_by);
@endphp

<div style="max-width:1000px;margin:18px auto;">

  {{-- Flash & error --}}
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul style="margin:0;padding-left:18px;">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <h2 style="margin-bottom:10px;">
    Draft: {{ $version->document->doc_code }}
    — {{ $version->document->title }}
    ({{ $version->version_label }})
  </h2>

  <div style="display:flex;gap:12px;align-items:flex-start;">
    {{-- LEFT: content --}}
    <div style="flex:1;background:#fff;padding:12px;border-radius:8px;">
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

      <h4 style="margin-top:0;">Content</h4>
      @if($version->pasted_text || $version->plain_text)
        <pre style="white-space:pre-wrap;margin:0;">
{!! nl2br(e($version->pasted_text ?? $version->plain_text)) !!}
        </pre>
      @elseif($version->file_path)
        <p style="margin:0;">
          File attached. <a href="{{ route('documents.versions.download', $version->id) }}">Download</a>
        </p>
      @else
        <p class="small-muted" style="margin:0;">No content</p>
      @endif
    </div>

    {{-- RIGHT: actions --}}
    <div style="width:320px;">
      <div style="background:#fff;padding:12px;border-radius:8px;">
        <h4 style="margin-top:0;">Actions</h4>

        {{-- APPROVE (hanya jika belum final) --}}
        @if($canModerate && ! $isFinal)
          <form method="post" action="{{ route('approval.approve', $version->id) }}" style="margin-bottom:10px;">
            @csrf
            <button class="btn" type="submit">Approve</button>
          </form>

          {{-- REJECT: note wajib (required) --}}
          <form method="post" action="{{ route('approval.reject', $version->id) }}">
            @csrf
            <div style="margin-bottom:8px;">
              <label for="reject-note-{{ $version->id }}" style="display:block;font-size:12px;margin-bottom:4px;">
                Reason for rejection <span style="color:#d00;">(required)</span>
              </label>
              <textarea id="reject-note-{{ $version->id }}" name="note" rows="3" required
                        placeholder="Tuliskan alasan penolakan"
                        style="width:100%;"></textarea>
            </div>
            <button class="btn-muted" type="submit">Reject</button>
          </form>
        @endif

        {{-- DELETE (hanya MR/Admin) --}}
        @if($canDelete && ! $isFinal)
          <form method="post" action="{{ route('drafts.destroy', $version->id) }}" style="margin-top:12px;">
            @csrf
            <button class="btn-small btn-danger" type="submit"
                    onclick="return confirm('Hapus draft ini? Tindakan tidak dapat dibatalkan.')">
              Delete draft
            </button>
          </form>
        @endif

        {{-- REOPEN (pemilik atau MR/Admin) --}}
        @if($canReopen && ! in_array($version->status, ['approved'], true))
          <form method="post" action="{{ route('drafts.reopen', $version->id) }}" style="margin-top:12px;">
            @csrf
            <button class="btn-small" type="submit">Reopen as Draft</button>
          </form>
        @endif

      </div>
    </div>
  </div>
</div>
@endsection
