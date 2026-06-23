# UX & MENTAL MODEL AUDIT REPORT

## TASK 1 — MENTAL MODEL AUDIT
Jika user (staf pabrik/non-IT) pertama kali membuka `/documents/create`, respons mereka:

1.  **Kapan memilih "Dokumen Baru":** **Agak Membingungkan**. Istilah "Baseline v1" terdengar sangat teknis (IT-centric) dan asing bagi tim Mutu/ISO. Deskripsi "menerbitkannya langsung" juga memicu salah paham karena dalam kaidah ISO, dokumen baru dilarang diterbitkan tanpa melewati siklus verifikasi.
2.  **Kapan memilih "Ganti Versi Lama":** **Agak Membingungkan**. Kata "Ganti" memberi impresi buruk bahwa file lama akan langsung terhapus/tertimpa (menghilangkan riwayat), padahal sistem sebenarnya membuat draf usulan revisi baru.
3.  **Perbedaan Keduanya:** **Membingungkan**. Formulir untuk membuat dokumen baru dan memperbarui dokumen lama disatukan dalam satu template form yang sama. Hal ini tidak sejalan dengan alur kerja riil di mana "membuat" dan "merevisi" adalah dua prosedur QMS yang sangat berbeda.
4.  **Apa yang terjadi setelah submit:** **Membingungkan**. Perubahan nama tombol secara dinamis dan perbedaan efek submit (langsung terbit vs masuk draf) membingungkan pengguna terkait di mana posisi dokumen mereka setelah tombol diklik.

---

## TASK 2 — FIELD NECESSITY AUDIT

| Field | Dokumen Baru | Revisi Dokumen | Perlu Ditampilkan? |
| --- | --- | --- | --- |
| **Department** | **Wajib** | Sebaiknya disembunyikan | Ya, hanya untuk Dokumen Baru. |
| **Category** | **Wajib** | Sebaiknya disembunyikan | Ya, hanya untuk Dokumen Baru. |
| **Doc Code** | **Wajib** | *Readonly* / Sembunyikan | Ya, namun wajib terkunci (*readonly*) saat revisi agar kode tidak bergeser. |
| **Related Documents** | Opsional | Opsional | Ya, untuk melampirkan referensi. |
| **PDF** | Opsional | Opsional | Ya, sebagai berkas salinan resmi. |
| **Master File** | **Wajib** | **Wajib** | Ya, sebagai sumber dokumen asli yang dapat disunting (.docx/.xlsx). |
| **Version Label** | Sembunyikan | Sembunyikan | **Tidak**. Harus diatur penuh secara otomatis oleh sistem (`v1` atau `vN+1`). |
| **Plain Text (Paste)** | Opsional | Opsional | Ya, namun area salin-tempel ini sangat mengganggu kenyamanan pengguna. |
| **Change Note** | Sembunyikan | **Wajib** | Ya, hanya wajib untuk revisi dokumen (ISO Klausul 7.5.3.2). |

---

## TASK 3 — VERSION LABEL AUDIT
*   **Apakah user perlu melihat/mengisi field ini?** **Tidak**. User tidak boleh menentukan label versi secara manual untuk mencegah ketidakpatuhan terhadap format penulisan revisi mutu (misal: ada yang menulis `Rev.01`, `v2`, `A`, dll.).
*   **Rekomendasi MVP:** Sembunyikan input text `version_label` dari form. Biarkan sistem menghitungnya di latar belakang:
    *   Jika Dokumen Baru: Otomatis diset `v1`.
    *   Jika Revisi Dokumen: Otomatis mengambil versi terakhir lalu menambahkannya sebesar `+1` (misal dari `v1` menjadi `v2`).
    *   Cukup tampilkan teks statis read-only (misalnya: "Versi Terkalkulasi: v2") untuk sekadar informasi kepada user.

---

## TASK 4 — SUBMIT BUTTON AUDIT
*   **Publish (Terbitkan):** Membingungkan dan melanggar prinsip ISO (bisa menerbitkan sepihak tanpa approval).
*   **Save Baseline:** Istilah teknis yang tidak familiar bagi staf administrasi pabrik.
*   **Save Draft (Simpan Draf):** Cukup dipahami, namun kurang memberikan ketegasan tujuan pengajuan.
*   **Rekomendasi Bahasa Indonesia (Mutu & Pabrik-friendly):**
    *   Untuk menyimpan pekerjaan sementara: **"Simpan Draf Kerja"**
    *   Untuk mengajukan ke atasan/proses approval: **"Kirim untuk Ditinjau (Approval)"** atau **"Ajukan Usulan Dokumen"**
    *   Tombol Batal: **"Kembali"**

---

## TASK 5 — TOP 10 UX CONFUSIONS (RANKING)
1.  **Formulir Tunggal Multifungsi (Dropdown Switcher):** Menggabungkan pembuatan dokumen baru dan revisi dokumen lama dalam satu form dinamis.
2.  **Kewajiban Salin-Tempel Teks (Plain Text Area):** Kewajiban menyalin seluruh teks Word ke textarea demi indexing pencarian internal sistem.
3.  **Terminologi "Baseline v1":** Penggunaan jargon IT di sistem manajemen mutu pabrik.
4.  **Kolom Input Label Versi Manual:** Membiarkan user mengetik nama versi sendiri yang berisiko merusak standarisasi penomoran revisi ISO.
5.  **Perubahan Dinamis Tombol Submit:** Hilangnya kepastian aksi akibat tombol yang berubah label secara otomatis via JS.
6.  **"Catatan Perubahan" pada Dokumen Baru:** Adanya kewajiban mengisi "Catatan Perubahan" saat dokumen baru pertama kali dibuat.
7.  **Edit Metadata Bebas saat Revisi:** Form Edit mengizinkan perubahan Departemen & Kategori di tingkat draf versi, yang berisiko mengacaukan kepemilikan dokumen.
8.  **Kemampuan Mengubah Kode Dokumen saat Revisi:** Kotak input kode dokumen yang terbuka dan rentan salah ketik saat mengajukan revisi.
9.  **Kurangnya Panduan Format Unggah File:** Ketiadaan instruksi penamaan file master agar sinkron dengan Kode Dokumen.
10. **Tampilan Error 403 Tanpa Penjelasan:** User Viewer yang membuka dokumen draf langsung dilempar ke halaman 403 Forbidden default tanpa pesan petunjuk yang jelas.

---

## FINAL VERDICT
Halaman Create saat ini lebih condong terlihat sebagai:
**`A. Form Upload Dokumen`**

**Alasan:**
Formulir ini dirancang layaknya media penyimpanan file (uploader) biasa yang mengandalkan manipulasi input manual oleh user dan skrip JavaScript untuk membedakan mode operasinya. Tidak ada pembagian alur visual yang mencerminkan fase-fase resmi pengendalian dokumen ISO 9001 (Penyusunan → Pemeriksaan Kabag → Verifikasi MR → Pengesahan Direktur). User dipaksa mengunggah data master dan salinan teks secara bersamaan di satu tempat tanpa panduan alur kerja QMS yang terarah. Akibatnya, sistem ini terasa seperti folder berbagi berkas digital ketimbang aplikasi tata kelola dokumen mutu yang patuh standar regulasi.
