<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $query = Blog::latest('id');

        // Status Filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search Filter
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('details', 'like', "%{$search}%");
            });
        }

        // Date Range
        if ($request->filled('from_date')) {
            $query->whereDate('publish_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('publish_date', '<=', $request->to_date);
        }

        $perPage = (int) ($request->per_page ?? 10);
        $blogs = $query->paginate($perPage);

        // Stats
        $total = Blog::count();
        $active = Blog::where('status', 'active')->count();
        $inactive = Blog::where('status', 'inactive')->count();
        $scheduled = Blog::where('status', 'active')->where('publish_date', '>', Carbon::today())->count();
        $totalViews = Blog::sum('views');

        return response()->json([
            'status' => true,
            'data' => $blogs->items(),
            'pagination' => [
                'current_page' => $blogs->currentPage(),
                'last_page' => $blogs->lastPage(),
                'per_page' => $blogs->perPage(),
                'total' => $blogs->total(),
            ],
            'stats' => [
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive,
                'scheduled' => $scheduled,
                'total_views' => $totalViews,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('blogs', 'slug')->whereNull('deleted_at'),
            ],
            'details' => 'nullable|string',
            'publish_date' => 'required|date',
            'status' => 'required|in:active,inactive',
            'img' => 'nullable',
        ]);

        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']);
        
        // Permanently clear any previous soft-deleted blog with this slug to prevent duplicate key constraint
        Blog::withTrashed()->where('slug', $slug)->forceDelete();

        $originalSlug = $slug;
        $count = 1;
        while (Blog::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        $imgPath = null;
        if ($request->hasFile('img')) {
            $file = $request->file('img');
            $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $destinationPath = public_path('uploads/blogs');
            if (!File::isDirectory($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true, true);
            }
            $file->move($destinationPath, $fileName);
            $imgPath = '/uploads/blogs/' . $fileName;
        } elseif ($request->filled('img') && is_string($request->img)) {
            $imgPath = $request->img;
        }

        $blog = Blog::create([
            'title' => $validated['title'],
            'slug' => $slug,
            'details' => $validated['details'] ?? '',
            'publish_date' => $validated['publish_date'],
            'status' => $validated['status'],
            'img' => $imgPath,
            'views' => 0,
            'likes' => 0,
            'shares' => 0,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Blog post created successfully.',
            'data' => $blog,
        ], 201);
    }

    public function show($id)
    {
        $blog = Blog::findOrFail($id);
        return response()->json([
            'status' => true,
            'data' => $blog,
        ]);
    }

    public function update(Request $request, $id)
    {
        $blog = Blog::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('blogs', 'slug')->ignore($id)->whereNull('deleted_at'),
            ],
            'details' => 'nullable|string',
            'publish_date' => 'required|date',
            'status' => 'required|in:active,inactive',
            'img' => 'nullable',
        ]);

        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']);

        $imgPath = $blog->img;
        if ($request->hasFile('img')) {
            $file = $request->file('img');
            $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $destinationPath = public_path('uploads/blogs');
            if (!File::isDirectory($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true, true);
            }
            $file->move($destinationPath, $fileName);
            $imgPath = '/uploads/blogs/' . $fileName;
        } elseif ($request->filled('img') && is_string($request->img)) {
            $imgPath = $request->img;
        }

        $blog->update([
            'title' => $validated['title'],
            'slug' => $slug,
            'details' => $validated['details'],
            'publish_date' => $validated['publish_date'],
            'status' => $validated['status'],
            'img' => $imgPath,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Blog updated successfully.',
            'data' => $blog,
        ]);
    }

    public function updateWithFile(Request $request, $id)
    {
        return $this->update($request, $id);
    }

    public function toggleStatus($id)
    {
        $blog = Blog::findOrFail($id);
        $blog->status = $blog->status === 'active' ? 'inactive' : 'active';
        $blog->save();

        return response()->json([
            'status' => true,
            'message' => 'Blog status updated successfully.',
            'status_val' => $blog->status,
        ]);
    }

    public function destroy($id)
    {
        $blog = Blog::withTrashed()->findOrFail($id);
        $blog->forceDelete();

        return response()->json([
            'status' => true,
            'message' => 'Blog deleted successfully.',
        ]);
    }
}
