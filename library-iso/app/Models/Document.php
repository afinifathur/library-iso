<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'doc_code',
        'title',
        'description',
        'department_id',
        'revision_number',
        'revision_date',
    ];

    /**
     * Cast tanggal ke Carbon instances
     */
    protected $casts = [
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'revision_date' => 'datetime',
    ];

    /**
     * Relasi ke Department
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Semua versi dokumen
     */
    public function versions()
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /**
     * Versi terbaru (latest)
     */
    public function currentVersion()
    {
        return $this->hasOne(DocumentVersion::class)->latestOfMany();
    }
}
