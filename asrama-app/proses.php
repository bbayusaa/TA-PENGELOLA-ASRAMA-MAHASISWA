<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

function read_json($path) {
    if (!file_exists($path)) {
        if (!is_dir(__DIR__ . "/data")) {
            mkdir(__DIR__ . "/data", 0777, true);
        }
        return [];
    }
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function write_json($path, $data) {
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function biaya_per_bulan($kamar) {
    return $kamar === "Single" ? 750000 : 1000000;
}

function sort_queue_by_tanggal($queue) {
    usort($queue, function($a, $b) {
        $tanggal_a = strtotime($a['tanggal_mulai'] ?? '9999-12-31');
        $tanggal_b = strtotime($b['tanggal_mulai'] ?? '9999-12-31');
        return $tanggal_a - $tanggal_b;
    });
    return $queue;
}

function auto_check_expired($aktif, $riwayat) {
    $hari_ini = date('Y-m-d');
    $aktif_baru = [];
    foreach ($aktif as $p) {
        if (isset($p['tanggal_berakhir']) && $p['tanggal_berakhir'] < $hari_ini) {
            $riwayat[] = $p;
        } else {
            $aktif_baru[] = $p;
        }
    }
    return [$aktif_baru, $riwayat];
}

$queue_path = __DIR__ . "/data/queue.json";
$aktif_path = __DIR__ . "/data/aktif.json";
$riwayat_path = __DIR__ . "/data/riwayat.json";

$queue = read_json($queue_path);
$aktif = read_json($aktif_path);
$riwayat = read_json($riwayat_path);

// AUTO CHECK EXPIRED
list($aktif, $riwayat) = auto_check_expired($aktif, $riwayat);
write_json($aktif_path, $aktif);
write_json($riwayat_path, $riwayat);

$kapasitas = [
    1 => ['Single' => 3, 'Double' => 2],
    2 => ['Single' => 3, 'Double' => 2],
    3 => ['Single' => 3, 'Double' => 2],
];

$aksi = $_POST['aksi'] ?? '';

if ($aksi === 'tambah_antrian') {
    $kamar = $_POST['kamar'] ?? '';
    $lantai = (int)($_POST['lantai'] ?? 0);
    $tanggal_mulai = $_POST['tanggal_mulai'] ?? '';
    $lama_bulan = (int)($_POST['lama_bulan'] ?? 0);
    
    // DATA Nama dan No. WA
    $nama1 = htmlspecialchars(trim($_POST['nama1'] ?? ''));
    $nama2 = htmlspecialchars(trim($_POST['nama2'] ?? ''));
    $no_wa1 = htmlspecialchars(trim($_POST['no_wa1'] ?? ''));
    $no_wa2 = htmlspecialchars(trim($_POST['no_wa2'] ?? ''));

    // Validasi
    if (!in_array($kamar, ['Single','Double'], true) || $lantai < 1 || $lantai > 3 || $lama_bulan <= 0 || empty($tanggal_mulai)) {
        header("Location: index.php"); exit;
    }
    
    // Perbaikan Validasi
    if ($kamar === 'Single' && ($nama1 === '' || $no_wa1 === '')) { header("Location: index.php"); exit; }
    if ($kamar === 'Double' && ($nama1 === '' || $nama2 === '' || $no_wa1 === '' || $no_wa2 === '')) { header("Location: index.php"); exit; }

    // Hitung tanggal berakhir
    try {
        $mulai_date = new DateTime($tanggal_mulai);
        $mulai_date->modify("+{$lama_bulan} months");
        $tanggal_berakhir = $mulai_date->format('Y-m-d');
    } catch (\Exception $e) {
        $tanggal_berakhir = $tanggal_mulai; 
    }

    $biaya_total = biaya_per_bulan($kamar) * $lama_bulan;

    $queue[] = [
        'nama1' => $nama1,
        'nama2' => ($kamar === 'Double' ? $nama2 : null),
        'no_wa1' => $no_wa1, 
        'no_wa2' => ($kamar === 'Double' ? $no_wa2 : null), 
        'kamar' => $kamar,
        'lantai' => $lantai,
        'tanggal_mulai' => $tanggal_mulai,
        'tanggal_berakhir' => $tanggal_berakhir,
        'lama_bulan' => $lama_bulan,
        'biaya_total' => $biaya_total
    ];

    $queue = sort_queue_by_tanggal($queue);
    write_json($queue_path, $queue);
    header("Location: index.php"); exit;
}

if ($aksi === 'tempatkan') {
    if (!empty($queue)) {
        $calon = array_shift($queue);
        
        // PERBAIKAN KRITIS: Hitung kamar terpakai berdasarkan tipe kamar penghuni aktif ($p['kamar']), bukan tipe kamar calon ($calon['kamar'])
        $terpakai = ['Single'=>0,'Double'=>0];
        foreach ($aktif as $p) {
            $tipe_aktif = $p['kamar'] ?? '';
            if ((int)$p['lantai'] === (int)$calon['lantai'] && isset($terpakai[$tipe_aktif])) {
                $terpakai[$tipe_aktif]++;
            }
        }
        $kap = $kapasitas[$calon['lantai']];

        $boleh = false;
        if ($calon['kamar'] === 'Single' && $terpakai['Single'] < $kap['Single']) {
            $boleh = true;
        } elseif ($calon['kamar'] === 'Double' && $terpakai['Double'] < $kap['Double']) {
            $boleh = true;
        }

        if ($boleh) {
            $aktif[] = $calon;
        } else {
            // Jika tidak boleh (kapasitas penuh), kembalikan calon ke awal antrian
            array_unshift($queue, $calon);
        }

        write_json($queue_path, $queue);
        write_json($aktif_path, $aktif);
    }
    header("Location: index.php"); exit;
}

if ($aksi === 'perpanjang') {
    $index = (int)($_POST['index'] ?? -1);
    $bulan_baru = (int)($_POST['lama_bulan_baru'] ?? 0);
    if ($index >= 0 && $index < count($aktif) && $bulan_baru > 0 && $bulan_baru <= 12) {
        $aktif[$index]['lama_bulan'] += $bulan_baru;
        if (isset($aktif[$index]['tanggal_berakhir'])) {
            $tanggal_berakhir = new DateTime($aktif[$index]['tanggal_berakhir']);
            $tanggal_berakhir->modify("+{$bulan_baru} months");
            $aktif[$index]['tanggal_berakhir'] = $tanggal_berakhir->format('Y-m-d');
        }
        $aktif[$index]['biaya_total'] = biaya_per_bulan($aktif[$index]['kamar']) * $aktif[$index]['lama_bulan'];
        write_json($aktif_path, $aktif);
    }
    header("Location: index.php"); exit;
}

if ($aksi === 'keluarkan') {
    $index = (int)($_POST['index'] ?? -1);
    if ($index >= 0 && $index < count($aktif)) {
        $keluar = $aktif[$index];
        array_splice($aktif, $index, 1);
        array_push($riwayat, $keluar);
        write_json($aktif_path, $aktif);
        write_json($riwayat_path, $riwayat);
    }
    header("Location: index.php"); exit;
}

if ($aksi === 'reset_antrian') {
    write_json($queue_path, []);
    header("Location: index.php"); exit;
}

if ($aksi === 'reset_riwayat') {
    write_json($riwayat_path, []);
    header("Location: index.php"); exit;
}

if ($aksi === 'reset') {
    write_json($queue_path, []);
    write_json($aktif_path, []);
    write_json($riwayat_path, []);
    header("Location: index.php"); exit;
}

header("Location: index.php"); exit;
?>