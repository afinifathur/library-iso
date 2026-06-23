# DOCUMENTS PAGE MODERNIZATION OPTIONS
**Halaman Sasaran:** `/documents` (Daftar Dokumen)  
**Tujuan Dokumen:** Menyediakan analisis struktural visual dan tiga alternatif desain antarmuka modern yang siap diterapkan tanpa mengganggu logika bisnis atau alur kerja backend yang ada.

---

## TASK 1: PEMETAAN AREA HALAMAN SAAT INI (MENTAL SCREENSHOT)

Berdasarkan analisis visual antarmuka halaman `/documents` yang aktif, pemetaan fungsi ruang adalah sebagai berikut:

1. **Area yang Paling Sering Digunakan (High-Interaction Area)**
   * **Bilah Pencarian (Search Input):** Merupakan gerbang utama bagi user untuk mencari dokumen berdasarkan kode atau judul.
   * **Tautan Judul Dokumen (Title Links):** Tautan teks di dalam tabel yang mengarah langsung ke halaman detail dokumen (`documents.show`). Ini adalah aksi utama yang dilakukan pengguna untuk membuka atau mengunduh dokumen.
   
2. **Area yang Paling Memakan Ruang (High-Volume Area)**
   * **Badan Tabel (Table Body):** Daftar baris dokumen memakan hampir 80% dari area viewport pengguna.
   * **Tumpukan Badge di Kolom "Latest":** Penumpukan label versi (misal: `v1`), status persetujuan (`approved`/`rejected`), dan status indeksasi (`indexed`/`pasted`) secara horizontal memakan porsi lebar kolom paling besar di ujung kanan tabel.

3. **Area yang Terlihat Paling "Jadul" (Legacy Visual Area)**
   * **Input Box dan Select Filter Dropdown:** Menggunakan elemen default OS/browser tanpa border-radius halus atau bayangan lembut.
   * **Ketiadaan Card Wrapper:** Tabel diletakkan mengambang bebas di atas latar belakang abu-abu muda (`#faf8ff`) tanpa dibatasi oleh panel card putih terstruktur seperti halaman Departments.

---

## TASK 2 & 3: ALTERNATIF LAYOUT MODERN & WIREFRAME ASCII

### OPTION A: Minimal Change (Penyempurnaan Minimalis)
* **Deskripsi:** Menjaga tata letak dasar agar tidak ada pergeseran letak tombol. Perubahan hanya berfokus pada pembungkusan halaman ke dalam panel card putih dan merapikan visual elemen input agar seragam dengan desain modern.
* **Wireframe ASCII:**
```text
+-----------------------------------------------------------------+
|  Documents                                      [+ New Document] |
|                                                                 |
|  +-----------------------------------------------------------+  |
|  |  +-----------------------------------------------------+  |  |
|  |  | [ Search doc code, title... ]  [ All Dept v ] [Filt]|  |  |
|  |  +-----------------------------------------------------+  |  |
|  |                                                           |  |
|  |  +-----------------------------------------------------+  |  |
|  |  | Doc Code   | Title         | Dept | Revision| Latest|  |  |
|  |  +------------+---------------+------+---------+-------+  |  |
|  |  | ISO-QA-01  | SOP Quality   | QA   | 2       | v2.0  |  |  |
|  |  +-----------------------------------------------------+  |  |
|  |                                                           |  |
|  |  [<<] [1] [2] [>>]                                        |  |
|  +-----------------------------------------------------------+  |
+-----------------------------------------------------------------+
```

