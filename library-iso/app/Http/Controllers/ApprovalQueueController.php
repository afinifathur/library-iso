<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\DocumentVersion;

class ApprovalQueueController extends Controller
{
    public function index()
    {
        // show versions that need attention
        $queue = DocumentVersion::with('document','document.department')
            ->whereIn('status', ['submitted','under_review'])
            ->orderBy('created_at','desc')
            ->paginate(30);

        return view('approval.index', compact('queue'));
    }
}
