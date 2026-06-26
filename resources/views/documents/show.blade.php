{{-- resources/views/documents/show.blade.php --}}
@extends('layouts.iso')

@section('title', (($document->doc_code ?? '') ? ($document->doc_code.' — ') : '') . ($document->title ?? 'Document'))

@section('content')
@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Facades\Storage;
    use Carbon\Carbon;

    $user = auth()->user();

    // Resolve current preview version with fallbacks
    $currentVersion = $version ?? ($document->currentVersion ?? null);
    if (! $currentVersion) {
        $currentVersion = $latestVersion ?? null;
    }
    if (! $currentVersion && isset($document->versions) && $document->versions instanceof \Illuminate\Support\Collection) {
        $currentVersion = $document->versions->first() ?? null;
    }

    $submitVersionId = optional($currentVersion)->id ?? ($document->current_version_id ?? null);

    // Permissions
    $canShowSubmit = false;
    if ($user && $submitVersionId) {
        try {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['kabag'])) $canShowSubmit = true;
            if (method_exists($user, 'hasRole') && $user->hasRole('kabag')) $canShowSubmit = true;
        } catch (\Throwable $e) { /* ignore */ }
    }

    $currentStatus = optional($currentVersion)->status;
    $isFinal = in_array($currentStatus, ['approved','rejected'], true);

    $relatedLinks = $relatedLinks ?? [];
    if (! is_array($relatedLinks) || empty($relatedLinks)) {
        $relatedLinks = [];
        if (!empty($document->related_links)) {
            if (is_array($document->related_links)) {
                foreach ($document->related_links as $ln) {
                    $ln = trim((string)$ln);
                    if ($ln === '') continue;
                    $relatedLinks[] = ['url' => $ln, 'label' => $ln];
                }
            } elseif (is_string($document->related_links)) {
                $decoded = json_decode($document->related_links, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    foreach ($decoded as $ln) {
                        $ln = trim((string)$ln);
                        if ($ln === '') continue;
                        $relatedLinks[] = ['url' => $ln, 'label' => $ln];
                    }
                } else {
                    $lines = preg_split('/\r\n|\r|\n/', trim($document->related_links));
                    foreach ($lines as $ln) {
                        $ln = trim((string)$ln);
                        if ($ln === '') continue;
                        $relatedLinks[] = ['url' => $ln, 'label' => $ln];
                    }
                }
            }
        }
    }

    $canTrash = false;
    if ($user) {
        try {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['mr','director','admin'])) {
                $canTrash = true;
            } elseif (method_exists($user, 'roles')) {
                $roles = (array) optional($user->roles()->pluck('name'))->toArray();
                $canTrash = count(array_intersect($roles, ['mr','director','admin'])) > 0;
            }
        } catch (\Throwable $e) { /* ignore */ }
    }

    $canEditDocument = false;
    if ($user) {
        try {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['mr','admin','kabag'])) $canEditDocument = true;
            elseif (method_exists($user, 'hasRole') && $user->hasRole('kabag')) $canEditDocument = true;
            elseif (method_exists($user, 'can') && $user->can('update', $document)) $canEditDocument = true;
        } catch (\Throwable $e) { /* ignore */ }
    }

    // Disk checks
    $masterAvailable = false;
    $pdfAvailable = false;
    $pdfUrl = null;

    if ($currentVersion) {
        $masterCandidates = [
            $currentVersion->master_path ?? null,
            $currentVersion->file_path ?? null,
            $document->master_path ?? null,
            $document->file_path ?? null,
        ];
        $pdfCandidates = [
            $currentVersion->pdf_path ?? null,
            $currentVersion->file_path ?? null,
        ];

        foreach ($masterCandidates as $p) {
            if (empty($p)) continue;
            $p = ltrim($p, '/');
            try {
                $disk = Storage::disk('documents');
                if (method_exists($disk, 'exists') && $disk->exists($p)) { $masterAvailable = true; break; }
            } catch (\Throwable $e) {
                try {
                    $disk = Storage::disk('public');
                    if (method_exists($disk, 'exists') && $disk->exists($p)) { $masterAvailable = true; break; }
                } catch (\Throwable $_) { /* ignore */ }
            }
        }

        foreach ($pdfCandidates as $p) {
            if (empty($p)) continue;
            $p = ltrim($p, '/');
            $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') continue;
            if (Route::has('documents.versions.preview')) {
                $pdfUrl = route('documents.versions.preview', optional($currentVersion)->id);
                $pdfAvailable = true;
                break;
            }
            try {
                $disk = Storage::disk('documents');
                if (method_exists($disk, 'exists') && $disk->exists($p) && method_exists($disk, 'url')) {
                    $pdfUrl = $disk->url($p);
                    $pdfAvailable = true;
                    break;
                }
            } catch (\Throwable $e) {
                try {
                    $pub = Storage::disk('public');
                    if (method_exists($pub, 'exists') && $pub->exists($p) && method_exists($pub, 'url')) {
                        $pdfUrl = $pub->url($p);
                        $pdfAvailable = true;
                        break;
                    }
                } catch (\Throwable $_) { /* ignore */ }
            }
        }
    }

    // Fallbacks
    try {
        $departments = $departments ?? (\App\Models\Department::orderBy('code')->get() ?? collect());
    } catch (\Throwable $e) {
        $departments = collect();
    }
    try {
        $categories = $categories ?? (class_exists(\App\Models\Category::class) ? \App\Models\Category::orderBy('name')->get() : collect());
    } catch (\Throwable $e) {
        $categories = collect();
    }

    // Active version computation for Step 2 Panel
    $activeVersion = $versionHistory->where('status', 'approved')->last() ?? $versionHistory->last();
    $activeApprovedDate = $activeVersion && $activeVersion->approved_at ? Carbon::parse($activeVersion->approved_at)->format('d M Y') : '-';
    $activeApprovedBy = '-';
    if ($activeVersion) {
        if ($activeVersion->approved_by) {
            $approverUser = \App\Models\User::find($activeVersion->approved_by);
            $activeApprovedBy = $approverUser ? $approverUser->name : 'System';
        } elseif ($activeVersion->creator) {
            $activeApprovedBy = $activeVersion->creator->name;
        }
    }
@endphp

