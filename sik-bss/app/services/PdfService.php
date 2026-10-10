<?php
namespace App\Services;

use Dompdf\{Dompdf, Options};

class PdfService
{
    public static function render(string $html, string $paper = 'A4', string $orient = 'portrait'): string
    {
        $o = new Options();
        $o->set('isRemoteEnabled', false);
        $o->set('defaultFont', 'Helvetica');
        $o->set('chroot', [ROOT_PATH]);
        $d = new Dompdf($o);
        $d->loadHtml($html, 'UTF-8');
        $d->setPaper($paper, $orient);
        $d->render();
        return $d->output();
    }

    /** Kirim PDF ke browser. */
    public static function kirim(string $pdf, string $nama, bool $unduh): never
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($unduh ? 'attachment' : 'inline') . '; filename="' . $nama . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }
}
