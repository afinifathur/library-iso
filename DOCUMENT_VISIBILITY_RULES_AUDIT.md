# DOCUMENT VISIBILITY RULES AUDIT

## TASK 1 — CURRENT QUERY
Documents Index menampilkan **seluruh dokumen** yang terdaftar di tabel `documents`, tanpa memedulikan status versinya (baik itu `draft`, `submitted`, `rejected`, `approved`, maupun `superseded`).

### Query Aktual (`DocumentController@index`):
```php
$docs = Document::with(['department', 'currentVersion'])
    ->when($request->filled('department'), fn($q) => $q->whereHas('department', fn($qd) => $qd->where('code', $request->input('department'))))
    ->when($request->filled('search'), function ($q) use ($request) {
        $s = $request->input('search');
        $q->where(function ($qq) use ($s) {
            $qq->where('doc_code', 'like', "%{$s}%")
               ->orWhere('title', 'like', "%{$s}%")
               ->orWhereHas('versions', fn($qv) => $qv->where('plain_text', 'like', "%{$s}%"));
        });
    })
    ->orderBy('doc_code')
    ->paginate(25)
    ->appends($request->query());
```

---

## TASK 2 — ROLE ACCESSIBILITY
*   **Apakah seluruh role melihat daftar yang sama?**
    *   **Ya.** Tidak ada filter berbasis role/permission di dalam method `index`. Semua pengguna terotentikasi (Viewer, Kabag, MR, Director, Admin) disajikan dengan daftar hasil query yang sama.

---

## TASK 3 — BEHAVIOR FOR UNAPPROVED DOCUMENTS
*   **Apakah dokumen dengan `current_version_id = null` (hanya punya versi `draft`) muncul di index?**
    *   **Ya.** Dokumen tersebut tetap muncul karena query memuat semua entri di tabel `documents` tanpa filter `whereNotNull('current_version_id')`.
    *   Relasi `currentVersion` (menggunakan `latestOfMany()`) akan secara otomatis mengambil versi draft v1 tersebut untuk ditampilkan di halaman index, namun ketika Viewer membukanya, ia akan terbentur 403 Forbidden.

---

## TASK 4 — MVP RECOMMENDATION
Untuk memastikan Viewer hanya melihat dokumen resmi sedangkan Admin/MR/Director tetap bisa melihat draf:

*   **Rekomendasi Filter Query di Controller (`DocumentController@index`):**
    ```php
    $user = $request->user();
    
    $docs = Document::with(['department', 'currentVersion'])
        // Filter hak akses: Viewer/User biasa hanya melihat dokumen dengan versi aktif
        ->when(!$user->hasAnyRole(['admin', 'mr', 'director']), function ($q) {
            $q->whereNotNull('current_version_id');
        })
        // ... (filter search & department yang sudah ada) ...
    ```
    *Rekomendasi ini sangat aman, berkinerja tinggi, dan mencegah Viewer melihat dokumen yang belum dipublikasikan.*
