@extends('layouts.iso')

@section('title', 'Daftar Departemen')

@section('content')
<div style="max-width:1200px;margin:28px auto;padding:0 16px;box-sizing:border-box;">
  <div style="background:#fff;border-radius:12px;padding:18px;box-shadow:0 8px 20px rgba(20,40,80,0.04);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:12px;">
      <h2 style="margin:0;font-size:1.4rem;display:flex;align-items:center;gap:8px;">📁 Daftar Departemen</h2>
      <div>
        <!-- Optional button area (mis. tambah departemen) -->
        {{-- <a href="{{ route('departments.create') }}" class="btn btn-primary">Tambah Departemen</a> --}}
      </div>
    </div>

    @if($departments->isEmpty())
      <p style="color:#6b7280;margin:0;">Belum ada departemen terdaftar.</p>
    @else
      <div style="overflow:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:720px;">
          <thead>
            <tr style="text-align:left;border-bottom:1px solid #eef2f7;">
              <th style="padding:12px 10px;width:120px;font-weight:600;color:#0f172a;">Kode</th>
              <th style="padding:12px 10px;font-weight:600;color:#0f172a;">Nama Departemen</th>
              <th style="padding:12px 10px;width:260px;font-weight:600;color:#0f172a;">PIC</th>
              <th style="padding:12px 10px;width:140px;font-weight:600;color:#0f172a;">Dokumen Aktif</th>
              <th style="padding:12px 10px;width:160px;font-weight:600;color:#0f172a;">Aksi</th>
            </tr>
          </thead>

          <tbody>
            @foreach($departments as $dept)
              <tr style="border-bottom:1px solid #f5f7fb;">
                <td style="padding:12px 10px;vertical-align:middle;">
                  <strong>{{ $dept->code }}</strong>
                </td>

                <td style="padding:12px 10px;vertical-align:middle;">
                  {{ $dept->name }}
                </td>

                <td style="padding:12px 10px;vertical-align:middle;color:#0f172a;">
                  @if($dept->relationLoaded('manager') && $dept->manager)
                    <div style="font-weight:600;">{{ optional($dept->manager)->name }}</div>
                    <div style="font-size:0.85rem;color:#6b7280;">{{ optional($dept->manager)->email }}</div>
                  @elseif(!empty($dept->pic_name))
                    <div style="font-weight:600;">{{ $dept->pic_name }}</div>
                    @if(!empty($dept->pic_email))
                      <div style="font-size:0.85rem;color:#6b7280;">{{ $dept->pic_email }}</div>
                    @endif
                  @else
                    <span style="color:#9ca3af;">-</span>
                  @endif
                </td>

                <td style="padding:12px 10px;vertical-align:middle;">
                  <a href="{{ route('departments.show', $dept->id) }}" style="text-decoration:none;">
                    <span style="display:inline-block;background:#eef7ff;color:#2563eb;padding:6px 10px;border-radius:8px;font-weight:600;">
                      {{ $dept->active_documents_count ?? 0 }}
                    </span>
                  </a>
                </td>

                <td style="padding:12px 10px;vertical-align:middle;">
                  <a href="{{ route('departments.show', $dept->id) }}" style="display:inline-block;padding:8px 10px;border-radius:8px;border:1px solid #eef2f7;background:#fff;color:#0f172a;text-decoration:none;font-weight:600;">
                    Buka Dokumen
                  </a>
                  {{-- contoh tombol tambahan:
                  <a href="{{ route('departments.edit', $dept->id) }}" style="margin-left:8px;" class="btn btn-sm">Edit</a>
                  --}}
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
@endsection