<div class="app-container" style="max-width:1280px; margin:20px auto; padding:0 16px; font-family:'Inter', sans-serif;">
  
  {{-- Obsolete version banner --}}
  @if($document->current_version_id && $currentVersion && $currentVersion->id != $document->current_version_id && $currentVersion->status === 'approved')
    <div class="obsolete-banner" style="background: linear-gradient(135deg, #fee4e2, #fecdca); border-left: 5px solid #d92d20; color: #b42318; padding: 16px; font-weight: 700; font-size: 1.1rem; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(217,45,32,0.1); display:flex; align-items:center; gap:12px;">
        <span class="material-symbols-outlined" style="font-size:28px;">warning</span>
        <div>
          <div style="font-size:15px; text-transform:uppercase; letter-spacing:0.05em;">OBSOLETE VERSION — FOR REFERENCE ONLY</div>
          <div style="font-size:13px; font-weight:normal; opacity:0.85; margin-top:2px;">Anda sedang melihat revisi historis. Versi aktif yang berlaku saat ini adalah <strong>{{ $document->currentVersion->version_label ?? 'Terbaru' }}</strong>.</div>
        </div>
    </div>
  @endif

  {{-- Top Navigation & Actions Bar --}}
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:12px;">
    <div>
      <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
        <code style="font-family: var(--mono-font); font-size:12px; background:#e0ebff; color:#0b63d4; border-radius:4px; padding: 3px 8px; font-weight: 700;">{{ $document->doc_code }}</code>
        <h1 style="font-size:24px; font-weight:800; color:#1e293b; margin:0; display:inline-flex; align-items:center; gap:8px; flex-wrap:wrap;">
          {{ $document->title }}
        </h1>
        @if($revisionCandidate)
          <span style="font-size: 11px; font-weight: 700; color: #b45309; background-color: #fef3c7; border: 1px solid #fde68a; border-radius: 9999px; padding: 3.5px 10px; text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 4px; vertical-align: middle;">
            <span class="material-symbols-outlined" style="font-size: 14px;">pending</span> Draft Revision In Progress
          </span>
        @endif
      </div>
    </div>
  </div>

  {{-- Modernized Action Bar Container --}}
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    <!-- Left: Primary & Secondary Action Groups -->
    <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
      
      <!-- Primary Group -->
      <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        @if($canEditDocument)
          <button class="btn" id="btnEditDoc" type="button" style="display: inline-flex; align-items: center; gap: 6px; background: #2563eb; color: #ffffff; font-weight: 600; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; transition: background 0.2s;">
            <span class="material-symbols-outlined" style="font-size: 18px;">edit</span> Edit Metadata
          </button>
          
          <a class="btn" href="{{ route('versions.create') }}?document_id={{ $document->id }}" style="display: inline-flex; align-items: center; gap: 6px; background: #059669; color: #ffffff; font-weight: 600; text-decoration: none; padding: 8px 16px; border-radius: 8px; font-size: 14px; transition: background 0.2s;">
            <span class="material-symbols-outlined" style="font-size: 18px;">add_circle</span> Create Revision
          </a>
        @endif

        @if(Route::has('documents.compare'))
          <a class="btn" href="{{ route('documents.compare', $document->id ?? 0) }}" style="display: inline-flex; align-items: center; gap: 6px; background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; text-decoration: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 14px; transition: background 0.2s;">
            <span class="material-symbols-outlined" style="font-size: 18px;">difference</span> Compare Engine
          </a>
        @endif
      </div>

      <!-- Divider line -->
      <div style="width: 1px; height: 28px; background: #e2e8f0;"></div>

      <!-- Secondary Group -->
      <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        @if($currentVersion && $currentVersion->file_path && Route::has('documents.versions.download'))
          <a class="btn" href="{{ route('documents.versions.download', $currentVersion->id) }}" style="display: inline-flex; align-items: center; gap: 6px; background: #ffffff; border: 1px solid #cbd5e1; color: #475569; text-decoration: none; padding: 8px 16px; border-radius: 8px; font-weight: 500; font-size: 14px;">
            <span class="material-symbols-outlined" style="font-size: 18px;">picture_as_pdf</span> Download PDF
          </a>
        @endif

        @if($currentVersion && $masterAvailable && Route::has('documents.versions.downloadMaster'))
          <a class="btn" href="{{ route('documents.versions.downloadMaster', $currentVersion->id) }}" style="display: inline-flex; align-items: center; gap: 6px; background: #ffffff; border: 1px solid #cbd5e1; color: #475569; text-decoration: none; padding: 8px 16px; border-radius: 8px; font-weight: 500; font-size: 14px;">
            <span class="material-symbols-outlined" style="font-size: 18px;">article</span> Download Master
          </a>
        @endif
      </div>
      
    </div>

    <!-- Right: Danger Zone & Submit Actions -->
    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
      @if($canShowSubmit && ! $isFinal && $currentVersion)
        <form method="POST" action="{{ route('versions.submit', $submitVersionId) }}" style="display: inline;">
          @csrf
          <button type="submit" class="btn" style="display: inline-flex; align-items: center; gap: 6px; background: #004ac6; color: #ffffff; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer;">
            <span class="material-symbols-outlined" style="font-size: 18px;">publish</span> Submit Approval
          </button>
        </form>
      @endif

      @if($canTrash && $currentVersion && Route::has('versions.trash'))
        <form method="POST" action="{{ route('versions.trash', $currentVersion->id) }}" style="display: inline;" onsubmit="return confirm('Move this version to Recycle Bin?');">
          @csrf
          <button type="submit" class="btn" style="display: inline-flex; align-items: center; gap: 6px; background: #ffffff; border: 1px solid #fecaca; color: #ba1a1a; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer;">
            <span class="material-symbols-outlined" style="font-size: 18px;">delete</span> Delete Version
          </button>
        </form>
      @endif
    </div>
  </div>

  {{-- Subtle Action Helper Legend --}}
  <div style="margin-top: 0; margin-bottom: 24px; padding: 0 4px; display: flex; gap: 24px; font-size: 11px; color: #6b7280; flex-wrap: wrap;">
    <div style="display: flex; align-items: center; gap: 4px;">
      <span style="font-weight: 600; color: #4b5563;">Edit Metadata:</span> Perbarui informasi dokumen tanpa membuat revisi baru.
    </div>
    <div style="display: flex; align-items: center; gap: 4px;">
      <span style="font-weight: 600; color: #4b5563;">Create Revision:</span> Ajukan perubahan isi dokumen melalui workflow approval.
    </div>
  </div>

  {{-- STEP 2: ACTIVE VERSION PANEL (Professional Dashboard Summary Widget) --}}
  <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:24px; box-shadow:0 4px 6px rgba(0,0,0,0.02);">
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:14px; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">
      <span class="material-symbols-outlined" style="color:#004ac6; font-size:22px;">verified</span>
      <h3 style="font-size:15px; font-weight:700; color:#1e293b; margin:0;">Active Version Panel</h3>
      <span style="font-size:11px; color:#64748b; background:#f1f5f9; padding:2px 8px; border-radius:12px; margin-left:auto; font-weight:600;">ISO 9001:2015 Approved</span>
    </div>
    
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:16px;">
      <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #f1f5f9;">
        <span style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:700; display:block; margin-bottom:4px;">Current Version</span>
        <span style="font-size:18px; font-weight:800; color:#0f172a;">{{ $activeVersion->version_label ?? 'v0' }}</span>
      </div>
      <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #f1f5f9;">
        <span style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:700; display:block; margin-bottom:4px;">Current Status</span>
        @php
          $statusClass = 'status-draft';
          $statusLabel = $activeVersion ? ucfirst($activeVersion->status) : 'Draft';
          if ($activeVersion) {
              if ($activeVersion->status === 'approved') {
                  $statusClass = 'status-approved';
                  $statusLabel = 'Active';
              } elseif ($activeVersion->status === 'superseded') {
                  $statusClass = 'status-draft';
                  $statusLabel = 'Superseded';
              } elseif ($activeVersion->status === 'submitted') {
                  $statusClass = 'status-review';
                  $statusLabel = 'Pending Review';
              }
          }
        @endphp
        <span class="status-badge {{ $statusClass }}" style="font-size:12px; padding:2px 10px; font-weight:700;">{{ $statusLabel }}</span>
      </div>
      <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #f1f5f9;">
        <span style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:700; display:block; margin-bottom:4px;">Approved Date</span>
        <span style="font-size:15px; font-weight:700; color:#0f172a;">{{ $activeApprovedDate }}</span>
      </div>
      <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #f1f5f9;">
        <span style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:700; display:block; margin-bottom:4px;">Approved By</span>
        <span style="font-size:14px; font-weight:700; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block;" title="{{ $activeApprovedBy }}">{{ $activeApprovedBy }}</span>
      </div>
      <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #f1f5f9;">
        <span style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:700; display:block; margin-bottom:4px;">Total Versions</span>
        <span style="font-size:18px; font-weight:800; color:#0f172a;">{{ $versionHistory->count() }}</span>
      </div>
      <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #f1f5f9;">
        <span style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:700; display:block; margin-bottom:4px;">Last Revision Date</span>
        <span style="font-size:15px; font-weight:700; color:#0f172a;">{{ $document->revision_date ? Carbon::parse($document->revision_date)->format('d M Y') : '-' }}</span>
      </div>
    </div>
  </div>

  <div style="display:grid; grid-template-columns: 1fr 340px; gap:20px; align-items:flex-start;">
    
    {{-- LEFT COLUMN: Workspace, Content, Timeline, Logs --}}
    <div style="display:flex; flex-direction:column; gap:24px;">

      {{-- STEP 3: REVISION WORKSPACE REMOVED --}}

      {{-- PDF / Text Preview Container --}}
      <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; box-shadow:0 4px 6px rgba(0,0,0,0.02);">
        @if($pdfAvailable && $pdfUrl)
          <div id="pdfViewerWrap" style="margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; background:#f8fafc; padding:8px 12px; border-radius:6px; border:1px solid #f1f5f9;">
              <div style="display:flex; gap:6px; align-items:center;">
                <button id="pdfZoomIn" type="button" class="btn-small" style="background:#fff; border:1px solid #e2e8f0; font-weight:700; cursor:pointer;" title="Zoom in">+</button>
                <button id="pdfZoomOut" type="button" class="btn-small" style="background:#fff; border:1px solid #e2e8f0; font-weight:700; cursor:pointer;" title="Zoom out">−</button>
                <span id="pdfZoomPct" class="small-muted" style="margin-left:6px; font-weight:600;">100%</span>
              </div>
              <div style="display:flex; gap:6px;">
                <button id="pdfClose" type="button" class="btn-small" style="background:#fee2e2; color:#ef4444; border:1px solid #fecaca; font-weight:600; cursor:pointer;">Close</button>
              </div>
            </div>

            <div style="border:1px solid #cbd5e1; border-radius:8px; overflow:hidden; background:#525659;">
              <iframe id="pdfIframe" src="{{ $pdfUrl }}" width="100%" height="700" frameborder="0" style="display:block; border:0; transform-origin:top left; transition: transform 0.1s ease;"></iframe>
            </div>
          </div>
        @else
          @if($currentVersion && $currentVersion->file_path)
            <div style="margin-bottom:16px; color:#64748b; background:#f8fafc; padding:12px; border-radius:6px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:8px;">
              <span class="material-symbols-outlined" style="color:#64748b;">info</span>
              <span>PDF preview tidak tersedia untuk format non-PDF. Silakan gunakan tombol download untuk meninjau file.</span>
            </div>
          @endif
        @endif

        {{-- Document Text Content --}}
        @php
          $text = $currentVersion ? ($currentVersion->pasted_text ?? $currentVersion->plain_text ?? '') : '';
          $parsedSignatures = [];
          if (preg_match_all('/(\d+)?Disetujui oleh,\s*([^D\n]+)(Director|Direktur)/i', $text, $matches, PREG_SET_ORDER)) {
              foreach ($matches as $m) {
                  $parsedSignatures[] = [
                      'hash' => trim($m[1] ?? ''),
                      'signer' => trim($m[2] ?? ''),
                      'role' => trim($m[3] ?? 'Director'),
                      'date' => $currentVersion->signed_at ? $currentVersion->signed_at->format('d M Y') : ($currentVersion->created_at ? $currentVersion->created_at->format('d M Y') : '-')
                  ];
              }
          }
          if (empty($parsedSignatures) && $currentVersion && $currentVersion->signed_by) {
              $parsedSignatures[] = [
                  'hash' => $currentVersion->checksum ? substr($currentVersion->checksum, 0, 16) : '',
                  'signer' => $currentVersion->signed_by,
                  'role' => 'Authorized Signatory',
                  'date' => $currentVersion->signed_at ? $currentVersion->signed_at->format('d M Y') : ($currentVersion->created_at ? $currentVersion->created_at->format('d M Y') : '-')
              ];
          }
          
          $cleanText = $text;
          if (!empty($parsedSignatures) && !empty($matches)) {
              foreach ($matches as $m) {
                  $cleanText = str_replace($m[0], '', $cleanText);
              }
              $cleanText = trim($cleanText);
          }
        @endphp

        <div style="background:#ffffff; border:1px solid #f1f5f9; border-radius:8px; padding:20px; min-height:280px; position:relative; overflow:hidden;">
          @if($currentVersion && $cleanText !== '')
            <div style="white-space:pre-wrap; font-family:'Inter', sans-serif; font-size:14px; line-height:1.7; color:#334155;">{!! nl2br(e($cleanText)) !!}</div>
          @elseif($currentVersion && $currentVersion->file_path && ! $pdfAvailable)
            <div style="text-align:center; padding:40px 20px; color:#64748b;">
              <span class="material-symbols-outlined" style="font-size:48px; color:#cbd5e1; margin-bottom:12px;">description</span>
              <p style="margin:0 0 12px; font-weight:600;">File Terlampir</p>
              @if(Route::has('documents.versions.download'))
                <a href="{{ route('documents.versions.download', $currentVersion->id) }}" class="btn btn-secondary" style="background:#fff; border:1px solid #cbd5e1;">Unduh Lampiran</a>
              @endif
            </div>
          @else
            <div style="text-align:center; padding:40px 20px; color:#64748b;">
              <span class="material-symbols-outlined" style="font-size:48px; color:#cbd5e1; margin-bottom:12px;">edit_note</span>
              <p style="margin:0;">Dokumen kosong. Silakan unggah PDF atau isi teks dokumen.</p>
            </div>
          @endif
        </div>

        {{-- Signatures --}}
        @if(!empty($parsedSignatures))
          <div style="margin-top:24px; border-top:1px solid #f1f5f9; padding-top:20px;">
            <h4 style="font-size:14px; font-weight:700; color:#0f172a; margin:0 0 14px; display:flex; align-items:center; gap:8px;">
              <span class="material-symbols-outlined" style="color:#10b981; font-size:20px;">verified_user</span> Verified Digital Signatures
            </h4>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:12px;">
              @foreach($parsedSignatures as $sig)
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; position:relative; overflow:hidden;">
                  <div style="position:absolute; right:-10px; bottom:-10px; opacity:0.04; color:#10b981; pointer-events: none;">
                    <span class="material-symbols-outlined" style="font-size:72px;">verified</span>
                  </div>
                  <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
                    <div>
                      <div style="font-size:14px; font-weight:700; color:#1e293b;">{{ $sig['signer'] }}</div>
                      <div style="font-size:12px; color:#64748b; font-weight:600;">{{ $sig['role'] }}</div>
                    </div>
                    <span class="status-badge status-approved" style="font-size:9px; padding:2px 6px; border-radius:4px; font-weight:800;">VERIFIED</span>
                  </div>
                  @if($sig['hash'])
                    <div style="margin-top:4px; border-top:1px dashed #e2e8f0; padding-top:6px; font-family:var(--mono-font); font-size:10px; color:#64748b;">
                      ID: <code>{{ $sig['hash'] }}</code>
                    </div>
                  @endif
                  <div style="font-size:11px; color:#64748b; margin-top:8px;">Date: <strong>{{ $sig['date'] }}</strong></div>
                </div>
              @endforeach
            </div>
          </div>
        @endif
      </div>

      {{-- STEP 4: VERSION HISTORY TIMELINE (Chronological visual timeline) --}}
      <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; box-shadow:0 4px 6px rgba(0,0,0,0.02);">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:20px; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">
          <span class="material-symbols-outlined" style="color:#004ac6; font-size:22px;">history</span>
          <h3 style="font-size:15px; font-weight:700; color:#1e293b; margin:0;">Version History</h3>
        </div>

        <div style="position:relative; padding-left:24px; border-left:2px solid #cbd5e1; margin-left:12px; display:flex; flex-direction:column; gap:20px;">
          @forelse($versionHistory as $ver)
            @php
              $isCurrent = $document->current_version_id && $ver->id == $document->current_version_id;
              $verStatus = strtolower($ver->status);
              
              // Map colors
              $dotColor = '#cbd5e1';
              $nodeBg = '#ffffff';
              $nodeBorder = '#cbd5e1';
              
              if ($verStatus === 'approved') {
                  $dotColor = '#10b981';
                  $nodeBorder = '#10b981';
                  if ($isCurrent) {
                      $nodeBg = '#f0fdf4';
                  }
              } elseif ($verStatus === 'superseded') {
                  $dotColor = '#64748b';
                  $nodeBorder = '#64748b';
              } elseif ($verStatus === 'submitted') {
                  $dotColor = '#f59e0b';
                  $nodeBorder = '#f59e0b';
              } elseif ($verStatus === 'rejected') {
                  $dotColor = '#ef4444';
                  $nodeBorder = '#ef4444';
              }
            @endphp
            
            <div style="position:relative; background:{{ $nodeBg }}; border:1px solid {{ $nodeBorder }}; border-radius:8px; padding:16px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: all 0.2s;">
              
              {{-- Timeline Dot --}}
              <div style="position:absolute; left:-33px; top:18px; width:16px; height:16px; border-radius:50%; background:#ffffff; border:4px solid {{ $dotColor }}; z-index:10;"></div>
              
              <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
                <div>
                  <strong style="font-size:15px; color:#1e293b; display:inline-flex; align-items:center; gap:6px;">
                    Rev. {{ $ver->version_label }}
                    @if($isCurrent)
                      <span style="font-size:10px; background:#d1fae5; color:#065f46; border-radius:4px; padding:1px 6px; font-weight:700;">CURRENT ACTIVE</span>
                    @endif
                  </strong>
                  
                  @php
                    $hBadgeClass = 'status-draft';
                    if ($verStatus === 'approved') $hBadgeClass = 'status-approved';
                    elseif ($verStatus === 'submitted') $hBadgeClass = 'status-review';
                    elseif ($verStatus === 'rejected') $hBadgeClass = 'status-rejected';
                  @endphp
                  <span class="status-badge {{ $hBadgeClass }}" style="font-size:9px; padding:1px 6px; margin-left:6px; font-weight:700;">{{ ucfirst($ver->status) }}</span>
                </div>
                
                <div style="display:flex; align-items:center; gap:8px;">
                  @if(Route::has('documents.compare'))
                    <a href="{{ route('documents.compare', $document->id) }}?v1={{ $ver->id }}" class="btn btn-secondary btn-sm" style="background:#fff; border:1px solid #cbd5e1; font-size:11px; padding:3px 8px; font-weight:600; color:#475569;">
                      <span class="material-symbols-outlined" style="font-size:14px;">difference</span> Compare
                    </a>
                  @endif
                  <a href="{{ route('documents.show', $document->id) }}?version_id={{ $ver->id }}" class="btn btn-primary btn-sm" style="background:#004ac6; border:none; font-size:11px; padding:4px 10px; font-weight:600; color:#fff;">
                    View Version
                  </a>
                </div>
              </div>

              <div style="font-size:12px; color:#64748b; margin-bottom:8px; display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:4px;">
                <div>Created: <strong>{{ $ver->created_at ? Carbon::parse($ver->created_at)->format('d M Y, H:i') : '-' }}</strong> by <strong>{{ $ver->creator->name ?? 'System' }}</strong></div>
                @if($verStatus === 'approved' && $ver->approved_at)
                  <div>Approved: <strong>{{ Carbon::parse($ver->approved_at)->format('d M Y, H:i') }}</strong></div>
                @endif
              </div>

              @if($ver->change_note)
                <div style="font-size:12px; background:#f8fafc; border-left:3px solid #cbd5e1; padding:6px 10px; color:#475569; font-style:italic;">
                  "{{ $ver->change_note }}"
                </div>
              @endif

            </div>
          @empty
            <div style="color:#64748b; padding:20px 0; text-align:center;">Tidak ada riwayat versi yang ditemukan.</div>
          @endforelse
        </div>
      </div>

      {{-- STEP 5: AUDIT TRAIL PANEL (Dedicated Section Below Timeline) --}}
      <div id="audit-trail-panel" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; box-shadow:0 4px 6px rgba(0,0,0,0.02);">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">
          <span class="material-symbols-outlined" style="color:#004ac6; font-size:22px;">gavel</span>
          <h3 style="font-size:15px; font-weight:700; color:#1e293b; margin:0;">Document Audit Trail</h3>
        </div>

        <div style="display:flex; flex-direction:column; gap:12px;">
          @forelse($auditLogs as $log)
            @php
              $act = strtolower($log->action);
              $logIcon = 'info';
              $logIconColor = '#64748b';
              $logBg = '#f8fafc';
              
              if ($act === 'approve' || $act === 'approved') {
                  $logIcon = 'check_circle';
                  $logIconColor = '#10b981';
                  $logBg = '#f0fdf4';
              } elseif ($act === 'reject' || $act === 'rejected') {
                  $logIcon = 'cancel';
                  $logIconColor = '#ef4444';
                  $logBg = '#fef2f2';
              } elseif ($act === 'submit' || $act === 'submitted' || $act === 'version_created') {
                  $logIcon = 'arrow_circle_up';
                  $logIconColor = '#2563eb';
                  $logBg = '#eff6ff';
              }
            @endphp
            
            <div style="background:{{ $logBg }}; border:1px solid #f1f5f9; border-radius:8px; padding:12px 16px; display:flex; gap:12px; align-items:flex-start;">
              <span class="material-symbols-outlined" style="color:{{ $logIconColor }}; font-size:22px; margin-top:2px;">{{ $logIcon }}</span>
              <div style="flex:1;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                  <div>
                    <strong style="font-size:13px; color:#1e293b;">{{ $log->user_name }}</strong>
                    <span style="font-size:12px; color:#64748b; margin-left:4px;">performed <strong>{{ strtoupper($log->action) }}</strong></span>
                    @if(isset($log->role) && $log->role)
                      <span style="font-size:10px; background:#e2e8f0; color:#475569; padding:1px 6px; border-radius:4px; margin-left:6px; font-weight:700;">{{ strtoupper($log->role) }}</span>
                    @endif
                  </div>
                  <span style="font-size:11px; color:#94a3b8;">{{ Carbon::parse($log->created_at)->format('d M Y, H:i') }}</span>
                </div>
                
                @if(isset($log->note) && $log->note)
                  <p style="font-size:12px; color:#475569; margin:6px 0 0; padding:6px; background:#fff; border-radius:4px; border:1px solid #f1f5f9; font-style:italic;">
                    Note: "{{ $log->note }}"
                  </p>
                @endif
              </div>
            </div>
          @empty
            <div style="text-align:center; padding:20px; color:#94a3b8; font-size:13px;">No approval history recorded.</div>
          @endforelse
        </div>
      </div>

    </div>

    {{-- RIGHT COLUMN: Metadata & Sidebar details --}}
    <div style="display:flex; flex-direction:column; gap:16px;">
      
      {{-- Document Details Sidebar --}}
      <div class="card" style="padding:16px; border-radius:12px; border:1px solid #e2e8f0; background:#fff;">
        <h3 style="font-size:13px; font-weight:700; color:#475569; border-bottom:1px solid #f1f5f9; padding-bottom:8px; margin:0 0 12px; display:flex; align-items:center; gap:6px;">
          <span class="material-symbols-outlined" style="font-size:18px;">info</span> Document Information
        </h3>
        <div style="display:flex; flex-direction:column; gap:10px; font-size:13px;">
          <div style="display:flex; justify-content:space-between; padding-bottom:4px; border-bottom:1px dashed #f1f5f9;">
            <span style="color:#64748b;">Department</span>
            <span style="font-weight:600; color:#1e293b;">{{ optional($document->department)->name ?? '-' }}</span>
          </div>
          <div style="display:flex; justify-content:space-between; padding-bottom:4px; border-bottom:1px dashed #f1f5f9;">
            <span style="color:#64748b;">Category</span>
            <span style="font-weight:600; color:#1e293b;">
              @php
                $catLabel = '-';
                if ($document->category_id) {
                    $catObj = \App\Models\Category::find($document->category_id);
                    if ($catObj) $catLabel = ($catObj->code ? $catObj->code . ' - ' : '') . $catObj->name;
                }
                if ($catLabel === '-' && $document->category) {
                    $catObj = \App\Models\Category::where('code', $document->category)->first();
                    if ($catObj) {
                        $catLabel = ($catObj->code ? $catObj->code . ' - ' : '') . $catObj->name;
                    } else {
                        $catLabel = $document->category;
                    }
                }
              @endphp
              {{ $catLabel }}
            </span>
          </div>
          <div style="display:flex; justify-content:space-between; padding-bottom:4px; border-bottom:1px dashed #f1f5f9;">
            <span style="color:#64748b;">Revision Number</span>
            <span style="font-weight:600; color:#1e293b;">Rev. {{ str_pad((string)($document->revision_number ?? 0), 2, '0', STR_PAD_LEFT) }}</span>
          </div>
          <div style="display:flex; justify-content:space-between; padding-bottom:4px; border-bottom:1px dashed #f1f5f9;">
            <span style="color:#64748b;">Effective Date</span>
            <span style="font-weight:600; color:#1e293b;">{{ $document->revision_date ? Carbon::parse($document->revision_date)->format('d M Y') : '-' }}</span>
          </div>
          <div style="display:flex; justify-content:space-between; padding-bottom:4px; border-bottom:1px dashed #f1f5f9;">
            <span style="color:#64748b;">Owner</span>
            <span style="font-weight:600; color:#1e293b;">{{ optional($currentVersion)->creator->name ?? 'System' }}</span>
          </div>
          <div style="display:flex; justify-content:space-between;">
            <span style="color:#64748b;">ISO Clause</span>
            <span style="font-weight:600; color:#1e293b;">{{ $document->short_code ?? '8.4.2' }}</span>
          </div>
        </div>
      </div>

      {{-- Review Schedule Card --}}
      <div class="card" style="padding:16px; border-radius:12px; border:1px solid #e2e8f0; background:#fff;">
        <h3 style="font-size:13px; font-weight:700; color:#475569; border-bottom:1px solid #f1f5f9; padding-bottom:8px; margin:0 0 12px; display:flex; align-items:center; gap:6px;">
          <span class="material-symbols-outlined" style="font-size:18px;">calendar_today</span> Review Schedule
        </h3>
        <div style="display:flex; flex-direction:column; gap:10px; font-size:13px;">
          <div style="display:flex; justify-content:space-between; padding-bottom:4px; border-bottom:1px dashed #f1f5f9;">
            <span style="color:#64748b;">Last Review</span>
            <span style="font-weight:600; color:#1e293b;">{{ $document->revision_date ? Carbon::parse($document->revision_date)->format('d M Y') : '-' }}</span>
          </div>
          @php
            $nextReview = $document->next_review_date;
            $isOverdue = $nextReview && $nextReview->isPast();
          @endphp
          <div style="display:flex; justify-content:space-between; padding-bottom:4px; border-bottom:1px dashed #f1f5f9;">
            <span style="color:#64748b;">Next Review</span>
            <span style="font-weight:600; @if($isOverdue) color:#ef4444; @else color:#1e293b; @endif">
              {{ $nextReview ? Carbon::parse($nextReview)->format('d M Y') : '-' }}
            </span>
          </div>
          <div style="display:flex; justify-content:space-between;">
            <span style="color:#64748b;">Frequency</span>
            <span style="font-weight:600; color:#1e293b;">
              @if($document->review_frequency == 12)
                Annual
              @elseif($document->review_frequency == 6)
                Semi-Annual
              @elseif($document->review_frequency == 3)
                Quarterly
              @elseif($document->review_frequency)
                {{ $document->review_frequency }} Months
              @else
                Annual
              @endif
            </span>
          </div>
        </div>
      </div>

      {{-- Related Documents --}}
      <div class="card" style="padding:16px; border-radius:12px; border:1px solid #e2e8f0; background:#fff;">
        <h3 style="font-size:13px; font-weight:700; color:#475569; border-bottom:1px solid #f1f5f9; padding-bottom:8px; margin:0 0 12px; display:flex; align-items:center; gap:6px;">
          <span class="material-symbols-outlined" style="font-size:18px;">link</span> Related Documents
        </h3>
        <div style="display:flex; flex-direction:column; gap:8px;">
          @forelse($relatedLinks as $link)
            @php
              $lkUrl = is_array($link) ? ($link['url'] ?? '#') : (is_object($link) ? ($link->url ?? '#') : (string)$link);
              $lkLabel = is_array($link) ? ($link['label'] ?? $lkUrl) : (is_object($link) ? ($link->label ?? $lkUrl) : $lkUrl);
            @endphp
            <a href="{{ $lkUrl }}" target="_blank" rel="noopener noreferrer" style="font-size:12px; color:#004ac6; text-decoration:none; display:flex; align-items:center; gap:6px; font-weight:600;">
              <span class="material-symbols-outlined" style="font-size:16px;">link</span> {{ $lkLabel }}
            </a>
          @empty
            <div style="font-size:12px; color:#94a3b8; text-align:center; padding:10px 0;">No related documents.</div>
          @endforelse
        </div>
      </div>

      {{-- Storage Metadata --}}
      <div class="card" style="padding:16px; border-radius:12px; border:1px solid #e2e8f0; background:#f8fafc; box-shadow:none;">
        <p style="font-size:11px; font-weight:700; color:#475569; text-transform:uppercase; margin:0 0 10px; letter-spacing:0.05em;">Storage Metadata</p>
        <table style="width:100%; font-size:12px; border-collapse:collapse;">
          <tbody>
            <tr>
              <td style="padding:4px 0; color:#64748b;">Format</td>
              <td style="padding:4px 0; font-weight:600; color:#1e293b; text-align:right;">
                {{ $currentVersion ? (explode('/', $currentVersion->file_mime ?? '')[1] ?? 'PDF') : 'PDF' }}
              </td>
            </tr>
            <tr>
              <td style="padding:4px 0; color:#64748b;">Size</td>
              <td style="padding:4px 0; font-weight:600; color:#1e293b; text-align:right;">
                @php
                  $size = '2.4 MB';
                  if ($currentVersion && $currentVersion->pdf_path) {
                      try {
                          $disk = Storage::disk('documents');
                          if ($disk->exists($currentVersion->pdf_path)) {
                              $bytes = $disk->size($currentVersion->pdf_path);
                              $size = round($bytes / (1024 * 1024), 2) . ' MB';
                          }
                      } catch (\Throwable $e) {}
                  }
                @endphp
                {{ $size }}
              </td>
            </tr>
            <tr>
              <td style="padding:4px 0; color:#64748b;">Retention</td>
              <td style="padding:4px 0; font-weight:600; color:#1e293b; text-align:right;">10 Years</td>
            </tr>
            @if($currentVersion && $currentVersion->checksum)
              <tr>
                <td style="padding:4px 0; color:#64748b; vertical-align:top;">SHA-256</td>
                <td style="padding:4px 0; font-weight:600; color:#1e293b; text-align:right; font-family:var(--mono-font); font-size:10px; word-break:break-all; max-width:180px;">
                  {{ substr($currentVersion->checksum, 0, 16) }}...
                </td>
              </tr>
            @endif
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>

