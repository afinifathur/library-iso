# UI ARCHITECTURE & LAYOUT PROPOSAL: PHASE B2 REFINED
**Project:** Library-ISO — PT Peroni Karya Sentra  
**Date:** June 19, 2026  
**Auditor:** Antigravity (Advanced Agentic Coding AI)

---

## 1. CURRENT UI PROBLEMS

Berdasarkan audit halaman `documents.show` dan `documents.compare` saat ini, ditemukan masalah kritis sebagai berikut:
1. **Pencampuran Konteks (Context Mixing):** Area riwayat versi di `documents.show` memuat baris draf (`draft`, `rejected`) di samping versi resmi (`approved`, `superseded`). Hal ini membingungkan auditor eksternal karena draf kerja disajikan setara dengan dokumen yang sah.
2. **Ketiadaan Visual Timeline:** Hubungan rantai versi hanya disajikan berupa daftar tabel baris demi baris, tidak ada visualisasi linimasa kronologis (`v1 ──► v2`) untuk melihat suksesi dokumen secara cepat.
3. **Posisian Fitur Compare yang Tidak Terpusat:** Akses ke halaman compare tersebar di beberapa tombol yang kurang intuitif, menyulitkan MR/Direktur membandingkan draf baru langsung dari halaman detail dokumen.
4. **Audit Trail Terserak:** Riwayat pengajuan, penolakan, dan persetujuan (approval logs) terpisah dan tidak terintegrasi ke dalam layar detail dokumen utama, mengharuskan auditor bolak-balik antar menu.

---

## 2. OFFICIAL TIMELINE DESIGN (BAGIAN A)

* **Filter Query Database:**
  Hanya memuat versi yang berstatus `approved` atau `superseded`.
  `$officialVersions = $document->versions()->whereIn('status', ['approved', 'superseded'])->orderBy('id', 'asc')->get();`
* **Elemen Visual Per Simpul (Node):**
  * **Version Label:** Label versi resmi (misal: `v1`, `v2`, `v3`).
  * **Release Date:** Tanggal persetujuan/rilis (`approved_at` / `created_at` terformat).
  * **Approved By:** Nama Direktur yang menyetujui (`approved_by` -> relation `approver->name`).
  * **Change Note:** Penjelasan singkat alasan perubahan (`change_note`).
  * **Status Badge:** Badge berwarna (Hijau Terang untuk `approved`/aktif saat ini, Abu-abu Pastel untuk `superseded`/versi lama).
* **Interaktivitas:**
  * Di antara simpul linimasa, dipasang ikon konektor interaktif `[Compare]` (misal antara `v2` dan `v3`) yang mengarah ke tautan:
    `route('documents.compare', [$doc->id, 'v1' => $v2->id, 'v2' => $v3->id])`

---

## 3. REVISION WORKSPACE DESIGN (BAGIAN B)

Revision Workspace diposisikan sebagai kartu informasi mandiri di bagian atas halaman detail dokumen, **hanya jika** terdeteksi adanya draf atau berkas yang sedang dalam proses persetujuan:
* **Filter Query Database:**
  `$candidate = $document->versions()->whereIn('status', ['draft', 'submitted', 'rejected'])->orderByDesc('id')->first();`
* **Kondisi Tampilan:**
  * **Jika Ada Candidate:** Tampilkan kartu berwarna kuning/biru pastel bertajuk **"Active Revision Candidate"** yang merangkum status draf terkini (misal: `Director Review` atau `Rejected by MR`), nama inisiator, tanggal submit, catatan perubahan draf, serta aksi khusus:
    * `[Compare with Current Active Version]` ──► membandingkan draf ini dengan `current_version_id` yang sedang aktif saat ini.
    * `[Open/Edit Draft]` ──► masuk ke halaman edit draf (untuk Kabag/QA).
    * `[View Approval Logs]` ──► membuka popup log approval draf ini.
  * **Jika Tidak Ada Candidate:** Tampilkan banner abu-abu tipis bertuliskan *"No active revision candidate. This document is fully synchronized."*.

