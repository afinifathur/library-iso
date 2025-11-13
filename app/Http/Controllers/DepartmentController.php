<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Department;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    /**
     * Tampilkan daftar department beserta PIC (dari mapping) dan hitungan dokumen aktif / pending.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Definisikan status yang dianggap "aktif" / "published"
        $activeStatuses = ['approved', 'published'];

        // Status yang dianggap pending / in progress
        $pendingStatuses = ['draft', 'submitted', 'in_review', 'pending'];

        // Ambil daftar department beserta hitungan dokumen aktif & pending
        // withCount menggunakan relationship 'documents' pada model Department
        $departments = Department::withCount([
            // hitung dokumen yang memiliki minimal 1 versi dengan status aktif
            'documents as active_count' => function ($q) use ($activeStatuses) {
                $q->whereHas('versions', function ($v) use ($activeStatuses) {
                    $v->whereIn('status', $activeStatuses);
                });
            },
            // hitung dokumen yang memiliki minimal 1 versi dengan status pending/in progress
            'documents as pending_count' => function ($q) use ($pendingStatuses) {
                $q->whereHas('versions', function ($v) use ($pendingStatuses) {
                    $v->whereIn('status', $pendingStatuses);
                });
            },
        ])->orderBy('code')->get();

        // Mapping PIC manual (bukan relasi user)
        $picList = [
            'DIR' => 'Direktur',
            'MR' => 'Management Representative',
            'PRS' => 'Manajer HR',
            'PPIC' => 'Manajer PPIC',
            'MKT-EXIM' => 'Manajer Marketing-Exim',
            'PBL' => 'Manajer Pembelian',
            'PRD-FL' => 'Manajer Produksi Flange',
            'PRD-PF' => 'Manajer Produksi Fitting',
            'QA-FL' => 'Kabag QC Flange',
            'QA-PF' => 'Kabag QC Fitting',
            'QA-BHN' => 'Supervisor QC Bahan Baku',
            'QA-AL' => 'Supervisor QC Aluminium',
            'MTC' => 'Kabag Maintenance',
            'TAX' => 'Manajer Pajak',
            'ACC & FIN' => 'Manajer Accounting & Finance',
            'GUD-JFL' => 'Supervisor Gudang Jadi Flange',
            'GUD-JPF' => 'Supervisor Gudang Jadi Fitting',
            'GUD-BHN' => 'Supervisor Gudang Bahan',
            'PAM' => 'Kepala Keamanan',
            'LILIN-PF' => 'Supervisor Lilin Fitting',
            'COR-PF' => 'Kabag Cor Fitting',
            'COR-FL' => 'Kabag Cor Flange',
            'NT-PF' => 'Supervisor Netto Fitting',
            'NT-FL' => 'Supervisor Netto Flange',
            'BBT-FL' => 'Supervisor Bubut Flange',
            'BOR-FL' => 'Supervisor Bor Flange',
            'BBT-PF' => 'Kabag Bubut Fitting',
            'MNJ' => 'Manajemen',
        ];

        // Render view dengan data
        return view('departments.index', compact('departments', 'picList'));
    }

    /**
     * Show a department and its documents (with versions), active count and related documents.
     * Tetap tersedia bila diperlukan halaman detail.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Department $department
     * @return \Illuminate\View\View
     */
    public function show(Request $request, Department $department)
    {
        $activeStatusList = ['approved', 'published'];

        // Ambil dokumen departemen dengan versi (terbaru diurutkan)
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

        // Ambil dokumen terkait (fallback aman jika tabel relasi tidak ada)
        $related = collect();
        try {
            if (DB::getSchemaBuilder()->hasTable('document_relations')) {
                $myDocIds = $documents->pluck('id')->filter()->values()->all();

                if (!empty($myDocIds)) {
                    $related = DB::table('document_relations as dr')
                        ->join('documents as d', function($join) use ($myDocIds) {
                            // Join aman: pilih sisi yang bukan dari myDocIds
                            $join->on('d.id', '=', DB::raw('CASE WHEN dr.related_document_id IN (' . implode(',', array_map('intval', $myDocIds)) . ') THEN dr.document_id ELSE dr.related_document_id END'));
                        })
                        ->select('d.id', 'd.doc_code', 'd.title', 'd.department_id')
                        ->where(function ($q) use ($myDocIds) {
                            $q->whereIn('dr.document_id', $myDocIds)
                              ->orWhereIn('dr.related_document_id', $myDocIds);
                        })
                        ->groupBy('d.id', 'd.doc_code', 'd.title', 'd.department_id')
                        ->get();
                }
            }
        } catch (\Throwable $e) {
            $related = collect();
        }

        return view('departments.show', compact('department', 'documents', 'activeCount', 'related'));
    }
}
