<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories with document counts.
     */
    public function index(Request $request)
    {
        // ambil semua kategori (urut berdasarkan code jika ada, fallback ke name)
        $categories = Category::orderByRaw("COALESCE(code, name) ASC")->get();

        // untuk performa dataset kecil-menengah: loop dan hitung per kategori
        // (jika dataset besar, kita bisa gabungkan ke 1 query agregat)
        foreach ($categories as $cat) {
            // total documents in this category
            $total = DB::table('documents')
                ->where('category_id', $cat->id)
                ->count();

            // count documents whose latest version is approved
            $active = DB::table('documents')
                ->join('document_versions', function ($join) {
                    $join->on('documents.id', '=', 'document_versions.document_id')
                        ->whereRaw('document_versions.id = (SELECT MAX(dv.id) FROM document_versions dv WHERE dv.document_id = documents.id)');
                })
                ->where('documents.category_id', $cat->id)
                ->where('document_versions.status', 'approved')
                ->count();

            // count documents whose latest version is in-progress (draft/submitted/pending)
            $inProgress = DB::table('documents')
                ->join('document_versions', function ($join) {
                    $join->on('documents.id', '=', 'document_versions.document_id')
                        ->whereRaw('document_versions.id = (SELECT MAX(dv.id) FROM document_versions dv WHERE dv.document_id = documents.id)');
                })
                ->where('documents.category_id', $cat->id)
                ->whereIn('document_versions.status', ['draft', 'submitted', 'pending'])
                ->count();

            // attach counts to model instance for view usage
            $cat->total = (int) $total;
            $cat->active_count = (int) $active;
            $cat->in_progress_count = (int) $inProgress;
        }

        return view('categories.index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Show documents for a category.
     */
    public function show(Category $category)
    {
        $documents = $category->documents()
            ->with(['currentVersion', 'department'])
            ->orderBy('doc_code')
            ->paginate(25);

        return view('categories.show', compact('category', 'documents'));
    }
}
