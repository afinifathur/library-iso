<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentVersion extends Model
{
    // Jika kamu menggunakan guarded, kamu bisa ganti dengan protected $guarded = [];
    protected $fillable = [
        'document_id',
        'version_label',
        'status',
        'approval_stage',     // stage saat ini (KABAG / MR / DIRECTOR / DONE)
        'created_by',
        'file_path',
        'file_mime',
        'checksum',
        'change_note',
        'signed_by',
        'signed_at',
        'plain_text',
        'pasted_text',
        'diff_summary',
        'summary_changed',
        'prev_version_id',
        // approval / audit fields (opsional — tambahkan jika kolom ada)
        'approval_note',
        'approval_notes',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'reject_reason',
    ];

    protected $casts = [
        'signed_at'     => 'datetime',
        'approved_at'   => 'datetime',
        'rejected_at'   => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'diff_summary'  => 'array',
    ];

    /**
     * Versi ini milik 1 dokumen.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    /**
     * Ambil department lewat relasi document (helper sederhana).
     * Contoh penggunaan: $version->document->department
     * (tidak perlu method tersendiri, tapi bisa dibuat accessor jika sering digunakan)
     */

    /**
     * Versi sebelumnya (jika ada).
     */
    public function prevVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'prev_version_id');
    }

    /**
     * Versi selanjutnya (jika ada).
     *
     * Catatan: 'hasOne' valid karena versi lain akan menyimpan prev_version_id pointing ke current.
     * Alternatif: gunakan belongsToMany/self-relasi jika struktur berbeda.
     */
    public function nextVersion(): HasOne
    {
        return $this->hasOne(self::class, 'prev_version_id');
    }

    /**
     * User yang membuat versi ini.
     * Alias 'creator' dan 'created_by_user' berguna di controller/view.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Alias yang beberapa view/controller gunakan.
     */
    public function created_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ---------------------------
       Query scopes (optional)
       --------------------------- */

    /**
     * Scope: versi yang sedang pending / menunggu approval.
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['submitted', 'under_review', 'draft']);
    }

    /**
     * Scope: versi yang aktif / approved.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
