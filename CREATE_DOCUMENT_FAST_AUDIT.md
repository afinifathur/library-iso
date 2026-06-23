# CREATE_DOCUMENT_FAST_AUDIT

## 1. WORKFLOW LOGIC
Alur aktual saat user submit form:
*   **Dokumen Baru (Baseline v1):** 
    *   Jika `upload_type === 'new'` dan `submit_for === 'publish'`, controller langsung mengeset `status = 'approved'` dan `approval_stage = 'DONE'`.
    *   `document->current_version_id` langsung diperbarui ke version ID baru, `revision_number = 1`, dan `revision_date = now()`.
    *   **Hasil:** Dokumen langsung aktif/approved dan terbit secara instan tanpa masuk Draft Container, Approval Queue, ataupun memerlukan persetujuan dari Kabag, MR, dan Director.
*   **Ganti Versi Lama (Buat Draft):** 
    *   Jika `upload_type === 'replace'`, controller menyimpan data sebagai `status = 'draft'` dan `approval_stage = 'KABAG'`.
    *   **Hasil:** Dokumen masuk ke **Draft Container** (`drafts.index`) dan harus melalui proses approval berjenjang mulai dari KABAG, MR, hingga Director sebelum dipublikasikan.

---

## 2. DOC CODE GOVERNANCE
*   **Keharusan Pengisian (Optional/Required):**
    *   Pada level UI HTML (`_form.blade.php`), input `doc_code` diberi label *"optional"* dan tidak memiliki atribut HTML `required`.
    *   Namun, pada level backend validation di `DocumentController@handleCreateNew`, field `doc_code` didefinisikan sebagai **`required`**:
        `'doc_code' => 'required|string|max:120|unique:documents,doc_code'`
    *   **Hasil:** User **wajib** mengisi `doc_code` saat membuat dokumen baru; jika dikosongkan, submit akan gagal pada validasi Laravel.
*   **Validasi Duplikasi:**
    *   Terdapat rule validasi `unique:documents,doc_code` untuk mencegah duplikasi kode dokumen.
    *   Jika user mencoba memasukkan kode `IK.GUD-BHN.01` yang sudah ada di database, validasi Laravel akan langsung memicu error dan mengembalikan user ke halaman form dengan pesan error *"The doc code has already been taken."*

---

## 3. VERSION LABEL
*   **Metode Pengisian:**
    *   Field `version_label` bersifat **manual** berupa text input biasa pada form.
*   **Source of Truth & Auto-Coercion:**
    *   Jika dikosongkan, sistem secara otomatis menghitung label menggunakan method `nextVersionLabelForDocument()`, yaitu mencari angka versi tertinggi dengan pola regex `^v[0-9]+` lalu menambahkan 1 (misal `v2`).
    *   Di level controller, method `resolveVersionLabelForNewVersion()` mengamankan input manual user:
        *   Jika label yang dimasukkan berupa angka versi yang lebih rendah atau sama dengan versi tertinggi yang sudah ada di database, sistem akan mengabaikan input user dan memaksa menaikkan label ke nomor versi berikutnya (misal: jika versi tertinggi `v2` dan user mengetik `v1`, sistem akan memaksa menyimpannya sebagai `v3` untuk mencegah overwrite data).
        *   Jika input label bukan berformat `vN` standar (misal: "draft-1"), sistem akan menggunakan versi kalkulasi `vN` berikutnya sebagai pengaman.

---

## 4. CHANGE NOTE
*   **Keharusan Pengisian:**
    *   Field `change_note` bersifat **opsional** (tidak memiliki atribut `required` di HTML dan divalidasi backend sebagai `nullable|string|max:2000`).
*   **Penggunaan Data:**
    *   **Compare:** Ditampilkan pada halaman perbandingan (`documents/compare.blade.php`) untuk melihat catatan revisi dari kedua versi dokumen yang sedang dibandingkan.
    *   **Timeline:** Ditampilkan pada halaman detail dokumen (`documents/show.blade.php`) di bagian riwayat versi/timeline sebagai informasi historis perubahan.
    *   **Approval Review:** Ditampilkan pada halaman detail antrean draf (`drafts/show.blade.php`) untuk memberikan informasi kepada reviewer/approver mengenai tujuan perubahan versi tersebut.

---

## 5. FILE UPLOAD
*   **Ekstensi yang Diperbolehkan:**
    *   **Master File (`master_file`):** `.doc`, `.docx`, `.xls`, `.xlsx` (Maksimum size: 100 MB / `102400` KB).
    *   **PDF File (`file`):** `.pdf` (Maksimum size: 50 MB / `51200` KB).
*   **Fungsi Aktual:**
    *   **Master File:** Menyimpan file sumber asli yang dapat diedit (Word/Excel) untuk kebutuhan pemeliharaan dokumen. Disimpan di disk di bawah folder path `{doc_code}/{version_label}/master/{filename}`.
    *   **PDF Preview:** Digunakan sebagai file pratinjau utama di halaman web. Sistem merender file PDF ini menggunakan tag `<iframe>` atau `<object>` pada view detail dokumen (`show.blade.php`) agar pembaca dapat membaca dokumen secara online tanpa perlu mengunduh file master yang dapat diedit.
