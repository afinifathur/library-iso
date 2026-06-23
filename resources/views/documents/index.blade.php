@extends('layouts.iso')

@section('title', 'Daftar Dokumen')

@section('content')
<style>
  /* Clean pagination: toggle mobile/desktop correctly without Tailwind loaded */
  @media (min-width: 640px) {
      .pagination-wrapper nav > div:first-child {
          display: none !important;
      }
  }
  @media (max-width: 639px) {
      .pagination-wrapper nav > div:last-child {
          display: none !important;
      }
  }
  .pagination-wrapper nav p {
      display: none !important;
  }
  .pagination-wrapper nav > div:last-child {
      display: flex !important;
      justify-content: flex-end !important;
  }

  /* Modernized pagination links */
  .pagination-wrapper nav span.relative.z-0 {
      display: inline-flex !important;
      gap: 8px !important;
      box-shadow: none !important;
      background: transparent !important;
      border: none !important;
  }
  .pagination-wrapper nav span.relative.z-0 a,
  .pagination-wrapper nav span.relative.z-0 span[aria-disabled="true"] > span,
  .pagination-wrapper nav span.relative.z-0 span.cursor-default {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      width: 36px !important;
      height: 36px !important;
      padding: 0 !important;
      font-size: 0.85rem !important;
      font-weight: 600 !important;
      border: 1px solid #e2e8f0 !important;
      border-radius: 50% !important;
      background-color: #f8fafc !important;
      color: #64748b !important;
      text-decoration: none !important;
      transition: all 0.15s ease-in-out !important;
      box-sizing: border-box !important;
  }
  .pagination-wrapper nav span.relative.z-0 span[aria-current="page"] > span {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      width: 36px !important;
      height: 36px !important;
      padding: 0 !important;
      font-size: 0.85rem !important;
      font-weight: 700 !important;
      border: 1px solid #1d4ed8 !important;
      border-radius: 50% !important;
      background-color: #1d4ed8 !important;
      color: #ffffff !important;
      box-shadow: 0 4px 12px rgba(29, 78, 216, 0.2) !important;
      box-sizing: border-box !important;
  }
  .pagination-wrapper nav span.relative.z-0 a:hover {
      border-color: #cbd5e1 !important;
      background-color: #e2e8f0 !important;
      color: #0f172a !important;
      transform: translateY(-1px);
  }
  .pagination-wrapper nav span.relative.z-0 span[aria-disabled="true"] > span:not(:has(svg)) {
      border: none !important;
      background: transparent !important;
      color: #94a3b8 !important;
      cursor: default !important;
  }

  /* Modern input and select styles */
  .input-modern {
      width: 100%;
      height: 42px;
      padding: 0 14px;
      border: 1px solid #cbd5e1 !important;
      border-radius: 8px !important;
      font-size: 0.9rem;
      box-sizing: border-box;
      outline: none;
      background-color: #ffffff;
      transition: all 0.15s ease-in-out !important;
  }
  .input-modern:hover {
      border-color: #94a3b8 !important;
  }
  .input-modern:focus {
      border-color: #1d4ed8 !important;
      box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.15);
  }

  .select-modern {
      width: 100%;
      height: 42px;
      padding: 0 40px 0 14px !important;
      border: 1px solid #cbd5e1 !important;
      border-radius: 8px !important;
      font-size: 0.9rem;
      box-sizing: border-box;
      outline: none;
      color: #0f172a;
      background-color: #ffffff;
      cursor: pointer;
      appearance: none !important;
      -webkit-appearance: none !important;
      -moz-appearance: none !important;
      background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
      background-repeat: no-repeat !important;
      background-position: right 14px center !important;
      background-size: 16px !important;
      transition: all 0.15s ease-in-out !important;
  }
  .select-modern:hover {
      border-color: #94a3b8 !important;
      background-color: #f8fafc;
  }
  .select-modern:focus {
      border-color: #1d4ed8 !important;
      box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.15);
      background-color: #ffffff;
  }
</style>

