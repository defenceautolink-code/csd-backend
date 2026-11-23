<?php

namespace App\Http\Controllers;

use App\Models\ContactInquiry;
use Illuminate\Http\Request;

class ContactInquiryController extends Controller
{
    public function index(Request $request)
    {
        // Auto-purge any soft-deleted records from DB to ensure clean table
        ContactInquiry::onlyTrashed()->forceDelete();

        $query = ContactInquiry::latest('id');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($request->per_page ?? 15);
        $inquiries = $query->paginate($perPage);

        $total = ContactInquiry::count();
        $today = ContactInquiry::whereDate('created_at', today())->count();

        return response()->json([
            'status' => true,
            'data' => $inquiries->items(),
            'pagination' => [
                'current_page' => $inquiries->currentPage(),
                'last_page' => $inquiries->lastPage(),
                'per_page' => $inquiries->perPage(),
                'total' => $inquiries->total(),
            ],
            'stats' => [
                'total' => $total,
                'today' => $today,
            ],
        ]);
    }

    public function show($id)
    {
        $inquiry = ContactInquiry::findOrFail($id);
        if ($inquiry->status === 'new') {
            $inquiry->update(['status' => 'read']);
        }

        return response()->json([
            'status' => true,
            'data' => $inquiry,
        ]);
    }

    public function destroy($id)
    {
        $inquiry = ContactInquiry::withTrashed()->findOrFail($id);
        $inquiry->forceDelete();

        return response()->json([
            'status' => true,
            'message' => 'Contact inquiry permanently deleted successfully.',
        ]);
    }
}