---

## 4. INTERNAL AUDIT TRAIL DESIGN (BAGIAN D)

Dipisahkan secara tegas dari Linimasa Resmi Dokumen. Bagian ini diletakkan pada tab bawah atau panel tersendiri bernama **"Revision Activity Logs"** yang memuat log aktivitas internal:
* **Sumber Data:** Data ditarik dari tabel `approval_logs` yang diurutkan dari waktu terbaru (`created_at` descending).
* **Bentuk Tampilan:** Daftar vertikal (*timeline activity feed*) yang menampilkan:
  * Tanggal & Jam Kejadian.
  * Aksi Aktivitas (misal: *Rejected by Director*, *Submitted by Yudha*, *Draft created*).
  * Catatan Alasan (misal: *"Format penulisan Prosedur Kalibrasi tidak sesuai standar ISO Section 7.1.5"*).

---

## 5. COMPARE STRATEGY PLACEMENT (BAGIAN C)

Untuk mengoptimalkan fungsionalitas, kita merampingkan lokasi compare engine menjadi dua fungsi utama:

1. **Compare sebagai Alat Approval (MR & Director):**
   * *Lokasi:* Di dalam dashboard approval (`approvals.show`).
   * *Fungsi:* Menampilkan perbedaan antara draft pengajuan dengan versi aktif saat ini. Ini **wajib dipertahankan** agar persetujuan dilakukan secara informatif.
2. **Compare sebagai Alat Audit (Auditor & Staff QA):**
   * *Lokasi:* Di halaman detail dokumen (`documents.show`) dalam bentuk tombol perbandingan linimasa resmi (`v1 vs v2`).
3. **Redudansi yang Dihapus/Dipindah:**
   * Menghapus halaman compare global mandiri yang tidak memiliki konteks dokumen induk. Semua komparasi harus diakses di dalam lingkup dokumen tertentu (`/documents/{document}/compare`).

---

## 6. WIREFRAME MOCKUPS (BAGIAN E)

### A. Tampilan Baru `documents.show` (Detail Dokumen)

```text
+-----------------------------------------------------------------------------------------+
|  [Back]   IK.GUD-BHN.01 - PROSEDUR PENERIMAAN BAHAN BAKU                   [Download PDF] |
+-----------------------------------------------------------------------------------------+
|                                                                                         |
|  1. ACTIVE REVISION WORKSPACE (Hanya muncul jika ada Draft/Submitted/Rejected)          |
|  +-----------------------------------------------------------------------------------+  |
|  |  🚧 REVISION CANDIDATE IN PROGRESS (v3)                                           |  |
|  |  Status      : Mr Review (Submitted on 18 Jun 2026 by Yudha QA)                   |  |
|  |  Change Note : Tambah klausul inspeksi visual menggunakan Spectrometer            |  |
|  |                                                                                   |  |
|  |  [Compare with Active (v2)]    [Edit Draft Document]    [View Workflow Log]       |  |
|  +-----------------------------------------------------------------------------------+  |
|                                                                                         |
|  2. OFFICIAL VERSION TIMELINE (Hanya Versi Approved & Superseded)                       |
|  +-----------------------------------------------------------------------------------+  |
|  |                                                                                   |  |
|  |  ( v1 Superseded ) ────────[Compare v1 vs v2]────────► [ v2 Approved ] (Active)    |  |
|  |   Released: 01 Jan 2026                                 Released: 10 May 2026     |  |
|  |   By      : Admin                                       By      : Budi QA         |  |
|  |   Appr.By : Direktur                                    Appr.By : Direktur        |  |
|  |   Note    : Initial release                             Note    : Kalibrasi alat  |  |
|  |                                                                                   |  |
|  +-----------------------------------------------------------------------------------+  |
|                                                                                         |
|  3. DOCUMENT PREVIEW (PDF Viewer untuk Versi Aktif saat ini)                            |
|  +-----------------------------------------------------------------------------------+  |
|  |                                                                                   |  |
|  |  [ PDF Viewer Frame / Preview Versi Aktif v2 ]                                    |  |
|  |                                                                                   |  |
|  +-----------------------------------------------------------------------------------+  |
|                                                                                         |
|  4. REVISION ACTIVITY LOGS (Internal Audit Trail - Tab Bottom)                          |
|  +-----------------------------------------------------------------------------------+  |
|  |  • 18 Jun 2026 10:15 - Submitted by Yudha QA (Stage: MR)                          |  |
|  |  • 16 Jun 2026 09:00 - Draft v3 created by Yudha QA                               |  |
|  |  • 12 May 2026 14:00 - Approved by Direktur (v2 Published)                        |  |
|  |  • 10 May 2026 11:00 - Submitted by Budi QA                                       |  |
|  +-----------------------------------------------------------------------------------+  |
+-----------------------------------------------------------------------------------------+
```

