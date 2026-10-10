<?php
namespace App\Controllers;

use App\Core\{Controller, Auth, Database, Flash, HttpException};
use App\Services\{InvoiceService, KopService, PdfService};

class InvoiceController extends Controller
{
    private function find(string $id): array
    {
        $r = Database::one('SELECT i.*, p.kode_pelanggan, p.nama_pelanggan, p.alamat, p.telepon, p.email
                              FROM invoice i JOIN pelanggan p ON p.id_pelanggan = i.id_pelanggan WHERE i.id_invoice = ?', [(int)$id]);
        if (!$r) throw new HttpException(404, 'Invoice tidak ditemukan.');
        return $r;
    }

    public function index(): void
    {
        $q = (string)$this->input('q');
        $status = (string)$this->input('status');
        $dari = (string)$this->input('dari');
        $sampai = (string)$this->input('sampai');
        $w = ['1=1']; $p = [];
        if ($q !== '') { $w[] = '(i.no_invoice LIKE ? OR p.nama_pelanggan LIKE ?)'; array_push($p, "%$q%", "%$q%"); }
        if (in_array($status, ['belum_lunas', 'sebagian', 'lunas', 'batal'], true)) { $w[] = 'i.status_invoice = ?'; $p[] = $status; }
        if (valid_date($dari)) { $w[] = 'i.tanggal >= ?'; $p[] = $dari; }
        if (valid_date($sampai)) { $w[] = 'i.tanggal <= ?'; $p[] = $sampai; }
        $pg = Database::paged('SELECT i.*, p.nama_pelanggan', 'FROM invoice i JOIN pelanggan p ON p.id_pelanggan = i.id_pelanggan WHERE ' . implode(' AND ', $w),
            $p, 'i.tanggal DESC, i.id_invoice DESC', $this->page(), $this->per());
        $this->view('invoice/index', ['title' => 'Invoice', 'pg' => $pg, 'q' => $q, 'status' => $status, 'dari' => $dari, 'sampai' => $sampai]);
    }

    private function formData(): array
    {
        return [
            'pelanggan' => Database::all("SELECT id_pelanggan, kode_pelanggan, nama_pelanggan FROM pelanggan WHERE status='aktif' ORDER BY nama_pelanggan"),
            'layanan' => InvoiceService::LAYANAN,
        ];
    }

    public function create(): void
    {
        $this->view('invoice/form', $this->formData() + [
            'title' => 'Buat Invoice', 'inv' => null, 'items' => [], 'locked' => false,
            'ppnDefault' => (float)KopService::get()['ppn_default'], 'action' => url('/invoice/store'),
        ]);
    }

    private function head(): array
    {
        $h = [
            'id_pelanggan' => (int)$this->input('id_pelanggan'),
            'tanggal' => (string)$this->input('tanggal'),
            'jatuh_tempo' => (string)$this->input('jatuh_tempo'),
            'keterangan' => (string)$this->input('keterangan'),
            'ppn_persen' => $this->input('ppn_persen', 0),
        ];
        if (!valid_date($h['tanggal']) || !valid_date($h['jatuh_tempo'])) throw new \DomainException('Tanggal dan jatuh tempo harus diisi dengan benar.');
        if ($h['jatuh_tempo'] < $h['tanggal']) throw new \DomainException('Jatuh tempo tidak boleh sebelum tanggal invoice.');
        if (mb_strlen($h['keterangan']) > 255) throw new \DomainException('Keterangan maksimal 255 karakter.');
        return $h;
    }

    private function cekPelanggan(int $id): void
    {
        if (!Database::val("SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan = ? AND status = 'aktif'", [$id])) {
            throw new \DomainException('Pilih pelanggan yang aktif.');
        }
    }

    public function store(): void
    {
        try {
            $h = $this->head();
            $this->cekPelanggan($h['id_pelanggan']);
            $id = InvoiceService::simpan($h, InvoiceService::parseItems($_POST), Auth::id());
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), '/invoice/create');
        }
        Flash::set('success', 'Invoice berhasil dibuat.');
        redirect('/invoice/' . $id);
    }

    public function show(string $id): void
    {
        $inv = $this->find($id);
        $items = Database::all('SELECT * FROM invoice_item WHERE id_invoice = ? ORDER BY id_item', [$inv['id_invoice']]);
        $bayar = Database::all("SELECT t.*, k.nama_kas_bank FROM transaksi_pemasukan t JOIN kas_bank k ON k.id_kas_bank = t.id_kas_bank
                                 WHERE t.id_invoice = ? ORDER BY t.tanggal_pembayaran, t.id_pemasukan", [$inv['id_invoice']]);
        $this->view('invoice/show', ['title' => 'Invoice ' . $inv['no_invoice'], 'inv' => $inv, 'items' => $items, 'bayar' => $bayar]);
    }

    public function edit(string $id): void
    {
        $inv = $this->find($id);
        if ($inv['status_invoice'] === 'batal') {
            Flash::set('warning', 'Invoice yang dibatalkan tidak dapat diubah.');
            redirect('/invoice/' . $inv['id_invoice']);
        }
        $items = Database::all('SELECT * FROM invoice_item WHERE id_invoice = ? ORDER BY id_item', [$inv['id_invoice']]);
        $this->view('invoice/form', $this->formData() + [
            'title' => 'Ubah Invoice ' . $inv['no_invoice'], 'inv' => $inv, 'items' => $items,
            'locked' => InvoiceService::punyaPembayaran((int)$inv['id_invoice']),
            'ppnDefault' => (float)$inv['ppn_persen'], 'action' => url('/invoice/update/' . $inv['id_invoice']),
        ]);
    }

    public function update(string $id): void
    {
        $inv = $this->find($id);
        $to = '/invoice/' . $inv['id_invoice'] . '/edit';
        try {
            if (InvoiceService::punyaPembayaran((int)$inv['id_invoice'])) {
                // field yang dikunci tidak dikirim form: pakai data lama
                $_POST += ['id_pelanggan' => $inv['id_pelanggan'], 'tanggal' => $inv['tanggal'], 'ppn_persen' => $inv['ppn_persen']];
                $h = $this->head();
                InvoiceService::ubah((int)$inv['id_invoice'], $h, null);
            } else {
                $h = $this->head();
                $this->cekPelanggan($h['id_pelanggan']);
                InvoiceService::ubah((int)$inv['id_invoice'], $h, InvoiceService::parseItems($_POST));
            }
        } catch (\DomainException $e) {
            $this->fail($e->getMessage(), $to);
        }
        Flash::set('success', 'Invoice berhasil diubah.');
        redirect('/invoice/' . $inv['id_invoice']);
    }

    public function status(string $id): void
    {
        $inv = $this->find($id);
        try {
            Flash::set('success', InvoiceService::toggleBatal((int)$inv['id_invoice']));
        } catch (\DomainException $e) {
            Flash::set('danger', $e->getMessage());
        }
        redirect('/invoice/' . $inv['id_invoice']);
    }

    public function cetak(string $id): void
    {
        $inv = $this->find($id);
        $items = Database::all('SELECT * FROM invoice_item WHERE id_invoice = ? ORDER BY id_item', [$inv['id_invoice']]);
        $kop = KopService::siap();
        ob_start();
        include APP_PATH . '/views/pdf/invoice.php';
        $pdf = PdfService::render(ob_get_clean());
        PdfService::kirim($pdf, str_replace('/', '-', $inv['no_invoice']) . '.pdf', isset($_GET['unduh']));
    }
}
