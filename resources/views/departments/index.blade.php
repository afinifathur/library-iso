@extends('layouts.iso')

@section('title', 'Daftar Departemen')

@section('content')
<div style="max-width:1200px;margin:28px auto;padding:0 16px;box-sizing:border-box;">
  <div style="background:#fff;border-radius:16px;padding:24px;box-shadow:0 8px 24px rgba(20,40,80,0.04);">

    <h2 style="margin:0 0 18px;font-size:1.35rem;font-weight:600;">
        Daftar Departemen
    </h2>

    <div style="overflow:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:860px;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid #eef2f7;">
                    <th style="padding:12px 10px;font-weight:600;color:#0f172a;">Kode</th>
                    <th style="padding:12px 10px;font-weight:600;color:#0f172a;">Nama Departemen</th>
                    <th style="padding:12px 10px;font-weight:600;color:#0f172a;">PIC</th>
                    <th style="padding:12px 10px;width:140px;font-weight:600;color:#0f172a;">Dokumen Aktif</th>
                    <th style="padding:12px 10px;width:140px;font-weight:600;color:#0f172a;">In Progress</th>
                    <th style="padding:12px 10px;width:160px;font-weight:600;color:#0f172a;text-align:right;">Aksi</th>
                </tr>
            </thead>

            <tbody>
                @foreach($departments as $dept)
                    <tr style="border-bottom:1px solid #f4f6fa;">
                        <td style="padding:12px 10px;"><strong>{{ $dept->code }}</strong></td>

                        <td style="padding:12px 10px;">{{ $dept->name }}</td>

                        <td style="padding:12px 10px;">
                            {{ $picList[$dept->code] ?? '-' }}
                        </td>

                        <td style="padding:12px 10px;text-align:center;">
                            <span style="display:inline-block;background:#e8f0ff;color:#2563eb;padding:6px 12px;border-radius:10px;font-weight:600;">
                                {{ $dept->active_count ?? 0 }}
                            </span>
                        </td>

                        <td style="padding:12px 10px;text-align:center;">
                            @if(($dept->pending_count ?? 0) > 0)
                                <a href="{{ url('/approval?dept=' . urlencode($dept->code)) }}"
                                   style="display:inline-block;background:#fff4cc;color:#8a6d00;padding:6px 12px;border-radius:10px;font-weight:600;text-decoration:none;">
                                    {{ $dept->pending_count }}
                                </a>
                            @else
                                <span style="display:inline-block;background:#f3f4f6;color:#9ca3af;padding:6px 12px;border-radius:10px;font-weight:600;">
                                    0
                                </span>
                            @endif
                        </td>

                        <td style="padding:12px 10px;text-align:right;">

                            {{-- TOMBOL BARU: PUTIH, BORDER BIRU, TEKS BIRU, MIRIP NAVBAR --}}
                            <a href="{{ route('departments.show', $dept->id) }}"
   style="
        display:inline-block;
        padding:6px 18px;
        background:#1d4ed8;
        color:#ffffff;
        border-radius:999px;
        font-weight:600;
        text-decoration:none;
        font-size:0.88rem;
        border:1px solid #1d4ed8;
        transition:0.15s;
    "
    onmouseover="this.style.background='#1e40af'"
    onmouseout="this.style.background='#1d4ed8'">
    Buka Dokumen
</a>

                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

  </div>
</div>
@endsection
