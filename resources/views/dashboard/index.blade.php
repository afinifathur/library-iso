<!-- resources/views/dashboard/index.blade.php -->
@extends('layouts.iso')

@section('title', 'Dashboard')

@section('content')
<div style="max-width:1200px;margin:18px auto;padding:6px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h1 style="margin:0;font-size:20px">Dashboard</h1>
    <div>
      <a class="btn" href="{{ route('documents.create') }}">+ New Document</a>
    </div>
  </div>

  <!-- Cards -->
  <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
    <div class="card">
      <div class="card-title">Total Documents</div>
      <div class="card-value clickable" data-href="{{ route('documents.index') }}">{{ number_format($totalDocuments) }}</div>
    </div>

    <div class="card">
      <div class="card-title">Total Versions</div>
      <div class="card-value clickable" data-href="{{ route('documents.index') }}">{{ number_format($totalVersions) }}</div>
    </div>

    <div class="card">
      <div class="card-title">Pending / In Progress</div>
      <div class="card-value clickable" data-href="{{ route('approval.index', ['status' => 'pending']) }}">{{ number_format($pendingCount) }}</div>
      <div class="card-note">Click to open approval queue</div>
    </div>

    <div class="card">
      <div class="card-title">Approved</div>
      <div class="card-value clickable" data-href="{{ route('approval.index', ['status' => 'approved']) }}">{{ number_format($approvedCount) }}</div>
    </div>

    <div class="card">
      <div class="card-title">Rejected</div>
      <div class="card-value clickable" data-href="{{ route('approval.index', ['status' => 'rejected']) }}">{{ number_format($rejectedCount) }}</div>
    </div>

    <div class="card" style="flex:1 1 100%;padding:10px;">
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <div>
          <div class="card-title">Versions (last 6 months)</div>
          <div style="margin-top:6px;">
            <div class="spark" data-labels='@json($spark_labels)' data-values='@json($spark_data)'></div>
          </div>
        </div>

        <div style="width:160px;text-align:center">
          <canvas id="statusPie" width="140" height="140"></canvas>
          <div style="font-size:12px;color:#556">Status distribution</div>
        </div>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns: 1fr 420px; gap:16px;">
    <!-- Left: Department summary + list -->
    <div>
      <div style="background:#fff;border:1px solid #eef3f8;border-radius:10px;padding:12px;margin-bottom:12px;">
        <h3 style="margin:0 0 8px 0">Per Department</h3>
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="text-align:left;color:#555">
              <th style="padding:6px 8px;border-bottom:1px solid #f0f4f8">Dept</th>
              <th style="padding:6px 8px;border-bottom:1px solid #f0f4f8">Documents</th>
              <th style="padding:6px 8px;border-bottom:1px solid #f0f4f8">Pending</th>
              <th style="padding:6px 8px;border-bottom:1px solid #f0f4f8">Action</th>
            </tr>
          </thead>
          <tbody>
            @foreach($departments as $d)
              <tr>
                <td style="padding:8px 8px;border-bottom:1px solid #fafafa">{{ $d['code'] }} — {{ $d['name'] }}</td>
                <td style="padding:8px 8px;border-bottom:1px solid #fafafa">{{ $d['doc_count'] }}</td>
                <td style="padding:8px 8px;border-bottom:1px solid #fafafa">{{ $d['pending'] }}</td>
                <td style="padding:8px 8px;border-bottom:1px solid #fafafa">
                  <a class="btn-muted" href="{{ route('departments.show', $d['id']) }}">Open</a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <!-- Recent Versions table (wide) -->
      <div style="background:#fff;border:1px solid #eef3f8;border-radius:10px;padding:12px;">
        <h3 style="margin-top:0;margin-bottom:8px">Recent activity</h3>
        <div style="max-height:360px;overflow:auto">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="text-align:left;color:#555">
                <th style="padding:6px 8px;border-bottom:1px solid #f0f4f8">When</th>
                <th style="padding:6px 8px;border-bottom:1px solid #f0f4f8">Document</th>
                <th style="padding:6px 8px;border-bottom:1px solid #f0f4f8">Version</th>
                <th style="padding:6px 8px;border-bottom:1px solid #f0f4f8">By</th>
                <th style="padding:6px 8px;border-bottom:1px solid #f0f4f8">Status</th>
              </tr>
            </thead>
            <tbody>
              @forelse($recentVersions as $rv)
              <tr>
                <td style="padding:8px 8px;border-bottom:1px solid #fafafa;font-size:13px">
                  {{ $rv->created_at ? $rv->created_at->format('Y-m-d H:i') : '-' }}
                </td>
                <td style="padding:8px 8px;border-bottom:1px solid #fafafa">
                  <a href="{{ route('documents.show', $rv->document->id) }}">{{ $rv->document->doc_code }} — {{ \Illuminate\Support\Str::limit($rv->document->title,60) }}</a>
                </td>
                <td style="padding:8px 8px;border-bottom:1px solid #fafafa">{{ $rv->version_label }}</td>
                <td style="padding:8px 8px;border-bottom:1px solid #fafafa">{{ optional($rv->creator)->name ?? '—' }}</td>
                <td style="padding:8px 8px;border-bottom:1px solid #fafafa">{{ $rv->status }}</td>
              </tr>
              @empty
              <tr><td colspan="5" class="small-muted" style="padding:12px">No recent activity</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Right column: shortcuts / quick actions -->
    <div>
      <div style="background:#fff;border:1px solid #eef3f8;border-radius:10px;padding:12px;margin-bottom:12px;">
        <h3 style="margin:0 0 8px 0">Quick actions</h3>
        <div style="display:flex;flex-direction:column;gap:8px">
          <a class="btn" href="{{ route('documents.create') }}">Upload New Document</a>
          <a class="btn-muted" href="{{ route('documents.index') }}">Browse Documents</a>
          <a class="btn-muted" href="{{ route('approval.index') }}">Approval Queue</a>
        </div>
      </div>

      <div style="background:#fff;border:1px solid #eef3f8;border-radius:10px;padding:12px;">
        <h3 style="margin:0 0 8px 0">Help & Tips</h3>
        <div class="small-muted">
          - Use the Compare button in document pages to see changes.<br>
          - Click department "Open" to view grouped docs.<br>
          - Upload PDF + pasted text for faster indexing.
        </div>
      </div>
    </div>
  </div>
