<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Illuminate\Validation\ValidationException;

class SafeLogoStorage
{
    public function store(UploadedFile $file, string $directory, ?int $square = null): string
    {
        Validator::make(['logo' => $file], ['logo' => [
            'required', 'image', 'mimes:jpeg,png,webp', 'extensions:jpg,jpeg,png,webp',
            'max:2048', 'dimensions:max_width=4096,max_height=4096',
        ]])->validate();
        try {
            $manager = extension_loaded('gd') ? ImageManager::gd() : ImageManager::imagick();
            $image = $manager->read($file->getRealPath());
            if ($square !== null) $image->cover($square, $square);
            else $image->scaleDown(width: 2000, height: 2000);
            // Decodificar y volver a codificar elimina contenido y metadatos añadidos.
            $bytes = (string) $image->toPng();
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['logo' => 'No se pudo procesar la imagen de forma segura.']);
        }
        $path = trim($directory, '/').'/'.Str::uuid().'.png';
        if (!Storage::disk('public')->put($path, $bytes)) {
            throw new \RuntimeException('No se pudo guardar el logo.');
        }
        return $path;
    }

    public static function isSafePath(string $path): bool
    {
        return (bool) preg_match('#\A(?:logos|emisores/logos|establecimientos/logos)/[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp)\z#i', $path);
    }
}
