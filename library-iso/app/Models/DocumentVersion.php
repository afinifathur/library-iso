<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentVersion extends Model
{
    protected $fillable = [
        'document_id',
        'version_label',
        'status',
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
    ];

    protected $casts = [
        'signed_at'     => 'datetime',
        'diff_summary'  => 'array',
    ];

    /**
     * Relasi: versi ini milik 1 dokumen
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    /**
     * Versi sebelumnya (jika ada)
     */
    public function prevVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'prev_version_id');
    }

    /**
     * Versi selanjutnya (jika ada)
     */
    public function nextVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class, 'prev_version_id');
    }

    /**
     * User yang membuat versi ini
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
