<?php

namespace App\Http\Controllers;

use App\Models\CrmPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CrmPageController extends Controller
{
    public function index(Request $request)
    {
        $query = CrmPage::orderBy('position', 'asc')->latest('id');

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
                  ->orWhere('detail', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($request->per_page ?? 10);
        $pages = $query->paginate($perPage);

        // Stats
        $total = CrmPage::count();
        $active = CrmPage::where('status', 'active')->count();
        $inactive = CrmPage::where('status', 'inactive')->count();

        return response()->json([
            'status' => true,
            'data' => $pages->items(),
            'pagination' => [
                'current_page' => $pages->currentPage(),
                'last_page' => $pages->lastPage(),
                'per_page' => $pages->perPage(),
                'total' => $pages->total(),
            ],
            'stats' => [
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive,
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
                Rule::unique('crm_pages', 'slug')->whereNull('deleted_at'),
            ],
            'detail' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'position' => 'required|integer|min:0|max:5',
            'img' => 'nullable',
        ]);

        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']);
        
        // Permanently clear any previous soft-deleted page with this slug to prevent duplicate key constraint
        CrmPage::withTrashed()->where('slug', $slug)->forceDelete();

        $originalSlug = $slug;
        $count = 1;
        while (CrmPage::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        $imgPath = null;
        if ($request->hasFile('img')) {
            $file = $request->file('img');
            $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $destinationPath = public_path('uploads/crm');
            if (!File::isDirectory($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true, true);
            }
            $file->move($destinationPath, $fileName);
            $imgPath = '/uploads/crm/' . $fileName;
        } elseif ($request->filled('img') && is_string($request->img)) {
            $imgPath = $request->img;
        }

        $crmPage = CrmPage::create([
            'title' => $validated['title'],
            'slug' => $slug,
            'detail' => $validated['detail'] ?? '',
            'status' => $validated['status'],
            'position' => $validated['position'],
            'img' => $imgPath,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'CRM Page created successfully.',
            'data' => $crmPage,
        ], 201);
    }

    public function show($id)
    {
        $page = CrmPage::findOrFail($id);
        return response()->json([
            'status' => true,
            'data' => $page,
        ]);
    }

    public function update(Request $request, $id)
    {
        $page = CrmPage::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('crm_pages', 'slug')->ignore($id)->whereNull('deleted_at'),
            ],
            'detail' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'position' => 'required|integer|min:0|max:5',
            'img' => 'nullable',
        ]);

        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']);

        $imgPath = $page->img;
        if ($request->hasFile('img')) {
            $file = $request->file('img');
            $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $destinationPath = public_path('uploads/crm');
            if (!File::isDirectory($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true, true);
            }
            $file->move($destinationPath, $fileName);
            $imgPath = '/uploads/crm/' . $fileName;
        } elseif ($request->filled('img') && is_string($request->img)) {
            $imgPath = $request->img;
        }

        $page->update([
            'title' => $validated['title'],
            'slug' => $slug,
            'detail' => $validated['detail'],
            'status' => $validated['status'],
            'position' => $validated['position'],
            'img' => $imgPath,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'CRM Page updated successfully.',
            'data' => $page,
        ]);
    }

    public function updateWithFile(Request $request, $id)
    {
        return $this->update($request, $id);
    }

    public function toggleStatus($id)
    {
        $page = CrmPage::findOrFail($id);
        $page->status = $page->status === 'active' ? 'inactive' : 'active';
        $page->save();

        return response()->json([
            'status' => true,
            'message' => 'Status updated successfully.',
            'status_val' => $page->status,
        ]);
    }

    public function destroy($id)
    {
        $page = CrmPage::withTrashed()->findOrFail($id);
        $page->forceDelete();

        return response()->json([
            'status' => true,
            'message' => 'CRM Page deleted successfully.',
        ]);
    }
}
