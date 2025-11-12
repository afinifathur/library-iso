@extends('layouts.iso')

@section('title', $department->name ?? 'Department')

@section('content')
<div class="container" style="max-width:1200px;margin:28px auto;padding:0 16px;box-sizing:border-box;">
  <div style="background:#fff;border-radius:12px;padding:18px;box-shadow:0 8px 20px rgba(20,40,80,0.04);">

    {{-- Header --}}
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:12px;">
      <div>
        <h2 style="margin:0;font-size:1.4rem;">{{ $department->code }} — {{ $department->name }}</h2>
        <div style="color:#6b7280;margin-top:6px;">
          PIC:
          @if(method_exists($department,'manager') && $department->relationLoaded('manager') && $department->manager)
            {{ $department->manager->name }}{{ $department->manager->email ? ' — ' . $department->manager->email : '' }}
          @else
            {{ $department->pic_name ?? '-' }}
          @endif
        </div>
      </div>

      <div style="text-align:right;">
        <div style="color:#6b7280;">Active documents: <strong>{{ $activeCount ?? 0 }}</strong></div>
        <a href="{{ route('documents.index') }}?department={{ $department->id }}" style="display:inline-block;margin-top:8px;padding:8px 12px;border-radius:8px;background:#1e88ff;color:#fff;text-decoration:none;font-weight:600;">
          Browse Documents
        </a>
      </div>
    </div>

    <hr style="border:none;border-top:1px solid #eef2f7;margin:12px 0;"/>

    {{-- Build groups: prefer provided $groups, otherwise build from $documents --}}
    @php
      // $groupsFromController: if provided, use it (already grouped)
      $groupsData = null;
      if (isset($groups) && is_iterable($groups) && count($groups)) {
          $groupsData = $groups;
      } elseif (isset($documents) && is_iterable($documents)) {
          $groupsData = collect($documents)->groupBy(function($d){
              return $d->short_code ?? 'Uncategorized';
          });
      } else {
          $groupsData = collect();
      }

      // helper for status badge html
      function statusBadge($status) {
          $s = strtolower((string) $status);
          if ($s === 'approved' || $s === 'published') {
              return '<span style="display:inline-block;padding:4px 8px;border-radius:8px;background:#10b981;color:#fff;font-weight:600;">' . e($status) . '</span>';
          }
          if ($s === 'rejected') {
              return '<span style="display:inline-block;padding:4px 8px;border-radius:8px;background:#ef4444;color:#fff;font-weight:600;">' . e($status) . '</span>';
          }
          return '<span style="display:inline-block;padding:4px 8px;border-radius:8px;background:#f59e0b;color:#fff;font-weight:600;">' . e($status) . '</span>';
      }
    @endphp

    {{-- Document groups --}}
    <h4 style="margin-top:6px;margin-bottom:8px;color:#0f172a;">Documents grouped by type</h4>

    @if($groupsData->isEmpty())
      <div style="color:#6b7280;padding:12px;border-radius:8px;background:#f8fafc;">No documents found in this department.</div>
    @else
      @foreach($groupsData as $prefix => $docs)
        <div style="margin-top:18px;">
          <h5 style="margin:0 0 8px 0;color:#0f172a;">{{ $prefix }}</h5>

          <div style="background:#fff;border:1px solid #eef3f8;border-radius:10px;padding:12px;">
            <div style="overflow:auto;">
              <table style="width:100%;border-collapse:collapse;min-width:720px;">
                <thead>
                  <tr style="text-align:left;border-bottom:1px solid #eef2f7;">
                    <th style="padding:10px 8px;width:140px;font-weight:600;color:#0f172a;">Code</th>
                    <th style="padding:10px 8px;font-weight:600;color:#0f172a;">Title</th>
                    <th style="padding:10px 8px;width:160px;font-weight:600;color:#0f172a;">Latest</th>
                    <th style="padding:10px 8px;width:120px;font-weight:600;color:#0f172a;">Status</th>
                    <th style="padding:10px 8px;width:180px;font-weight:600;color:#0f172a;">Actions</th>
                  </tr>
                </thead>

                <tbody>
                  @foreach($docs as $d)
                    @php
                      // $d might be a model or array/stdClass (if coming from a join)
                      $docModel = $d;
                      // safe access to versions
                      $versions = isset($d->versions) ? collect($d->versions) : collect();
                      // assume versions already ordered desc (controller responsibility)
                      $latest = $versions->first();
                      // compute latest label and date safely
                      $latestLabel = $latest->version_label ?? ($latest->label ?? null);
                      $latestDate = null;
                      if (!empty($latest->created_at)) {
                        try { $latestDate = \Carbon\Carbon::parse($latest->created_at)->format('Y-m-d'); } catch (\Throwable $e) { $latestDate = null; }
                      }
                    @endphp

                    <tr style="border-bottom:1px solid #f5f7fb;">
                      <td style="padding:10px 8px;vertical-align:middle;">
                        {{ $d->doc_code ?? ($d->code ?? '-') }}
                      </td>

                      <td style="padding:10px 8px;vertical-align:middle;">
                        @if(!empty($d->id))
                          <a href="{{ route('documents.show', $d->id) }}" style="color:#0f172a;text-decoration:none;font-weight:600;">{{ $d->title ?? '—' }}</a>
                        @else
                          <span style="font-weight:600;">{{ $d->title ?? '—' }}</span>
                        @endif
                      </td>

                      <td style="padding:10px 8px;vertical-align:middle;">
                        @if($latestLabel)
                          {{ $latestLabel }} @if($latestDate) — <small style="color:#6b7280;">{{ $latestDate }}</small>@endif
                        @else
                          <span style="color:#6b7280;">-</span>
                        @endif
                      </td>

                      <td style="padding:10px 8px;vertical-align:middle;">
                        @if($latest && !empty($latest->status))
                          {!! statusBadge($latest->status) !!}
                        @else
                          <span style="color:#9ca3af;">no-version</span>
                        @endif
                      </td>

                      <td style="padding:10px 8px;vertical-align:middle;">
                        @if(!empty($d->id))
                          <a href="{{ route('documents.show', $d->id) }}" style="display:inline-block;padding:6px 10px;border-radius:8px;border:1px solid #eef2f7;background:#fff;color:#0f172a;text-decoration:none;margin-right:8px;font-weight:600;">View</a>

                          <a href="{{ route('documents.create', ['document_id' => $d->id]) }}" style="display:inline-block;padding:6px 10px;border-radius:8px;background:#1e88ff;color:#fff;text-decoration:none;font-weight:600;">+ New Version</a>
                        @else
                          <span style="color:#6b7280;">—</span>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        </div>
      @endforeach
    @endif

    {{-- Related Documents --}}
    <div style="margin-top:28px;">
      <h4 style="margin:0 0 8px 0;color:#0f172a;">Related documents (referenced by documents in this department)</h4>

      @if(empty($related) || (is_countable($related) && count($related) === 0))
        <div style="color:#6b7280;padding:12px;border-radius:8px;background:#f8fafc;">Tidak ada dokumen terkait yang ditemukan (atau table relation belum tersedia).</div>
      @else
        <div style="background:#fff;border:1px solid #eef3f8;border-radius:10px;padding:12px;">
          <div style="overflow:auto;">
            <table style="width:100%;border-collapse:collapse;min-width:600px;">
              <thead>
                <tr style="text-align:left;border-bottom:1px solid #eef2f7;">
                  <th style="padding:10px 8px;font-weight:600;color:#0f172a;">Document</th>
                  <th style="padding:10px 8px;font-weight:600;color:#0f172a;">References / Notes</th>
                </tr>
              </thead>
              <tbody>
                @foreach($related as $r)
                  @php
                    // r might be a join result (with doc_id, related_to, related_code...)
                    // or a Document model
                    $isJoinRow = isset($r->doc_id) || isset($r->related_to) || isset($r->related_code);
                    // Normalize values
                    if ($isJoinRow) {
                      $docId = $r->doc_id ?? $r->id ?? null;
                      $docCode = $r->doc_code ?? ($r->code ?? null);
                      $docTitle = $r->title ?? null;
                      $relatedTo = $r->related_to ?? $r->related_document_id ?? null;
                      $relatedCode = $r->related_code ?? null;
                    } else {
                      $docId = $r->id ?? null;
                      $docCode = $r->doc_code ?? ($r->code ?? null);
                      $docTitle = $r->title ?? null;
                      $relatedTo = null;
                      $relatedCode = null;
                    }
                  @endphp

                  <tr style="border-bottom:1px solid #f5f7fb;">
                    <td style="padding:10px 8px;vertical-align:middle;">
                      @if($docId)
                        <a href="{{ route('documents.show', $docId) }}" style="font-weight:600;color:#0f172a;text-decoration:none;">
                          {{ $docCode ?? '—' }} {{ $docTitle ? '— ' . $docTitle : '' }}
                        </a>
                      @else
                        <span style="font-weight:600;">{{ $docCode ?? ($docTitle ?? '-') }}</span>
                      @endif
                    </td>

                    <td style="padding:10px 8px;vertical-align:middle;color:#6b7280;">
                      @if($relatedTo)
                        references
                        @if($relatedCode && $relatedTo)
                          <a href="{{ route('documents.show', $relatedTo) }}" style="margin-left:6px;color:#2563eb;text-decoration:none;font-weight:600;">{{ $relatedCode }}</a>
                        @else
                          <a href="{{ $relatedTo ? route('documents.show', $relatedTo) : '#' }}" style="margin-left:6px;color:#2563eb;text-decoration:none;font-weight:600;">View</a>
                        @endif
                      @else
                        <span>-</span>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif
    </div>

  </div>
</div>
@endsection
