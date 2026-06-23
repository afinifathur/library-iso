# SEARCH RELEVANCE & RANKING FORENSIC AUDIT
**Halaman Sasaran:** `/documents` (Daftar Dokumen)  
**Tujuan Audit:** Menganalisis cara kerja mesin pencari (search engine) internal Library-ISO saat ini untuk mendeteksi perilaku pencarian, sistem pemeringkatan hasil, serta keterbatasan fungsionalitas pencarian.

---

## TASK 1: QUERY SEARCH AKTUAL (KODE SUMBER)

Fungsionalitas pencarian pada halaman `/documents` ditangani oleh berkas kontroler berikut:

* **Controller:** `App\Http\Controllers\DocumentController`
* **Method:** `index`
* **Query Builder / Eloquent:**

```php
$docs = Document::with(['department', 'currentVersion'])
    ->when($request->filled('department'), fn($q) => $q->where('department_id', $request->input('department')))
    ->when($request->filled('search'), function ($q) use ($request) {
        $s = $request->input('search');
        $q->where(function ($qq) use ($s) {
            $qq->where('doc_code', 'like', "%{$s}%")
               ->orWhere('title', 'like', "%{$s}%")
               ->orWhereHas('versions', fn($qv) => $qv->where('plain_text', 'like', "%{$s}%"));
        });
    })
    ->orderBy('doc_code')
    ->paginate(25)
    ->appends($request->query());
```

### Penjelasan Query Database (SQL yang Dihasilkan):
Jika pengguna melakukan pencarian dengan kata kunci (misalnya: `quality`), Eloquent di atas menghasilkan query SQL sebagai berikut:

```sql
SELECT * FROM `documents` 
WHERE (
    `doc_code` LIKE '%quality%' 
    OR `title` LIKE '%quality%' 
    OR EXISTS (
        SELECT * FROM `document_versions` 
        WHERE `document_versions`.`document_id` = `documents`.`id` 
        AND `plain_text` LIKE '%quality%'
    )
) 
ORDER BY `doc_code` ASC 
LIMIT 25 OFFSET 0;
```

---

## TASK 2: AUDIT KOLOM YANG DICARI

Tabel berikut menunjukkan hasil audit terhadap kolom-kolom database untuk mengetahui apakah kolom tersebut diikutsertakan dalam query pencarian kata kunci saat ini:

| Kolom | Ikut Dicari? | Sumber & Analisis Teknis |
| :--- | :---: | :--- |
| **doc_code** | **Yes** | Diperiksa langsung pada tabel `documents` via `where('doc_code', 'like', "%{$s}%")`. |
| **title** | **Yes** | Diperiksa langsung pada tabel `documents` via `orWhere('title', 'like', "%{$s}%")`. |
| **plain_text** | **Yes** | Diperiksa melalui relasi `versions` pada tabel `document_versions` via `where('plain_text', 'like', "%{$s}%")`. |
| **pasted_text** | **No** | Kolom `pasted_text` di tabel `document_versions` tidak dicari, query hanya menggunakan kolom `plain_text`. Namun, secara fungsional, teks yang di-copy-paste disalin ke kedua kolom ini saat penyimpanan dokumen. |
| **change_note** | **No** | Kolom `change_note` di tabel `document_versions` diabaikan sepenuhnya dalam query pencarian. |
| **version_label** | **No** | Kolom `version_label` di tabel `document_versions` diabaikan dalam pencarian. |
| **department_name**| **No** | Tabel `departments` tidak di-join untuk pencarian teks nama departemen. Filter Department hanya bekerja berdasarkan pencocokan nilai `department_id` numerik. |
| **category_name** | **No** | Kolom kode atau nama kategori pada tabel `categories` diabaikan sepenuhnya dalam pencarian. |

---

## TASK 3: AUDIT RANKING / RELEVANCE

### Apakah hasil pencarian memiliki sistem ranking?
**TIDAK.** Sistem pencarian Library-ISO saat ini **tidak memiliki mekanisme pembobotan (weighting)**, penilaian relevansi (relevance scoring), atau sistem pemeringkatan hasil pencarian.

### Urutan Hasil Pencarian
Semua hasil yang cocok dengan kondisi query SQL `LIKE` akan diurutkan secara **alfabetis/numerik naik (Ascending)** berdasarkan kolom **`doc_code`** (Kode Dokumen).

### Bukti Kode Sumber:
Baris ke-40 pada file `app/Http/Controllers/DocumentController.php` menunjukkan pengurutan eksplisit:
```php
->orderBy('doc_code')
```
Pengurutan ini berada di luar penanganan blok pencarian kata kunci (`when($request->filled('search'), ...)`), yang berarti tidak peduli di mana atau seberapa sering kata kunci tersebut ditemukan, urutan alfabetis kode dokumen akan selalu memegang kendali penuh atas tampilan hasil.

