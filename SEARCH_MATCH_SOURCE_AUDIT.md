# SEARCH SPRINT S1 — MATCH SOURCE AUDIT
**Halaman Sasaran:** `/documents` (Daftar Dokumen) dan `/documents/{id}` (Detail Dokumen)  
**Tujuan Audit:** Mengevaluasi alur navigasi dari hasil pencarian ke halaman detail, mengidentifikasi versi dokumen yang benar-benar dimuat ke pengguna (Source of Truth), dan memetakan risiko kebocoran informasi draft/obsolete.

---

## TASK 1: ALUR KLIK HASIL PENCARIAN (CLICK PATH)

Ketika pengguna melakukan pencarian di `/documents` dan mengklik judul dokumen dari hasil pencarian, sistem memproses permintaan dengan alur berikut:

```text
[Blade View]      --> Klik pada tautan judul dokumen (index.blade.php)
                       Kode: <a href="{{ route('documents.show', $d->id) }}">{{ $d->title }}</a>
       │
       ▼
   [Route]        --> Cocok dengan GET /documents/{document}
                       Nama Route: documents.show
       │
       ▼
 [Controller]     --> Diproses oleh DocumentController@show(Document $document)
       │
       ▼
[Model Relation]  --> Memuat relasi versi: $document->load(['versions.creator'])
                       Relasi: HasMany pada model App\Models\Document
       │
       ▼
[Render Detail]   --> Merender resources/views/documents/show.blade.php dengan variabel $version
```

### Komponen Teknis Utama:
* **Route Definition (routes/web.php):**
  ```php
  Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
  ```
* **Controller Handler (app/Http/Controllers/DocumentController.php):**
  ```php
  public function show(Document $document) { ... }
  ```
* **Blade Link (resources/views/documents/index.blade.php):**
  ```html
  <a href="{{ route('documents.show', $d->id) }}">{{ $d->title }}</a>
  ```

---

## TASK 2: SOURCE OF TRUTH HALAMAN DETAIL DOKUMEN

Audit logika penentuan versi dokumen yang ditampilkan ke pengguna pada method `DocumentController@show` membuktikan prioritas pemuatan versi sebagai berikut:

### Potongan Kode Sumber Penentu Versi (baris 945-967):
```php
// 1. Prioritas Utama: Parameter Query eksplisit (?version_id=XX)
$requestedVersionId = null;
try { $requestedVersionId = request()->query('version_id') ? (int) request()->query('version_id') : null; } catch (\Throwable) { $requestedVersionId = null; }

$version = null;
if ($requestedVersionId) {
    $version = $document->versions()->where('id', $requestedVersionId)->first();
}

// 2. Prioritas Kedua: Kolom current_version_id pada tabel documents (Approved Version)
if (! $version && isset($document->current_version_id) && $document->current_version_id) {
    $version = $document->versions()->where('id', $document->current_version_id)->first();
}

// 3. Prioritas Ketiga: Versi Approved terbaru secara historis
if (! $version) {
    $version = $document->versions()->where('status', 'approved')->orderByDesc('id')->first();
}

// 4. Fallback Terakhir: Versi terbaru apa saja (termasuk draft/rejected jika tidak ada yang approved)
if (! $version) {
    $version = $document->versions()->orderByDesc('id')->first();
}
```

### Kesimpulan Bukti:
Jika pengguna mengklik tautan biasa dari hasil pencarian (tanpa parameter query `?version_id`), sistem akan memuat versi yang ditunjuk oleh **`current_version_id`** (versi approved yang aktif secara sah). Ini adalah *Source of Truth* visual utama untuk halaman detail.

---

## TASK 3: SIMULASI KASUS NYATA (REV 00 VS REV 01)

### Kondisi Simulasi:
* **Revisi 00 (Obsolete/Superseded):** Memuat teks kata kunci *"handheld"*.
* **Revisi 01 (Current/Approved):** Teks *"handheld"* telah dihapus/direvisi.

