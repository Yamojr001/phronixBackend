<?php

namespace App\Http\Controllers;

use App\Models\AppVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AppVersionController extends Controller
{
    /**
     * Display a listing of the resource for Admin.
     */
    public function index()
    {
        $versions = AppVersion::orderBy('created_at', 'desc')->paginate(10);
        return response()->json($versions);
    }

    /**
     * Display a listing of the resource for Public Download page.
     */
    public function publicIndex()
    {
        $versions = AppVersion::orderBy('created_at', 'desc')->get();
        return response()->json($versions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'version' => 'required|string|max:50',
            'release_notes' => 'nullable|string|max:10000',
            'file' => 'nullable|file|max:512000', // Allow up to 500MB if file is provided
            'link' => 'nullable|url|max:2048',
        ]);

        if (!$request->hasFile('file') && !$request->filled('link')) {
            return response()->json(['message' => 'Please provide either an app file or a download link.'], 422);
        }

        try {
            $path = null;
            if ($request->hasFile('file')) {
                $path = $request->file('file')->store('mobile_apps', 'public');
            }

            AppVersion::create([
                'name' => $request->name,
                'version' => $request->version,
                'release_notes' => $request->release_notes,
                'file_path' => $path,
                'link' => $request->link,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'App version saved successfully.',
                'app_version' => AppVersion::latest()->first()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upload app version: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to save app version. Please try again.'], 500);
        }
    }

    /**
     * Download the specified resource.
     */
    public function download(AppVersion $appVersion)
    {
        $appVersion->increment('downloads');

        if ($appVersion->link) {
            return redirect()->away($appVersion->link);
        }

        if (!$appVersion->file_path || !Storage::disk('public')->exists($appVersion->file_path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        return Storage::disk('public')->download(
            $appVersion->file_path, 
            $appVersion->name . '_' . $appVersion->version . '.' . pathinfo($appVersion->file_path, PATHINFO_EXTENSION)
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AppVersion $appVersion)
    {
        try {
            if (Storage::disk('public')->exists($appVersion->file_path)) {
                Storage::disk('public')->delete($appVersion->file_path);
            }
            $appVersion->delete();

            return response()->json([
                'success' => true,
                'message' => 'App version deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete app version.'], 500);
        }
    }
}
