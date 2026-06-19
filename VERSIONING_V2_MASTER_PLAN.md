# BLUEPRINT & MASTER PLAN: DOCUMENT VERSIONING V2
**Project:** Library-ISO — PT Peroni Karya Sentra  
**Date:** June 19, 2026  
**Author:** Antigravity (Advanced Agentic Coding AI)

---

## PHASE 1 — AUDIT CURRENT IMPLEMENTATION & ARCHITECTURE REVIEW

### 1. Diagram Aliran Data (Data Flow Diagram)

Berikut adalah visualisasi aliran data dari pembuatan dokumen hingga proses komparasi di layar pengguna:

```mermaid
graph TD
    A[Document Upload / Revision Web Form] -->|1. Simpan Meta| B(Document Model)
    A -->|2. Upload File & Paste Teks| C(DocumentVersion Model)
    C -->|3. Ekstraksi File Fisik| D{Extraction CLI / pdftotext}
    D -->|4. Update Column| E[(DB: plain_text & pasted_text)]
    
    F[Compare View Dropdowns] -->|5. GET Request with ?v1=X&v2=Y| G[DocumentController@compare]
    G -->|6. Fetch Text & Metadata| E
    G -->|7. Calculate Changes| H[jfcherng/php-diff Engine]
    H -->|8. Generate HTML diff| G
    G -->|9. Pass compact variables| I[Blade Renderer compare.blade.php]
    I -->|10. Custom CSS Highlight| J[User Browser Screen]
```

### 2. Sumber Data, Relasi & Dependensi
* **Sumber Data:** Kolom `plain_text` (Prioritas 1) dan `pasted_text` (Prioritas 2) di tabel `document_versions`.
* **Relasi:**
  * `Document` ─── *hasMany* ───► `DocumentVersion` (Semua histori versi)
  * `Document` ─── *belongsTo* ───► `DocumentVersion` (Menggunakan `current_version_id` untuk versi aktif)
  * `DocumentVersion` ─── *belongsTo* ───► `DocumentVersion` (Menggunakan `prev_version_id` untuk rantai versi sebelumnya)
* **Dependensi:**
  * `jfcherng/php-diff` untuk kalkulasi diff.
  * `smalot/pdfparser` & `phpoffice/phpword` untuk ekstraksi teks dokumen fisik.

### 3. Titik Lemah & Technical Debt (Hutang Teknis)

#### A. Bug Logika Transisi Status `superseded`
Di dalam berkas `app/Models/DocumentVersion.php` baris 113–116, terdapat bug logika kritis:
```php
if ($old && ! in_array($old->status, ['approved','rejected','superseded'], true)) {
    $old->status = 'superseded';
    $old->save();
}
```
* **Analisis Masalah:** Karena status versi aktif lama adalah `'approved'`, maka `in_array('approved', ...)` mengembalikan `true`. Negasinya (`!`) membuat kondisi menjadi `false`. Akibatnya, **versi lama TIDAK PERNAH diubah statusnya menjadi `superseded`** dan tetap tertinggal dengan status `approved`.
* **Solusi Perbaikan:** Ubah array pengecekan agar tidak menyertakan `'approved'`.

#### B. Tidak Ada Model Eloquent untuk `approval_logs`
* **Analisis Masalah:** Riwayat approval disimpan di tabel `approval_logs`, tetapi **tidak memiliki Model Eloquent** (`App\Models\ApprovalLog`). Seluruh operasi penulisan dan pembacaan saat ini menggunakan Query Builder mentah (`DB::table('approval_logs')`).
* **Solusi Perbaikan:** Buat model `ApprovalLog` dan definisikan relasi `approvalLogs()` pada model `DocumentVersion` agar penarikan data audit trail bisa dilakukan secara bersih dan deklaratif.

#### C. Chaining Versi Secara Manual
* **Analisis Masalah:** Pengisian `prev_version_id` sepenuhnya bergantung pada perintah CLI `php artisan documents:build-relations`. Pada upload web harian, kolom ini dibiarkan `NULL`.

---

## PHASE 2 — VERSION TIMELINE DESIGN

Rancangan komponen linimasa revisi untuk disajikan secara visual pada halaman detail dokumen (`documents.show`) dan perbandingan.

### 1. Visualisasi Linimasa (Revision Chain Timeline)

