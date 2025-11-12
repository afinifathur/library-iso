<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Department;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Show dashboard.
     *
     * - Produce: basic counts, weekly buckets (last 26 weeks),
     *   donut status counts, recent activity and recent published lists.
     *
     * Note: if your DB uses different status names, adjust the status arrays accordingly.
     */
    public function index(Request $request)
    {
        // basic counts
        $totalDocuments = Document::count();
        $totalVersions  = DocumentVersion::count();

        // status counts (adjust status names if your app uses different values)
        $pendingCount   = DocumentVersion::where('status', 'draft')->count();
        $approvedCount  = DocumentVersion::whereIn('status', ['approved', 'published'])->count();
        $rejectedCount  = DocumentVersion::where('status', 'rejected')->count();

        // weekly buckets (last 26 weeks)
        $weeks = [];
        $counts = [];
        $now = Carbon::now();

        // Build 26 weeks: W-25 ... W0 (week start = Monday)
        for ($i = 25; $i >= 0; $i--) {
            $start = $now->copy()->startOfWeek()->subWeeks($i); // Monday
            $end   = $start->copy()->endOfWeek();               // Sunday
            $weeks[] = $start->format('d M');                   // e.g. "07 Jul"
            $counts[] = DocumentVersion::whereBetween('created_at', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])->count();
        }

        // donut values
        $pending  = $pendingCount;
        $approved = $approvedCount;
        $rejected = $rejectedCount;
        $other    = max(0, $totalVersions - ($pending + $approved + $rejected));

        // recent lists (eager load to avoid N+1)
        $recentActivity = DocumentVersion::with(['document', 'creator'])
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $recentPublished = DocumentVersion::with('document')
            ->whereIn('status', ['approved', 'published'])
            ->orderByDesc('signed_at')
            ->limit(10)
            ->get();

        // return view with compacted data
        return view('dashboard.index', compact(
            'totalDocuments',
            'totalVersions',
            'pendingCount',
            'approvedCount',
            'rejectedCount',
            'weeks',
            'counts',
            'pending',
            'approved',
            'rejected',
            'other',
            'recentActivity',
            'recentPublished'
        ));
    }
}
