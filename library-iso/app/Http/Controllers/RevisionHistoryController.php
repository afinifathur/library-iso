<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\DocumentVersion;

class RevisionHistoryController extends Controller
{
    public function index()
    {
        // flat list of version history (filterable later)
        $history = DocumentVersion::with('document')->orderBy('document_id')->orderBy('created_at','desc')->paginate(50);
        return view('revision.index', compact('history'));
    }
}
