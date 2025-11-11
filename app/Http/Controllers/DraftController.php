<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DocumentVersion;

class DraftController extends Controller
{
    public function index(Request $request)
    {
        // show versions that are 'draft' or 'rejected'
        $query = DocumentVersion::with(['document','creator'])
            ->whereIn('status', ['draft','rejected'])
            ->orderByDesc('updated_at');

        $user = $request->user();

        // if non-admin and has department, limit to that department's documents
        if ($user && $user->department_id && ! $user->hasAnyRole(['admin','mr','director'])) {
            $query->whereHas('document', function($q) use($user){
                $q->where('department_id', $user->department_id);
            });
        }

        $versions = $query->paginate(30);
        return view('drafts.index', compact('versions'));
    }
}
