# SPRINT S2A — VERSION ACCESS SECURITY AUDIT
**Halaman Sasaran:** `/documents/{id}` (Detail Dokumen)  
**Tujuan Audit:** Mengevaluasi kerentanan keamanan bypass otorisasi pada parameter query URL `?version_id=` yang dapat digunakan oleh pengguna biasa (Viewer) untuk mengakses dokumen berstatus Draft, Submitted, Rejected, atau Superseded.

---

## TASK 1: TRACE VERSION ACCESS FLOW (ALUR AKSES VERSI)

Alur pemrosesan parameter query `version_id` pada halaman detail dokumen didefinisikan sebagai berikut:

1. **Permintaan Pengguna (Request):** Pengguna mengirimkan permintaan GET ke `/documents/{id}?version_id=XXX`.
2. **Controller Extraction (app/Http/Controllers/DocumentController.php):**
   ```php
   // Baris 945-946: Parameter version_id ditangkap dan di-cast menjadi integer
   $requestedVersionId = null;
   try { $requestedVersionId = request()->query('version_id') ? (int) request()->query('version_id') : null; } catch (\Throwable) { $requestedVersionId = null; }
   ```
3. **Database Query (Eloquent):**
   ```php
   // Baris 950-952: Query memanggil relasi versions() berdasarkan ID yang diminta
   if ($requestedVersionId) {
       $version = $document->versions()->where('id', $requestedVersionId)->first();
   }
   ```
4. **Blade Render (resources/views/documents/show.blade.php):**
   ```php
   // Baris 16-17: Menetapkan $currentVersion dari $version hasil pencarian controller
   $currentVersion = $version ?? ($document->currentVersion ?? null);
   ```
   Data dari versi ini (termasuk draf/rejected) langsung ditampilkan pada layar detail:
   ```html
   <!-- Baris 457: Teks dokumen dari versi terpilih dirender bebas ke HTML -->
   <div style="white-space:pre-wrap; ...">{!! nl2br(e($cleanText)) !!}</div>
   ```

---

## TASK 2: AUTHORIZATION AUDIT (AUDIT OTORISASI)

Audit terhadap file kontroler, middleware, dan model membuktikan ketiadaan lapisan otorisasi (security validation) untuk parameter `version_id`:

| Protection Layer | Ada? | Lokasi File / Deskripsi |
| :--- | :---: | :--- |
| **Gate Validation** | **No** | Tidak ada pemanggilan `Gate::allows` atau `Gate::authorize`. |
| **Policy Validation** | **No** | Tidak ada Policy (misalnya `DocumentPolicy`) yang membatasi pemuatan versi berdasarkan peranan. |
| **Middleware Security** | **Partial** | Menggunakan middleware `auth` global di `routes/web.php` (mencegah guest), tetapi membiarkan seluruh user terotentikasi mengakses parameter query tanpa filter peran. |
| **Role Check** | **No** | Tidak ada pengecekan peran (Role Check) sebelum mengambil data versi dari database. |
| **Permission Check** | **No** | Tidak ada pengecekan izin (Permission Check) terhadap data versi. |
| **Ownership Check** | **No** | Tidak ada validasi kepemilikan (memastikan pembuat draf adalah pengakses versi). |

---

## TASK 3: STATUS VISIBILITY AUDIT (AUDIT VISIBILITAS STATUS)

Karena tidak adanya lapisan otorisasi, berikut adalah visibilitas status versi dokumen melalui parameter `?version_id=`:

* **Approved (Aktif):** **Accessible** (Diizinkan - perilaku normal).
* **Superseded (Usang):** **Accessible** (Dapat diakses. Muncul banner "OBSOLETE VERSION", tetapi teks konten dan tombol unduh master tetap aktif).
* **Draft (Draf Baru):** **Accessible** (Dapat diakses sepenuhnya tanpa batasan/banner).
* **Submitted (Menunggu Persetujuan):** **Accessible** (Dapat diakses sepenuhnya).
* **Rejected (Ditolak):** **Accessible** (Dapat diakses sepenuhnya beserta catatan penolakan).

### Bukti Query SQL Hasil Kompilasi:
Pencarian menggunakan `$document->versions()->where('id', $requestedVersionId)->first()` yang menghasilkan SQL:
```sql
SELECT * FROM `document_versions` 
WHERE `document_versions`.`document_id` = ? 
AND `id` = ? 
LIMIT 1;
```
**Analisis Keamanan:** Query di atas murni hanya memvalidasi apakah versi tersebut milik dokumen terkait. Query **tidak menyaring kolom `status`**, sehingga record dengan status draf/rejected akan dikembalikan oleh database dan ditampilkan ke browser.

---

