<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\CrmPage;
use App\Models\ContactInquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VisitorController extends Controller
{
    /**
     * 1. GET Visitor Blogs: Only active blogs whose publish_date is <= today
     */
    public function getBlogs(Request $request)
    {
        $today = Carbon::today()->format('Y-m-d');

        $query = Blog::where('status', 'active')
            ->whereNotNull('publish_date')
            ->whereDate('publish_date', '<=', $today)
            ->orderBy('publish_date', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('limit')) {
            $blogs = $query->limit((int) $request->limit)->get();
        } else {
            $blogs = $query->get();
        }

        $formatted = $blogs->map(function ($b) {
            // Generate clean snippet without HTML tags
            $rawText = strip_tags($b->details ?? '');
            $snippet = StrLimit($rawText, 140);

            return [
                'id' => $b->id,
                'title' => $b->title,
                'slug' => $b->slug,
                'snippet' => $snippet,
                'image' => $b->img ?: '/visitor/home/1775506047_Tata-Safari-Facelift-1160x653.webp',
                'publish_date' => $b->publish_date ? Carbon::parse($b->publish_date)->format('M d, Y') : '',
                'publish_date_raw' => $b->publish_date ? Carbon::parse($b->publish_date)->format('Y-m-d') : '',
                'likes' => (int) $b->likes,
                'views' => (int) $b->views,
                'shares' => (int) $b->shares,
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $formatted,
        ]);
    }

    /**
     * 2. GET Single Blog by Slug: Only if active and published
     */
    public function getBlogBySlug($slug)
    {
        $today = Carbon::today()->format('Y-m-d');

        $blog = Blog::where('slug', $slug)
            ->where('status', 'active')
            ->whereDate('publish_date', '<=', $today)
            ->first();

        if (!$blog) {
            return response()->json([
                'status' => false,
                'message' => 'Blog post not found or not published yet.',
            ], 404);
        }

        // Increment views
        $blog->increment('views');

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $blog->id,
                'title' => $blog->title,
                'slug' => $blog->slug,
                'details' => $blog->details,
                'image' => $blog->img ?: '/visitor/home/1775506047_Tata-Safari-Facelift-1160x653.webp',
                'publish_date' => $blog->publish_date ? Carbon::parse($blog->publish_date)->format('M d, Y') : '',
                'publish_date_raw' => $blog->publish_date ? Carbon::parse($blog->publish_date)->format('Y-m-d') : '',
                'likes' => (int) $blog->likes,
                'views' => (int) $blog->views,
                'shares' => (int) $blog->shares,
            ],
        ]);
    }

    /**
     * 3. Like a Blog
     */
    public function likeBlog($id)
    {
        $blog = Blog::find($id);
        if (!$blog) {
            return response()->json(['status' => false, 'message' => 'Blog not found'], 404);
        }

        $blog->increment('likes');

        return response()->json([
            'status' => true,
            'likes' => $blog->likes,
        ]);
    }

    /**
     * 4. GET Visitor Useful Links / CRM Pages: Only active, ordered by position
     */
    public function getCrmPages()
    {
        $pages = CrmPage::where('status', 'active')
            ->orderBy('position', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'title', 'slug', 'position', 'img']);

        return response()->json([
            'status' => true,
            'data' => $pages,
        ]);
    }

    /**
     * 5. GET Single CRM Page by Slug
     */
    public function getCrmPageBySlug($slug)
    {
        $page = CrmPage::where('slug', $slug)
            ->where('status', 'active')
            ->first();

        if (!$page) {
            return response()->json([
                'status' => false,
                'message' => 'Page not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $page,
        ]);
    }

    /**
     * 6. Submit Contact Us Form
     */
    public function submitContact(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:30',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string',
        ]);

        $inquiry = ContactInquiry::create([
            'name' => trim($validated['name']),
            'email' => trim($validated['email']),
            'phone' => trim($validated['phone']),
            'subject' => !empty($validated['subject']) ? trim($validated['subject']) : 'General Website Inquiry',
            'message' => trim($validated['message']),
            'status' => 'new',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Thank you for reaching out! Our team will get back to you shortly.',
            'data' => $inquiry,
        ], 201);
    }
}

// Simple Helper for clean string truncation
function StrLimit($string, $limit = 100, $end = '...')
{
    if (mb_strwidth($string, 'UTF-8') <= $limit) {
        return $string;
    }
    return rtrim(mb_strimwidth($string, 0, $limit, '', 'UTF-8')) . $end;
}
