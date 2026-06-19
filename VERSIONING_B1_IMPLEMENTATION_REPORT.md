# IMPLEMENTATION REPORT: VERSION CHAIN HARDENING & SUPERSEDED STATUS FIX (PHASE B1)
**Project:** Library-ISO — PT Peroni Karya Sentra  
**Date:** June 19, 2026  
**Author:** Antigravity (Advanced Agentic Coding AI)

---

## 1. Root Cause Analysis

### A. Automatic Version Chain (`prev_version_id` = NULL)
* **Root Cause:** Kolom `prev_version_id` pada tabel `document_versions` tidak diisi di tingkat controller maupun database saat dokumen/versi baru ditambahkan. Sistem sepenuhnya bergantung pada eksekusi manual command CLI `php artisan documents:build-relations` untuk membangun relasi linked-list tersebut.
* **Solusi:** Menambahkan event hook model `creating` pada model `DocumentVersion` melalui method `booted()`. Hook ini akan mendeteksi versi terakhir dokumen secara dinamis dan mengisi `prev_version_id` secara real-time sebelum data dimasukkan ke database.

### B. Superseded Status Bug (Versi Lama Tetap 'approved')
* **Root Cause:** Di dalam method `DocumentVersion@approveByDirector`, terdapat kode pengaman transisi status sebagai berikut:
  ```php
  if ($old && ! in_array($old->status, ['approved','rejected','superseded'], true)) {
      $old->status = 'superseded';
      $old->save();
  }
  ```
  Karena status versi aktif lama adalah `'approved'`, maka `in_array('approved', ...)` bernilai `true`. Negasi (`!`) mengubah nilainya menjadi `false`, sehingga blok update status diabaikan. Akibatnya, versi terdahulu tetap berstatus `'approved'` dan tidak pernah ditandai sebagai `'superseded'`.
* **Solusi:** Mengubah kondisi logika tersebut agar secara eksplisit mendeteksi status `'approved'` untuk diubah menjadi `'superseded'`.

---

## 2. Berkas yang Diubah (Files to Modify)
* `app/Models/DocumentVersion.php` (Model versioning dokumen)

---

## 3. Logic Sebelum vs Logic Sesudah

### A. Pembangunan Rantai Versi (`prev_version_id`)

#### Sebelum:
*(Tidak ada logika penanganan otomatis di model `DocumentVersion`)*

#### Sesudah:
Menambahkan method static `booted()` pada model `DocumentVersion`:
```php
protected static function booted(): void
{
    static::creating(function (DocumentVersion $version) {
        // Cari versi terakhir dari dokumen yang sama berdasarkan ID terbesar
        $latest = self::where('document_id', $version->document_id)
            ->orderByDesc('id')
            ->first();

        if ($latest) {
            $version->prev_version_id = $latest->id;
        }
    });
}
```

---

### B. Transisi Status Versi Lama (`superseded`)

#### Sebelum (`app/Models/DocumentVersion.php` baris 113-116):
```php
if ($old && ! in_array($old->status, ['approved','rejected','superseded'], true)) {
    $old->status = 'superseded';
    $old->save();
}
```

#### Sesudah:
```php
if ($old && $old->status === 'approved') {
    $old->status = 'superseded';
    $old->save();
}
```

---

## 4. Analisis Risiko Regresi (Regression Safety)

* **Risiko pada Fitur Compare:** **Sangat Rendah / Tidak Ada.** Fungsionalitas compare membaca `plain_text` versi bersangkutan. Status `'superseded'` tidak membatasi pembacaan teks komparasi.
* **Risiko pada Approval Queue:** **Tidak Ada.** Antrean approval menyaring versi dokumen yang berstatus `'submitted'`. Perubahan status versi lama dari `'approved'` ke `'superseded'` tidak memengaruhi dokumen yang sedang berjalan di antrean.
* **Risiko pada Dashboard & Document Listing:** **Tidak Ada.** Query dashboard dan listing mengambil versi aktif dokumen menggunakan field `current_version_id` pada tabel `documents`. Nilai field ini tetap terisi ID versi yang baru disetujui (sesuai alur kerja aslinya).

---

## 5. Verification Plan (Rencana Verifikasi)

Kami akan menulis dan menjalankan script PHP verifikasi terisolasi (`verify_b1.php`) menggunakan tinker yang melakukan pengujian end-to-end sebagai berikut:
1. Membuat dokumen tiruan (mock document) baru.
2. Membuat versi pertama (`v1`) untuk dokumen tersebut dengan status `'approved'`.
3. Membuat versi kedua (`v2`) dengan status `'draft'`.
4. Memverifikasi secara asersi bahwa `v2->prev_version_id` **otomatis terisi** dengan ID dari `v1` seketika saat di-save.
5. Memanggil fungsi `$v2->approveByDirector()`.
6. Memverifikasi bahwa:
   * Status `v1` berubah menjadi `'superseded'`.
   * Status `v2` berubah menjadi `'approved'`.
   * Dokumen induk memperbarui `current_version_id` ke ID `v2`.
7. Menghapus (cleanup) seluruh data tiruan dari database.
