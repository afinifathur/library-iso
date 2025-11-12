<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Department;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    /**
     * List all departments with PIC (if relation exists) and active document count.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Sesuaikan status yang dianggap "aktif" atau "published" pada aplikasi Anda
        $activeStatusList = ['approved', 'published'];

        $departments = Department::query()
            // hitung dokumen yang memiliki minimal satu version dengan status aktif
            ->withCount(['documents as active_documents_count' => function ($q) use ($activeStatusList) {
                $q->whereHas('versions', function ($v) use ($activeStatusList) {
                    $v->whereIn('status', $activeStatusList);
                });
            }])
            // eager-load PIC/manager jika relasi didefinisikan pada model Department
            ->with(['manager'])
            ->orderBy('code')
            ->get();

        return view('departments.index', compact('departments'));
    }

    /**
     * Show a department and its documents (with versions), active count and related documents.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Department    $department
     * @return \Illuminate\View\View
     */
    public function show(Request $request, Department $department)
    {
        $activeStatusList = ['approved', 'published'];

        // Ambil dokumen departemen dengan versions (versi terbaru diurutkan)
        $documents = Document::with(['versions' => function ($q) {
                $q->orderByDesc('created_at');
            }])
            ->where('department_id', $department->id)
            ->orderBy('doc_code')
            ->get();

        // Hitung jumlah dokumen yang memiliki versi aktif
        $activeCount = $documents->filter(function ($doc) use ($activeStatusList) {
            return $doc->versions->contains(function ($v) use ($activeStatusList) {
                return in_array($v->status, $activeStatusList, true);
            });
        })->count();

        // Cari dokumen terkait dengan fallback aman jika tabel relasi tidak ada
        $related = collect();
        try {
            if (DB::getSchemaBuilder()->hasTable('document_relations')) {
                $myDocIds = $documents->pluck('id')->filter()->values()->all();

                if (!empty($myDocIds)) {
                    // Ambil dokumen yang terkait (kedua arah) dan kelompokkan unik
                    $related = DB::table('document_relations as dr')
                        ->join('documents as d', 'd.id', '=', DB::raw('CASE WHEN dr.related_document_id IN (' . implode(',', array_map('intval', $myDocIds)) . ') THEN dr.document_id ELSE dr.related_document_id END'))
                        ->select('d.id', 'd.doc_code', 'd.title', 'd.department_id')
                        // cari relasi di mana salah satu sisi adalah dokumen kita
                        ->where(function ($q) use ($myDocIds) {
                            $q->whereIn('dr.document_id', $myDocIds)
                              ->orWhereIn('dr.related_document_id', $myDocIds);
                        })
                        ->groupBy('d.id', 'd.doc_code', 'd.title', 'd.department_id')
                        ->get();
                }
            }
        } catch (\Throwable $e) {
            // Jika terjadi error (mis. schema builder tidak tersedia), lanjutkan dengan koleksi kosong
            $related = collect();
        }

        return view('departments.show', compact('department', 'documents', 'activeCount', 'related'));
    }
}
