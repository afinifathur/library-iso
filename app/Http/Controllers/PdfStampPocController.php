<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

/**
 * PdfStampPocController
 *
 * PHASE C2B — FPDI PROOF OF CONCEPT (TEMPORARY / ISOLATED)
 *
 * PURPOSE  : Validate that FPDI can import existing Library-ISO PDFs
 *            and place a footer stamp on every page, then stream the result.
 *
 * SCOPE    : This controller is completely standalone.
 *            It does NOT reuse, call, or modify ANY production download logic.
 *            It does NOT write to the database.
 *            It does NOT save any file to disk.
 *
 * REMOVE AFTER : Phase C2B is complete.
 */
class PdfStampPocController extends Controller
{
    /**
     * The hardcoded test matrix.
     *
     * Three doc types: one DP, one IK, one FR.
     * Paths are relative to the 'documents' disk root (storage/app/documents).
     */
    private const TEST_MATRIX = [
        'DP' => [
            'doc_code'   => 'DP.MR.02',
            'label'      => 'BISNIS PROSES',
            'pdf_path'   => 'DP.MR.02/v1/1764736526_pdf_4A63ls_DP.MR.02_BISNIS_PROSES.pdf',
        ],
        'IK' => [
            'doc_code'   => 'IK.BBT-FL.01',
            'label'      => 'SOP LAS SERVIS BUBUT',
            'pdf_path'   => 'IK.BBT-FL.01/v1/1764742061_pdf_uwPaMw_IK.BBT-FL.01_SOP_LAS_SERVIS_BUBUT.pdf',
        ],
        'FR' => [
            'doc_code'   => 'FR.MKT.01',
            'label'      => 'FORM SALES ORDER',
            'pdf_path'   => 'FR.MKT.01/v1/1764740992_pdf_1hNmpx_FR.MKT-EXIM.01_FORM_SALES_ORDER.pdf',
        ],
    ];

    /**
     * GET /pdf-stamp-poc/{type}
     *
     * @param  string $type  One of: DP | IK | FR
     */
    public function stamp(string $type)
    {
        $type = strtoupper($type);

        if (! array_key_exists($type, self::TEST_MATRIX)) {
            abort(404, 'Unknown doc type. Use: DP, IK, or FR');
        }

        $entry   = self::TEST_MATRIX[$type];
        $disk    = Storage::disk('documents');
        $relPath = $entry['pdf_path'];

        // ── 1. Verify the file exists on disk ────────────────────────────────
        if (! $disk->exists($relPath)) {
            abort(404, "POC: File not found on disk. Path: {$relPath}");
        }

        $absolutePath = $disk->path($relPath);

        // ── 2. Attempt FPDI import and stamp ─────────────────────────────────
        try {
            $pdf = new Fpdi();
            $pdf->SetCreator('Library-ISO POC C2B');
            $pdf->SetAuthor('POC');

            $pageCount = $pdf->setSourceFile($absolutePath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                // Import the existing page to get its actual dimensions
                $tpl = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($tpl);

                $width  = $size['width'];
                $height = $size['height'];

                // Detect orientation from page size
                $orientation = ($width > $height) ? 'L' : 'P';

                // Add a new blank page with exact original size and orientation
                $pdf->AddPage($orientation, [$width, $height]);

                // Lay the imported page template at position 0,0 filling the whole page
                $pdf->useTemplate($tpl, 0, 0, $width, $height);

                // ── Footer Stamp ──────────────────────────────────────────────
                // Spec: Helvetica, 6pt, Gray, centered, bottom of page, single line.
                $pdf->SetFont('Helvetica', '', 6);
                $pdf->SetTextColor(128, 128, 128);   // Gray

                // Position: 5 points from the bottom edge
                $yPos = $height - 5;
                $pdf->SetXY(0, $yPos);
                $pdf->Cell($width, 0, 'CC:DISTR-TEST-000001', 0, 0, 'C');
            }

            // ── 3. Stream to browser (no disk write) ─────────────────────────
            $pdfContent = $pdf->Output('S');   // 'S' = return as string

            $filename = 'poc_stamp_' . $entry['doc_code'] . '.pdf';

            return response($pdfContent, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
                'Content-Length'      => strlen($pdfContent),
                'Cache-Control'       => 'no-store, no-cache',
            ]);

        } catch (\Throwable $e) {
            // ── 4. FPDI exception capture (Step 7 of spec) ───────────────────
            // Read the PDF header to capture version string
            $pdfVersion = 'unknown';
            try {
                $handle = fopen($absolutePath, 'rb');
                if ($handle) {
                    $header = fread($handle, 16);
                    fclose($handle);
                    if (preg_match('/%PDF-(\d+\.\d+)/', $header, $m)) {
                        $pdfVersion = $m[1];
                    }
                }
            } catch (\Throwable) {
                // ignore version probe failure
            }

            $likelyCause = str_contains($e->getMessage(), 'compression')
                ? 'PDF 1.5 Compressed Object Streams / Cross-Reference Streams are not supported by FPDI Community Edition free parser.'
                : 'Unexpected FPDI error — see exception message.';

            // Return a plain-text diagnostic response for the POC report
            return response(
                implode("\n", [
                    '=== FPDI POC EXCEPTION REPORT ===',
                    '',
                    'Document Type : ' . $type,
                    'Doc Code      : ' . $entry['doc_code'],
                    'Label         : ' . $entry['label'],
                    'PDF Path      : ' . $relPath,
                    '',
                    'PDF Version   : ' . $pdfVersion,
                    '',
                    'Exception Class   : ' . get_class($e),
                    'Exception Message : ' . $e->getMessage(),
                    '',
                    'Likely Cause  : ' . $likelyCause,
                    '',
                    '--- Stack Trace (first 5 frames) ---',
                    collect(explode("\n", $e->getTraceAsString()))
                        ->take(5)
                        ->implode("\n"),
                    '',
                    '=== END OF REPORT ===',
                ]),
                500,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }
    }

    /**
     * GET /pdf-stamp-poc
     *
     * Quick index showing available test endpoints.
     */
    public function index()
    {
        $lines = [
            'PHASE C2B — FPDI POC CONTROLLER',
            str_repeat('=', 40),
            '',
            'Available test endpoints:',
            '',
        ];

        foreach (self::TEST_MATRIX as $type => $entry) {
            $lines[] = "  GET /pdf-stamp-poc/{$type}";
            $lines[] = "       Doc Code : {$entry['doc_code']}";
            $lines[] = "       Title    : {$entry['label']}";
            $lines[] = "       Path     : {$entry['pdf_path']}";
            $lines[] = '';
        }

        $lines[] = 'Expected stamp text: CC:DISTR-TEST-000001';
        $lines[] = 'Font: Helvetica 6pt | Color: Gray | Position: Bottom Center';

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
