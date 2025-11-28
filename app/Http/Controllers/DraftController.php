<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\DB;

class DraftController extends Controller
{
    /**
     * List draft
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $rows = DocumentVersion::with(['document','creator'])
            ->whereIn('status', ['draft','rejected'])
            ->orderByDesc('updated_at')
            ->paginate(25);

        return view('drafts.index', compact('rows'));
    }

    /**
     * Show one draft
     */
    public function show(Request $request, DocumentVersion $version)
    {
        return view('drafts.show', compact('version'));
    }

    /**
     * EDIT draft (form)
     */
    public function edit(Request $request, DocumentVersion $version)
    {
        return view('drafts.edit', compact('version'));
    }

    /**
     * DELETE draft (soft)
     */
    public function destroy(Request $request, DocumentVersion $version)
    {
        if (!in_array($version->status, ['draft','rejected'])) {
            return back()->with('error','Only draft/rejected can be deleted.');
        }

        $version->update(['status' => 'trashed', 'approval_stage' => 'NONE']);

        return back()->with('success','Draft moved to Recycle Bin.');
    }

    /**
     * REOPEN rejected/draft → become draft again
     */
    public function reopen(Request $request, DocumentVersion $version)
    {
        $version->update([
            'status' => 'draft',
            'approval_stage' => 'KABAG'
        ]);

        return back()->with('success','Draft reopened.');
    }

    /**
     * SUBMIT DRAFT → approval queue
     */
    public function submit(Request $request, DocumentVersion $version)
    {
        $user = $request->user();
        if (!$user) abort(403);

        // Only owner or MR/Admin can submit
        if ($version->created_by != $user->id &&
            !(method_exists($user,'hasAnyRole') && $user->hasAnyRole(['mr','admin']))
        ) {
            abort(403);
        }

        // prevent double submission
        $pending = DocumentVersion::where('document_id', $version->document_id)
            ->whereIn('status', ['submitted','under_review'])
            ->exists();

        if ($pending) {
            return back()->with('error','Another pending submission exists.');
        }

        // mark as submitted
        $version->update([
            'status'        => 'submitted',
            'approval_stage'=> 'MR',
            'submitted_by'  => $user->id,
            'submitted_at'  => now(),
        ]);

        return redirect()
            ->route('approval.index')
            ->with('success','Draft submitted to MR.');
    }
}
