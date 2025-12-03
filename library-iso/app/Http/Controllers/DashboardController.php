<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Department;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // cache entire dashboard payload for 30 seconds to reduce DB load
        $data = Cache::remember('dashboard.payload', 30, function () {
            // top-level counters
            $totalDocuments = Document::count();
            $totalVersions = DocumentVersion::count();

            // statuses
            $pendingCount = DocumentVersion::whereIn('status', ['draft','pending','submitted'])->count();
            $approvedCount = DocumentVersion::where('status', 'approved')->count();
            $rejectedCount = DocumentVersion::where('status','rejected')->count();
            $otherCount = $totalVersions - ($pendingCount + $approvedCount + $rejectedCount);
            if ($otherCount < 0) $otherCount = 0;

            // recent activity: latest 12 versions uploaded
            $recentVersions = DocumentVersion::with('document','creator')
                ->orderByDesc('created_at')
                ->take(12)
                ->get();

            // per-department stats
            $departments = Department::orderBy('code')->get()->map(function($d){
                $docCount = $d->documents()->count();
                $pending = \DB::table('document_versions as dv')
                    ->join('documents as d','dv.document_id','d.id')
                    ->where('d.department_id', $d->id)
                    ->whereIn('dv.status', ['draft','pending','submitted'])
                    ->count();
                return [
                    'id' => $d->id,
                    'code' => $d->code,
                    'name' => $d->name,
                    'doc_count' => $docCount,
                    'pending' => $pending,
                ];
            });

            // monthly new versions (last 6 months)
            $months = collect();
            $labels = collect();
            for ($i = 5; $i >= 0; $i--) {
                $m = Carbon::now()->subMonths($i);
                $labels->push($m->format('M Y'));
                $months->push(DocumentVersion::whereYear('created_at', $m->year)
                    ->whereMonth('created_at', $m->month)
                    ->count());
            }

            return [
                'totalDocuments' => $totalDocuments,
                'totalVersions' => $totalVersions,
                'pendingCount' => $pendingCount,
                'approvedCount' => $approvedCount,
                'rejectedCount' => $rejectedCount,
                'otherCount' => $otherCount,
                'recentVersions' => $recentVersions,
                'departments' => $departments,
                'spark_labels' => $labels->toArray(),
                'spark_data' => $months->toArray(),
            ];
        });

        // pass data to view
        return view('dashboard.index', $data);
    }
}
