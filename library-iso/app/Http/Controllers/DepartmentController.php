<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Department;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    /**
     * Tampilkan daftar departemen
     */
    public function index()
    {
        // Ambil semua departemen, urutkan alfabet
        $departments = Department::orderBy('code')->get();

        return view('departments.index', compact('departments'));
    }

    /**
     * Tampilkan detail departemen + dokumen + relasi
     */
    public function show(Request $request, Department $department)
    {
        // Ambil dokumen di departemen ini, include versions (urut desc)
        $docs = Document::where('department_id', $department->id)
            ->with(['versions' => function ($q) {
                $q->orderByDesc('id');
            }])
            ->orderBy('short_code')
            ->orderBy('doc_code')
            ->get();

        // Group berdasarkan short_code (IK, SOP, FORM, dll)
        $groups = $docs->groupBy(function ($doc) {
            return $doc->short_code ?? 'Uncategorized';
        });

        // Ambil dokumen yang berelasi dengan dokumen pada departemen ini
        $related = DB::table('document_relations as dr')
            ->join('documents as d1', 'dr.document_id', '=', 'd1.id')
            ->join('documents as d2', 'dr.related_document_id', '=', 'd2.id')
            ->select(
                'd1.id as doc_id',
                'd1.doc_code as doc_code',
                'd1.title as title',
                'd2.id as related_to',
                'd2.doc_code as related_code',
                'd2.title as related_title'
            )
            ->where('d2.department_id', $department->id)
            ->get();

        return view('departments.show', [
            'department' => $department,
            'groups'     => $groups,
            'related'    => $related,
        ]);
    }
}
