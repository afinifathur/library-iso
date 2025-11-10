<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Department;

class DepartmentController extends Controller
{
    public function index()
    {
        $depts = Department::orderBy('code')->withCount('documents')->get();
        return view('departments.index', compact('depts'));
    }

    public function show(Department $department)
    {
        $documents = $department->documents()->with('currentVersion')->orderBy('doc_code')->paginate(25);
        return view('departments.show', compact('department','documents'));
    }
}
