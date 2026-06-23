# SEARCH ENGINE FORENSIC AUDIT (TECHNICAL REPORT)
**Halaman Sasaran:** `/documents` (Daftar Dokumen)  
**Tujuan Dokumen:** Melakukan analisis forensik terhadap sistem pencarian dokumen Library-ISO saat ini untuk memetakan alur query, cakupan pencarian, performa database, relevansi, serta risiko kepatuhan ISO 9001:2015.

---

## TASK 1: TRACE SEARCH FLOW (ALUR PENCARIAN)

Alur penanganan kata kunci pencarian dari input pengguna hingga tampil di layar didefinisikan sebagai berikut:

```text
User Input (Kata Kunci / Filter)
       │
       ▼
   [Route]       --> GET /documents (routes/web.php)
       │
       ▼
 [Controller]    --> DocumentController@index (app/Http/Controllers/DocumentController.php)
       │
       ▼
   [Query]       --> Document::when($request->filled('search'), ...) menggunakan Eloquent Builder
       │
       ▼
  [Database]     --> Eksekusi query SQL dengan operator LIKE + EXISTS subquery
       │
       ▼
   [Result]      --> Paginated Eloquent Collection (App\Models\Document)
       │
       ▼
   [Blade]       --> Render ke index.blade.php (resources/views/documents/index.blade.php)
```

### Rincian File dan Method:
1. **Route:** File `routes/web.php` mengarahkan URI `/documents` ke kontroler:
   ```php
   Route::get('', [DocumentController::class, 'index'])->name('index');
   ```
2. **Controller & Method:** File `app/Http/Controllers/DocumentController.php` memproses parameter request pada method `public function index(Request $request)`.
3. **Model & Relationship:** File `app/Models/Document.php` mendefinisikan relasi `versions()` (HasMany) yang dipanggil oleh query `whereHas`.
4. **View:** File `resources/views/documents/index.blade.php` merender tabel baris demi baris menggunakan arahan `@forelse($docs as $d)`.

---

## TASK 2: SEARCH SCOPE AUDIT (CAKUPAN PENCARIAN KOLOM)

Berdasarkan analisis kode pada file kontroler `DocumentController.php` baris 32-39, berikut adalah tabel pembuktian kolom yang dicari:

| Kolom | Dicari? | Bukti Kode Sumber |
| :--- | :---: | :--- |
| **documents.doc_code** | **Yes** | `$qq->where('doc_code', 'like', "%{$s}%")` |
| **documents.title** | **Yes** | `->orWhere('title', 'like', "%{$s}%")` |
| **document_versions.plain_text** | **Yes** | `->orWhereHas('versions', fn($qv) => $qv->where('plain_text', 'like', "%{$s}%"))` |
| **document_versions.pasted_text** | **No** | Tidak tercantum di dalam builder `whereHas('versions', ...)`. |
| **document_versions.change_note** | **No** | Tidak tercantum di dalam builder. |
| **document_versions.version_label**| **No** | Tidak tercantum di dalam builder. |
| **departments.name** | **No** | Relasi `department` tidak di-join untuk pencarian teks; input filter departemen hanya mencocokkan `department_id`. |
| **categories.name** | **No** | Relasi `category` / model `Category` tidak dilibatkan dalam query pencarian. |

---

## TASK 3: CURRENT VERSION VS ALL VERSIONS (AUDIT VERSI DOKUMEN)

### Apakah sistem mencari di Semua Versi (All Versions) atau Hanya Versi Aktif (Current Version)?
**A. Semua Versi Dokumen (ALL VERSIONS).**

### Bukti Kode Sumber & Query SQL:
Pencarian teks dokumen menggunakan relasi `versions()` yang merupakan relasi `HasMany` (seluruh riwayat revisi):
```php
// File: app/Models/Document.php
public function versions(): HasMany
{
    return $this->hasMany(DocumentVersion::class);
}
```
Query pembangun pada `DocumentController.php` memanggil relasi ini tanpa filter status (`approved`, `superseded`, dll.):
```php
$qv->where('plain_text', 'like', "%{$s}%")
```
Sehingga menghasilkan query SQL `EXISTS` sebagai berikut:
```sql
SELECT * FROM `documents` 
WHERE EXISTS (
    SELECT * FROM `document_versions` 
    WHERE `document_versions`.`document_id` = `documents`.`id` 
    AND `plain_text` LIKE '%keyword%'
);
```
**Analisis Dampak:** Subquery di atas akan mengembalikan nilai TRUE jika kata kunci ditemukan di **versi mana pun** pada riwayat dokumen tersebut. Dokumen akan lolos filter dan tampil di hasil pencarian, meskipun versi yang aktif (`current_version_id`) sudah diubah dan tidak lagi mengandung kata kunci tersebut.

---

## TASK 4: SEARCH PERFORMANCE AUDIT (ANALISIS KINERJA DATABASE)

### Statistik Database Riil Saat Ini:
* **Jumlah Documents:** 385 dokumen
* **Jumlah Document Versions:** 422 versi
* **Rata-rata Panjang `plain_text`:** 1.782 karakter (~1,7 KB)
* **Dokumen Terbesar:** `MJM.MR.01` (Versi ID: 589, Panjang: 38.044 karakter / ~38 KB)
* **Jumlah Total Karakter `plain_text`:** 719.959 karakter (~720 KB)

### Evaluasi & Estimasi Performa Query `LIKE`:

1. **Kondisi Saat Ini (385 Dokumen, 720 KB Teks):**
   * **Analisis Kinerja:** Sangat aman dan instan (< 2 ms). Seluruh dataset teks berukuran di bawah 1 MB, sehingga pencarian `LIKE` wildcard ganda (`%keyword%`) yang memaksa *full table scan* di RAM tidak memberikan dampak beban CPU yang berarti.
   