</div>

<!-- tiny CSS for cards/buttons (blue theme) -->
<style>
:root { --brand-blue: #0ea5ff; --muted: #6b7280; }
.card { background:#fff;border:1px solid #eef3f8;border-radius:10px;padding:12px;width:180px; }
.card-title{ color:var(--muted);font-size:13px }
.card-value{ font-size:20px;font-weight:700;margin-top:6px;color:var(--brand-blue);cursor:default }
.card-note{ font-size:12px;color:#7b8794;margin-top:6px }
.btn{ display:inline-block;padding:8px 10px;border-radius:8px;background:var(--brand-blue);color:#fff;text-decoration:none }
.btn-muted{ display:inline-block;padding:6px 8px;border-radius:8px;background:transparent;color:var(--brand-blue);text-decoration:none;border:1px solid transparent }
.small-muted{ color:#7b8794;font-size:13px }
.table td, .table th{ vertical-align:middle; }

/* clickable value */
.card-value.clickable { cursor:pointer; text-decoration:underline; }
</style>

<!-- pie chart + sparkline scripts -->
<script>
document.addEventListener('DOMContentLoaded', function(){
  // clickable cards
  document.querySelectorAll('.card-value.clickable').forEach(el=>{
    el.addEventListener('click', ()=> {
      const href = el.dataset.href;
      if(href) window.location.href = href;
    });
  });

  // sparkline (blue)
  const sparkEl = document.querySelector('.spark');
  if(sparkEl){
    const labels = JSON.parse(sparkEl.dataset.labels || '[]');
    const values = JSON.parse(sparkEl.dataset.values || '[]');
    const c = document.createElement('canvas');
    c.width = 600; c.height = 80;
    sparkEl.appendChild(c);
    const ctx = c.getContext('2d');
    const max = Math.max(...values,1);
    const padding = 8;
    const w = c.width - padding*2;
    const h = c.height - padding*2;
    const step = values.length > 1 ? w / (values.length - 1) : w;
    ctx.fillStyle = '#f8fafb';
    ctx.fillRect(0,0,c.width,c.height);
    ctx.beginPath();
    ctx.strokeStyle = '#0ea5ff';
    ctx.lineWidth = 2;
    values.forEach((v,i)=>{
      const x = padding + i*step;
      const y = padding + h - (v / max) * h;
      if(i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
    });
    ctx.stroke();
    ctx.lineTo(padding + w, padding + h);
    ctx.lineTo(padding, padding + h);
    ctx.closePath();
    ctx.fillStyle = 'rgba(14,165,255,0.12)';
    ctx.fill();
  }

  // PIE CHART
  // data from blade (PHP) — derive from counts in DOM (more robust)
  const pending = parseInt(@json($pendingCount));
  const approved = parseInt(@json($approvedCount));
  const rejected = parseInt(@json($rejectedCount));
  const other = parseInt(@json($otherCount));

  const pieData = [pending, approved, rejected, other];
  const pieLabels = ['Pending','Approved','Rejected','Other'];
  const colors = ['#0ea5ff','#0b74ff','#ff6b6b','#94a3b8'];

  const canvas = document.getElementById('statusPie');
  if (canvas && canvas.getContext) {
    const ctx = canvas.getContext('2d');
    const total = pieData.reduce((s,v)=>s+v,0) || 1;
    const cx = canvas.width/2;
    const cy = canvas.height/2;
    const radius = Math.min(cx,cy) - 6;
    let start = -Math.PI/2;
    pieData.forEach((val, i) => {
      const slice = (val/total) * Math.PI * 2;
      ctx.beginPath();
      ctx.moveTo(cx,cy);
      ctx.arc(cx,cy, radius, start, start + slice);
      ctx.closePath();
      ctx.fillStyle = colors[i] || '#ddd';
      ctx.fill();
      start += slice;
    });

    // legend
    const legend = document.createElement('div');
    legend.style.marginTop = '6px';
    legend.style.fontSize = '12px';
    legend.style.color = '#556';
    pieLabels.forEach((lab,i)=>{
      const item = document.createElement('div');
      item.style.display='flex';
      item.style.alignItems='center';
      item.style.gap='8px';
      item.style.marginTop='4px';
      const sw = document.createElement('span');
      sw.style.width='12px';
      sw.style.height='12px';
      sw.style.display='inline-block';
      sw.style.background = colors[i];
      sw.style.borderRadius = '2px';
      item.appendChild(sw);
      const txt = document.createElement('span');
      txt.textContent = lab + ' (' + pieData[i] + ')';
      item.appendChild(txt);
      legend.appendChild(item);
    });
    canvas.parentNode.appendChild(legend);
  }
});
</script>
@endsection
