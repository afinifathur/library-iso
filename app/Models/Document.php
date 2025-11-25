<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'doc_code',
        'category',            // IK, UT, FR, ...
        'title',
        'description',
        'department_id',
        'category_id',
        'revision_number',
        'revision_date',
        'doc_number',          // nomor urut dokumen (disimpan varchar di DB, dipakai sebagai angka)
        'short_code',
        'current_version_id',
        'rejected_reason',
        'approved_by',
        'approved_at',
        'related_links',       // <== tambahan: JSON array link terkait
    ];

    /**
     * Cast tanggal & angka.
     *
     * @var array
     */
    protected $casts = [
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
        'revision_date'       => 'datetime',
        'approved_at'         => 'datetime',
        'doc_number'          => 'integer',
        'revision_number'     => 'integer',
        'department_id'       => 'integer',
        'category_id'         => 'integer',
        'approved_by'         => 'integer',
        'current_version_id'  => 'integer',
        'related_links'       => 'array',   // <== otomatis cast ke array
    ];

    /* -------------------------
     | Relationships
     |------------------------- */

    /**
     * Relasi ke Department.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Relasi ke Category (jika ada model Category terpisah).
     *
     *gunakan nama method lain (categoryRelation) agar tidak bentrok
     * dengan kolom 'category' di tabel.
     */
    public function categoryRelation(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category', 'code');
    }

    /**
     * Semua versi dokumen.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /**
     * Versi terbaru (latest).
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->latestOfMany();
    }

    /* -------------------------
     | Doc code generation
     |------------------------- */

    /**
     * Generate doc_code dari category + department code + next number.
     * Contoh output: IK.QA-FL.001
     *
     * @param  string $category  IK, UT, FR, ...
     * @param  string $deptCode  QA-FL, PPIC, MTC, ...
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public static function generateDocCode(string $category, string $deptCode): string
    {
        $category = strtoupper(trim($category));
        $deptCode = strtoupper(trim($deptCode));

        // pastikan department ada
        $dept = Department::query()->where('code', $deptCode)->first();
        if (! $dept) {
            throw new InvalidArgumentException("Department dengan code '{$deptCode}' tidak ditemukan.");
        }

        // cari nomor terakhir berdasarkan doc_number (lebih akurat & cepat)
        $lastNumber = (int) static::query()
            ->where('category', $category)
            ->where('department_id', $dept->id)
            ->max('doc_number');

        // fallback: coba parse dari doc_code terakhir jika doc_number belum dipakai historisnya
        if ($lastNumber === 0) {
            $lastDocCode = static::query()
                ->where('category', $category)
                ->where('department_id', $dept->id)
                ->orderByDesc('id')
                ->value('doc_code');

            if ($lastDocCode && preg_match('/(\d{1,})$/', $lastDocCode, $m)) {
                $lastNumber = (int) $m[1];
            }
        }

        $nextNum = $lastNumber + 1;
        $numStr  = str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);

        return "{$category}.{$deptCode}.{$numStr}";
    }

    /**
     * Auto-set doc_code & doc_number saat create jika belum diisi.
     * - Menggunakan category + department->code
     */
    protected static function booted(): void
    {
        static::creating(function (Document $doc) {
            // jika doc_code belum ada namun category & department_id tersedia
            if (empty($doc->doc_code) && ! empty($doc->category) && ! empty($doc->department_id)) {
                $dept = Department::find($doc->department_id);

                if ($dept) {
                    try {
                        $doc->doc_code = static::generateDocCode($doc->category, $dept->code);
                    } catch (InvalidArgumentException $e) {
                        // kalau department code invalid, biarkan saja
                        // bisa ditangani di layer lain kalau perlu
                    }

                    // set doc_number jika belum ada (ambil dari tail number doc_code)
                    if (
                        empty($doc->doc_number)
                        && ! empty($doc->doc_code)
                        && preg_match('/(\d{1,})$/', $doc->doc_code, $m)
                    ) {
                        $doc->doc_number = (int) $m[1];
                    }
                }
            }
        });
    }
}