---

## TASK 4: SIMULASI RELEVANCE TEORITIS

Berikut adalah simulasi teoretis dari hasil pencarian berdasarkan data tiruan di database untuk memvisualisasikan dampak dari ketiadaan sistem ranking:

### Skenario 1: Kata Kunci `quality`
Terdapat 3 dokumen yang cocok di database:
* **Dokumen A:** Kode `IK.QA-01` (Judul: *"Instruksi Kerja Quality Inspection"*) — Kata kunci di judul.
* **Dokumen B:** Kode `SOP.HR-02` (Judul: *"Prosedur Rekrutmen"*, isi dokumen: *"...quality employee..."*) — Kata kunci hanya di isi.
* **Dokumen C:** Kode `FR.QA-02` (Judul: *"Formulir Quality Check"*) — Kata kunci di judul.

**Urutan Hasil yang Tampil:**
1. `FR.QA-02` (Formulir Quality Check)
2. `IK.QA-01` (Instruksi Kerja Quality Inspection)
3. `SOP.HR-02` (Prosedur Rekrutmen)

*Analisis:* `FR.QA-02` muncul di posisi teratas semata-mata karena huruf 'F' secara alfabetis mendahului 'I' dan 'S'. Padahal, Dokumen A (`IK.QA-01`) mungkin lebih relevan bagi pengguna karena memiliki kata kunci di bagian awal judul.

### Skenario 2: Kata Kunci `handheld`
Terdapat 2 dokumen yang cocok di database:
* **Dokumen A:** Kode `UT.MTC-05` (Judul: *"Manual Handheld Multimeter"*) — Kata kunci di judul.
* **Dokumen B:** Kode `IK.PROD-12` (Judul: *"SOP Pengelasan"*, isi dokumen: *"...menggunakan alat handheld..."*) — Kata kunci di isi.

**Urutan Hasil yang Tampil:**
1. `IK.PROD-12` (SOP Pengelasan)
2. `UT.MTC-05` (Manual Handheld Multimeter)

*Analisis:* Dokumen B yang hanya menyebut kata kunci di dalam isi teks diposisikan di atas Dokumen A yang memiliki kata kunci eksplisit di dalam judulnya, hanya karena huruf 'I' (`IK`) mendahului 'U' (`UT`).

### Skenario 3: Kata Kunci `spectro`
Terdapat 2 dokumen yang cocok di database:
* **Dokumen A:** Kode `SOP.QC-01` (Judul: *"SOP Penggunaan Spectrophotometer"*) — Kata kunci di judul.
* **Dokumen B:** Kode `IK.QC-04` (Judul: *"Kalibrasi Alat Spectro"*) — Kata kunci di judul.

**Urutan Hasil yang Tampil:**
1. `IK.QC-04` (Kalibrasi Alat Spectro)
2. `SOP.QC-01` (SOP Penggunaan Spectrophotometer)

*Analisis:* Urutan tetap mengikuti urutan abjad kode dokumen (`IK` sebelum `SOP`), mengabaikan panjang judul atau tingkat kecocokan kata kunci.

---

## TASK 5: KLASIFIKASI MESIN PENCARI SAAT INI

Berdasarkan analisis arsitektur query dan data di atas, mesin pencari saat ini diklasifikasikan sebagai:

### **LEVEL 2 — Full Text Search Basic**
*(Pencarian teks lengkap dasar melalui operator SQL `LIKE` terhadap plain_text)*

### Alasan Teknis:
1. **Pencarian Melampaui Metadata:** Mesin pencarian tidak hanya memeriksa kolom metadata (seperti kode dan judul dokumen), melainkan mampu memindai seluruh isi teks mentah dokumen melalui relasi tabel `document_versions` pada kolom `plain_text`.
2. **Ketiadaan Relevance Scoring:** Pencarian ini tidak masuk ke Level 3 (Weighted Search) atau Level 4 (Knowledge Search Engine) karena tidak menggunakan operator pencarian teks bawaan database yang lebih canggih (seperti `MATCH...AGAINST` di MySQL, TSVector di PostgreSQL, atau tool eksternal seperti Elasticsearch) yang dapat menghitung frekuensi kata atau memberi bobot (weight) khusus ke kolom judul dibanding isi konten.
3. **Pengurutan Statis:** Hasil query diurutkan secara kaku menggunakan urutan alfabetis `doc_code` tanpa memperhitungkan bobot kecocokan kata kunci.
