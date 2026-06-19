# DOCUMENT DIFF DATA AUDIT
**Project:** Library-ISO — PT Peroni Karya Sentra  
**Date of Audit:** June 19, 2026  
**Auditor:** Antigravity (Advanced Agentic Coding AI)

---

## Statistik Global Availability Data Plain Text

Berikut adalah hasil audit ketersediaan data teks untuk perbandingan versi dokumen:

```text
Total Versions              : 422
Has Plain Text             : 404
Missing Plain Text         : 18
Has Pasted Text            : 251
Has PDF                    : 422
Has Master                 : 422
Ready For Diff             : 404
Need Re-Extraction         : 18
```

### Detil Hasil Analisis:
1. **Total Versions (422):** Total keseluruhan baris pada tabel `document_versions` di database.
2. **Has Plain Text (404):** Jumlah versi yang memiliki teks pada kolom `plain_text` (siap dibandingkan).
3. **Missing Plain Text (18):** Jumlah versi yang tidak memiliki teks (`NULL` atau kosong) pada kolom `plain_text`.
4. **Has Pasted Text (251):** Jumlah versi yang memiliki teks yang dimasukkan/ditempel secara manual oleh pengguna pada kolom `pasted_text`.
5. **Has PDF (422) & Has Master (422):** Seluruh versi dokumen di database memiliki data file path PDF (`pdf_path`) dan file master (`master_path`).
6. **Ready For Diff (404):** Versi dokumen yang siap dibandingkan langsung menggunakan plain text yang tersedia.
7. **Need Re-Extraction (18):** Terdapat 18 versi yang memiliki path file PDF/Word tetapi tidak memiliki data teks terekstraksi pada kolom `plain_text`. Versi-versi ini membutuhkan proses ekstraksi ulang (re-extraction) menggunakan command:
   ```bash
   php artisan reextract:versions
   ```
   atau
   ```bash
   php artisan documents:extract-text
   ```
   untuk mengekstraksi teks dari file fisik ke database agar dapat dibandingkan.