```text
  [ v1: Approved ] ───► [ v2: Superseded ] ───► [ v3: Approved ] ───► [ v4: Draft ]
   Tgl: 01 Jan 2026       Tgl: 10 May 2026        Tgl: 18 Jun 2026      Tgl: 19 Jun 2026
   By : Admin QC          By : Budi QA            By : Budi QA          By : Andi QA
   Note: Baseline         Note: Tambah Kalibrasi  Note: Metode Spectro  Note: Edit Poin 1.2
   Diff: (First Ver)      Diff: +150 / -24        Diff: +88 / -12       Diff: (Pending Approval)
```

### 2. Struktur Data Node & Interaktivitas
Setiap node dalam linimasa akan memuat informasi kontekstual yang diambil langsung dari database:
* **Label & Status:** badge warna untuk `status` (hijau: Approved, kuning: Draft/Submitted, abu-abu: Superseded).
* **Tanggal & Pengunggah:** `created_at` dan nama `creator->name`.
* **Change Note:** String `change_note` (alasan revisi).
* **Statistik Perubahan:** Jumlah penambahan dan pengurangan kata (dihitung dinamis/cached).
* **Aksi Klik:**
  * Mengklik node memicu popover pilihan:
    1. **"Bandingkan dengan versi sebelumnya"** (Redirect ke `/documents/{id}/compare?v1={prev_id}&v2={node_id}`).
    2. **"Bandingkan dengan versi aktif saat ini"** (Redirect ke `/documents/{id}/compare?v1={current_active_id}&v2={node_id}`).

### 3. Analisis Kesenjangan Data (Gap Analysis)
* **Data Tersedia:** `version_label`, `status`, `created_at`, `created_by`, `change_note`.
* **Gap Teridentifikasi:**
  1. Relasi `prev_version_id` yang kosong pada runtime membuat pembentukan rantai versi di timeline gagal jika tidak dijalankan rebuild CLI terlebih dahulu.
  2. Nilai statistik jumlah perubahan (`diff_summary` / total added/removed) belum disimpan secara persisten di database saat persetujuan versi baru, sehingga memicu beban kalkulasi diff berulang kali setiap halaman dimuat.

---

## PHASE 3 — VERSION CHAIN HARDENING

Solusi penguncian integritas hubungan rantai versi secara otomatis dan real-time:

### 1. Mengapa `prev_version_id` Kosong pada Runtime?
Sebab kode pada controller (`DocumentController` & `DocumentVersionController`) saat mengeksekusi `DocumentVersion::create([...])` tidak mencari versi sebelumnya untuk dihubungkan. Kolom ini hanya terisi saat CLI artisan `documents:build-relations` dijalankan.

### 2. Risiko Jika Admin Lupa Menjalankan Perintah CLI
* Linimasa dokumen tidak akan menampilkan urutan revisi yang benar (hanya menampilkan node tunggal atau rantai yang terputus).
* Tombol "Bandingkan dengan versi sebelumnya" pada antarmuka tidak dapat berfungsi secara otomatis karena sistem tidak mengetahui ID versi pendahulu langsungnya.

### 3. Solusi Otomatisasi (Laravel Model Observer)
Kami akan membuat `DocumentVersionObserver` untuk mengotomatisasi pengisian linked-list ini seketika saat data baru disimpan.

#### Rancangan Detil Kode Observer:
```php
namespace App\Observers;

use App\Models\DocumentVersion;

class DocumentVersionObserver
{
    /**
     * Dijalankan otomatis sebelum versi dokumen disimpan ke database.
     */
    public function creating(DocumentVersion $version)
    {
        // Cari versi terakhir dari dokumen yang sama berdasarkan ID terbesar
        $latest = DocumentVersion::where('document_id', $version->document_id)
            ->orderByDesc('id')
            ->first();

        if ($latest) {
            // Tautkan versi baru ke versi terakhir yang sudah ada
            $version->prev_version_id = $latest->id;
        }
    }
}
```

---

## PHASE 4 — EXECUTIVE CHANGE SUMMARY ENGINE (NON-AI)

Sistem akan membedah HTML output dari `jfcherng/php-diff` secara deterministik dan menyajikannya dalam format ringkasan eksekutif.

