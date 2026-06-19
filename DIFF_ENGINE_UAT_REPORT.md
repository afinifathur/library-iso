# DIFF ENGINE UAT & DESIGN RECOMMENDATION REPORT
**Project:** Library-ISO — PT Peroni Karya Sentra  
**Date of Review:** June 19, 2026  
**Auditor:** Antigravity (Advanced Agentic Coding AI)

---

## 1. Evaluasi Kualitas Diff Saat Ini

Berdasarkan pengujian programmatik pada dokumen **`IK.GUD-BHN.01`** (ID: 31), berikut adalah evaluasi kualitas keluaran mesin perbandingan saat ini:

### A. Analisis Hasil Perbandingan `v1` vs `v2`
* **Perubahan Riil:** Pada versi `v2`, staf menambahkan frasa `" dan update stock melalui program"` pada akhir klausul/poin 2.9.
* **Output Rendering HTML:**
  ```html
  ... berdasarkan bukti timbang dan surat jalan yang telah distempel<ins> dan update stock melalui program</ins>.2.10 Menyerahkan BPB ...
  ```
* **Visualisasi:** Terlihat dengan latar belakang hijau lembut (`#d1fae5`) untuk teks baru. Mesin diff berhasil melokalisasi perubahan ini dengan baik.

---

### B. Analisis Hasil Perbandingan `v2` vs `v3` (Perubahan Besar)
* **Perubahan Riil:** Penyesuaian besar pada Bagian 2 (Pengecekan Bahan Baku), seperti penambahan detail alat spectro, perubahan dari "Handheld" menjadi "x-ray", dan revisi penanggung jawab.
* **Output Rendering HTML:**
  ```text
  BAGIAN 2 : PENGECEKAN BAHAN BAKUStaff bahan baku akan:2.1<ins>.</ins> Memeriksa kondisi...
  ...
  Dibuat oleh, Diperiksa oleh Disetujui oleh, <del> Kabag. Gudang </del><ins>Gudang </ins><del>Bahan Baku </del> Management Representative Direktur
  ```
* **Masalah Kritis pada Mode `line` Saat Ini:**
  1. **Teks Terlalu Rapat (Collapsing Newlines):** Karena proses ekstraksi plain text dari PDF/Word menggabungkan baris tanpa pembatas paragraf yang bersih, teks tampil sebagai satu blok raksasa. Hal ini membuat perbandingan berbasis baris (`line-by-line`) menjadi sangat kasar.
  2. **Tanda Baca yang Mengganggu:** Penambahan karakter kecil seperti titik (`.`) atau spasi menyebabkan mesin menganggap seluruh baris/bagian tersebut berubah, menyisipkan tag `<ins>` dan `<del>` di tengah kata, yang mengurangi legibilitas.

---

## 2. Rekomendasi Mode Diff: `line` vs `word`

### Perbandingan Karakteristik Mode

| Karakteristik | Mode Saat Ini (`line`) | Mode Rekomendasi (`word`) |
| :--- | :--- | :--- |
| **Granularitas** | Membandingkan seluruh baris teks secara utuh. | Membandingkan kata demi kata di dalam baris. |
| **UX & Keterbacaan** | Kasar. Jika satu kata berubah, seluruh kalimat atau paragraf sering ditandai sebagai berubah. | Sangat halus. Hanya kata spesifik yang berubah yang diberi warna hijau/merah. |
| **Kesesuaian ISO** | Kurang ideal untuk dokumen SOP/IK yang revisinya sangat spesifik (misal: hanya mengubah angka dosis atau nama jabatan). | **Sangat Ideal.** Auditor atau pengguna dapat langsung melihat kata kunci yang direvisi dalam hitungan detik. |

### Rekomendasi Teknis
> [!IMPORTANT]
> **Rekomendasi:** Ubah konfigurasi `detailLevel` dari `'line'` ke `'word'` di dalam `rendererOptions` pada `DocumentController::buildDiff()`.
> Langkah ini akan memberikan tingkat presisi yang sangat tinggi untuk dokumen SOP/Instruksi Kerja (IK) yang umumnya hanya mengalami revisi minor per klausul.

---

## 3. Desain Fitur "Version History" pada Detail Dokumen

Saat ini, halaman detail dokumen (`documents.show`) tidak menampilkan riwayat versi secara kronologis yang memudahkan perbandingan instan. Direkomendasikan untuk menambahkan bagian **Version History** dengan tata letak berikut.

### A. Mockup Desain Riwayat Versi (Wireframe)

```text
=============================================================================================
VERSION HISTORY (Riwayat Versi Dokumen)
=============================================================================================
Versi  | Status       | Tanggal Rilis | Pengunggah       | Aksi / Pembandingan
---------------------------------------------------------------------------------------------
v3     | Approved     | 18 Juni 2026  | Budi (QA Staff)  | [Compare with v2]  [Compare Active]
v2     | Superseded   | 10 Mei 2026   | Andi (QA Staff)  | [Compare with v1]  [Compare Active]
v1     | Superseded   | 01 Jan 2026   | Admin QC         | (Baseline Version)
---------------------------------------------------------------------------------------------
```

### B. Manfaat Desain Ini
1. **Audit-Trail Compliance (ISO 9001:2015):** Memudahkan auditor eksternal melihat siklus hidup dokumen secara kronologis lengkap dengan tanggal persetujuan (`Approved Date`).
2. **One-Click Comparison:** Tombol `Compare with Previous` langsung mengarahkan pengguna ke route `/documents/{id}/compare?v1={prev_version_id}&v2={current_version_id}`, menghemat waktu navigasi secara signifikan.
3. **Penyelarasan Status `Superseded`:** Mengubah label versi lama dari `Approved` menjadi `Superseded` di UI memberikan kejelasan bahwa versi tersebut sudah tidak berlaku lagi secara hukum organisasi.
