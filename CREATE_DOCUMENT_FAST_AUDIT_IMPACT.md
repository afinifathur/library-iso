# FAST IMPACT AUDIT — BASELINE V1 SHOULD FOLLOW APPROVAL WORKFLOW

## TASK 1 — FIND ALL ASSUMPTIONS

| File | Method | Asumsi yang ditemukan | Tingkat Risiko |
| --- | --- | --- | --- |
| `app/Http/Controllers/DocumentController.php` | `handleCreateNew` | Mengasumsikan `$submit === 'publish'` langsung membuat versi `approved` (DONE) dan menyetel `current_version_id` & `revision_number = 1`. | **MEDIUM** |
| `app/Http/Controllers/ApprovalController.php` | `approve` | Mengasumsikan `current_version_id` diset saat Director approve, tetapi tidak memperbarui `revision_number` (karena diasumsikan `revision_number` sudah bernilai `1` sejak create). | **MEDIUM** |
| `app/Http/Controllers/ReviewController.php` | `index` | Mengasumsikan dokumen yang siap direviu wajib memiliki `current_version_id` (`whereNotNull`). | **LOW** |
| `app/Models/DocumentVersion.php` | `approveByDirector` | Mengasumsikan promosi ke `current_version_id` & `revision_date` dilakukan saat disetujui Director, tanpa mengubah `revision_number`. | **MEDIUM** |

---

## TASK 2 — DASHBOARD IMPACT

*   **Dashboard (`DashboardController@index`):**
    *   `totalDocuments` tetap bertambah 1 karena record di tabel `documents` dibuat langsung saat submit.
    *   `pendingCount` (jumlah draft) akan **bertambah** karena baseline v1 yang baru dibuat akan berstatus `draft`.
    *   `approvedCount` **tidak akan bertambah** sampai dokumen baru tersebut disetujui (approve) oleh Director.
*   **Documents Index (`documents/index.blade.php`):**
    *   Dokumen baru berstatus draft akan muncul di tabel utama (karena query memuat semua dokumen dan relasi `currentVersion` menggunakan `latestOfMany()` yang menarik versi draft). 
    *   Viewer yang mengklik tombol detail akan terkena blokir 403, yang dapat membingungkan jika dokumen draft ditampilkan ke semua user di index utama.

---

## TASK 3 — CURRENT VERSION IMPACT

**Apakah sistem akan error jika dokumen baru belum memiliki `current_version_id` sampai Director approve?**
*   **Tidak (No).**
*   Sistem telah diimplementasikan dengan fallback yang aman. Jika `current_version_id` bernilai `null`, relasi `currentVersion()` (`latestOfMany()`) akan secara otomatis mengambil versi draft terbaru yang tersedia. Di level blade view (`show.blade.php`), data versi tetap dapat dirender dengan aman berkat pengecekan null-safe (`optional($currentVersion)`).

---

## TASK 4 — DOCUMENT DETAIL IMPACT

Jika dokumen baru berstatus `draft` dibuka detailnya:
*   **Apakah halaman masih bisa tampil?** Ya, halaman tampil normal bagi user yang berwenang (Creator, Admin, Director, Kabag, MR).
*   **Apakah fallback version masih bekerja?** Ya, sistem jatuh ke fallback `versions()->orderByDesc('id')->first()` yang mengembalikan versi draft v1 tersebut.
*   **Apakah akan muncul error?** Tidak. Tidak ada error 500. Bagi Viewer biasa (tidak berwenang), sistem akan merespons dengan **403 Forbidden** secara aman via helper `canViewVersion()`.

---

## TASK 5 — FINAL VERDICT

### SAFE TO IMPLEMENT

*   **Jumlah file terdampak:** 3 file (`DocumentController.php`, `ApprovalController.php`, `DocumentVersion.php`)
*   **Jumlah controller terdampak:** 2 controller (`DocumentController`, `ApprovalController`)
*   **Jumlah view terdampak:** 1 view (`documents/index.blade.php` - opsional, jika ingin menyembunyikan dokumen draft dari Viewer di index utama)
*   **Estimasi kompleksitas:** **LOW**
