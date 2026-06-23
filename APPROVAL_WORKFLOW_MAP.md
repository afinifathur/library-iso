# APPROVAL WORKFLOW AUDIT

## TASK 1 — FLOWCHART TEKS (ACTUAL VS IDEAL)

### A. DOKUMEN BARU (BASELINE V1)

#### ALUR AKTUAL (Bypass Approval / Instant Publish)
```
[Create Form] 
     │
     ▼ (Submit: Publish)
[Document & Version Created] (status: 'approved', stage: 'DONE')
     │
     ├─► current_version_id ──► Langsung diisi ID v1
     ├─► revision_number ────► Langsung diset 1
     └─► revision_date ──────► Langsung diset waktu saat ini
     │
     ▼
[Published] ──► Dapat dilihat oleh Viewer / Publik
```

#### ALUR IDEAL (Kepatuhan ISO 9001)
```
[Create Form] 
     │
     ▼ (Submit: Simpan Draf)
[Draft Container] (status: 'draft', stage: 'KABAG')
     │
     ▼ (Aksi: Ajukan Persetujuan)
[Approval Queue] (status: 'submitted', stage: 'KABAG' / 'MR')
     │
     ▼ (Kabag & MR forward ke Director)
[Final Review] (status: 'submitted', stage: 'DIRECTOR')
     │
     ▼ (Director: Approve)
[Document Promoted] (status: 'approved', stage: 'DONE')
     │
     ├─► current_version_id ──► Diarahkan ke ID v1
     ├─► revision_number ────► Diubah dari 0 menjadi 1
     └─► revision_date ──────► Diset waktu disetujui
     │
     ▼
[Published] ──► Dapat dilihat oleh Viewer / Publik
```

---

### B. REVISI DOKUMEN (REPLACE)

#### ALUR AKTUAL (Sudah Mengikuti Workflow)
```
[Replace Form] (Mengisi form revisi pada dokumen aktif)
     │
     ▼
[Draft Container] (status: 'draft', stage: 'KABAG')
     │
     ▼ (Submit ke MR)
[Submitted Queue] (status: 'submitted', stage: 'MR')
     │
     ▼ (MR Forward ke Director)
[Director Queue] (status: 'submitted', stage: 'DIRECTOR')
     │
     ▼ (Director: Approve)
[Promoted & Superseded] (status: 'approved', stage: 'DONE')
     │
     ├─► Versi lama diubah statusnya menjadi 'superseded'
     ├─► current_version_id diisi ID versi baru
     └─► revision_number naik +1 (Bug: Di ApprovalController, langkah ini terlewat)
```

---

## TASK 2 — AUDIT MUTASI FIELD DATABASE
*   **`current_version_id`:** Seharusnya diperbarui **HANYA** ketika Director/Admin menyetujui dokumen secara final. Selama masa draf/antrean, field ini harus menunjuk ke versi aktif lama (atau `null` untuk dokumen baru).
*   **`revision_number`:** Seharusnya bertambah `+1` **HANYA** saat persetujuan final Director/Admin. Untuk dokumen baru v1, nilainya berpindah dari `0` ke `1` saat disetujui.
*   **`revision_date`:** Seharusnya diperbarui dengan timestamp `now()` **HANYA** pada saat persetujuan final Director/Admin.
*   **`version_label`:** Ditentukan otomatis sejak draf dibuat (`v1` atau `vN+1`) dan bersifat *read-only* (tidak boleh dimutasi di tengah alur).

---

## TASK 3 — MATRIKS VISIBILITAS & AKSES HAK UTAMA

| Status Versi | Viewer / Publik | Kabag (Pemilik Dept) | MR (Management Rep) | Director | Admin | Auditor (Eksternal) |
| --- | --- | --- | --- | --- | --- | --- |
| **`draft`** | Tidak / - / - | Ya / Ya (Milik Sendiri) | Ya / Ya (Semua) | Ya / Ya (Semua) | Ya / Ya / - | Tidak / - / - |
| **`submitted`**| Tidak / - / - | Ya / - / Approve (KABAG)| Ya / - / Approve (MR) | Ya / - / Approve (DIR) | Ya / - / Approve (ALL) | Tidak / - / - |
| **`rejected`** | Tidak / - / - | Ya / Ya (Milik Sendiri) | Ya / - / - | Ya / - / - | Ya / Ya / - | Tidak / - / - |
| **`approved`** | Ya / - / - | Ya / - / - | Ya / - / - | Ya / - / - | Ya / - / - | Ya / - / - |
| **`superseded`**| Ya (Banner) / - / -| Ya (Banner) / - / - | Ya (Banner) / - / - | Ya (Banner) / - / - | Ya (Banner) / - / - | Ya (Banner) / - / - |

*Format Sel: Bisa Melihat? / Bisa Edit? / Bisa Approve? (Ya/Tidak)*

---

## TASK 4 — STATUS DOKUMEN BARU V1
Dokumen baru (v1) wajib **B. Masuk Approval Workflow**.

**Alasan:**
1.  **Kepatuhan ISO 9001:2015 Klausul 7.5.3.2:** Menuntut dokumen mutu ditinjau dan disetujui aspek kelayakannya sebelum dirilis ke organisasi.
2.  **Jejak Audit (Auditability):** Menerbitkan versi v1 langsung tanpa log approval menciptakan lubang audit. Auditor mutu akan mempertanyakan keabsahan versi pertama tersebut.
3.  **Konsistensi Sistem:** Menyeragamkan logika kode di database di mana semua versi aktif wajib memiliki log approval resmi di tabel `approval_logs`.
4.  **Kontrol Mutu:** Mencegah staf menerbitkan dokumen baru secara sepihak tanpa sepengetahuan Kabag, MR, dan Director.

---

## TASK 5 — WORKFLOW FINAL MVP (SEDERHANA & PATUH)
1.  **Penyusunan (Draft):** User mengunggah berkas. Status: `draft`, Stage: `KABAG`. Hanya pembuat dan admin yang dapat melihat draf ini.
2.  **Pemeriksaan (Submitted):** User mengirim draf. Status: `submitted`, Stage: `MR` (atau `KABAG` jika perlu verifikasi departemen terlebih dahulu).
3.  **Persetujuan (Approved):** MR memverifikasi keselarasan sistem. Director/Admin menyetujui final. Status berubah menjadi `approved`, Stage: `DONE`.
4.  **Publikasi (Promoted):** Sistem memperbarui `current_version_id`, menaikkan `revision_number`, dan dokumen resmi terbit di index utama.
