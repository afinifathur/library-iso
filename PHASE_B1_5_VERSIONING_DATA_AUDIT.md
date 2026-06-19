# LAPORAN AUDIT DATA VERSIONING & DESAIN REVISION CANDIDATE (PHASE B1.5)
**Project:** Library-ISO — PT Peroni Karya Sentra  
**Date of Audit:** June 19, 2026  
**Auditor:** Antigravity (Advanced Agentic Coding AI)

---

## 1. STATISTIK AKTUAL DATABASE (TASK 1)

Berikut adalah statistik riil hasil kueri langsung terhadap seluruh baris di tabel `document_versions` database saat ini:

* **Total Record Versi:** 422 versi
* **approved:** 422 (100.00%)
* **superseded:** 0 (0.00%)
* **draft:** 0 (0.00%)
* **submitted:** 0 (0.00%)
* **rejected:** 0 (0.00%)

> [!IMPORTANT]
> **Temuan Kunci Status Anomali:**
> Fakta bahwa status `superseded` bernilai **0 (0%)** membuktikan secara nyata adanya **Bug Transisi Status** (yang telah diidentifikasi pada Phase B1) di mana sistem gagal menurunkan status versi lama ke `superseded` ketika versi baru disetujui. Akibatnya, semua histori dokumen menumpuk dengan status `approved`.

---

## 2. MULTI-CANDIDATE DETECTION (TASK 2)

Kueri untuk mendeteksi dokumen yang memiliki lebih dari satu versi aktif non-official (status `draft`, `submitted`, `rejected`):
* **Jumlah Dokumen Terdeteksi:** **0 Dokumen**
* **Analisis Data:**
  Saat ini tidak ada draf aktif (`draft`/`submitted`/`rejected`) di database. Hal ini disebabkan database saat ini berada dalam kondisi "Clean/Migrated State" (semua draf histori telah disetujui atau dibersihkan sebelum audit dilakukan).

---

## 3. BUKTI REJECT CHAIN & VERSION GAP (TASK 3 & 4)

* **Reject Chain:** **0 Dokumen** (Tidak ada baris berstatus `rejected` di database saat ini).
* **Version Label Gaps:** **0 Dokumen** (Semua 383 dokumen yang memiliki versi berada dalam kondisi urutan bersih tanpa ada versi rejected yang menyelip di antara versi approved).

### Kesimpulan Kasus Riil:
Meskipun secara teoritis pola *"version gap"* (seperti `v1 approved -> v2 rejected -> v3 approved`) sangat mungkin terjadi pada operasional sehari-hari akibat perilaku penulisan draf di sistem lama, **data riil di database saat ini masih bersih**. Ini adalah momen terbaik bagi kita untuk mengunci sistem sebelum data kotor masuk ke database produksi.

---

## 4. FEASIBILITY REVISION CANDIDATE WORKSPACE (TASK 5)

Kita ingin menguji kelayakan penerapan aturan baru **TANPA** membuat tabel baru (menggunakan tabel `document_versions` yang ada):

### A. Mekanisme Penerapan Aturan (Rules)
* **RULE A (Maksimal 1 Candidate Aktif):**
  Sebelum user membuat revisi baru, controller melakukan asersi:
  `$exists = DocumentVersion::where('document_id', $id)->whereIn('status', ['draft', 'submitted', 'rejected'])->exists();`
  Jika `true`, sistem menolak pembuatan draf baru dan mengarahkan user untuk melanjutkan draf yang sudah ada.
* **RULE B (Update In-Place):**
  Jika draf berstatus `rejected`, user tidak membuat baris versi baru saat mengunggah perbaikan. Tombol "Re-Submit" akan memicu query `UPDATE` pada record candidate yang sama (mengganti plain text, path file, dan mengubah statusnya kembali ke `submitted`).