### 1. Logika Perhitungan Statistik (Added / Removed / Modified)
Diff engine mengembalikan string HTML yang dibubuhi tag `<ins>` dan `<del>`. Kita dapat mengkalkulasi statistik ini di server-side (PHP) sebelum dikirim ke view:
* **Added (Penambahan):** Dihitung berdasarkan frekuensi kemunculan tag `<ins>` di dalam teks ter-diff.
* **Removed (Penghapusan):** Dihitung berdasarkan frekuensi kemunculan tag `<del>` di dalam teks ter-diff.
* **Modified (Modifikasi):** Apabila dalam satu baris komparasi terdapat perubahan karakter spesifik, kita menghitung baris yang memiliki pasangan `<del>` dan `<ins>` secara bersamaan.

### 2. Algoritma Pembuatan Executive Summary secara Deterministik (Tanpa AI)
Kami akan menulis parser string PHP di controller untuk mengekstrak potongan kalimat yang berubah:
```php
public function extractExecutiveSummary(string $diffHtml): array
{
    $addedPhrases = [];
    $removedPhrases = [];

    // Ekstraksi teks di dalam tag <ins>
    preg_match_all('/<ins>(.*?)<\/ins>/s', $diffHtml, $insMatches);
    foreach ($insMatches[1] as $phrase) {
        $phrase = trim(strip_tags($phrase));
        if (strlen($phrase) > 5) { // Filter out tanda baca / spasi kosong
            $addedPhrases[] = Str::limit($phrase, 60);
        }
    }

    // Ekstraksi teks di dalam tag <del>
    preg_match_all('/<del>(.*?)<\/del>/s', $diffHtml, $delMatches);
    foreach ($delMatches[1] as $phrase) {
        $phrase = trim(strip_tags($phrase));
        if (strlen($phrase) > 5) {
            $removedPhrases[] = Str::limit($phrase, 60);
        }
    }

    return [
        'added' => array_slice(array_unique($addedPhrases), 0, 5), // Ambil 5 teratas
        'removed' => array_slice(array_unique($removedPhrases), 0, 5),
    ];
}
```
* **Kelebihan:** 100% aman dari hallucination, sangat cepat (kalkulasi mikrodetik), dan bebas biaya API eksternal.

---

## PHASE 5 — MODERN COMPARE UI DESIGN

Evaluasi antarmuka perbandingan versi saat ini dan usulan rancangan baru:

### 1. Penilaian Kualitas UI Saat Ini (Skor 1 - 10)
* **Readability:** **5 / 10** (Font monospace di dalam block `<pre>` terasa sempit dan melelahkan dibaca).
* **Audit Usability:** **4 / 10** (Tidak menunjukkan bukti formal persetujuan versi di layar yang sama).
* **Management Usability:** **3 / 10** (Tidak memiliki summary ringkas untuk Direktur/MR).
* **Mobile Usability:** **2 / 10** (Tidak responsif, pre-tag meluber ke samping).

---

### 2. Rencana Desain Antarmuka Baru (Wireframe Compare V2)

```text
+-----------------------------------------------------------------------------------------+
| [Back]   IK.GUD-BHN.01 - PROSEDUR GUDANG BAHAN BAKU                       [Export PDF]  |
+-----------------------------------------------------------------------------------------+
|                                                                                         |
|  REVISION TIMELINE                                                                      |
|  (v1) Approved ───► (v2) Superseded ───► [v3] Approved (Selected)                       |
|  03 Dec 2025        10 May 2026          19 Jun 2026                                    |
|                                                                                         |
+-----------------------------------------------------------------------------------------+
|  METADATA COMPARISON                                                                    |
|  +-------------------------------------+ +--------------------------------------------+ |
|  | BASE VERSION: v1                    | | TARGET VERSION: v3                         | |
|  | Status   : Approved                 | | Status   : Approved                        | |
|  | Date     : 03 Dec 2025              | | Date     : 19 Jun 2026                     | |
|  | Approver : Direktur                 | | Approver : Direktur                        | |
|  | Note     : Baseline upload          | | Note     : Metode Spectro & Radiasi        | |
|  +-------------------------------------+ +--------------------------------------------+ |
|                                                                                         |
|  CHANGE STATISTICS & EXECUTIVE SUMMARY                                                  |
|  +-------------------------------------+ +--------------------------------------------+ |
|  | STATISTICS                          | | EXECUTIVE SUMMARY                          | |
|  |                                     | | Added:                                     | |
|  | Added Words   : [ 15 ] (Green)      | | * "... metode radiasi Spectro ..."         | |
|  | Removed Words : [ 4  ] (Red)        | | * "... pemeriksaan visual material ..."    | |
|  | Modified Lines: [ 3  ] (Blue)       | | Removed:                                   | |
|  |                                     | | * "... manual stempel laser ..."           | |
|  +-------------------------------------+ +--------------------------------------------+ |
|                                                                                         |
|  DIFF VIEWER (Unified View)                                                             |
|  +------------------------------------------------------------------------------------+ |
|  | Line 12: Staff bahan baku akan melakukan pemeriksaan bahan baku dari efek          | |
|  | [del] manual stempel laser [-] [ins] radiasi Spectro dan pengecekan visual [ins].  | |
|  +------------------------------------------------------------------------------------+ |
+-----------------------------------------------------------------------------------------+
```

