<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Intervention\Image\ImageManager;
use App\Models\Company;

class LogoController extends Controller
{
    // Public getter for a company's logo URL
    public function show($companyId)
    {
        $company = Company::find($companyId);
        if (!$company || !$company->logo_path) {
            return response()->json(['url' => null]);
        }
        return response()->json(['url' => asset('storage/' . $company->logo_path)]);
    }

    // Store/replace logo for a company
    public function store(Request $request, $companyId)
    {
        $company = Company::find($companyId);
        if (!$company) {
            // create a minimal company record if it doesn't exist so uploads can proceed
            try {
                DB::table('companies')->insert([
                    'id' => $companyId,
                    'name' => 'Company ' . $companyId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                // ignore if insert fails (e.g., id already exists concurrently)
            }
            $company = Company::find($companyId);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $file = $request->file('logo');

        // Create ImageManager using the driver available on the system (Imagick preferred)
        try {
            if (extension_loaded('imagick')) {
                $manager = ImageManager::imagick();
            } else {
                $manager = ImageManager::gd();
            }
        } catch (\Exception $e) {
            // If an image driver is not available, fallback to saving the original upload
            // (no resize). This lets uploads work on systems without GD/Imagick while
            // we recommend enabling one of those extensions for server-side resizing.
            try {
                $ext = $file->getClientOriginalExtension() ?: 'png';
                $filename = 'logos/company_' . $company->id . '_' . time() . '.' . $ext;
                // store the uploaded file as-is
                Storage::disk('public')->putFileAs('logos', $file, basename($filename));

                // delete old file if exists
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

        // Normalize: crop center to a square and resize to a fixed pixel size.
        // Change $targetSize below if you want larger/smaller images.
        $targetSize = 200; // <-- adjust this number to increase/decrease saved logo pixels

        // Read the uploaded file into an Image instance (Intervention Image v3)
        $image = $manager->read($file->getRealPath());

        try {
            // Prefer fit() which crops and resizes centered; upsize() prevents enlarging tiny images
            if (method_exists($image, 'fit')) {
                $image->fit($targetSize, $targetSize, function ($constraint) {
                    $constraint->upsize();
                });
            } else {
                // Fallback: manual center-crop then resize
                $w = method_exists($image, 'width') ? $image->width() : $image->getWidth();
                $h = method_exists($image, 'height') ? $image->height() : $image->getHeight();
                $min = min($w, $h);
                $cropX = intval(($w - $min) / 2);
                $cropY = intval(($h - $min) / 2);
                if (method_exists($image, 'crop')) {
                    $image->crop($min, $min, $cropX, $cropY);
                }
                $image->resize($targetSize, $targetSize);
            }

            // Save processed image to a temporary file then persist to storage
            $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'logo_' . uniqid() . '.png';
            // quality 90
            $image->save($tempPath, 90);
            $imageContents = file_get_contents($tempPath);
            $filename = 'logos/company_' . $company->id . '_' . time() . '.png';
            Storage::disk('public')->put($filename, $imageContents);
        } finally {
            if (isset($tempPath) && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }

        // Optionally delete old file (if exists)
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
    }
}
