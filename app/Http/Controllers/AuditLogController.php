<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        // Export CSV via query param ?export=csv
        if ($request->filled('export') && $request->input('export') === 'csv') {
            $headers = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => 'attachment; filename="audit_log.csv"',
            ];

            $callback = function () {
                $handle = fopen('php://output', 'w');

                // Header kolom
                fputcsv($handle, ['id', 'event', 'user', 'document', 'version', 'detail', 'ip', 'created_at']);

                \App\Models\AuditLog::with(['user', 'document', 'version'])
                    ->orderBy('created_at', 'desc')
                    ->chunk(200, function ($rows) use ($handle) {
                        foreach ($rows as $r) {
                            fputcsv($handle, [
                                $r->id,
                                $r->event,
                                $r->user->email ?? $r->user->name ?? '',
                                $r->document->doc_code ?? '',
                                $r->version->version_label ?? '',
                                is_string($r->detail) ? $r->detail : json_encode($r->detail),
                                $r->ip,
                                $r->created_at,
                            ]);
                        }
                    });

                fclose($handle);
            };

            return new StreamedResponse($callback, 200, $headers);
        }

        // List view (paginate)
        $events = \App\Models\AuditLog::with(['user', 'document', 'version'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('audit.index', compact('events'));
    }
}