---

## PHASE 6 — AUDIT TRAIL INTEGRATION

Untuk memenuhi standar kepatuhan ISO 9001:2015, halaman compare wajib menampilkan **Audit Trail 3-Pihak** (Pembuat, Pemeriksa, Penyetuju) secara terpadu.

### Jalur Penarikan Data Audit:
1. **Created By (Pembuat):**
   * Diambil dari kolom `created_by` pada tabel `document_versions` (relasi `creator` ke tabel `users`).
2. **Reviewed By (Pemeriksa - MR):**
   * Diambil dari tabel `approval_logs` dengan kueri:
     `approval_logs.document_version_id = ? AND approval_logs.role = 'MR' AND approval_logs.action = 'approve'`.
   * Menampilkan nama user dan tanggal persetujuan MR.
3. **Approved By (Penyetuju - Direktur):**
   * Diambil dari kolom `approved_by` pada model `DocumentVersion` (atau dari `approval_logs` dengan `role = 'DIRECTOR'`).
   * Menampilkan nama Direktur dan tanggal persetujuan akhir.

Informasi ini akan ditampilkan dalam bentuk panel tabel **Approval Signatures** di bawah metadata komparasi.

---

## PHASE 7 — IMPLEMENTATION ROADMAP (PHASE B)

Berikut adalah rencana rilis bertahap untuk modernisasi fitur versioning:

### PHASE B1: Version Chain Hardening
* **Tingkat Risiko:** **Rendah** (Low)
* **Estimasi Effort:** **Low** (1 hari)
* **File yang Diubah:** `app/Models/DocumentVersion.php`, pembuatan `app/Observers/DocumentVersionObserver.php`, `app/Providers/AppServiceProvider.php`.
* **Migration Baru:** Tidak diperlukan.

### PHASE B2: Timeline Component
* **Tingkat Risiko:** **Rendah** (Low)
* **Estimasi Effort:** **Medium** (2 hari)
* **File yang Diubah:** `app/Http/Controllers/DocumentController.php` (menambahkan logika linked-list traversal), `resources/views/documents/show.blade.php`.
* **Migration Baru:** Tidak diperlukan.

### PHASE B3: Executive Summary Engine
* **Tingkat Risiko:** **Rendah** (Low)
* **Estimasi Effort:** **Low** (1 hari)
* **File yang Diubah:** `app/Http/Controllers/DocumentController.php` (penulisan parser regex diff).
* **Migration Baru:** Tidak diperlukan.

### PHASE B4: Modern Compare UI
* **Tingkat Risiko:** **Sedang** (Medium - karena perombakan tampilan CSS/Blade)
* **Estimasi Effort:** **Medium** (3 hari)
* **File yang Diubah:** `resources/views/documents/compare.blade.php`, `public/css/app.css` (atau file CSS kustom).
* **Migration Baru:** Tidak diperlukan.

### PHASE B5: Audit Trail Visualization
* **Tingkat Risiko:** **Rendah** (Low)
* **Estimasi Effort:** **Low** (1 hari)
* **File yang Diubah:** Pembuatan model `App\Models\ApprovalLog.php`, update controller compare untuk memuat data log, penambahan tabel tanda tangan di view compare.
* **Migration Baru:** Tidak diperlukan (tabel `approval_logs` sudah ada di database).
