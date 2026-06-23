# Phase B4: Workflow Debt Remediation Report

## 1. File Yang Diubah
Perubahan dilakukan secara minimal dan terfokus pada file-file berikut:
1. **`app/Models/DocumentVersion.php`**
   - Penyatuan logika promosi versi (`approveByDirector`).
   - Penyatuan logika penolakan versi (`rejectByRole`).
2. **`app/Http/Controllers/DocumentController.php`**
   - Refaktorisasi `handleCreateNew` untuk menonaktifkan auto-publish pada Baseline v1.
   - Refaktorisasi `approveVersion` untuk memanggil `approveByDirector` (SSOT), melakukan pengecekan self-approval, dan menulis log ke `approval_logs`.
   - Refaktorisasi `rejectVersion` untuk memanggil `rejectByRole` (SSOT).
   - Penambahan filter visibilitas pada `index` query untuk menyaring draf dokumen dari Viewer.
3. **`app/Http/Controllers/ApprovalController.php`**
   - Refaktorisasi `approve` dan `reject` untuk memanggil metode SSOT pada model `DocumentVersion`.

---

## 2. Single Source of Truth (SSOT) Yang Dipilih
Dipilih metode **`DocumentVersion::approveByDirector()`** dan **`DocumentVersion::rejectByRole()`** sebagai satu-satunya lokasi resmi untuk melakukan mutasi status versi dokumen dan atribut-atribut terkait pada dokumen:
- `current_version_id`
- `revision_number` (meningkat secara otomatis menggunakan `$doc->revision_number = max(1, (int)($doc->revision_number ?? 0) + 1)`)
- `revision_date`
- `status = superseded` pada versi terdahulu
- Pencatatan transaksi persetujuan ke tabel `approval_logs` secara otomatis.

---

## 3. Resolusi TD-01 sampai TD-04

### TD-01: Baseline v1 Wajib Mengikuti Workflow
- Logika bypass publish di `DocumentController@handleCreateNew` telah dihapus.
- Dokumen baru yang diunggah akan otomatis memiliki status `draft` dengan `approval_stage = KABAG` dan tidak mengisi `current_version_id` maupun `revision_number`. Dokumen harus melalui proses pengajuan dan persetujuan bertahap.

### TD-02: Tutup Celah Self-Approval
- Di `DocumentController@approveVersion` dan `ApprovalController@approve`, ditambahkan pengecekan kepemilikan draf:
  ```php
  if ($version->created_by === $user->id) { ... }
  ```
  Ini memblokir MR, Director, dan Admin dari memberikan persetujuan pada draf dokumen yang mereka buat sendiri.

### TD-03: Pencatatan Log Audit (`approval_logs`)
- Seluruh mutasi persetujuan (`approve`), penolakan (`reject`), maupun penerusan (`forward`) telah diintegrasikan langsung ke dalam metode SSOT (`approveByDirector`, `rejectByRole`) dan penanganan MR forward, memastikan entri di `approval_logs` selalu bertambah saat aksi berhasil dilakukan.

### TD-04: Filter Visibilitas Dokumen Bagi Viewer
- Di `DocumentController@index`, ditambahkan filter query:
  ```php
  $isModerator = $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'mr', 'director']);
  // ...
  ->when(!$isModerator, function ($q) {
      $q->whereNotNull('current_version_id');
  })
  ```
  This ensures that Viewer and user umum tidak dapat melihat dokumen baru berstatus draf (yang memiliki `current_version_id = null`) pada Documents Index.

---

## 5. Risiko Tersisa
- **Role Sync:** Jika ada modifikasi custom di masa mendatang pada relasi role user di luar library Spatie Permission default, logika resolusi role string di model `DocumentVersion` mungkin memerlukan pembaruan. Namun, fallback `director` telah disiapkan untuk memastikan logging audit tidak pernah gagal (crash).