2. **Kondisi 1.000 Dokumen (~2,1 MB Teks):**
   * **Analisis Kinerja:** Masih aman (< 10 ms). Meskipun database tidak menggunakan indeks B-Tree (karena pencarian wildcard depan mencegah penggunaan indeks kolumnar), ukuran data yang sangat kecil membuat scan memori berjalan sangat cepat.

3. **Kondisi 5.000 Dokumen (~10-15 MB Teks dengan ~6.000 Versi):**
   * **Analisis Kinerja:** Latensi akan meningkat secara eksponensial (50-150 ms) terutama jika terjadi lalu lintas pencarian konkuren yang tinggi. Melakukan scan teks 15 MB baris demi baris menggunakan regex SQL `LIKE` tanpa index akan memakan utilisasi CPU server secara signifikan. Di tingkat ini, transisi ke Fulltext Indexing MySQL (`MATCH...AGAINST`) atau Search Engine eksternal sangat disarankan.

---

## TASK 5: RELEVANCE RANKING AUDIT (SISTEM RELEVANSI)

### Apakah sistem memiliki sistem ranking relevansi?
**TIDAK.** Tidak ditemukan penggunaan `orderBy` skor relevansi, pencarian `MATCH AGAINST` (MySQL Fulltext), Laravel Scout, Meilisearch, maupun Elasticsearch.

### Bukti Kode Sumber Urutan Hasil:
Query pencarian diakhiri dengan:
```php
->orderBy('doc_code')
```
Urutan ini bersifat statis (alfabetis naik berdasarkan Kode Dokumen). 

### Simulasi Prioritas Penemuan:
Jika pengguna mencari kata kunci `quality`:
* **Kasus A:** Dokumen `SOP.MTC-01` (Judul: *"Prosedur Pemeliharaan"*, teks isi mengandung kata: *"quality"*)
* **Kasus B:** Dokumen `UT.QA-01` (Judul: *"Panduan Standard Quality"*, teks isi: *""*)

**Urutan Tampilan:**
1. `SOP.MTC-01` (Kasus A)
2. `UT.QA-01` (Kasus B)

**Pembuktian:** Dokumen A muncul lebih dahulu karena huruf 'S' (`SOP`) secara alfabetis mendahului 'U' (`UT`), walaupun Dokumen B jauh lebih relevan karena kata kunci tertulis langsung pada judul dokumen.

---

## TASK 6: LEGACY VERSION RISK (RISIKO BISNIS & KEPATUHAN ISO)

Pencarian yang memindai seluruh versi (`versions` HasMany) tanpa memfilter status memicu risiko kepatuhan ISO 9001:2015 yang sangat serius:

1. **Temuan Audit ISO (Klausul 7.5.3 - Pengendalian Informasi Terdokumentasi):**
   * ISO 9001 mewajibkan organisasi mencegah penggunaan dokumen usang secara tidak sengaja.
   * **Risiko Nyata:** Dokumen lama (`superseded`) yang memuat instruksi kerja usang akan memicu dokumen tersebut muncul di hasil pencarian utama, padahal versi terbarunya telah direvisi total dan kata kunci tersebut sudah dihapus. Auditor ISO dapat menetapkannya sebagai temuan Mayor (ketidaksesuaian sistem kendali dokumen).
2. **Kecelakaan Operasional / Kesalahan Prosedur:**
   * Karyawan dapat menemukan dokumen berdasarkan kata kunci yang hanya ada di versi draf, ditolak (`rejected`), atau draf usang. Jika draf tersebut berisi instruksi teknis yang belum divalidasi, implementasi di lapangan dapat menyebabkan cacat produksi atau bahaya keselamatan kerja.
3. **Kebocoran Informasi Draf Internal:**
   * Istilah atau rahasia bisnis yang ditulis pada versi draf yang ditolak/ditunda tetap dapat dicari oleh seluruh pengguna biasa karena status versi tidak disaring dalam pencarian.

---

## TASK 7: SEARCH QUALITY SCORE & KLASIFIKASI

### Penilaian Kualitas Mesin Pencari (Skala 1-10):

| Area | Score 1-10 | Justifikasi Teknis |
| :--- | :---: | :--- |
| **Coverage** | **7/10** | Berhasil memindai kode, judul, dan isi dokumen, namun melewatkan nama departemen dan kategori. |
| **Accuracy** | **3/10** | Rendah, karena mengikutsertakan versi draf, ditolak, dan superseded dalam pencarian. |
| **Relevance** | **1/10** | Tidak memiliki sistem relevansi; diurutkan murni secara alfabetis kode dokumen. |
| **Performance** | **9/10** | Saat ini instan (< 2 ms) karena dataset teks sangat kecil (~720 KB). |
| **Audit Readiness** | **2/10** | Sangat berisiko gagal audit ISO karena ketidakmampuan memilah data versi aktif vs usang. |
| **Scalability** | **4/10** | Akan melambat saat jumlah revisi dan data dokumen meningkat melampaui 5.000 entri. |

### Klasifikasi:
Mesin pencari Library-ISO saat ini berada pada **LEVEL 2 — Basic Full Text Search**.

* **Alasan:** Sistem mampu memindai teks penuh isi dokumen (`plain_text`) melalui pencarian relasi versi, namun masih menggunakan operator SQL dasar (`LIKE %...%`) tanpa pembobotan judul vs isi, serta tidak menerapkan filter status ke versi dokumen yang aktif/sah.
