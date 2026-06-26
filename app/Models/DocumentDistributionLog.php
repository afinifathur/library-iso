<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class DocumentDistributionLog extends Model
{
    public $timestamps = false; // We only need created_at

    protected $fillable = [
        'document_id',
        'document_version_id',
        'doc_code',
        'document_title',
        'version_label',
        'user_id',
        'user_name',
        'user_email',
        'user_role',
        'user_department',
        'action',
        'trace_id',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Relationship to Document
     */
    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    /**
     * Relationship to Document Version
     */
    public function version()
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    /**
     * Log a new distribution action and return the generated Trace ID.
     *
     * Return type changed from void → string so that callers (e.g. downloadVersion)
     * can embed the same Trace ID in the Controlled Copy footer stamp.
     * Existing callers that ignore the return value are unaffected.
     */
    public static function log(DocumentVersion $version, string $action): string
    {
        $user = Auth::user();
        $document = $version->document;

        $deptName = ($user && $user->relationLoaded('department') && $user->department)
            ? $user->department->name
            : (($user && method_exists($user, 'department') && $user->department) ? $user->department->name : '-');

        $roleName = ($user && method_exists($user, 'getRoleNames'))
            ? $user->getRoleNames()->first()
            : '-';

        $traceId = self::generateTraceId();

        self::create([
            'document_id'         => $document ? $document->id : null,
            'document_version_id' => $version->id,
            'doc_code'            => $document ? $document->doc_code : null,
            'document_title'      => $document ? $document->title : null,
            'version_label'       => $version->version_label,
            'user_id'             => $user ? $user->id : null,
            'user_name'           => $user ? $user->name : 'System/Guest',
            'user_email'          => $user ? $user->email : 'guest@peroniks.com',
            'user_role'           => $roleName ?: '-',
            'user_department'     => $deptName ?: '-',
            'action'              => $action,
            'trace_id'            => $traceId,
            'ip_address'          => request()->ip() ?: '127.0.0.1',
            'created_at'          => now(),
        ]);

        return $traceId;
    }

    /**
     * Generate sequential Trace ID: DISTR-YYYYMMDD-XXXXXX
     */
    public static function generateTraceId(): string
    {
        $today = now()->format('Ymd');
        $count = self::whereRaw("DATE(created_at) = ?", [now()->toDateString()])->count();
        
        $num = $count + 1;
        do {
            $sequence = str_pad($num, 6, '0', STR_PAD_LEFT);
            $traceId = "DISTR-{$today}-{$sequence}";
            $num++;
        } while (self::where('trace_id', $traceId)->exists());

        return $traceId;
    }
}
