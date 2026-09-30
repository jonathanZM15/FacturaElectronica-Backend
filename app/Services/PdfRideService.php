<?php

namespace App\Services;

use App\Models\Comprobante;
use Barryvdh\DomPDF\Facade\Pdf;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Illuminate\Support\Facades\Storage;

class PdfRideService
{
    public function generateRide(Comprobante $comprobante, bool $saveToDisk = false): string
    {
        $company = $comprobante->company;
        $establecimiento = $comprobante->establecimiento;
        $cliente = $comprobante->cliente;

        // Base64 Logo
        $logoBase64 = null;
        if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
            $logoContent = Storage::disk('public')->get($company->logo_path);
            $mime = Storage::disk('public')->mimeType($company->logo_path);
            $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode($logoContent);
        } else {
            // Default blank logo or handle empty
            $logoBase64 = null;
        }

        // Base64 Barcode
        $generator = new BarcodeGeneratorPNG();
        $barcodeContent = $generator->getBarcode($comprobante->clave_acceso, $generator::TYPE_CODE_128);
        $barcodeBase64 = 'data:image/png;base64,' . base64_encode($barcodeContent);

        $pdf = Pdf::loadView('pdf.ride', [
            'comprobante' => $comprobante,
            'company' => $company,
            'establecimiento' => $establecimiento,
            'cliente' => $cliente,
            'logoBase64' => $logoBase64,
            'barcodeBase64' => $barcodeBase64,
        ]);

        $pdf->setPaper('A4', 'portrait');

        if ($saveToDisk) {
            $path = 'sri/rides/' . $comprobante->clave_acceso . '.pdf';
            Storage::disk(config('sri.certificate_disk', 'local'))->put($path, $pdf->output());
            return Storage::disk(config('sri.certificate_disk', 'local'))->path($path);
        }

        return $pdf->output();
    }
}
