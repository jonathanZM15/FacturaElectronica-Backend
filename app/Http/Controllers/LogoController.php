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
            try {
                DB::table('companies')->insert([
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

        $targetSize = 200;

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
}