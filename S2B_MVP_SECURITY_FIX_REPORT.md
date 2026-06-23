# S2B MVP SECURITY FIX REPORT
**Fokus Perbaikan:** Penutupan Celah Akses Otorisasi Versi Dokumen  
**Status:** **SUKSES & TERVERIFIKASI**

---

## 1. FILE YANG DIUBAH
*   `app/Http/Controllers/DocumentController.php`

---

## 2. LOGIKA OTORISASI (canViewVersion)
Fungsi pembantu terpusat ditambahkan di `DocumentController`:
```php
protected function canViewVersion($user, $version): bool
```
Kewenangan akses diatur sebagai berikut:
*   **APPROVED:** Semua user terotentikasi dapat melihat.
*   **SUPERSEDED:** Semua user terotentikasi dapat melihat (disertai banner obsolete).
*   **DRAFT:** Hanya pembuat versi (creator), Admin, dan Director yang diizinkan.
*   **SUBMITTED:** Hanya pembuat versi (creator), Admin, Director, dan MR yang diizinkan.
*   **REJECTED:** Hanya pembuat versi (creator), Admin, dan Director yang diizinkan.

---

## 3. FUNGSI DAN ROUTE YANG DIAMANKAN

### A. Detail Tampilan (`show()`)
*   **Aksi:** Mengakses detail dokumen dengan parameter `?version_id=`.
*   **Keamanan:** Jika pengguna yang masuk tidak memiliki izin sesuai fungsi `canViewVersion()`:
    1. Sistem menulis peringatan (`warning`) ke `laravel.log`.
    2. Sistem melempar pesan peringatan flash ke session.
    3. Sistem secara otomatis memutar-kembali (*fallback*) ke versi terbitan terakhir (`current_version_id` / `approved`).

### B. Perbandingan Teks (`compare()`)
*   **Aksi:** Membandingkan revisi teks dokumen `/compare?v1=XX&v2=YY`.
*   **Keamanan:** Memeriksa otoritas kedua versi. Jika salah satu tidak diizinkan, eksekusi dihentikan dengan `abort(403)`.

### C. Unduh & Preview (`downloadVersion`, `downloadMaster`, `previewVersion`)
*   **Aksi:** Mengunduh atau melihat file PDF dari versi tertentu.
*   **Keamanan:** Eksekusi langsung dihentikan dengan `abort(403)` jika pengguna tidak sah.

---

## 4. HASIL REGRESSION TEST (PENGUJIAN KEMBALI)
Pengujian dijalankan secara otomatis dalam transaksi database terisolasi untuk mereproduksi skenario hak akses pengguna riil:

```text
Found test users:
  Admin: direktur@peroniks.com (ID: 1)
  Viewer: adminflange@peroniks.com (ID: 20)
  MR: MR@peroniks.com (ID: 2)

Testing on Document: UT.ACC&FIN.01 (ID: 1)

Running helper checks (canViewVersion):
  [PASS] Viewer -> Approved: actual: true, expected: true
  [PASS] Viewer -> Superseded: actual: true, expected: true
  [PASS] Viewer -> Draft (Blocked): actual: false, expected: false
  [PASS] Viewer -> Submitted (Blocked): actual: false, expected: false
  [PASS] Viewer -> Rejected (Blocked): actual: false, expected: false
  [PASS] Creator -> Draft (Allowed): actual: true, expected: true
  [PASS] Creator -> Submitted (Allowed): actual: true, expected: true
  [PASS] Creator -> Rejected (Allowed): actual: true, expected: true
  [PASS] MR -> Submitted (Allowed): actual: true, expected: true
  [PASS] MR -> Draft (Blocked): actual: false, expected: false
  [PASS] MR -> Rejected (Blocked): active: false, expected: false
  [PASS] Admin -> Draft (Allowed): actual: true, expected: true
  [PASS] Admin -> Submitted (Allowed): actual: true, expected: true
  [PASS] Admin -> Rejected (Allowed): actual: true, expected: true

ALL TESTS PASSED SUCCESSFULLY!
```
*(Catatan: Transaksi rollback dieksekusi dengan aman setelah tes selesai).*
