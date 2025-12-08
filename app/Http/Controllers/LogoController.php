<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
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
        if (!$company || !$company->logo_path) {
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
        $company = Company::find($companyId);
        if (!$company) {
            try {
                DB::table('emisores')->insert([
                    'id' => $companyId,
                    'name' => 'Company ' . $companyId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                // ignore if insert fails
            }
            $company = Company::find($companyId);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $file = $request->file('logo');

        // Create ImageManager with correct v3 syntax
        try {
            if (extension_loaded('imagick')) {
                $manager = new ImageManager(new ImagickDriver());
            } else {
                $manager = new ImageManager(new GdDriver());
            }
        } catch (\Exception $e) {
            // Fallback without image processing
            try {
                $ext = $file->getClientOriginalExtension() ?: 'png';
                $filename = 'logos/company_' . $company->id . '_' . time() . '.' . $ext;
                Storage::disk('public')->putFileAs('logos', $file, basename($filename));

                if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
                    try { Storage::disk('public')->delete($company->logo_path); } catch (\Exception $_) {}
                }

                $company->logo_path = 'logos/' . basename($filename);
                $company->save();

                return response()->json([
                    'message' => 'Logo uploaded without resizing (no image driver available)',
                    'path' => $company->logo_path,
                    'url' => asset('storage/' . $company->logo_path),
                ]);
            } catch (\Exception $ex) {
                return response()->json([
                    'message' => 'Image driver not available on server and fallback storage failed.',
                    'error' => $e->getMessage(),
                    'fallback_error' => $ex->getMessage(),
                ], 500);
            }
        }

    $targetSize = self::LOGO_TARGET_SIZE;

        try {
            // Read image with v3 syntax
            $image = $manager->read($file->getRealPath());

            // Use cover() method which crops and resizes to exact dimensions
            $image->cover($targetSize, $targetSize);

            // Generate filename
            $filename = 'logos/company_' . $company->id . '_' . time() . '.png';
            
            // Encode to PNG with quality (v3 syntax)
            $encoded = $image->toPng();
            
            // Save to storage
            Storage::disk('public')->put($filename, (string) $encoded);

            // Delete old logo if exists
            if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
                try {
                    Storage::disk('public')->delete($company->logo_path);
                } catch (\Exception $e) {
                    // ignore deletion errors
                }
            }

            $company->logo_path = $filename;
            $company->save();

            return response()->json([
                'message' => 'Logo updated',
                'path' => $filename,
                'url' => asset('storage/' . $filename),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error processing image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Serve the raw logo image from the public disk.
     * This allows browsers to request the image through a Laravel route
     * without relying on the public/storage symlink.
     */
    public function file($companyId)
    {
        $company = Company::find($companyId);
        if (!$company || !$company->logo_path) {
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
        $est = \App\Models\Establecimiento::where('company_id', $companyId)->find($estId);
        if (!$est || !$est->logo_path) {
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