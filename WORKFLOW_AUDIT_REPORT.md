# WORKFLOW AUDIT REPORT

## TASK 1 — CREATE VS EDIT COMPARISON

| Aspek / Kriteria | Mode Create (`store`) | Mode Edit (`updateCombined`) |
| --- | --- | --- |
| **Field Khusus** | - `upload_type` (pilihan jenis pengajuan)<br>- Metadata awal (`title`, `department_id`, `category_id`) | - `version_id` (hidden input untuk tracking draf)<br>- Teks bantuan file master lama |
| **Validasi `doc_code`** | `required|string|max:120|unique:documents,doc_code` | `required|string|max:80|unique` (dengan pengecualian ID saat ini) |
| **Validasi `category_id`**| `required|integer|exists:categories,id` | `nullable|integer|exists:categories,id` |
| **Aturan Upload File** | File master `.doc/.docx` diwajibkan di level HTML form jika data kosong, namun di level controller berstatus `nullable`. | File master bersifat opsional baik di level HTML form maupun controller. |
| **Catatan Perubahan** | Bersifat wajib (`required|string|max:2000`). | Bersifat wajib (`required|string|max:2000`). |

---

## TASK 2 — NEW DOCUMENT VS REPLACE DOCUMENT

| Parameter Workflow | Dokumen Baru (`upload_type = new`) | Ganti Versi Lama (`upload_type = replace`) |
| --- | --- | --- |
| **Status Awal** | `approved` (jika publish langsung) / `draft` (jika save/draf) | `draft` |
| **Approval Stage Awal** | `DONE` (jika publish langsung) / `KABAG` (jika draf) | `KABAG` |
| **Version Label** | `v1` (atau label custom user jika valid) | Versi baru dihitung dinamis (`max(vN) + 1`) |
| **Promosi `current_version_id`**| Langsung diperbarui ke versi baru saat create (jika langsung publish) | Tidak berubah (tetap null / tetap versi aktif lama) |
| **Kenaikan `revision_number`** | Menjadi `1` langsung saat create (jika langsung publish) | Tidak berubah saat draf dibuat |

---

## TASK 3 — DRAFT CONTAINER AUDIT
1.  **Draft yang muncul:** Halaman `/drafts` memuat semua versi `DocumentVersion` dengan status `draft` dan `rejected`.
2.  **Hak Akses:** Semua user terotentikasi dapat mengakses halaman index. Namun, query menyaring data sehingga Viewer/Kabag hanya melihat draf buatan mereka sendiri (`created_by`), sedangkan Admin, MR, dan Director dapat melihat semua draf.
3.  **Akses Draf Milik User Lain:** Di level detail (`DraftController@show`), hak akses diperiksa secara ketat oleh helper `canViewVersion()`. User biasa tidak bisa melihat draf milik user lain (respons 403).
4.  **Draf Tidak Layak Tampil:** Draf lama yang sudah usang (superseded/obsolete) atau draf duplikat untuk dokumen yang sama tetap muncul karena filter query di index hanya didasarkan pada status `draft`/`rejected`.

---

## TASK 4 — APPROVAL REVIEW AUDIT
1.  **Otoritas Approve:** Kabag (meneruskan ke MR), MR (meneruskan ke Director), Director/Admin (menyetujui final ke status `approved`).
2.  **Otoritas Reject:** Kabag, MR, Director, Admin.
3.  **Self-Approval:** 
    *   **Di `ApprovalController`:** Diblokir secara ketat untuk MR dan Director.
    *   **Di `DocumentController@approveVersion`:** **Bocor (mungkin terjadi)**. Tidak ada validasi kepemilikan draf, sehingga Director/Admin yang membuat draf dokumen dapat menyetujui dokumennya sendiri.
4.  **Approval Log:** 
    *   **Di `ApprovalController`:** Selalu dicatat ke tabel `approval_logs`.
    *   **Di `DocumentController@approveVersion`:** **Tidak dicatat**. Persetujuan lewat endpoint ini tidak meninggalkan jejak audit di tabel `approval_logs`.

---

## TASK 5 — TOP 10 TECHNICAL DEBT

### Priority 1: ISO Compliance & Audit Trail (Tinggi)
1.  **Bypass Approval Dokumen Baru:** Dokumen Baru (Baseline v1) langsung berstatus `approved` tanpa melalui alur persetujuan Kabag/MR/Director jika opsi "Simpan & Terbitkan" dipilih.
2.  **Celah Self-Approval di `DocumentController`:** Tidak ada proteksi self-approval pada endpoint persetujuan di `DocumentController@approveVersion`.
3.  **Approval Log Bocor:** Tindakan approve/reject di `DocumentController` tidak mencatat data log ke tabel `approval_logs`.
4.  **Inkonsistensi Panjang Validasi `doc_code`:** Batas karakter maksimal `doc_code` adalah 120 pada saat Create, tetapi 80 pada saat Edit/Update.

### Priority 2: Workflow Consistency (Sedang)
5.  **Inkonsistensi Opsi `category_id`:** Diwajibkan (`required`) saat Create, tetapi diperbolehkan kosong (`nullable`) saat Edit.
6.  **`revision_number` Tidak Diperbarui di `ApprovalController`:** Saat Director menyetujui versi melalui `ApprovalController`, kolom `revision_number` dokumen tidak naik (hanya dilakukan di `DocumentController`).
7.  **Dokumen Draft Muncul di Dokumen Aktif:** Dokumen baru yang masih berstatus draft (belum pernah approved) tetap muncul di index utama `/documents` bagi seluruh user.

### Priority 3: User Experience & Data Integrity (Rendah)
8.  **Penumpukan Multi-Draf:** Sistem mengizinkan pembuatan beberapa draf aktif untuk satu dokumen yang sama, menyebabkan kerancuan versi.
9.  **Ketidakcocokan Validasi File Master:** Diwajibkan di form HTML (`required` jika dokumen baru), namun divalidasi sebagai `nullable` di backend controller.
10. **Inkonsistensi String Status:** Penggunaan kata status antara `approved` dan `published` digunakan secara bergantian dan tidak konsisten di kode program.
