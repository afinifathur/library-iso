<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model
{
    protected $fillable = [
        'document_id','version_label','status','created_by',
        'file_path','file_mime','checksum','change_note','signed_by','signed_at',
        'plain_text','pasted_text','diff_summary','summary_changed','prev_version_id'
    ];

    protected $dates = ['signed_at','created_at','updated_at'];

    protected $casts = [
        'diff_summary' => 'array'
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function prevVersion()
    {
        return $this->belongsTo(DocumentVersion::class, 'prev_version_id');
    }

    public function nextVersion()
    {
        return $this->hasOne(DocumentVersion::class, 'prev_version_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
