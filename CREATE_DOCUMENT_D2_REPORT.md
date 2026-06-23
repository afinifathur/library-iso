# CREATE_DOCUMENT_D2_REPORT

## 1. FILE YANG DIUBAH
*   `routes/web.php`
*   `app/Http/Controllers/DocumentController.php`
*   `resources/views/documents/_form.blade.php`
*   `resources/views/documents/create.blade.php`
*   `resources/views/documents/edit.blade.php`

---

## 2. RINCIAN IMPLEMENTASI & PERBAIKAN
1.  **Validasi Kode Dokumen (TASK 1):**
    *   Label form diubah menjadi `"Kode Dokumen *"`.
    *   Menambahkan keterangan pembantu *"Wajib dan harus unik. Contoh: IK.GUD-BHN.01"* di bawah input.
    *   Menambahkan atribut HTML `required` pada input.
2.  **Pengecekan Duplikasi AJAX (TASK 2):**
    *   Menambahkan API endpoint `Route::get('check-code')` yang memetakan ke `DocumentController@checkCode`.
    *   Pada input `blur`, JS memicu request fetch untuk mendeteksi apakah kode dokumen sudah digunakan (hanya saat mode "Dokumen Baru").
    *   Jika sudah ada, SweetAlert2 (atau native alert fallback) akan memunculkan warning:
        *"Kode dokumen [Kode] sudah terdaftar sebagai: [Judul]. Silakan gunakan menu 'Ganti Versi Lama (Buat Draft)'"*.
3.  **Readonly Version Label (TASK 3):**
    *   Input `version_label` diubah menjadi `readonly` dengan cursor styling `not-allowed` dan warna latar abu-abu untuk mematikan input manual user dengan tetap menampilkan kalkulasi sistem.
4.  **Catatan Perubahan Wajib (TASK 4):**
    *   Label diubah menjadi `"Catatan apa yang dirubah di versi ini *"`.
    *   Input textarea ditambahkan placeholder penjelas dan atribut `required`.
    *   Backend validation di `DocumentController` diubah dari `'nullable'` menjadi `'required'`.
5.  **Dinamisasi Teks Tombol Submit (TASK 5):**
    *   Menyesuaikan script di form partial, create, dan edit view agar tombol submit bertuliskan:
        *   **Dokumen Baru:** `"Simpan Dokumen Baru"`
        *   **Ganti Versi Lama:** `"Kirim Revisi ke Draft Container"`