{{-- Edit Metadata Modal --}}
@if($canEditDocument)
  <div id="editModal"
       style="display:none; position:fixed; left:50%; top:50%; transform:translate(-50%,-50%);
              width:880px; max-width:95%; z-index:999; background:#fff; padding:20px;
              border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,0.15); border:1px solid #e2e8f0;">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">
      <h3 style="font-size:16px; font-weight:700; color:#1e293b; margin:0; display:flex; align-items:center; gap:6px;">
        <span class="material-symbols-outlined" style="color:#004ac6;">edit</span> Edit Document Metadata
      </h3>
      <span class="material-symbols-outlined" style="cursor:pointer; color:#94a3b8;" onclick="document.getElementById('editModal').style.display='none'">close</span>
    </div>

    <form method="post"
          action="{{ route('documents.updateCombined', $document->id) }}"
          enctype="multipart/form-data"
          novalidate>
      @csrf
      @method('PUT')

      <div style="display:flex; gap:16px; flex-wrap:wrap; max-height: 70vh; overflow-y: auto; padding-right:8px;">
        <div style="flex:1; min-width:260px;">
          {{-- Category --}}
          <label for="category" style="font-size:12px; font-weight:600; color:#475569;">Kategori</label>
          @php $cat = old('category_id', $document->category_id ?? ''); @endphp
          <select id="category" name="category_id" class="input" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; margin-top:4px; margin-bottom:12px;">
            <option value="" {{ $cat ? '' : 'selected' }}>Pilih kategori…</option>
            @if($categories && count($categories))
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" {{ (string)$cat === (string)$c->id ? 'selected' : '' }}>
                        {{ $c->code ?? $c->name }}
                    </option>
                @endforeach
            @else
                <option value="IK"  {{ $cat==='IK'  ? 'selected' : '' }}>IK - Instruksi Kerja</option>
                <option value="UT"  {{ $cat==='UT'  ? 'selected' : '' }}>UT - Uraian Tugas</option>
                <option value="FR"  {{ $cat==='FR'  ? 'selected' : '' }}>FR - Formulir</option>
                <option value="PJM" {{ $cat==='PJM' ? 'selected' : '' }}>PJM - Prosedur Jaminan Mutu</option>
                <option value="MJM" {{ $cat==='MJM' ? 'selected' : '' }}>MJM - Manual Jaminan Mutu</option>
                <option value="DP"  {{ $cat==='DP'  ? 'selected' : '' }}>DP - Dokumen Pendukung</option>
                <option value="DE"  {{ $cat==='DE'  ? 'selected' : '' }}>DE - Dokumen Eksternal</option>
            @endif
          </select>

          {{-- Document code --}}
          <label for="doc_code" style="font-size:12px; font-weight:600; color:#475569;">Kode Dokumen (Permanent)</label>
          <input id="doc_code" type="text" value="{{ $document->doc_code }}" class="input" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; margin-top:4px; margin-bottom:4px; background-color:#f1f5f9; color:#64748b; cursor:not-allowed; opacity:0.85;" readonly disabled>
          <div style="font-size:11px; color:#64748b; margin-bottom:12px; line-height: 1.4;">
            Kode dokumen merupakan identitas permanen dokumen dan tidak dapat diubah setelah dibuat.
          </div>

          {{-- Title --}}
          <label for="title" style="font-size:12px; font-weight:600; color:#475569;">Title</label>
          <input id="title" type="text" name="title" value="{{ old('title', $document->title) }}" class="input" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; margin-top:4px; margin-bottom:12px;" required>
        </div>

        <div style="flex:1; min-width:260px;">
          <input type="hidden" name="version_id" value="{{ old('version_id', optional($currentVersion)->id ?? '') }}">
          <input type="hidden" name="version_label" value="{{ old('version_label', optional($currentVersion)->version_label ?? 'v1') }}">

          {{-- Department --}}
          <label for="department_id" style="font-size:12px; font-weight:600; color:#475569;">Department</label>
          @php $selectedDept = old('department_id', $document->department_id ?? ($user->department_id ?? null)); @endphp
          <select id="department_id" name="department_id" class="input" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; margin-top:4px; margin-bottom:12px;" required>
            @foreach($departments as $dep)
              <option value="{{ $dep->id }}" {{ (string)$selectedDept === (string)$dep->id ? 'selected' : '' }}>
                {{ $dep->code }} — {{ $dep->name }}
              </option>
            @endforeach
          </select>

          {{-- Review Frequency --}}
          <label for="review_frequency" style="font-size:12px; font-weight:600; color:#475569; display:block; margin-top:4px;">Review Frequency (months)</label>
          <input id="review_frequency" type="number" name="review_frequency" value="{{ old('review_frequency', $document->review_frequency) }}" class="input" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; margin-top:4px; margin-bottom:12px;">

          {{-- Next Review Date --}}
          <label for="next_review_date" style="font-size:12px; font-weight:600; color:#475569; display:block; margin-top:4px;">Next Review Date</label>
          @php
            $nextReviewDefault = old('next_review_date') ?: ($document->next_review_date ? $document->next_review_date->format('Y-m-d') : '');
          @endphp
          <input id="next_review_date" type="date" name="next_review_date" value="{{ $nextReviewDefault }}" class="input" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; margin-top:4px; margin-bottom:12px;">
        </div>
      </div>

      {{-- Related links --}}
      <div style="margin-top: 12px; margin-bottom: 20px;">
        @php
          $relatedDefault = old('related_links');
          if ($relatedDefault === null) {
              if (is_array($document->related_links)) {
                  $relatedDefault = implode("\n", $document->related_links);
              } elseif (is_string($document->related_links) && $document->related_links !== '') {
                  $decoded = json_decode($document->related_links, true);
                  if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                      $relatedDefault = implode("\n", $decoded);
                  } else {
                      $relatedDefault = $document->related_links;
                  }
              } else {
                  $relatedDefault = '';
              }
          }
        @endphp
        <label for="related_links" style="font-size:12px; font-weight:600; color:#475569;">Related Documents (One URL per line)</label>
        <textarea id="related_links" name="related_links" rows="3" class="input" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; margin-top:4px; min-height:60px;">{{ $relatedDefault }}</textarea>
      </div>

      <div style="display:flex; gap:8px; justify-content: flex-end; border-top: 1px solid #f1f5f9; padding-top: 16px;">
        <button type="button" class="btn btn-secondary" id="cancelEdit" style="background:#fff; border:1px solid #cbd5e1; color:#64748b; font-size:13px; font-weight:600; padding:8px 14px; border-radius: 6px; cursor: pointer;">Cancel</button>
        <button class="btn btn-primary" type="submit" name="submit_for" value="save" style="background:#004ac6; font-size:13px; font-weight:600; color:#fff; border:none; padding:8px 14px; border-radius: 6px; cursor: pointer;">Save Metadata</button>
      </div>

      @if ($errors->any())
        <div style="margin-top:12px; color:#b42318; background:#fee4e2; border:1px solid #fecdca; border-radius:6px; padding:10px;">
          <ul style="margin:0; padding-left:18px; font-size:12px;">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif
    </form>
  </div>
