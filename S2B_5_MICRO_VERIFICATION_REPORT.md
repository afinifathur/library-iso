# S2B_5_MICRO_VERIFICATION_REPORT

## 1. ROUTE COVERAGE CHECK
Melakukan pencarian cepat terhadap seluruh controller untuk menemukan endpoint lain yang memuat atau memproses parameter `version_id` atau model `DocumentVersion` secara langsung tanpa otorisasi.

**Endpoint tambahan yang diidentifikasi dan berhasil diamankan:**
1. **`DraftController@show` (`/drafts/{version}`)**: Sebelumnya tidak membatasi Viewer untuk mengakses data draft/rejected via ID langsung. Sekarang ditambahkan proteksi `canViewVersion()`.
2. **`DocumentVersionController@show` (`/versions/{version}`)**: Sebelumnya membolehkan semua pengguna terotentikasi membuka versi apa saja secara langsung. Sekarang ditambahkan proteksi `canViewVersion()`.
3. **`DocumentController@chooseCompare` (`/documents/{version}/choose-compare`)**: Sekarang diverifikasi menggunakan `canViewVersion()`.

---

## 2. FLASH WARNING VALIDATION
*   **Status:** **PASS**
*   **Perbaikan:** Menambahkan penanganan rendering pesan `session('warning')` pada template layout utama `resources/views/layouts/app.blade.php`, baik di kontainer peringatan HTML biasa maupun di visual popup SweetAlert2 (`Swal.fire`).
*   **Hasil Uji Skenario:** Ketika Viewer mencoba memuat `/documents/{id}?version_id={draft_id}`, Viewer tidak akan bisa melihat draft tersebut dan akan dialihkan ke versi aktif resmi dengan pesan peringatan: *"Anda tidak memiliki hak akses untuk melihat revisi ini. Dialihkan ke versi aktif."*

---

## 3. COMPARE DROPDOWN CLEANUP
*   **Status:** **PASS**
*   **Perbaikan:** Memperbaiki kueri di method `DocumentController@compare` dengan memfilter koleksi `$versions` menggunakan `canViewVersion($user, $version)` sebelum diteruskan ke view.
*   **Hasil Uji Skenario:**
    *   **Viewer:** Hanya melihat opsi versi berstatus `approved` dan `superseded` pada dropdown perbandingan.
    *   **MR/Admin/Director:** Melihat semua versi sesuai dengan kewenangan peran masing-masing.

---

## 4. REGRESSION CHECK
Skenario pengujian pengguna riil berhasil dijalankan dan divalidasi melintasi semua controller (`DocumentController`, `DraftController`, `DocumentVersionController`):

```text
Running canViewVersion checks across controllers:
  [PASS] Viewer -> Approved: expected: true | Doc: true | Draft: true | Ver: true
  [PASS] Viewer -> Superseded: expected: true | Doc: true | Draft: true | Ver: true
  [PASS] Viewer -> Draft (Blocked): expected: false | Doc: false | Draft: false | Ver: false
  [PASS] Viewer -> Submitted (Blocked): expected: false | Doc: false | Draft: false | Ver: false
  [PASS] Viewer -> Rejected (Blocked): expected: false | Doc: false | Draft: false | Ver: false
  [PASS] Creator -> Draft (Allowed): expected: true | Doc: true | Draft: true | Ver: true
  [PASS] Creator -> Submitted (Allowed): expected: true | Doc: true | Draft: true | Ver: true
  [PASS] Creator -> Rejected (Allowed): expected: true | Doc: true | Draft: true | Ver: true
  [PASS] MR -> Submitted (Allowed): expected: true | Doc: true | Draft: true | Ver: true
  [PASS] MR -> Draft (Blocked): expected: false | Doc: false | Draft: false | Ver: false
  [PASS] MR -> Rejected (Blocked): expected: false | Doc: false | Draft: false | Ver: false
  [PASS] Admin -> Draft (Allowed): expected: true | Doc: true | Draft: true | Ver: true
  [PASS] Admin -> Submitted (Allowed): expected: true | Doc: true | Draft: true | Ver: true
  [PASS] Admin -> Rejected (Allowed): expected: true | Doc: true | Draft: true | Ver: true
```

---

## KESIMPULAN
**READY FOR COMMIT AND PUSH**