### Perilaku Sistem Saat User Mencari Kata Kunci `handheld`:
1. **Hasil Pencarian:** Dokumen tersebut **tetap muncul** di daftar halaman `/documents`. Hal ini karena query pencarian menggunakan `orWhereHas('versions', ...)` yang memindai seluruh riwayat versi tanpa memedulikan status keaktifan versi tersebut.
2. **Saat Judul Diklik:** Tautan mengarah ke `/documents/{id}`. Kontroler memproses method `show()` tanpa adanya query parameter `version_id`.
3. **Versi yang Ditampilkan:** Sistem memuat **Revisi 01** karena `current_version_id` menunjuk pada Revisi 01.

### Pembuktian Masalah (False Positive):
User akan diarahkan ke halaman detail dokumen yang **tidak lagi memuat kata kunci "handheld"**. Hal ini menyebabkan kebingungan operasional (*false positive search result*) karena sistem pencarian menyatakan dokumen tersebut cocok, tetapi saat dibuka informasi tersebut tidak ditemukan pada versi aktif.

---

## TASK 4: AUDIT VISIBILITAS VERSI NON-AKTIF (DRAFT/REJECTED/SUPERSEDED)

### Apakah user biasa bisa melihat versi draft, submitted, rejected, atau superseded melalui jalur pencarian?
**YA.** Terdapat celah bypass visual melalui parameter query URL.

### Langkah Reproduksi Celah Keamanan:
1. Jalankan pencarian kata kunci yang hanya ada pada draf dokumen yang belum diapprove (atau ditolak).
2. Dokumen akan lolos filter pencarian dan muncul di daftar karena kecocokan pada relasi `versions`.
3. Jika pengguna membuka halaman detail dokumen `/documents/{id}` dan secara manual menambahkan parameter query ID versi draf pada URL (misalnya: `/documents/45?version_id=102` di mana `102` adalah ID versi draf), maka kontroler akan mengeksekusi prioritas pertama:
   ```php
   if ($requestedVersionId) {
       $version = $document->versions()->where('id', $requestedVersionId)->first();
   }
   ```
4. **Hasilnya:** Pengguna biasa dapat membaca seluruh isi teks draf atau dokumen yang ditolak (`rejected`) secara bebas tanpa ada batasan otorisasi peran (*role checking*).

---

## TASK 5: VERDICT (PUTUSAN AUDIT)

### **PUTUSAN: VERDICT A** (Secara visual default) berpadu dengan **VERDICT B** (Melalui celah manipulasi parameter)

* **Sifat Pencarian Default (VERDICT A):** Sistem menemukan kecocokan di versi lama/draf, tetapi saat diklik secara default membuka versi aktif saat ini (`current_version_id`).
* **Sifat Pencarian URL Bypass (VERDICT B):** Sistem memungkinkan pengguna biasa membuka versi draf, superseded, atau ditolak secara langsung jika parameter URL dimanipulasi (`?version_id=XX`), karena tiadanya proteksi otorisasi.

### Konsekuensi ISO 9001:2015:

* **Konsekuensi Model A (Inkonsistensi / False Positive):**
  * *Ketidakpatuhan Klausul 7.5.3 (Pengendalian Informasi Terdokumentasi):* Menurunkan kepercayaan sistem QMS sebagai *Single Source of Truth*. Auditor ISO akan mencatat kebingungan pengguna (menemukan hasil pencarian yang isinya tidak ada di dokumen aktif) sebagai kelemahan dalam efektivitas sistem distribusi informasi.
  
* **Konsekuensi Model B (Akses Bebas Dokumen Non-Aktif / Draft):**
  * *Pelanggaran Berat (Major Non-Conformance):* Membiarkan pengguna biasa mengakses dokumen draf atau dokumen yang ditolak tanpa otorisasi melanggar prinsip integritas kendali dokumen. Jika karyawan mengimplementasikan prosedur draf yang belum sah di lantai produksi, hal ini dapat menyebabkan cacat kualitas produk, kegagalan kepatuhan hukum, hingga bahaya keselamatan kerja operasional.
