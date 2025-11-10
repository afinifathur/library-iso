<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Department;

class DashboardController extends Controller
{
    private const PENDING_STATUSES = ['draft', 'submitted', 'under_review', 'rejected'];
    private const PUBLISHED_STATUS = 'approved';

    public function index(): View
    {
        // Aggregates
        $totalDocs        = Document::query()->count();
        $totalVersions    = DocumentVersion::query()->count();
        $pendingRevisions = DocumentVersion::query()
            ->whereIn('status', self::PENDING_STATUSES)
            ->count();
        $published        = DocumentVersion::query()
            ->where('status', self::PUBLISHED_STATUS)
            ->count();

        // Per-department counts
        $byDept = Department::query()
            ->withCount('documents')
            ->orderBy('code')                 // ganti ke kolom yang ada jika berbeda (mis. 'name')
            ->get(['id', 'name', 'code']);    // sesuaikan dengan skema kamu

        // Recent updates (hindari N+1) — pilih kolom yang dipastikan ada
        $recent = DocumentVersion::query()
            ->with(['document:id,title'])     // ambil kolom ringkas dari relasi
            ->latest('created_at')
            ->take(10)
            ->get(['id', 'document_id', 'status', 'created_at']);

        return view('dashboard.index', compact(
            'totalDocs',
            'totalVersions',
            'pendingRevisions',
            'published',
            'byDept',
            'recent'
        ));
    }
}
