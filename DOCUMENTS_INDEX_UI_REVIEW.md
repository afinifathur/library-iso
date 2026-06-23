# DOCUMENTS INDEX UI REVIEW
**Halaman Sasaran:** `/documents` (Daftar Dokumen)  
**Tujuan Audit:** Mengevaluasi kondisi antarmuka (UI) dan pengalaman pengguna (UX) saat ini serta memberikan rekomendasi visual agar selaras dengan desain QMS yang modern dan premium.

---

## TASK 1: IDENTIFIKASI ELEMEN HALAMAN

Hasil tangkapan layar dan audit struktural halaman `/documents` mengidentifikasi komponen-komponen berikut:

1. **Header Area**
   * **Kondisi Saat Ini:** Berisi judul halaman "Documents" (`h2`) di sebelah kiri dan tombol "+ New Document" di sebelah kanan. Layout menggunakan Flexbox (`space-between`).
   * **Masalah Visual:** Area ini diletakkan langsung di atas warna latar belakang halaman (`#faf8ff`) tanpa pembungkus (card/container), membuatnya terlihat mengambang bebas ("unanchored") dan tidak konsisten dengan halaman Departments/Categories yang membungkus konten di dalam card putih premium.

2. **Search Box**
   * **Kondisi Saat Ini:** Menggunakan elemen input teks HTML standar dengan atribut `placeholder="search doc code, title, or text"` dan `min-width:240px`.
   * **Masalah Visual:** Terlihat sangat "raw" (mentah) dengan border default browser, tidak memiliki ikon pencarian (kaca pembesar), serta sudut input yang kurang membulat (tidak estetis).

3. **Department Filter**
   * **Kondisi Saat Ini:** Berupa elemen `<select>` dropdown bawaan browser berisi daftar departemen aktif.
   * **Masalah Visual:** Sama seperti kotak pencarian, elemen dropdown ini menggunakan gaya bawaan browser (native styling) tanpa kustomisasi panah dropdown atau efek hover, memberikan kesan usang.

4. **Status Filter**
   * **Kondisi Saat Ini:** **TIDAK ADA**. Pengguna tidak memiliki opsi untuk memfilter daftar dokumen berdasarkan status persetujuan terbaru (misalnya: Approved, Submitted, Rejected, Draft).

5. **Action Buttons**
   * **Kondisi Saat Ini:** Tombol "+ New Document" di kanan atas (menggunakan `btn-primary` dengan ikon Material Symbols "add") serta tombol "Filter" dan "Reset" di area pencarian.
   * **Masalah Visual:** Tombol di baris pencarian diletakkan sebaris dengan gap seadanya. Selain itu, tidak ada tombol aksi langsung pada setiap baris tabel (seperti tombol "Buka Dokumen" pada Departments) untuk memudahkan navigasi langsung ke detail dokumen.

6. **Table**
   * **Kondisi Saat Ini:** Menggunakan class `.table` dari CSS global. Header tabel (`th`) dirender secara UPPERCASE dengan background abu-abu muda (`#f3f3fe`) dan border bawah abu-abu gelap.
   * **Masalah Visual:** Kolom "Latest" menampilkan dua badge sekaligus (badge status dokumen + badge teks dokumen seperti `pasted`/`indexed`/`no-text`) yang ditumpuk secara horizontal. Hal ini menyebabkan teks menumpuk rapat dan terkesan semrawut pada layar beresolusi rendah.

7. **Pagination**
   * **Kondisi Saat Ini:** Memanfaatkan fitur bawaan Laravel pagination `{{ $docs->links() }}`.
   * **Masalah Visual:** Link navigasi nomor halaman diletakkan begitu saja di bawah tabel tanpa pembungkus yang rapi, sehingga posisinya terlalu rapat ke kiri bawah tanpa ruang bernapas (padding/margin) yang cukup.

8. **Result Counter**
   * **Kondisi Saat Ini:** **TIDAK ADA**. Tidak ada teks indikator jumlah baris (misalnya: *"Showing 1-10 of 45 documents"*).

---

## TASK 2: PENILAIAN UI/UX (SCORE 1–10)

