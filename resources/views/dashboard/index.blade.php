<!-- resources/views/dashboard/index.blade.php -->
@extends('layouts.iso')

@section('title', 'Dashboard')

@section('content')
<div class="dashboard-container">
  <div class="dashboard-header">
    <h1>Dashboard</h1>
    <div>
      <a class="btn" href="{{ route('documents.create') }}">+ New Document</a>
    </div>
  </div>

  <!-- Cards -->
  <div class="cards-row">
    <div class="card">
      <div class="card-title">Total Documents</div>
      <div class="card-value clickable" data-href="{{ route('documents.index') }}">{{ number_format($totalDocuments ?? 0) }}</div>
    </div>

    <div class="card">
      <div class="card-title">Total Versions</div>
      <div class="card-value clickable" data-href="{{ route('documents.index') }}">{{ number_format($totalVersions ?? 0) }}</div>
    </div>

    <div class="card">
      <div class="card-title">Pending / In Progress</div>
      <div class="card-value clickable" data-href="{{ route('approval.index', ['status' => 'pending']) }}">{{ number_format($pendingCount ?? 0) }}</div>
      <div class="card-note">Click to open approval queue</div>
    </div>

    <div class="card">
      <div class="card-title">Approved</div>
      <div class="card-value clickable" data-href="{{ route('approval.index', ['status' => 'approved']) }}">{{ number_format($approvedCount ?? 0) }}</div>
    </div>

    <div class="card">
      <div class="card-title">Rejected</div>
      <div class="card-value clickable" data-href="{{ route('approval.index', ['status' => 'rejected']) }}">{{ number_format($rejectedCount ?? 0) }}</div>
    </div>

    <!-- Card full: charts -->
    <div class="card card-full">
      <div class="card-inner-flex">
        <!-- Main chart area (line) + donut side -->
        <div style="display:flex; gap:18px; align-items:flex-start; width:100%;">
          <div style="flex:1; min-height:220px;">
            <div class="small-muted">Versions (last 26 weeks)</div>
            <div style="height:220px; background:#f6f9fb; border-radius:6px; padding:12px;">
              <canvas id="versionsChart" style="width:100%; height:100%;"></canvas>
            </div>
          </div>

          <div style="width:240px; height:220px;">
            <div class="small-muted" style="margin-bottom:8px;">Status distribution</div>
            <div style="height:180px;">
              <canvas id="statusDonut" style="width:100%;height:100%;"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- MAIN GRID -->
  <div class="dashboard-grid">
    <!-- LEFT: Recent activity -->
    <div class="left-col">
      <div>
        <div class="section-title panel-title">Recent activity</div>
        <div class="card-section card-inner">
          <table class="table">
            <thead>
              <tr>
                <th>When</th>
                <th>Document</th>
                <th>Version</th>
                <th>By</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              @php
                $statusBadge = [
                  'draft' => 'badge-warning',
                  'pending' => 'badge-warning',
                  'submitted' => 'badge-warning',
                  'approved' => 'badge-success',
                  'published' => 'badge-success',
                  'rejected' => 'badge-danger',
                ];
              @endphp

              @forelse($recentVersions ?? ($recentActivity ?? collect()) as $rv)
                <tr>
                  <td>{{ $rv->created_at ? $rv->created_at->format('Y-m-d H:i') : '-' }}</td>
                  <td>
                    @if(optional($rv->document)->id)
                      <a href="{{ route('documents.show', $rv->document->id) }}">{{ $rv->document->doc_code ?? '-' }} — {{ \Illuminate\Support\Str::limit($rv->document->title ?? '-', 60) }}</a>
                    @else
                      -
                    @endif
                  </td>
                  <td>{{ $rv->version_label ?? '-' }}</td>
                  <td>{{ optional($rv->creator)->name ?? ($rv->created_by ?? '—') }}</td>
                  <td>
                    @php $s = strtolower($rv->status ?? 'other'); @endphp
                    <span class="badge {{ $statusBadge[$s] ?? '' }}">
                      {{ ucfirst($rv->status ?? 'Other') }}
                    </span>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="small-muted">No recent activity</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- RIGHT: Quick actions + Recently published -->
    <div class="right-col">
      <div class="card-section card-inner mb-3">
        <h4 class="panel-title">Quick actions</h4>
        <a href="{{ route('documents.create') }}" class="btn" style="display:block;margin-bottom:10px;background:#0b66ff;color:#fff;">Upload New Document</a>
        <a href="{{ route('documents.index') }}" class="btn btn-muted" style="display:block;margin-bottom:8px;">Browse Documents</a>
        <a href="{{ route('approval.index') }}" class="btn btn-muted" style="display:block;margin-bottom:2px;">Approval Queue</a>
      </div>

      <div class="card-section card-inner">
        <h4 class="panel-title">Recently published</h4>
        @if(!empty($recentPublished) && $recentPublished->count())
          <ul class="recent-list">
            @foreach($recentPublished as $v)
              <li>
                <a href="{{ route('documents.show', $v->document_id) }}">
                  {{ $v->document->doc_code ?? '-' }} — {{ \Illuminate\Support\Str::limit($v->document->title ?? '-', 50) }}
                </a>
                <div class="muted-small">
                  {{ $v->signed_at ? $v->signed_at->format('Y-m-d') : ($v->created_at ? $v->created_at->format('Y-m-d') : '-') }}
                </div>
              </li>
            @endforeach
          </ul>
        @else
          <div class="small-muted">Tidak ada publikasi baru.</div>
        @endif
      </div>
    </div>
  </div>