<div style="max-width:1200px;margin:28px auto;padding:0 16px;box-sizing:border-box;">
  <div style="background:#fff;border-radius:16px;padding:24px;box-shadow:0 8px 24px rgba(20,40,80,0.04);">

    {{-- Header Area --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
      <h2 style="margin:0;font-size:1.35rem;font-weight:600;color:#0f172a;">
        Daftar Dokumen
      </h2>
      <div>
        <a class="btn btn-primary" href="{{ route('documents.create') }}" style="display:inline-flex;align-items:center;gap:6px;padding:8px 18px;background:#1d4ed8;color:#ffffff;border-radius:999px;font-weight:600;text-decoration:none;font-size:0.88rem;border:1px solid #1d4ed8;transition:0.15s;" onmouseover="this.style.background='#1e40af'" onmouseout="this.style.background='#1d4ed8'">
          <span class="material-symbols-outlined" style="font-size:18px;">add</span> Tambah Dokumen
        </a>
      </div>
    </div>

    {{-- Filter Form --}}
    <form method="get" action="{{ route('documents.index') }}" style="margin-bottom:20px;padding:16px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;">
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        
        <div style="flex:1;min-width:260px;">
          <input
            type="text"
            name="search"
            placeholder="Cari kode, judul, atau isi dokumen..."
            value="{{ request('search') }}"
            class="input-modern"
          >
        </div>

        <div style="min-width:200px;">
          <select name="department" class="select-modern">
            <option value="">-- Semua Departemen --</option>
            @foreach($departments as $d)
              <option value="{{ $d->code }}" {{ request('department') == $d->code ? 'selected' : '' }}>
                {{ $d->code }} - {{ $d->name }}
              </option>
            @endforeach
          </select>
        </div>

        <div style="display:flex;gap:8px;">
          <button type="submit" style="height:42px;padding:0 24px;background:#1d4ed8;color:#ffffff;border:1px solid #1d4ed8;border-radius:8px;font-weight:600;font-size:0.9rem;cursor:pointer;transition:0.15s;box-sizing:border-box;" onmouseover="this.style.background='#1e40af'" onmouseout="this.style.background='#1d4ed8'">
            Filter
          </button>

          <a href="{{ route('documents.index') }}" style="display:inline-flex;align-items:center;justify-content:center;height:42px;padding:0 24px;background:#ffffff;color:#64748b;border:1px solid #e2e8f0;border-radius:8px;font-weight:600;font-size:0.9rem;text-decoration:none;transition:0.15s;box-sizing:border-box;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#ffffff'">
            Reset
          </a>
        </div>

      </div>
    </form>

    {{-- Documents Table --}}
    <div style="overflow:auto;">
      <table style="width:100%;border-collapse:collapse;min-width:1000px;">
        <thead>
          <tr style="text-align:left;border-bottom:2px solid #edf2f7;background:#f8fafc;">
            <th style="padding:14px 12px;font-weight:600;color:#334155;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;border-top-left-radius:8px;border-bottom-left-radius:8px;">Kode Dokumen</th>
            <th style="padding:14px 12px;font-weight:600;color:#334155;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">Judul</th>
            <th style="padding:14px 12px;font-weight:600;color:#334155;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">Dept</th>
            <th style="padding:14px 12px;font-weight:600;color:#334155;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">Revisi</th>
            <th style="padding:14px 12px;font-weight:600;color:#334155;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">Status Terbaru</th>
            <th style="padding:14px 12px;font-weight:600;color:#334155;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;text-align:right;border-top-right-radius:8px;border-bottom-right-radius:8px;">Aksi</th>
          </tr>
        </thead>

        <tbody>
        @forelse($docs as $d)
          <tr style="border-bottom:1px solid #f1f5f9;transition:background-color 0.15s;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
            
            {{-- Code --}}
            <td style="padding:12px 12px;font-weight:600;color:#0f172a;font-family:Consolas, Monaco, monospace;font-size:0.9rem;vertical-align:middle;">
              {{ $d->doc_code }}
            </td>

            {{-- Title --}}
            <td style="padding:12px 12px;vertical-align:middle;">
              <a href="{{ route('documents.show', $d->id) }}" style="color:#2563eb;text-decoration:none;font-weight:600;font-size:0.95rem;transition:color 0.15s;" onmouseover="this.style.color='#1d4ed8'" onmouseout="this.style.color='#2563eb'">
                {{ $d->title }}
              </a>
            </td>

            {{-- Dept --}}
            <td style="padding:12px 12px;color:#475569;font-size:0.9rem;vertical-align:middle;">
              <span style="background:#f1f5f9;color:#475569;padding:4px 8px;border-radius:6px;font-weight:500;">
                {{ $d->department->code ?? '-' }}
              </span>
            </td>

            {{-- Revision --}}
            <td style="padding:12px 12px;font-size:0.9rem;color:#334155;vertical-align:middle;">
              <span style="font-weight:600;color:#0f172a;">Rev {{ $d->revision_number ?? 0 }}</span>
              <div style="font-size:0.75rem;color:#64748b;margin-top:2px;">
                @php
                  $dt = $d->revision_date ?? null;
                  if (!is_null($dt) && !($dt instanceof \Carbon\Carbon)) {
                      try { $dt = \Illuminate\Support\Carbon::parse($dt); }
                      catch (\Throwable $e) { $dt = null; }
                  }
                @endphp
                {{ $dt ? $dt->format('Y-m-d') : '-' }}
              </div>
            </td>

            {{-- Latest Version --}}
            <td style="padding:12px 12px;font-size:0.9rem;vertical-align:middle;">
              @if($d->currentVersion)
                <div style="display:flex;flex-direction:column;gap:4px;">
                  
                  <span style="font-family:Consolas, monospace;font-weight:700;color:#0f172a;font-size:0.95rem;">
                    {{ $d->currentVersion->version_label }}
                  </span>

                  <div style="font-size:0.8rem;color:#475569;display:flex;align-items:center;gap:6px;font-weight:500;">
                    @php
                      $status = strtolower($d->currentVersion->status);
                      $statusColor = '#1d4ed8'; // default
                      if ($status === 'approved') $statusColor = '#059669';
                      elseif ($status === 'rejected') $statusColor = '#dc2626';
                      
                      $textType = 'no-text';
                      if ($d->currentVersion->pasted_text) $textType = 'Pasted';
                      elseif ($d->currentVersion->plain_text) $textType = 'Indexed';
                    @endphp
                    <span style="color:{{ $statusColor }};font-weight:600;">{{ ucfirst($status) }}</span>
                    <span style="color:#cbd5e1;">•</span>
                    <span style="color:#64748b;">{{ $textType }}</span>
                  </div>

                </div>
              @else
                <span style="color:#94a3b8">-</span>
              @endif
            </td>

            {{-- Actions --}}
            <td style="padding:12px 12px;text-align:right;vertical-align:middle;">
              <a href="{{ route('documents.show', $d->id) }}" style="display:inline-block;padding:6px 18px;background:#1d4ed8;color:#ffffff;border-radius:999px;font-weight:600;text-decoration:none;font-size:0.88rem;border:1px solid #1d4ed8;transition:0.15s;" onmouseover="this.style.background='#1e40af'" onmouseout="this.style.background='#1d4ed8'">
                Detail
              </a>
            </td>

          </tr>
        @empty
          <tr>
            <td colspan="6" style="text-align:center;color:#64748b;padding:32px 12px;font-size:0.95rem;">
              Tidak ada dokumen yang ditemukan.
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    {{-- Pagination Layout --}}
    @if($docs->total() > 0)
      <div class="pagination-wrapper" style="display:flex;justify-content:space-between;align-items:center;margin-top:24px;flex-wrap:wrap;gap:12px;border-top:1px solid #f1f5f9;padding-top:20px;">
        <div style="color:#94a3b8;font-size:0.78rem;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;">
          {{ $docs->total() }} Dokumen &bull; Halaman {{ $docs->currentPage() }} dari {{ $docs->lastPage() }}
        </div>
        <div style="box-sizing:border-box;">
          {{ $docs->links() }}
        </div>
      </div>
    @endif

  </div>
</div>
@endsection