| Area | Score | Rencana Justifikasi |
| :--- | :---: | :--- |
| **Search UX** | **4/10** | Kotak pencarian tidak memiliki ikon visual, tanpa validasi/debounce instan, dan bergaya input HTML jadul. |
| **Filter UX** | **3/10** | Filter sangat terbatas (hanya Department), tidak ada filter Status. Dropdown select menggunakan gaya native browser yang tidak premium. |
| **Table Readability** | **6/10** | Penggunaan font monospace untuk Kode Dokumen sudah tepat. Namun, visual tabel terasa sumpek karena tidak dibungkus dalam Card dengan padding yang baik. |
| **Information Density** | **7/10** | Kepadatan informasi sebenarnya pas, namun tumpukan dua badge di kolom "Latest" membuat informasi terlihat berantakan secara visual. |
| **Pagination** | **4/10** | Navigasi halaman tidak berpusat (centered) dan tidak dikelilingi ruang kosong yang estetis; terkesan sebagai elemen tempelan akhir. |
| **Mobile Readiness** | **5/10** | Filter akan turun ke bawah secara otomatis (wrapping), tetapi input pencarian dan tombol tidak memiliki lebar penuh (full-width) pada layar ponsel sehingga tampilannya asimetris. |
| **Executive Readability** | **4/10** | Secara estetika kurang memuaskan untuk level manajerial atau auditor ISO karena tidak memiliki sentuhan dashboard QMS modern yang bersih dan rapi. |

---

## TASK 3: ANALISIS ELEMEN JADUL & PERINGKAT KUALITAS

Bagian yang terlihat paling **"jadul"** adalah ketiadaan container card pembungkus halaman dan elemen filter input native. Berikut urutan kualitas elemen dari yang Terburuk ke Baik:

1. **Filter Form Input & Dropdown (Terburuk - Skor 2/10):** Tampilan kotak input teks dan dropdown select bawaan sistem operasi Windows/browser tanpa modifikasi CSS modern memberikan kesan web tahun 2010.
2. **Ketiadaan Card Wrapper (Buruk - Skor 3/10):** Tabel diletakkan langsung di body background. Tanpa pembatas tepi card, data tabel terlihat melebar liar dan kehilangan fokus struktural.
3. **Double Badge Layout (Buruk - Skor 3.5/10):** Tampilan visual dua badge bertumpuk (`approved` dan `indexed` / `pasted`) pada kolom "Latest" terkesan seperti debug log developer dibanding UI siap-produksi.
4. **Pagination Mentah (Cukup - Skor 4.5/10):** Tampilan navigasi halaman standar tanpa container penyeimbang visual di bagian bawah tabel.
5. **Ketiadaan Tombol Aksi di Baris Tabel (Cukup - Skor 5/10):** Pengguna harus menebak bahwa judul dokumen dapat diklik untuk masuk ke halaman detail. Standar UX yang baik memerlukan tombol aksi yang eksplisit (misal: "Buka" atau "Detail").
6. **Desain Header Halaman (Cukup - Skor 5.5/10):** Judul dan tombol baru teratur sejajar, namun terlihat kering tanpa deskripsi sub-header atau ikon representatif.
7. **Tipografi Kode Dokumen (Baik - Skor 7.5/10):** Penggunaan font monospace untuk kode ISO mempermudah pembacaan kode terstruktur.

---

## TASK 4: REKOMENDASI DESAIN ULANG QMS MODERN

### KEEP (Pertahankan)
* Font JetBrains Mono untuk kolom `Doc Code` untuk menjaga keterbacaan kode dokumen ISO yang presisi.
* Kolom data utama (Doc Code, Title, Dept, Revision) karena sudah mencakup informasi compliance dasar yang dibutuhkan.
* Ikon Material Symbols pada tombol aksi untuk memperkuat navigasi visual.

### IMPROVE (Tingkatkan)
* **Card Container Wrapping:** Bungkus seluruh area form filter, tabel, dan pagination ke dalam satu Card putih (`background: #ffffff`, `border-radius: 16px`, `padding: 24px`, `box-shadow: 0 8px 24px rgba(20,40,80,0.04)`) mengikuti layout Departments.
* **Modernisasi Form Filter:**
  * Tambahkan ikon pencarian di dalam input box, buat border input tipis modern dengan radius membulat (`border-radius: 8px`).
  * Desain ulang dropdown select agar memiliki style yang selaras dengan input teks (border seragam, custom arrow icon).
  * **Tambahkan Filter Status** (Dropdown select untuk memilih Approved, Rejected, Submitted, Draft).
* **Penyederhanaan Badge Kolom Latest:** Gabungkan badge status dan indexing menjadi satu status visual terpadu atau gunakan ikon kecil sebagai pengganti text badge kedua demi menghemat ruang kolom.
* **Tombol Aksi Eksplisit:** Tambahkan tombol aksi biru bulat premium pada setiap baris (misalnya ikon panah detail atau tombol teks "Buka Dokumen") agar selaras dengan Departments.
* **Result Counter & Pagination Layout:** Tambahkan teks counter data (*"Showing 1 to 10 of X entries"*) di sebelah kiri dan posisikan pagination di sebelah kanan secara simetris di bawah tabel.

### REMOVE (Hapus)
* Desain tabel tanpa pembungkus putih yang diletakkan langsung di latar belakang halaman.
* Style input native browser tanpa CSS reset yang merusak estetika antarmuka modern.
* Penumpukan ganda badge teks berwarna kontras tinggi yang menimbulkan polusi visual pada kolom Latest.