---

## 7. BERKAS YANG PERLU DIMODIFIKASI (FILES TO MODIFY)

1. **`app/Http/Controllers/DocumentController.php`:**
   * Modifikasi method `show` untuk membagi collection `$document->versions` menjadi dua variabel: `$officialVersions` (status approved/superseded) dan `$revisionCandidate` (status draft/submitted/rejected).
   * Memuat `approval_logs` yang berhubungan dengan versi dokumen untuk kebutuhan Audit Trail.
2. **`resources/views/documents/show.blade.php`:**
   * Perombakan UI Blade untuk menyajikan layout workspace di bagian atas, linimasa horizontal resmi di tengah, iframe preview di bawah, dan tabel activity log di bagian paling bawah.

---

## 8. ESTIMASI KOMPLEKSITAS & USAHA (COMPLEXITY & EFFORT)

* **Bagian A: Query Segregation (Controller & Model Scope)**
  * *Kompleksitas:* **Low** (Sederhana, memilah data menggunakan filter Eloquent).
  * *Effort:* 0.5 Hari.
* **Bagian B: Official Timeline Component HTML/CSS**
  * *Kompleksitas:* **Medium** (Membuat tampilan node horizontal yang responsif menggunakan CSS Flexbox/Grid).
  * *Effort:* 1.5 Hari.
* **Bagian C: Revision Workspace Widget**
  * *Kompleksitas:* **Low** (Kondisional Blade widget).
  * *Effort:* 0.5 Hari.
* **Bagian D: Activity Log Feed**
  * *Kompleksitas:* **Low** (Iterasi log dari database).
  * *Effort:* 0.5 Hari.

---

## 9. RISK ASSESSMENT & MITIGATION

* **Risiko 1: Dokumen Baru Tanpa Versi Resmi (Baru Upload Draf Pertama Kali)**
  * *Gejala:* Jika dokumen baru dibuat dan belum pernah disetujui, maka `$officialVersions` akan bernilai kosong (tidak ada node timeline), sedangkan `$revisionCandidate` akan terisi draft pertama (`v1`).
  * *Mitigasi:* Pada Blade view, tambahkan penanganan kondisional: jika `$officialVersions` kosong, sembunyikan linimasa dan tampilkan pesan *"No official version released yet. This document is under initial review."* di area preview.
* **Risiko 2: Responsivitas Layar (Mobile Friendliness)**
  * *Gejala:* Timeline horizontal akan meluber (overflow) ke kanan pada layar smartphone.
  * *Mitigasi:* Terapkan class `overflow-x-auto` pada kontainer timeline agar pengguna mobile dapat menggeser (swipe) linimasa secara horizontal dengan mulus.

---

## 10. RECOMMENDATION

Saya merekomendasikan untuk **segera melanjutkan implementasi Phase B2** berdasarkan proposal layout ini. Pemisahan visual ini 100% memecahkan kebingungan auditor tanpa merusak database, menjaga fungsionalitas compare tetap utuh pada fase krusial (approval & audit), dan meningkatkan skor visual kegunaan (*usability*) sistem secara signifikan.
