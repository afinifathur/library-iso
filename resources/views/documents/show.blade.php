{{-- resources/views/documents/show.blade.php --}}
@extends('layouts.iso')

@section('title', (($document->doc_code ?? '') ? ($document->doc_code.' — ') : '') . ($document->title ?? 'Document'))

@section('content')
@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Facades\Storage;

    $user = auth()->user();

    // Resolve current version with several fallbacks
    $currentVersion = $version ?? ($document->currentVersion ?? null);
    if (! $currentVersion) {
        $currentVersion = $latestVersion ?? null;
    }
    if (! $currentVersion && isset($document->versions) && $document->versions instanceof \Illuminate\Support\Collection) {
        $currentVersion = $document->versions->first() ?? null;
    }

    $submitVersionId = optional($currentVersion)->id ?? ($document->current_version_id ?? null);

    // permission: canShowSubmit (kabag)
    $canShowSubmit = false;
    if ($user && $submitVersionId) {
        try {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['kabag'])) $canShowSubmit = true;
            if (method_exists($user, 'hasRole') && $user->hasRole('kabag')) $canShowSubmit = true;
        } catch (\Throwable $e) { /* ignore permission check errors */ }
    }

    $currentStatus = optional($currentVersion)->status;
    $isFinal = in_array($currentStatus, ['approved','rejected'], true);

    // normalize relatedLinks -> array of ['url','label']
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

    // permission: canTrash (mr / director / admin)
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

    // permission: canEditDocument (kabag / mr / admin or policy)
    $canEditDocument = false;
    if ($user) {
        try {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['mr','admin','kabag'])) $canEditDocument = true;
            elseif (method_exists($user, 'hasRole') && $user->hasRole('kabag')) $canEditDocument = true;
            elseif (method_exists($user, 'can') && $user->can('update', $document)) $canEditDocument = true;
        } catch (\Throwable $e) { /* ignore */ }
    }

    // versions collection fallback
    $versions = $versions ?? ($document->versions ?? collect());

    // Build flags for master/pdf availability (defensive disk checks)
    $masterAvailable = false;
    $pdfAvailable = false;
    $pdfUrl = null;

    if ($currentVersion) {
        // master candidates: prefer explicit master_path, then file_path (some legacy rows)
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
            // prefer preview route if defined
            if (Route::has('documents.versions.preview')) {
                $pdfUrl = route('documents.versions.preview', optional($currentVersion)->id);
                $pdfAvailable = true;
                break;
            }
            // else try to build disk url (best-effort)
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

    // departments & categories fallbacks (prefer controller to pass)
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
@endphp

<div class="app-container" style="max-width:1280px;margin:18px auto;padding:0 12px;">
  
  {{-- Obsolete version banner --}}
  @if($document->current_version_id && $currentVersion && $currentVersion->id != $document->current_version_id && $currentVersion->status === 'approved')
    <div style="background-color: #fee4e2; border: 2px solid #b42318; color: #b42318; padding: 15px; text-align: center; font-weight: bold; font-size: 1.2rem; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
        ⚠️ OBSOLETE VERSION — FOR REFERENCE ONLY ⚠️
    </div>
  @endif

  {{-- Document Header Metadata Section --}}
  <section class="card" style="padding:24px; border-radius:8px; border:1px solid #c3c6d7; background:#fff; margin-bottom:24px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:16px; flex-wrap:wrap;">
      <div>
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
          <code style="font-family: var(--mono-font); font-size:13px; background:var(--surface-container); color:var(--on-surface-variant); border-radius:4px; padding: 2px 8px; font-weight: 600;">{{ $document->doc_code }}</code>
          @php
            $statusClass = '';
            $statusLabel = $currentVersion ? ucfirst($currentVersion->status) : 'Draft';
            if ($currentVersion) {
                if ($currentVersion->status === 'approved') {
                    $statusClass = 'status-approved';
                    $statusLabel = 'Active';
                } elseif ($currentVersion->status === 'rejected') {
                    $statusClass = 'status-rejected';
                } elseif ($currentVersion->status === 'submitted') {
                    $statusClass = 'status-review';
                    $statusLabel = 'Pending Review';
                } else {
                    $statusClass = 'status-draft';
                }
            } else {
                $statusClass = 'status-draft';
            }
          @endphp
          <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
        </div>
        <h2 style="font-size:24px; font-weight:700; color:#191b23; margin:0;">{{ $document->title ?? '-' }}</h2>
      </div>
      <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
        {{-- Action Buttons --}}
        @if($canEditDocument)
          <button class="btn btn-secondary" id="btnEditDoc" type="button" style="display:inline-flex; align-items:center; gap:6px;"><span class="material-symbols-outlined" style="font-size:18px;">edit</span> Edit</button>
        @endif

        @if($currentVersion && $masterAvailable && Route::has('documents.versions.downloadMaster'))
          <a class="btn btn-secondary" href="{{ route('documents.versions.downloadMaster', $currentVersion->id) }}" style="display:inline-flex; align-items:center; gap:6px;"><span class="material-symbols-outlined" style="font-size:18px;">description</span> Master</a>
        @elseif($currentVersion && Route::has('documents.versions.download') && $currentVersion->file_path)
          <a class="btn btn-secondary" href="{{ route('documents.versions.download', $currentVersion->id) }}" style="display:inline-flex; align-items:center; gap:6px;"><span class="material-symbols-outlined" style="font-size:18px;">description</span> File</a>
        @endif

        @if($currentVersion && $currentVersion->file_path && Route::has('documents.versions.download'))
          <a class="btn btn-secondary" href="{{ route('documents.versions.download', $currentVersion->id) }}" style="display:inline-flex; align-items:center; gap:6px;"><span class="material-symbols-outlined" style="font-size:18px;">picture_as_pdf</span> PDF</a>
        @endif

        @if(Route::has('documents.compare'))
          <a class="btn btn-secondary" href="{{ route('documents.compare', $document->id ?? 0) }}" style="display:inline-flex; align-items:center; gap:6px;"><span class="material-symbols-outlined" style="font-size:18px;">difference</span> Compare</a>
        @endif

        @if($canShowSubmit && ! $isFinal && $currentVersion)
          <form method="POST" action="{{ route('versions.submit', $submitVersionId) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:6px;"><span class="material-symbols-outlined" style="font-size:18px;">publish</span> Submit</button>
          </form>
        @endif

        @if($canTrash && $currentVersion && Route::has('versions.trash'))
          <form method="POST" action="{{ route('versions.trash', $currentVersion->id) }}" style="display:inline;" onsubmit="return confirm('Move this version to Recycle Bin?');">
            @csrf
            <button type="submit" class="btn btn-danger" style="display:inline-flex; align-items:center; gap:6px;"><span class="material-symbols-outlined" style="font-size:18px;">delete</span> Delete</button>
          </form>
        @endif
      </div>
    </div>

    {{-- Technical Specs Grid --}}
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:16px; border-top:1px solid #c3c6d7; padding-top:16px;">
      <div>
        <p style="font-size:11px; text-transform:uppercase; color:#434655; margin:0 0 4px; font-weight:600;">Department</p>
        <p style="font-size:14px; color:#191b23; margin:0; font-weight:500;">{{ optional($document->department)->name ?? '-' }}</p>
      </div>
      <div>
        <p style="font-size:11px; text-transform:uppercase; color:#434655; margin:0 0 4px; font-weight:600;">Category</p>
        <p style="font-size:14px; color:#191b23; margin:0; font-weight:500;">
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
        </p>
      </div>
      <div>
        <p style="font-size:11px; text-transform:uppercase; color:#434655; margin:0 0 4px; font-weight:600;">Revision</p>
        <p style="font-size:14px; color:#191b23; margin:0; font-weight:500;">Rev. {{ str_pad((string)($document->revision_number ?? 0), 2, '0', STR_PAD_LEFT) }}</p>
      </div>
      <div>
        <p style="font-size:11px; text-transform:uppercase; color:#434655; margin:0 0 4px; font-weight:600;">Effective Date</p>
        <p style="font-size:14px; color:#191b23; margin:0; font-weight:500;">{{ $document->revision_date ? $document->revision_date->format('M d, Y') : '-' }}</p>
      </div>
      <div>
        <p style="font-size:11px; text-transform:uppercase; color:#434655; margin:0 0 4px; font-weight:600;">Owner</p>
        <p style="font-size:14px; color:#191b23; margin:0; font-weight:500;">{{ optional($currentVersion)->creator->name ?? 'System' }}</p>
      </div>
      <div>
        <p style="font-size:11px; text-transform:uppercase; color:#434655; margin:0 0 4px; font-weight:600;">ISO Clause</p>
        <p style="font-size:14px; color:#191b23; margin:0; font-weight:500;">{{ $document->short_code ?? '8.4.2' }}</p>
      </div>
    </div>
  </section>

  <div style="display:flex;align-items:flex-start;gap:18px;flex-wrap:wrap;">

    {{-- LEFT: main content --}}
    <div style="flex:1;min-width:320px">
      {{-- PDF VIEWER (only when pdfAvailable and preview route or URL exists) --}}
      @if($pdfAvailable && $pdfUrl)
        <div id="pdfViewerWrap" style="margin-bottom:16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <div style="display:flex;gap:8px;align-items:center;">
              <button id="pdfZoomIn" type="button" class="btn-small" title="Zoom in">+</button>
              <button id="pdfZoomOut" type="button" class="btn-small" title="Zoom out">−</button>
              <span id="pdfZoomPct" class="small-muted" style="margin-left:6px;">100%</span>
            </div>
            <div style="display:flex;gap:8px;">
              <a id="pdfOpenNew" href="{{ $pdfUrl }}" target="_blank" rel="noopener noreferrer" class="btn-small">Open in new tab</a>
              @if(Route::has('documents.versions.download'))
                <a id="pdfDownload" href="{{ route('documents.versions.download', optional($currentVersion)->id) }}" class="btn-small" style="margin-left:6px;">Download</a>
              @endif
              <button id="pdfClose" type="button" class="btn-small" style="margin-left:6px;">Close</button>
            </div>
          </div>

          <div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
            <iframe id="pdfIframe"
                    src="{{ $pdfUrl }}"
                    width="100%"
                    height="700"
                    frameborder="0"
                    style="display:block;border:0;transform-origin:top left;"></iframe>
          </div>

          <div class="small-muted" style="margin-top:6px;">
            Jika PDF tidak tampil (browser memblokir), gunakan tombol "Open in new tab" or "Download".
          </div>
        </div>
      @else
        @if($currentVersion && $currentVersion->file_path)
          <div style="margin-bottom:12px;color:#6b7280;">
            PDF preview tidak tersedia. Gunakan tombol <b>Download PDF</b> untuk melihat.
          </div>
        @endif
      @endif

      {{-- Version content (plain text / pasted text) --}}
      @php
        $text = $currentVersion ? ($currentVersion->pasted_text ?? $currentVersion->plain_text ?? '') : '';
        $parsedSignatures = [];
        if (preg_match_all('/(\d+)?Disetujui oleh,\s*([^D\n]+)(Director|Direktur)/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $parsedSignatures[] = [
                    'hash' => trim($m[1] ?? ''),
                    'signer' => trim($m[2] ?? ''),
                    'role' => trim($m[3] ?? 'Director'),
                    'date' => $currentVersion->signed_at ? $currentVersion->signed_at->format('M d, Y') : ($currentVersion->created_at ? $currentVersion->created_at->format('M d, Y') : '-')
                ];
            }
        }
        if (empty($parsedSignatures) && $currentVersion && $currentVersion->signed_by) {
            $parsedSignatures[] = [
                'hash' => $currentVersion->checksum ? substr($currentVersion->checksum, 0, 16) : '',
                'signer' => $currentVersion->signed_by,
                'role' => 'Authorized Signatory',
                'date' => $currentVersion->signed_at ? $currentVersion->signed_at->format('M d, Y') : ($currentVersion->created_at ? $currentVersion->created_at->format('M d, Y') : '-')
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

      <div style="background:#fff;border:1px solid #eef3f8;border-radius:8px;padding:18px;min-height:300px;">
        @if($currentVersion && $cleanText !== '')
          <div style="white-space:pre-wrap;font-family:inherit;border:0;background:transparent;padding:0;margin:0;">
            {!! nl2br(e($cleanText)) !!}
          </div>
        @elseif($currentVersion && $currentVersion->file_path && ! $pdfAvailable)
          <div>
            File attached.
            @if(Route::has('documents.versions.download'))
              <a href="{{ route('documents.versions.download', $currentVersion->id) }}">Download</a> to view.
            @endif
          </div>
        @else
          <div class="small-muted">
            Belum ada isi versi. Klik <b>Edit</b> lalu tambahkan isi (paste text) atau upload file.
          </div>
        @endif
      </div>

      {{-- Digital Signature Card Grid --}}
      @if(!empty($parsedSignatures))
        <div style="margin-top:20px;">
          <h3 style="font-size:13px; font-weight:600; color:var(--primary); margin:0 0 12px 0; display:flex; align-items:center; gap:6px;">
            <span class="material-symbols-outlined" style="font-size:18px; color:var(--primary);">verified_user</span> Verified Digital Signatures
          </h3>
          <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:12px;">
            @foreach($parsedSignatures as $sig)
              <div style="background:#fff; border:1px solid var(--outline-variant); border-radius:8px; padding:16px; position:relative; overflow:hidden; display:flex; flex-direction:column; gap:8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <!-- Decorative verification badge watermark -->
                <div style="position:absolute; right:-10px; bottom:-10px; opacity:0.06; color:var(--success); transform:rotate(-15deg); pointer-events: none;">
                  <span class="material-symbols-outlined" style="font-size:80px;">verified</span>
                </div>
                
                <div style="display:flex; justify-content:space-between; align-items:flex-start; z-index: 1;">
                  <div>
                    <div style="font-size:14px; font-weight:700; color:var(--on-surface);">{{ $sig['signer'] }}</div>
                    <div style="font-size:12px; color:var(--on-surface-variant); font-weight:600;">{{ $sig['role'] }}</div>
                  </div>
                  <span class="status-badge status-approved" style="font-size:9px; padding:2px 6px; border-radius:4px; display:inline-flex; align-items:center; gap:2px; font-weight:700;">
                    <span class="material-symbols-outlined" style="font-size:10px; font-weight:bold;">check</span> VERIFIED
                  </span>
                </div>
                
                @if($sig['hash'])
                  <div style="margin-top:4px; border-top:1px dashed var(--outline-variant); padding-top:8px; z-index: 1;">
                    <span class="small-muted" style="font-size:10px; text-transform:uppercase; font-weight:600; display:block; margin-bottom:2px;">Signature ID</span>
                    <code style="font-family:var(--mono-font); font-size:11px; color:var(--muted); word-break:break-all;">{{ $sig['hash'] }}</code>
                  </div>
                @endif
                
                <div style="font-size:11px; color:var(--muted); margin-top:auto; z-index: 1;">
                  Signed Date: <strong>{{ $sig['date'] }}</strong>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      @endif
    </div>

    {{-- RIGHT: sidebar --}}
    <div style="width:340px; min-width:280px; flex-shrink:0; display:flex; flex-direction:column; gap:16px;">
      
      {{-- Review Schedule Card --}}
      <div class="card" style="padding:16px; border-radius:8px; border:1px solid #c3c6d7; background:#fff;">
        <h3 style="font-size:13px; font-weight:600; color:#515f74; border-bottom:1px solid #c3c6d7; padding-bottom:8px; margin:0 0 12px; display:flex; align-items:center; gap:6px;">
          <span class="material-symbols-outlined" style="font-size:18px;">calendar_today</span> Review Schedule
        </h3>
        <div style="display:flex; flex-direction:column; gap:8px;">
          <div style="display:flex; justify-content:space-between; font-size:12px;">
            <span style="color:#434655;">Last Review</span>
            <span style="font-weight:600; color:#191b23;">{{ $document->revision_date ? $document->revision_date->format('M d, Y') : '-' }}</span>
          </div>
          @php
            $nextReview = $document->next_review_date;
            $isOverdue = $nextReview && $nextReview->isPast();
          @endphp
          <div style="display:flex; justify-content:space-between; font-size:12px;">
            <span style="color:#434655;">Next Review</span>
            <span style="font-weight:600; @if($isOverdue) color:#ba1a1a; @else color:#191b23; @endif">
              {{ $nextReview ? $nextReview->format('M d, Y') : '-' }}
            </span>
          </div>
          <div style="display:flex; justify-content:space-between; font-size:12px;">
            <span style="color:#434655;">Frequency</span>
            <span style="font-weight:600; color:#191b23;">
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

      {{-- Collapsible Sections Container --}}
      <div class="card" style="border-radius:8px; border:1px solid #c3c6d7; background:#fff; display:flex; flex-direction:column; overflow:hidden;">
        
        {{-- Section: Revision History --}}
        <details open style="border-bottom:1px solid #c3c6d7;">
          <summary style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; cursor:pointer; list-style:none; user-select:none;">
            <span style="font-size:13px; font-weight:600; color:#191b23; display:flex; align-items:center; gap:6px;">
              <span class="material-symbols-outlined" style="font-size:18px;">history</span> Revision History
            </span>
            <span class="material-symbols-outlined" style="font-size:18px;">expand_more</span>
          </summary>
          <div style="padding:16px; display:flex; flex-direction:column; gap:12px;">
            @forelse($versions as $ver)
              <div style="position:relative; padding-left:16px; border-left:2px solid #c3c6d7;">
                @php
                  $isCurrent = $document->current_version_id && $ver->id == $document->current_version_id;
                  $dotBg = $isCurrent ? '#004ac6' : '#c3c6d7';
                @endphp
                <div style="position:absolute; left:-5px; top:4px; width:8px; height:8px; border-radius:50%; background:{{ $dotBg }};"></div>
                <p style="font-size:12px; font-weight:700; margin:0 0 2px;">
                  <a href="{{ route('documents.show', $document->id) }}?version_id={{ $ver->id }}" style="text-decoration:none; color:inherit;">
                    Rev. {{ $ver->version_label }}
                  </a>
                  @if($isCurrent)
                    <span style="font-weight:normal; color:#434655; margin-left:6px; font-size:11px;">(Current)</span>
                  @endif
                </p>
                <p style="font-size:11px; color:#434655; margin:0 0 4px;">
                  {{ $ver->created_at ? $ver->created_at->format('M d, Y') : '-' }} by {{ $ver->creator->name ?? 'System' }}
                </p>
                @if($ver->change_note)
                  <p style="font-size:11px; font-style:italic; color:#434655; margin:0;">"{{ $ver->change_note }}"</p>
                @endif
              </div>
            @empty
              <div style="font-size:12px; color:#434655;">No versions found.</div>
            @endforelse
          </div>
        </details>

        {{-- Section: Approvals --}}
        @php
          $approvalLogs = [];
          if ($currentVersion && \Schema::hasTable('approval_logs')) {
              $approvalLogs = \DB::table('approval_logs')
                  ->where('document_version_id', $currentVersion->id)
                  ->join('users', 'users.id', '=', 'approval_logs.user_id')
                  ->select('approval_logs.*', 'users.name as user_name')
                  ->orderBy('approval_logs.created_at', 'asc')
                  ->get();
          }
        @endphp
        <details style="border-bottom:1px solid #c3c6d7;">
          <summary style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; cursor:pointer; list-style:none; user-select:none;">
            <span style="font-size:13px; font-weight:600; color:#191b23; display:flex; align-items:center; gap:6px;">
              <span class="material-symbols-outlined" style="font-size:18px;">verified</span> Approvals
            </span>
            <span class="material-symbols-outlined" style="font-size:18px;">expand_more</span>
          </summary>
          <div style="padding:16px; display:flex; flex-direction:column; gap:8px;">
            @forelse($approvalLogs as $log)
              @php
                $isApproved = in_array(strtolower($log->action), ['approve', 'approved'], true);
                $actionIcon = $isApproved ? 'check_circle' : 'cancel';
                $iconColor = $isApproved ? '#16a34a' : '#ba1a1a';
              @endphp
              <div style="display:flex; align-items:center; gap:8px; font-size:12px;">
                <span class="material-symbols-outlined" style="font-size:16px; color:{{ $iconColor }};">{{ $actionIcon }}</span>
                <span style="font-weight:600; color:#191b23;">{{ ucwords($log->role) }}</span>
                <span style="color:#434655;">{{ \Carbon\Carbon::parse($log->created_at)->format('M d, Y') }}</span>
              </div>
            @empty
              <div style="font-size:12px; color:#434655;">No approvals recorded yet.</div>
            @endforelse
          </div>
        </details>

        {{-- Section: Related Documents --}}
        <details style="border-bottom:1px solid #c3c6d7;">
          <summary style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; cursor:pointer; list-style:none; user-select:none;">
            <span style="font-size:13px; font-weight:600; color:#191b23; display:flex; align-items:center; gap:6px;">
              <span class="material-symbols-outlined" style="font-size:18px;">link</span> Related Documents
            </span>
            <span class="material-symbols-outlined" style="font-size:18px;">expand_more</span>
          </summary>
          <div style="padding:16px; display:flex; flex-direction:column; gap:8px;">
            @forelse($relatedLinks as $link)
              @php
                $lkUrl = is_array($link) ? ($link['url'] ?? '#') : (is_object($link) ? ($link->url ?? '#') : (string)$link);
                $lkLabel = is_array($link) ? ($link['label'] ?? $lkUrl) : (is_object($link) ? ($link->label ?? $lkUrl) : $lkUrl);
              @endphp
              <a href="{{ $lkUrl }}" target="_blank" rel="noopener noreferrer" style="font-size:12px; color:#004ac6; text-decoration:none; display:flex; align-items:center; gap:6px;">
                <span class="material-symbols-outlined" style="font-size:16px;">description</span> {{ $lkLabel }}
              </a>
            @empty
              <div style="font-size:12px; color:#434655;">No related documents.</div>
            @endforelse
          </div>
        </details>

        {{-- Section: Audit Trail --}}
        @php
          $docAuditLogs = [];
          if (\Schema::hasTable('audit_logs')) {
              $docAuditLogs = \DB::table('audit_logs')
                  ->where('document_id', $document->id)
                  ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
                  ->select('audit_logs.*', 'users.name as user_name')
                  ->orderBy('audit_logs.created_at', 'desc')
                  ->take(5)
                  ->get();
          }
        @endphp
        <details>
          <summary style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; cursor:pointer; list-style:none; user-select:none;">
            <span style="font-size:13px; font-weight:600; color:#191b23; display:flex; align-items:center; gap:6px;">
              <span class="material-symbols-outlined" style="font-size:18px;">list_alt</span> Audit Trail
            </span>
            <span class="material-symbols-outlined" style="font-size:18px;">expand_more</span>
          </summary>
          <div style="padding:16px; display:flex; flex-direction:column; gap:12px;">
            @forelse($docAuditLogs as $log)
              <div style="font-size:11px;">
                <span style="font-weight:bold; color:#191b23;">{{ $log->user_name ?? 'System' }}</span>
                <span style="color:#434655;">{{ str_replace('_', ' ', $log->event) }}</span>
                <div style="color:#737686; font-size:10px; margin-top:2px;">{{ \Carbon\Carbon::parse($log->created_at)->diffForHumans() }}</div>
              </div>
            @empty
              <div style="font-size:12px; color:#434655;">No recent audit logs.</div>
            @endforelse
          </div>
        </details>

      </div>

      {{-- Technical Specs / Storage Metadata --}}
      <div class="card" style="padding:16px; border-radius:8px; border:1px solid #c3c6d7; background:#f3f3fe; box-shadow:none;">
        <p style="font-size:11px; font-weight:600; color:#515f74; text-transform:uppercase; margin:0 0 8px;">Storage Metadata</p>
        <table style="width:100%; font-size:12px; border-collapse:collapse;">
          <tbody>
            <tr>
              <td style="padding:4px 0; color:#434655;">Format</td>
              <td style="padding:4px 0; font-weight:600; color:#191b23; text-align:right;">
                {{ $currentVersion ? (explode('/', $currentVersion->file_mime ?? '')[1] ?? 'PDF') : 'PDF' }}
              </td>
            </tr>
            <tr>
              <td style="padding:4px 0; color:#434655;">Size</td>
              <td style="padding:4px 0; font-weight:600; color:#191b23; text-align:right;">
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
              <td style="padding:4px 0; color:#434655;">Retention</td>
              <td style="padding:4px 0; font-weight:600; color:#191b23; text-align:right;">10 Years</td>
            </tr>
            @if($currentVersion && $currentVersion->checksum)
              <tr>
                <td style="padding:4px 0; color:#434655; vertical-align:top;">SHA-256</td>
                <td style="padding:4px 0; font-weight:600; color:#191b23; text-align:right; font-family:var(--mono); font-size:10px; word-break:break-all; max-width:180px;">
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
  </div>
</div>

{{-- Edit / Create Version Modal (only for editors) --}}
@if($canEditDocument)
  <div id="editModal"
       style="display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);
              width:880px;max-width:95%;z-index:999;background:#fff;padding:18px;
              border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,0.15);">

    <form method="post"
          action="{{ route('documents.updateCombined', $document->id) }}"
          enctype="multipart/form-data"
          novalidate>
      @csrf
      @method('PUT')

      <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <div style="flex:1;min-width:260px;">
          {{-- Category --}}
          <label for="category">Kategori</label>
          @php $cat = old('category_id', $document->category_id ?? ''); @endphp
          <select id="category" name="category_id" class="input">
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
          <label for="doc_code" style="margin-top:8px">Document code</label>
          <input id="doc_code" type="text" name="doc_code" value="{{ old('doc_code', $document->doc_code) }}" class="input" placeholder="Kosongkan untuk auto-generate">

          {{-- Title --}}
          <label for="title" style="margin-top:8px">Title</label>
          <input id="title" type="text" name="title" value="{{ old('title', $document->title) }}" class="input" required>

          {{-- Department --}}
          <label for="department_id" style="margin-top:8px">Department</label>
          @php $selectedDept = old('department_id', $document->department_id ?? ($user->department_id ?? null)); @endphp
          <select id="department_id" name="department_id" class="input" required>
            @foreach($departments as $dep)
              <option value="{{ $dep->id }}" {{ (string)$selectedDept === (string)$dep->id ? 'selected' : '' }}>
                {{ $dep->code }} — {{ $dep->name }}
              </option>
            @endforeach
          </select>

          {{-- Change note --}}
          <label for="change_note" style="margin-top:8px">Change note (version)</label>
          <input id="change_note" name="change_note" value="{{ old('change_note', optional($currentVersion)->change_note ?? '') }}" class="input">

          {{-- Related links --}}
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

          <label for="related_links" style="margin-top:8px" class="small-muted">Dokumen terkait (satu URL per baris)</label>
          <textarea id="related_links" name="related_links" rows="4" class="input" style="min-height:80px;">{{ $relatedDefault }}</textarea>
        </div>

        <div style="width:360px;min-width:220px">
          <input type="hidden" name="version_id" value="{{ old('version_id', optional($currentVersion)->id ?? '') }}">

          {{-- Version label --}}
          <label for="version_label">Version label</label>
          <input id="version_label" name="version_label" value="{{ old('version_label', optional($currentVersion)->version_label ?? 'v1') }}" class="input" required>

          {{-- Master file --}}
          <label for="master_file" style="margin-top:8px">Master file (.doc/.docx/.xls/.xlsx) — opsional</label>
          <input id="master_file" type="file" name="master_file" accept=".doc,.docx,.xls,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" class="input">

          @php
            // show current master if available (consider master_path first then file_path legacy)
            $currMasterPath = optional($currentVersion)->master_path ?: optional($currentVersion)->file_path ?: '';
            $currMasterExt = $currMasterPath ? strtolower(pathinfo($currMasterPath, PATHINFO_EXTENSION)) : '';
            $masterAllowedExt = ['doc','docx','xls','xlsx'];
          @endphp

          @if($currMasterPath && in_array($currMasterExt, $masterAllowedExt, true))
            <div class="small-muted" style="margin-top:6px;">Master saat ini: {{ basename($currMasterPath) }}</div>
          @endif

          {{-- PDF file --}}
          <label for="file" style="margin-top:8px">Upload PDF (optional)</label>
          <input id="file" type="file" name="file" accept="application/pdf" class="input">
          @if(optional($currentVersion)->file_path && Str::endsWith(strtolower(optional($currentVersion)->file_path), '.pdf') )
            <div class="small-muted" style="margin-top:6px;">PDF saat ini: {{ basename(optional($currentVersion)->file_path) }}</div>
          @endif

          {{-- Pasted text --}}
          <label for="pasted_text" style="margin-top:8px">Paste text (for search / display)</label>
          @php
            $pastedForModal = old('pasted_text', optional($currentVersion)->pasted_text ?? optional($currentVersion)->plain_text ?? '');
          @endphp
          <textarea id="pasted_text" name="pasted_text" rows="6" class="input">{{ $pastedForModal }}</textarea>

          {{-- Signed by / date --}}
          <label for="signed_by" style="margin-top:8px">Signed by</label>
          <input id="signed_by" name="signed_by" value="{{ old('signed_by', optional($currentVersion)->signed_by ?? '') }}" class="input">

          <label for="signed_at" style="margin-top:8px">Signed date</label>
          @php
            $signedAtOld = old('signed_at');
            $signedAtDefault = $signedAtOld !== null ? $signedAtOld : (optional(optional($currentVersion)->signed_at)->format('Y-m-d') ?? '');
          @endphp
          <input id="signed_at" type="date" name="signed_at" value="{{ $signedAtDefault }}" class="input">

          <div style="margin-top:15px;display:flex;gap:8px;">
            <button class="btn btn-secondary" type="submit" name="submit_for" value="save">Save Draft</button>
            <button class="btn btn-primary" type="submit" name="submit_for" value="submit">Save & Submit</button>
            <button type="button" class="btn btn-secondary" id="cancelEdit">Cancel</button>
          </div>
        </div>
      </div>

      @if ($errors->any())
        <div style="margin-top:10px;color:#b42318;background:#fee4e2;border:1px solid #fecdca;border-radius:6px;padding:8px;">
          <ul style="margin:0;padding-left:18px;">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif
    </form>
  </div>
@endif

{{-- Page styles --}}
<style>
  .btn-small{ display:inline-block;padding:6px 8px;border-radius:6px;background:#eef7ff;color:#0b63d4;text-decoration:none;font-size:13px; }
  .input{ width:100%;padding:8px;border-radius:6px;border:1px solid #e6eef8;margin-top:6px;box-sizing:border-box; }
  .small-muted{ color:#6b7280; font-size:.95rem; }
  #pdfIframe { transition: transform .12s ease; }
  .btn:disabled, button[disabled] { opacity: 0.6; cursor: progress; }
</style>
@endsection {{-- end content --}}

@section('scripts')
<script>
  (function () {
    // Modal open/close
    var editBtn = document.getElementById('btnEditDoc');
    var modal = document.getElementById('editModal');
    var cancelBtn = document.getElementById('cancelEdit');
    if (editBtn && modal) editBtn.addEventListener('click', function () { modal.style.display = 'block'; });
    if (cancelBtn && modal) cancelBtn.addEventListener('click', function () { modal.style.display = 'none'; });

    // propagate version_id query param into hidden input
    try {
      var params = new URLSearchParams(window.location.search);
      var v = params.get('version_id');
      if (v) {
        var input = document.querySelector('input[name="version_id"]');
        if (input) input.value = v;
      }
    } catch (e) { /* ignore */ }

    // prevent double submit for modal form
    try {
      var editForm = modal ? modal.querySelector('form') : null;
      if (editForm) {
        editForm.addEventListener('submit', function (ev) {
          // disable all submit buttons to avoid double-clicks
          Array.from(editForm.querySelectorAll('button[type="submit"]')).forEach(function(b){
            b.disabled = true;
            b.dataset.origText = b.textContent;
            b.textContent = 'Processing...';
          });
          // allow submit to proceed
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
        // maintain visible height (avoid overflow by scaling container height)
        pdfIframe.style.height = (700 / currentZoom) + 'px';
      }
      if (pdfZoomPct) pdfZoomPct.textContent = Math.round(currentZoom * 100) + '%';
    }
    if (pdfClose) pdfClose.addEventListener('click', function () { if (pdfWrapper) pdfWrapper.style.display = 'none'; });
    if (pdfZoomIn) pdfZoomIn.addEventListener('click', function () { setZoom(currentZoom + 0.1); });
    if (pdfZoomOut) pdfZoomOut.addEventListener('click', function () { setZoom(currentZoom - 0.1); });

    // Attach handlers for "Open" links in Versions list (persist to localStorage)
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
            try { if (window.opener && !window.opener.closed) window.opener.postMessage({ iso_action: 'version_opened', version_id: vid }, '*'); } catch(e){}
          } catch(e){}
        }, { passive: true });

        link.addEventListener('auxclick', function (ev) {
          if (ev.button === 1) {
            try {
              var vid = this.getAttribute('data-version-id') || this.dataset.versionId;
              if (!vid) {
                var href = this.getAttribute('href') || '';
                var m = href.match(/\/(\d+)(?:$|[?#])/);
                if (m) vid = m[1];
              }
              if (vid) persistOpened(vid);
            } catch(e){}
          }
        }, { passive: true });
      });
    }
    attachVersionOpenHandlers();

    // listen for external messages to persist opened versions
    window.addEventListener('message', function (ev) {
      try {
        var d = ev.data || {};
        if (d && d.iso_action === 'version_opened' && d.version_id) {
          try { localStorage.setItem('iso_opened_version_' + String(d.version_id), '1'); } catch(e){}
        }
      } catch (e) {}
    }, false);

    // auto-open edit modal if edit=1 query parameter is present
    try {
      if (new URLSearchParams(window.location.search).get('edit') === '1') {
        var modalEl = document.getElementById('editModal');
        if (modalEl) modalEl.style.display = 'block';
      }
    } catch(e) {}

    // expose helper
    window.__docShow = { attachVersionOpenHandlers: attachVersionOpenHandlers };
  })();
</script>
@endsection