* **RULE C (Pemberian Label Versi Saat Approve):**
  Saat draft revisi dibuat, ia tidak diberi label `v2` atau `v3` secara permanen. Ia diberi label sementara `'Draft'` atau `'Candidate'`. Label resmi seperti `v2` baru di-assign pada kolom `version_label` saat Direktur mengklik **Approve**.

### B. Analisis Dampak Penerapan Opsi Ini:
* **Dampak ke Controller:** Memerlukan sedikit penyesuaian di `DocumentVersionController@store` (menambahkan pengecekan limit candidate) dan `update` (menulis ulang proses edit draf agar meng-update baris draf yang ditolak, bukan meng-insert baris baru).
* **Dampak ke Compare Engine:** **0% Dampak.** Compare engine tetap bekerja membandingkan dua string teks, baik itu antara dua versi resmi maupun antara versi resmi dengan draf candidate.
* **Dampak ke Approval Workflow:** Alur persetujuan tetap berjalan pada satu baris data yang sama, hanya mengalami perubahan status dari `draft` -> `submitted` -> `rejected` (diperbaiki/di-update) -> `submitted` -> `approved`.
* **Dampak ke `document_versions`:** Struktur tabel tidak perlu diubah sama sekali. Baris data menjadi sangat hemat dan bersih karena tidak ada baris sampah dari draf gagal.

---

## 5. IMPACT ANALYSIS: OPTION A VS OPTION B (TASK 6)

Berikut perbandingan berbasis data riil antara **Option A (Logical Filtering)** dan **Option B (Tabel Baru Change Request)**:

| Kriteria Evaluasi | Option A (Single Table + Logical Filtering) | Option B (Tabel Baru `document_change_requests`) |
| :--- | :--- | :--- |
| **Effort Pengembangan** | **Rendah (Low):** Hanya memodifikasi query select dan update di controller. | **Tinggi (High):** Membuat migrasi tabel baru, menulis ulang model, controller, dan relasi. |
| **Risiko Regresi** | **Sangat Rendah:** Struktur database tetap utuh. Fitur existing aman. | **Sedang-Tinggi:** Berisiko memutus relasi file preview dan logger approval yang ada. |
| **Kebutuhan Migrasi** | **0% (Tidak ada migrasi data).** | **Tinggi:** Harus memisahkan data draf berjalan ke tabel baru. |
| **Kompleksitas Maintenance**| **Rendah:** Satu pintu penyimpanan data versi dokumen. | **Sedang:** Harus mengelola sinkronisasi data antar dua tabel. |
| **Kepatuhan ISO (Compliance)**| **Sempurna:** Filter timeline membuang draf kerja dari mata auditor. | **Sempurna:** Pemisahan fisik dokumen terkendali dengan draf pengantar. |

---

## 6. REKOMENDASI FINAL PRODUCT OWNER

> [!IMPORTANT]
> **Keputusan Produk:** **PILIH OPTION A (Single Table + Strict Logical Filtering).**
> Data audit membuktikan bahwa saat ini database dalam kondisi bersih (0 active candidate, 0 reject chain). Ini berarti kita dapat mengunci aturan "Single Active Candidate" dan "Update In-Place" pada tabel yang sama secara sangat mudah sejak draf pertama diunggah, tanpa perlu menambah kompleksitas tabel baru `document_change_requests`.

### Langkah Konkrit Selanjutnya (Roadmap Phase B2):
1. Terapkan limitasi **Single Active Candidate** pada `DocumentVersionController`.
2. Terapkan update **In-Place** jika user mengajukan perbaikan atas draf yang ditolak (`status = 'rejected'`).
3. Buat UI linimasa **Official Timeline** dengan menyaring hanya status `'approved'` dan `'superseded'`.
4. Sajikan draf berjalan di widget khusus **Revision Candidate Workspace**.
5. Log aktivitas penolakan/pengajuan tetap disimpan di tabel `approval_logs` untuk di-render sebagai **Internal Audit Trail**.
