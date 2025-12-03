<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('name')->withCount('documents')->get();
        return view('categories.index', compact('categories'));
    }

    public function show(Category $category)
    {
        $documents = $category->documents()->with('currentVersion','department')->orderBy('doc_code')->paginate(25);
        return view('categories.show', compact('category','documents'));
    }
}