</div>

<!-- Inline CSS (optional: pindahkan ke file CSS) -->
<style>
:root{
  --brand-blue: #0ea5ff;
  --muted: #6b7280;
  --panel-border: #eef3f8;
}
.dashboard-container{ max-width:1200px; margin:18px auto; padding:6px; }
.dashboard-header{ display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
.dashboard-header h1{ margin:0; font-size:20px; }
.cards-row{ display:flex; gap:12px; flex-wrap:wrap; margin-bottom:18px; align-items:flex-start; }
.card{ background:#fff; border:1px solid var(--panel-border); border-radius:10px; padding:12px; width:180px; box-sizing:border-box; }
.card-title{ color:var(--muted); font-size:13px; }
.card-value{ font-size:20px; font-weight:700; margin-top:6px; color:var(--brand-blue); }
.card-note{ font-size:12px; color:#7b8794; margin-top:6px; }
.card-full{ flex:1 1 100%; padding:10px; width:auto; }
.card-inner-flex{ display:flex; align-items:center; justify-content:space-between; gap:12px; }
.small-muted{ color:#7b8794; font-size:13px; }
.table{ width:100%; border-collapse:collapse; }
.table th, .table td{ padding:8px 8px; border-bottom:1px solid #f0f4f8; text-align:left; }
.table th{ color:#555; font-weight:600; background:transparent; }
.table-scroll{ max-height:560px; overflow:auto; }
.panel{ background:#fff; border:1px solid var(--panel-border); border-radius:10px; padding:12px; margin-bottom:12px; }
.panel-title{ margin:0 0 8px 0; font-size:16px; }
.recent-list{ list-style:none; padding:0; margin:0; }
.recent-list li{ margin-bottom:10px; }
.muted-small{ font-size:12px; color:var(--muted); margin-top:4px; }

/* badges */
.badge { display:inline-block; padding:6px 10px; border-radius:999px; font-weight:600; font-size:12px; }
.badge-success { background:#16a34a; color:#fff; }
.badge-warning { background:#f59e0b; color:#fff; }
.badge-danger { background:#ef4444; color:#fff; }

/* buttons */
.btn{ display:inline-block; padding:8px 10px; border-radius:8px; background:var(--brand-blue); color:#fff; text-decoration:none; }
.btn-muted{ display:inline-block; padding:6px 8px; border-radius:8px; background:transparent; color:var(--brand-blue); text-decoration:none; border:1px solid transparent; }
.card-value.clickable{ cursor:pointer; text-decoration:underline; }

/* grid layout */
.dashboard-grid{ display:grid; grid-template-columns: 1fr 360px; gap:16px; align-items:start; }
.left-col{ display:flex; flex-direction:column; gap:12px; }
.right-col{ display:flex; flex-direction:column; gap:12px; }

/* responsive tweaks */
@media (max-width: 980px) {
  .dashboard-grid { grid-template-columns: 1fr; }
  .card { width: calc(50% - 12px); }
  .card-full { width:100%; }
}
</style>

@push('scripts')
  <!-- Chart.js (CDN) — replace with local file if offline -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function(){
    // clickable card navigation
    document.querySelectorAll('.card-value.clickable').forEach(el => {
      el.addEventListener('click', () => {
        const href = el.dataset.href;
        if (href) window.location.href = href;
      });
    });

    // Prepare data (support old/new variable names and defaults)
    const weeks = @json($weeks ?? $spark_labels ?? []);
    const counts = @json($counts ?? $spark_data ?? []);
    const pending = Number(@json($pending ?? ($donut['pending'] ?? $pendingCount ?? 0)));
    const approved = Number(@json($approved ?? ($donut['approved'] ?? $approvedCount ?? 0)));
    const rejected = Number(@json($rejected ?? ($donut['rejected'] ?? $rejectedCount ?? 0)));
    const other = Number(@json($other ?? ($donut['other'] ?? $otherCount ?? 0)));

    // LINE CHART (versions per week)
    const lineCtxEl = document.getElementById('versionsChart');
    if (lineCtxEl && typeof Chart !== 'undefined') {
      new Chart(lineCtxEl, {
        type: 'line',
        data: {
          labels: weeks,
          datasets: [{
            label: 'Dokumen / minggu',
            data: counts,
            fill: true,
            backgroundColor: 'rgba(14,165,233,0.08)',
            borderColor: '#0ea5e9',
            tension: 0.35,
            pointRadius: 3,
            pointBackgroundColor:'#fff',
            pointBorderColor:'#0ea5e9',
            borderWidth: 2
          }]
        },
        options: {
          plugins: { legend: { display:false }},
          scales: {
            x: { grid: { display:false }, ticks: { color: '#64748b', maxTicksLimit: 12, font: { size: 11 } } },
            y: { beginAtZero:true, grid: { color: 'rgba(15,23,42,0.06)' }, ticks: { stepSize: 1, color:'#64748b' } }
          },
          maintainAspectRatio:false,
          responsive:true
        }
      });
    }

    // DONUT CHART (status)
    const donutEl = document.getElementById('statusDonut');
    if (donutEl && typeof Chart !== 'undefined') {
      new Chart(donutEl, {
        type: 'doughnut',
        data: {
          labels: ['Pending','Approved','Rejected','Other'],
          datasets: [{
            data: [pending, approved, rejected, other],
            backgroundColor: ['#3b82f6','#16a34a','#ef4444','#9ca3af'],
            borderWidth:0
          }]
        },
        options: {
          plugins:{ legend:{ position:'right', labels:{ color:'#64748b', boxWidth:12 }}},
          cutout:'70%',
          maintainAspectRatio:false,
          responsive:true
        }
      });
    }
  });
  </script>
@endpush

@endsection
<!-- Chart.js (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function(){
  // debug: tampilkan data di console (bisa kamu lihat di DevTools)
  console.log('weeks', @json($weeks ?? []));
  console.log('counts', @json($counts ?? []));
  console.log('donut', { pending: @json($pending ?? 0), approved: @json($approved ?? 0), rejected: @json($rejected ?? 0), other: @json($other ?? 0) });

  // LINE chart
  const weeks = @json($weeks ?? []);
  const counts = @json($counts ?? []);
  if (weeks.length && document.getElementById('versionsChart')) {
    const ctx = document.getElementById('versionsChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: weeks,
        datasets: [{
          label: 'Docs/week',
          data: counts,
          fill: true,
          backgroundColor: 'rgba(14,165,233,0.08)',
          borderColor: '#0ea5e9',
          tension: 0.35,
          pointRadius: 3,
          borderWidth: 2
        }]
      },
      options: {
        plugins: { legend: { display:false }},
        scales: {
          x: { grid:{ display:false }, ticks:{ color:'#64748b', maxTicksLimit:12 }},
          y: { beginAtZero:true, grid:{ color:'rgba(15,23,42,0.06)' }, ticks:{ stepSize:1, color:'#64748b' } }
        },
        maintainAspectRatio:false, responsive:true
      }
    });
  }

  // DONUT chart
  const donutCtx = document.getElementById('statusDonut')?.getContext?.('2d');
  if (donutCtx) {
    new Chart(donutCtx, {
      type: 'doughnut',
      data: {
        labels: ['Pending','Approved','Rejected','Other'],
        datasets: [{
          data: [@json($pending ?? 0), @json($approved ?? 0), @json($rejected ?? 0), @json($other ?? 0)],
          backgroundColor: ['#3b82f6','#16a34a','#ef4444','#9ca3af'],
          borderWidth: 0
        }]
      },
      options: {
        plugins: { legend: { position:'right', labels:{ color:'#64748b', boxWidth:12 } } },
        maintainAspectRatio:false,
        responsive:true,
        cutout: '70%'
      }
    });
  }
});
</script>
