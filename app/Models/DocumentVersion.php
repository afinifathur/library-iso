<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DocumentVersion extends Model
{
    protected $fillable = [
    'document_id','version_label','status','approval_stage','created_by',
    'file_path','pdf_path','master_path','file_mime','checksum',
    'change_note','signed_by','signed_at',
    'plain_text','pasted_text','diff_summary','summary_changed','prev_version_id',
    'approval_note','approval_notes','approved_by','approved_at',
    'rejected_by','rejected_at','reject_reason','rejected_reason',
    'submitted_by','submitted_at'
];

    protected $casts = [
        'signed_at'     => 'datetime',
        'approved_at'   => 'datetime',
        'rejected_at'   => 'datetime',
        'submitted_at'  => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'diff_summary'  => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function prevVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'prev_version_id');
    }

    public function nextVersion(): HasOne
    {
        return $this->hasOne(self::class, 'prev_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ---------------------------
       Scopes
       --------------------------- */

    public function scopeDrafts($q)
    {
        return $q->whereIn('status', ['draft','rejected']);
    }

    public function scopePendingMR($q)
    {
        return $q->where('status','submitted')->where('approval_stage','MR');
    }

    public function scopePendingDirector($q)
    {
        return $q->where('status','submitted')->where('approval_stage','DIRECTOR');
    }

    /* ---------------------------
       Approval helpers
       --------------------------- */

    public function markSubmitted($userId = null, $nextStage = 'MR')
    {
        $this->status = 'submitted';
        $this->approval_stage = $nextStage;
        $this->submitted_by = $userId;
        $this->submitted_at = Carbon::now();
        $this->save();
    }

    public function approveByMR($userId = null)
    {
        // move to director
        $this->status = 'submitted';
        $this->approval_stage = 'DIRECTOR';
        $this->rejected_reason = null;
        $this->rejected_by = null;
        $this->rejected_at = null;
        $this->save();
    }

    public function approveByDirector($userId = null)
    {
        DB::transaction(function () use ($userId) {
            $this->status = 'approved';
            $this->approval_stage = 'DONE';
            $this->approved_by = $userId;
            $this->approved_at = Carbon::now();
            $this->rejected_reason = null;
            $this->rejected_by = null;
            $this->rejected_at = null;
            $this->save();

            // promote to document current version
            $doc = $this->document()->lockForUpdate()->first();
            if ($doc) {
                if ($doc->current_version_id && $doc->current_version_id != $this->id) {
                    $old = self::find($doc->current_version_id);
                    if ($old && ! in_array($old->status, ['approved','rejected','superseded'], true)) {
                        $old->status = 'superseded';
                        $old->save();
                    }
                }
                if (schemaHasColumn($doc->getTable(), 'current_version_id')) {
                    $doc->current_version_id = $this->id;
                } else {
                    $doc->current_version_id = $this->id;
                }
                if (schemaHasColumn($doc->getTable(), 'revision_date')) {
                    $doc->revision_date = $this->approved_at;
                } else {
                    $doc->revision_date = $this->approved_at;
                }
                $doc->approved_by = $userId;
                $doc->approved_at = $this->approved_at;
                $doc->save();
            }
        });
    }

    public function rejectByRole(string $reason = null, $userId = null)
    {
        $this->status = 'rejected';
        $this->approval_stage = 'KABAG';
        $this->rejected_reason = $reason;
        $this->rejected_by = $userId;
        $this->rejected_at = Carbon::now();
        $this->save();
    }
}