### OPTION B: Modern QMS (Penyelarasan Penuh dengan Halaman Departments)
* **Deskripsi:** Memasukkan judul halaman ke dalam card utama. Header kolom tabel diubah menjadi Title Case dengan padding longgar. Ditambahkan tombol aksi eksplisit "Buka Dokumen" di sebelah kanan setiap baris untuk konsistensi visual 100% dengan Departments.
* **Wireframe ASCII:**
```text
+-----------------------------------------------------------------+
|  +-----------------------------------------------------------+  |
|  |  Daftar Dokumen                                           |  |
|  |                                                           |  |
|  |  +-----------------------------------------------------+  |  |
|  |  | [ Cari dokumen... ] [ Dept v ] [ Status v ] [Filter]|  |  |
|  |  +-----------------------------------------------------+  |  |
|  |                                                           |  |
|  |  +-----------------------------------------------------+  |  |
|  |  | Kode       | Judul Dokumen | Dept | Revisi | Status |  |  |
|  |  +------------+---------------+------+--------+--------+  |  |
|  |  | ISO-QA-01  | SOP Quality   | QA   | Rev 2  | Active |  |  |
|  |  +------------+---------------+------+--------+--------+  |  |
|  |                                                           |  |
|  |  Showing 1-10 of 45                   [<<] [1] [2] [>>]   |  |
|  +-----------------------------------------------------------+  |
+-----------------------------------------------------------------+
```

### OPTION C: Executive Dashboard (Pusat Kontrol Kepatuhan Premium)
* **Deskripsi:** Menambahkan ringkasan statistik (KPI Cards) di bagian paling atas untuk direktur dan auditor. Tabel dilengkapi dengan indikator visual status cepat dan panel pencarian asimetris dengan ikon terintegrasi.
* **Wireframe ASCII:**
```text
+-----------------------------------------------------------------+
|  +--------------+  +--------------+  +--------------+           |
|  | Total Active |  | In Progress  |  | Overdue Rev  |           |
|  |  142 Docs    |  |  8 Docs      |  |  3 Docs      |           |
|  +--------------+  +--------------+  +--------------+           |
|                                                                 |
|  +-----------------------------------------------------------+  |
|  |  Compliance Document Registry                             |  |
|  |                                                           |  |
|  |  +---------------------------+   +---------------------+  |  |
|  |  | (o) Search by code/title  |   | [ All Departments v]|  |  |
|  |  +---------------------------+   +---------------------+  |  |
|  |                                                           |  |
|  |  +-----------------------------------------------------+  |  |
|  |  | Code       | Document Title       | Latest | Status |  |  |
|  |  +------------+----------------------+--------+--------+  |  |
|  |  | ISO-QA-01  | SOP Quality          | v2.0   | Active |  |  |
|  |  +-----------------------------------------------------+  |  |
|  |                                                           |  |
|  |  Page 1 of 15                        [<] 1  2  3 [>]      |  |
|  +-----------------------------------------------------------+  |
+-----------------------------------------------------------------+
```

---

## TASK 4: REKOMENDASI AKHIR

Jika kriteria utama yang diinginkan oleh Product Owner adalah:
1. **Sederhana** (tidak menambah komponen visual yang rumit).
2. **Cepat** (minim waktu pengerjaan dan risiko bug visual).
3. **Tidak mengubah alur kerja/workflow** (tetap mempertahankan fungsi pencarian dan relasi database).
4. **Konsisten dengan halaman Departments** (mengikuti pola desain referensi utama aplikasi).

Maka opsi yang **WAJIB DIPILIH** adalah: **OPTION B: Modern QMS**.

### Alasan Pemilihan:
* **Konsistensi Visual Instan:** Halaman Departments adalah standar referensi visual terbaik di aplikasi saat ini yang disukai pengguna. Mengadopsi struktur pembungkus card putih (`border-radius: 16px`, `padding: 24px`, shadow tipis) dan memindahkan judul "Daftar Dokumen" ke dalam card secara langsung menyatukan bahasa desain aplikasi.
* **UX Tombol Aksi yang Jelas:** Di halaman saat ini, pengguna harus menebak bahwa teks judul dokumen berwarna biru dapat diklik. Dengan menggunakan tombol aksi bertuliskan "Buka Dokumen" seperti pada Option B (dan Departments), alur navigasi menjadi jauh lebih intuitif dan ramah pengguna.
* **Bebas Perubahan Database & Route:** Opsi ini hanya menata ulang markup Blade (`index.blade.php`) dan memanfaatkan token CSS yang sudah ada di `public/css/style.css`. Tidak diperlukan perubahan pada controller, model, migration, seeder, maupun route sehingga pengerjaannya sangat aman dan cepat.
