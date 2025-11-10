<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    // Jika tabel kamu bernama "audit_logs", baris ini opsional (Laravel akan menebak dengan benar).
    // protected $table = 'audit_logs';

    protected $fillable = [
        'event',
        'user_id',
        'document_id',
        'document_version_id',
        'detail',
        'ip',
    ];

    // Simpan/muat kolom detail sebagai JSON array/object secara otomatis
    protected $casts = [
        'detail' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function document()
    {
        return $this->belongsTo(\App\Models\Document::class);
    }

    public function version()
    {
        return $this->belongsTo(\App\Models\DocumentVersion::class, 'document_version_id');
    }
}
