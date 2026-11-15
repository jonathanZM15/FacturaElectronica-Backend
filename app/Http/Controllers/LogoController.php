<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Company;

class LogoController extends Controller
{
    // Change this constant to adjust server-side saved logo size (square).
    // Increasing this value will store a larger image on disk.
    private const LOGO_TARGET_SIZE = 200; // pixels (square)
    // Public getter for a company's logo URL
    public function show($companyId)
    {
        $company = Company::find($companyId);
        if (!$company || !$company->logo_path || !\App\Services\SafeLogoStorage::isSafePath($company->logo_path)) {
            return response()->json(['url' => null]);
        }
        // Prefer the API file route if the file exists on the public disk.
        try {
            if (Storage::disk('public')->exists($company->logo_path)) {
                return response()->json(['url' => url('/api/companies/' . $company->id . '/logo-file')]);
            }
        } catch (\Exception $_) {
            // ignore and fallback
        }

        return response()->json(['url' => asset('storage/' . $company->logo_path)]);
    }

    // Store/replace logo for a company
    public function store(Request $request, $companyId)
    {
        $company = Company::findOrFail($companyId);

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $filename = app(\App\Services\SafeLogoStorage::class)->store($request->file('logo'), 'logos', self::LOGO_TARGET_SIZE);
        $previous = $company->logo_path;
        $company->logo_path = $filename;
        $company->save();
        if ($previous && \App\Services\SafeLogoStorage::isSafePath($previous)) {
            Storage::disk('public')->delete($previous);
        }
        return response()->json(['message' => 'Logo updated', 'path' => $filename, 'url' => asset('storage/'.$filename)]);
    }

    /**
     * Serve the raw logo image from the public disk.
     * This allows browsers to request the image through a Laravel route
     * without relying on the public/storage symlink.
     */
    public function file($companyId)
    {
        $company = Company::find($companyId);
        if (!$company || !$company->logo_path || !\App\Services\SafeLogoStorage::isSafePath($company->logo_path)) {
            return response(null, 404);
        }

        try {
            $disk = Storage::disk('public');
            if (!$disk->exists($company->logo_path)) {
                return response(null, 404);
            }

            $contents = $disk->get($company->logo_path);
            $mime = 'image/png';
            if (function_exists('mime_content_type')) {
                $mime = mime_content_type($disk->path($company->logo_path)) ?: 'image/png';
            }

            if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) return response(null, 404);

            // Generate ETag based on file contents and updated_at timestamp
            $etag = md5($contents . $company->updated_at);

            return response($contents, 200)
                ->header('Content-Type', $mime)
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate, private, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '-1')
                ->header('ETag', '"' . $etag . '"')
                ->header('X-Content-Type-Options', 'nosniff')
                ->header('Last-Modified', gmdate('D, d M Y H:i:s', $company->updated_at->timestamp) . ' GMT');
        } catch (\Exception $e) {
            return response()->json(['message' => 'Could not read logo', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Serve the raw logo image for an establecimiento
     */
    public function establecimientos_file($companyId, $estId)
    {
        $est = \App\Models\Establecimiento::where('emisor_id', $companyId)->find($estId);
        if (!$est || !$est->logo_path || !\App\Services\SafeLogoStorage::isSafePath($est->logo_path)) {
            return response(null, 404);
        }

        try {
            $disk = Storage::disk('public');
            if (!$disk->exists($est->logo_path)) {
                return response(null, 404);
            }

            $contents = $disk->get($est->logo_path);
            $mime = 'image/png';
            if (function_exists('mime_content_type')) {
                $mime = mime_content_type($disk->path($est->logo_path)) ?: 'image/png';
            }

            if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) return response(null, 404);

            // Generate ETag based on file contents and updated_at timestamp
            $etag = md5($contents . $est->updated_at);

            return response($contents, 200)
                ->header('Content-Type', $mime)
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate, private, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '-1')
                ->header('ETag', '"' . $etag . '"')
                ->header('X-Content-Type-Options', 'nosniff')
                ->header('Last-Modified', gmdate('D, d M Y H:i:s', $est->updated_at->timestamp) . ' GMT');
        } catch (\Exception $e) {
            return response()->json(['message' => 'Could not read logo', 'error' => $e->getMessage()], 500);
        }
    }
}