## TASK 4: DATABASE SIMULATION (SIMULASI DATA RIIL)

Simulasi dilakukan menggunakan model database riil pada dokumen `UT.ACC&FIN.01` (ID Dokumen: 1) dengan membuat tiga record versi buatan (DRAFT, REJECTED, SUPERSEDED) secara transaksional untuk menguji perilaku kontroler:

1. **Simulasi Akses DRAFT (ID Versi: 683):**
   * **Hasil:** **[FOUND]** Status: `draft`, Label: `v99_draft`.
   * **Data Terekspos:** Konten teks *"SECRET DRAFT CONTENT"* tampil utuh pada workspace.
2. **Simulasi Akses REJECTED (ID Versi: 684):**
   * **Hasil:** **[FOUND]** Status: `rejected`, Label: `v99_rejected`.
   * **Data Terekspos:** Konten teks *"REJECTED CONTENT"* dan alasan penolakan (*"Bad quality"*) tampil utuh.
3. **Simulasi Akses SUPERSEDED (ID Versi: 685):**
   * **Hasil:** **[FOUND]** Status: `superseded`, Label: `v99_superseded`.
   * **Data Terekspos:** Konten teks *"OLD OBSOLETE CONTENT"* tampil utuh dengan banner obsolete di atasnya.

*(Seluruh transaksi simulasi di-rollback otomatis setelah pengujian selesai untuk menjaga integritas database).*

---

## TASK 5: MATRIKS RISIKO KEBOCORAN DATA (INFORMATION DISCLOSURE)

| Elemen Data | Tingkat Kebocoran | Konsekuensi Keamanan & Bisnis |
| :--- | :---: | :--- |
| **plain_text / pasted_text** | **Tinggi (High)** | Isi dokumen rahasia, SOP baru yang belum tervalidasi, atau draf kebijakan perusahaan terekspos utuh. |
| **Attachment File / Master** | **Tinggi (High)** | File PDF/Word asli dapat diunduh langsung lewat tombol *"Download Master"* / *"Download File"* (menggunakan ID versi draf). |
| **Approval / Rejection Notes** | **Sedang (Medium)** | Catatan internal penolakan dokumen dan perdebatan revisi dapat dibaca oleh staf biasa. |
| **Signature Data & Checksum** | **Sedang (Medium)** | Tanda tangan digital terverifikasi dan hash sha256 dapat disalin sebelum dokumen resmi diterbitkan. |

---

## TASK 6: AUDIT PERAN PENGGUNA (USER ROLES AUDIT)

Sistem Library-ISO memiliki lima peran terdaftar (`admin`, `mr`, `kabag`, `viewer`, `director`). Berikut matriks kewenangan akses versi yang seharusnya diterapkan secara bisnis:

| Status Versi | Viewer (User Biasa) | Kabag (Dept Head) | MR (Quality Rep) | Director / Admin |
| :--- | :---: | :---: | :---: | :---: |
| **Approved** | **Boleh** | **Boleh** | **Boleh** | **Boleh** |
| **Superseded** | **Boleh** *(Dengan Banner)* | **Boleh** | **Boleh** | **Boleh** |
| **Draft** | **TIDAK** | **Boleh** *(Hanya milik Departemen)* | **TIDAK** | **Boleh** |
| **Submitted** | **TIDAK** | **Boleh** *(Hanya milik Departemen)* | **Boleh** | **Boleh** |
| **Rejected** | **TIDAK** | **Boleh** *(Hanya milik Departemen)* | **TIDAK** | **Boleh** |

---

## TASK 7: EXPLOITABILITY SCORE (SKOR KERENTANAN)

| Area Kerentanan | Skor (1-10) | Analisis Kerentanan |
| :--- | :---: | :--- |
| **Confidentiality Risk** | **9/10** | Tinggi. Rahasia operasional, draf kebijakan, dan dokumen gagal audit (rejected) terekspos. |
| **Integrity Risk** | **2/10** | Rendah. Eksploitasi bersifat *read-only* (tidak dapat mengubah data secara langsung lewat parameter query ini). |
| **Audit Risk** | **9/10** | Tinggi. Merupakan temuan mayor (Major Non-Conformance) kendali dokumen ISO 9001:2015. |
| **Ease of Exploitation**| **10/10** | Sangat Mudah. Hanya perlu mengganti angka pada URL browser (`?version_id=XX`). |

### KLASIFIKASI KERENTANAN:
# **CRITICAL**
*Alasan:* Celah ini sangat mudah dieksploitasi (Ease of Exploitation: 10/10) tanpa alat khusus, namun memiliki dampak kebocoran kerahasiaan informasi terdokumentasi (Confidentiality) dan risiko kegagalan audit ISO (Audit Risk) yang sangat tinggi.