@endif

{{-- Styling overrides --}}
<style>
  .btn-small { display:inline-block; padding:4px 10px; border-radius:4px; text-decoration:none; font-size:12px; }
  .small-muted { color:#64748b; font-size:12px; }
  #pdfIframe { transition: transform 0.12s ease; }
  .btn:disabled, button[disabled] { opacity: 0.6; cursor: progress; }
  
  /* Status Badges */
  .status-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }
  .status-approved { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
  .status-review { background: #dbeaf8; color: #1d4ed8; border: 1px solid #bfdbfe; }
  .status-rejected { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
  .status-draft { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
</style>
@endsection {{-- end content --}}

@section('scripts')
@once
  <script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
@endonce
<script>
  (function () {
    // Modal controls
    var editBtn = document.getElementById('btnEditDoc');
    var modal = document.getElementById('editModal');
    var cancelBtn = document.getElementById('cancelEdit');
    if (editBtn && modal) editBtn.addEventListener('click', function () { modal.style.display = 'block'; });
    if (cancelBtn && modal) cancelBtn.addEventListener('click', function () { modal.style.display = 'none'; });

    // propagate version_id parameter
    try {
      var params = new URLSearchParams(window.location.search);
      var v = params.get('version_id');
      if (v) {
        var input = document.querySelector('input[name="version_id"]');
        if (input) input.value = v;
      }
    } catch (e) { /* ignore */ }

    // prevent double submit
    try {
      var editForm = modal ? modal.querySelector('form') : null;
      if (editForm) {
        editForm.addEventListener('submit', function (ev) {
          Array.from(editForm.querySelectorAll('button[type="submit"]')).forEach(function(b){
            b.disabled = true;
            b.dataset.origText = b.textContent;
            b.textContent = 'Processing...';
          });
        });
      }
    } catch (e) { /* ignore */ }

    // PDF viewer controls
    var pdfWrapper = document.getElementById('pdfViewerWrap');
    var pdfIframe = document.getElementById('pdfIframe');
    var pdfClose = document.getElementById('pdfClose');
    var pdfZoomIn = document.getElementById('pdfZoomIn');
    var pdfZoomOut = document.getElementById('pdfZoomOut');
    var pdfZoomPct = document.getElementById('pdfZoomPct');
    var currentZoom = 1;

    function setZoom(z) {
      currentZoom = Math.max(0.5, Math.min(2.5, z));
      if (pdfIframe) {
        pdfIframe.style.transform = 'scale(' + currentZoom + ')';
        pdfIframe.style.height = (700 / currentZoom) + 'px';
      }
      if (pdfZoomPct) pdfZoomPct.textContent = Math.round(currentZoom * 100) + '%';
    }
    if (pdfClose) pdfClose.addEventListener('click', function () { if (pdfWrapper) pdfWrapper.style.display = 'none'; });
    if (pdfZoomIn) pdfZoomIn.addEventListener('click', function () { setZoom(currentZoom + 0.1); });
    if (pdfZoomOut) pdfZoomOut.addEventListener('click', function () { setZoom(currentZoom - 0.1); });

    // Open link persistence
    function qsa(selector, ctx) { return Array.from((ctx || document).querySelectorAll(selector)); }
    function persistOpened(vid) { if (!vid) return; try { localStorage.setItem('iso_opened_version_' + vid, '1'); } catch(e){} }
    function attachVersionOpenHandlers() {
      qsa('.action-open').forEach(function(link) {
        if (link.dataset && link.dataset.isoOpenAttached) return;
        link.dataset.isoOpenAttached = '1';
        link.addEventListener('click', function () {
          try {
            var vid = this.getAttribute('data-version-id') || this.dataset.versionId;
            if (!vid) {
              var href = this.getAttribute('href') || '';
              var m = href.match(/\/(\d+)(?:$|[?#])/);
              if (m) vid = m[1];
            }
            if (vid) persistOpened(vid);
          } catch(e){}
        }, { passive: true });
      });
    }
    attachVersionOpenHandlers();

    // Auto-open modal if edit=1 parameter is present
    try {
      if (new URLSearchParams(window.location.search).get('edit') === '1') {
        var modalEl = document.getElementById('editModal');
        if (modalEl) modalEl.style.display = 'block';
      }
    } catch(e) {}
  })();
</script>
@endsection
