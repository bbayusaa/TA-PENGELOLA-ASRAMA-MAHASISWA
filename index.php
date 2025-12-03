<?php
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

$queue_path = __DIR__ . "/data/queue.json";
$aktif_path = __DIR__ . "/data/aktif.json";
$riwayat_path = __DIR__ . "/data/riwayat.json";

$queue = read_json($queue_path);
$aktif = read_json($aktif_path);
$riwayat = read_json($riwayat_path);

$kapasitas = [
    1 => ['Single' => 3, 'Double' => 2],
    2 => ['Single' => 3, 'Double' => 2],
    3 => ['Single' => 3, 'Double' => 2],
];

$terpakai = [
    1 => ['Single' => 0, 'Double' => 0],
    2 => ['Single' => 0, 'Double' => 0],
    3 => ['Single' => 0, 'Double' => 0],
];
foreach ($aktif as $p) {
    $lantai = (int)($p['lantai'] ?? 0);
    $tipe = $p['kamar'] ?? '';
    if (isset($terpakai[$lantai][$tipe])) {
        $terpakai[$lantai][$tipe]++;
    }
}

// Fungsi pembantu untuk memformat tanggal ke D-M-Y
function format_tanggal_indonesia($date_string) {
    if (empty($date_string) || $date_string === 'N/A') return 'N/A';
    
    // Periksa apakah format Y-m-d valid
    if (strtotime($date_string)) {
        return date('d-m-Y', strtotime($date_string));
    }
    return $date_string; // Kembalikan string asli jika tidak valid
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Asrama Mahasiswa</title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime('style.css'); ?>" />
</head>
<body>
    <header class="hero">
        <div class="hero-content">
            <h1>🏠 Asrama Mahasiswa UNDIP</h1>
            <p>Sistem Pengelolaan Asrama Praktis</p>
        </div>
    </header>

    <nav class="navbar">
        <ul>
            <li><a href="#form">➕ Tambah Penghuni</a></li>
            <li><a href="#kapasitas">📊 Kamar Tersedia</a></li>
            <li><a href="#antrian">📥 Antrian (<?php echo count($queue); ?>)</a></li>
            <li><a href="#aktif">👥 Penghuni Aktif (<?php echo count($aktif); ?>)</a></li>
            <li><a href="#riwayat">📜 Riwayat</a></li>
        </ul>
    </nav>

    <main class="grid">
        <section class="card" id="form">
            <h2>➕ Tambah Calon Penghuni</h2>
            <form action="proses.php" method="post" id="formPenghuni" autocomplete="off">
                <input type="hidden" name="aksi" value="tambah_antrian" />

                <label for="kamarSelect">Jenis Kamar
                    <select name="kamar" id="kamarSelect" required>
                        <option value="Single" selected>Single (1 orang)</option>
                        <option value="Double">Double (2 orang)</option>
                    </select>
                </label>

                <label for="lantaiSelect">Lantai
                    <select name="lantai" id="lantaiSelect" required>
                        <option value="1" selected>Lantai 1</option>
                        <option value="2">Lantai 2</option>
                        <option value="3">Lantai 3</option>
                    </select>
                </label>

                <div id="namaFields" style="margin-top:8px;"></div> 
                <label for="tanggal_mulai">📅 Tanggal Mulai Sewa
                    <input type="date" id="tanggal_mulai" name="tanggal_mulai" required />
                </label>

                <label for="lama_bulan">⏳ Lama Tinggal (bulan)
                    <input type="number" id="lama_bulan" name="lama_bulan" min="1" max="12" value="1" required />
                </label>

                <button type="submit" class="btn primary">🚀 Tambah ke Antrian</button>
            </form>
        </section>

        <section class="card" id="kapasitas">
            <h2>📊 Info Kapasitas per Lantai</h2>
            <?php foreach ($kapasitas as $lantai => $kap): ?>
                <h3>🏢 Lantai <?php echo $lantai; ?></h3>
                <ul>
                    <li>🛏️ Kamar Single: <?php echo $terpakai[$lantai]['Single']; ?>/<?php echo $kap['Single']; ?></li>
                    <li>👥 Kamar Double: <?php echo $terpakai[$lantai]['Double']; ?>/<?php echo $kap['Double']; ?></li>
                </ul>
            <?php endforeach; ?>
            <form action="proses.php" method="post" style="margin-top: 15px;">
                <input type="hidden" name="aksi" value="tempatkan" />
                <button type="submit" class="btn success">➡️ Tempatkan dari Antrian</button>
            </form>
        </section>

        <section class="card" id="antrian">
            <h2>📥 Antrian Calon Penghuni</h2>
            <?php if (empty($queue)): ?>
                <p class="empty">Antrian kosong.</p>
            <?php else: ?>
                <ol>
                    <?php foreach ($queue as $p): ?>
                        <?php
                        $namaTampil = ($p['kamar'] === 'Double' && !empty($p['nama2']))
                            ? "{$p['nama1']} & {$p['nama2']}"
                            : ($p['nama1'] ?? '');
                        ?>
                        <li>
                            <div>👤 Nama: <?php echo htmlspecialchars($namaTampil); ?></div>
                            <div>📞 No. WA 1: <?php echo htmlspecialchars($p['no_wa1'] ?? 'N/A'); ?></div>
                            <?php if ($p['kamar'] === 'Double'): ?>
                                <div>📞 No. WA 2: <?php echo htmlspecialchars($p['no_wa2'] ?? 'N/A'); ?></div>
                            <?php endif; ?>
                            <div>🏢 Lantai: <?php echo (int)$p['lantai']; ?></div>
                            <div>🛏️ Tipe Kamar: <?php echo htmlspecialchars($p['kamar']); ?></div>
                            <div>📅 Mulai Sewa: <?php echo format_tanggal_indonesia($p['tanggal_mulai'] ?? 'N/A'); ?></div>
                            <div>⏰ Sewa Berakhir: <?php echo format_tanggal_indonesia($p['tanggal_berakhir'] ?? 'N/A'); ?></div>
                            <div>⏳ Lama Sewa: <?php echo (int)$p['lama_bulan']; ?> bulan</div>
                            <div>💰 Biaya Total: Rp <?php echo number_format((int)$p['biaya_total'], 0, ',', '.'); ?></div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            <form action="proses.php" method="post" style="margin-top: 20px;">
                <input type="hidden" name="aksi" value="reset_antrian" />
                <button type="submit" class="btn danger small">🗑️ Reset Antrian Saja</button>
            </form>
        </section>

        <section class="card" id="aktif">
            <h2>👥 Penghuni Aktif</h2>

            <div class="filter-controls">
                <label>Filter Lantai:
                    <select id="filterLantai" onchange="filterAktif()">
                        <option value="all">Semua</option>
                        <option value="1">Lantai 1</option>
                        <option value="2">Lantai 2</option>
                        <option value="3">Lantai 3</option>
                    </select>
                </label>
                <label>Filter Kamar:
                    <select id="filterKamar" onchange="filterAktif()">
                        <option value="all">Semua</option>
                        <option value="Single">Single</option>
                        <option value="Double">Double</option>
                    </select>
                </label>
            </div>

            <?php if (empty($aktif)): ?>
                <p class="empty" id="noActiveResidents">Belum ada penghuni aktif.</p>
            <?php else: ?>
                <ul id="listAktif">
                    <?php foreach ($aktif as $i => $p): ?>
                        <?php
                        $namaTampil = ($p['kamar'] === 'Double' && !empty($p['nama2']))
                            ? "{$p['nama1']} & {$p['nama2']}"
                            : ($p['nama1'] ?? '');
                        $statusClass = (isset($p['tanggal_berakhir']) && $p['tanggal_berakhir'] < date('Y-m-d')) ? 'status-expired' : '';
                        ?>
                        <li class="<?php echo $statusClass; ?>" data-lantai="<?php echo (int)$p['lantai']; ?>" data-kamar="<?php echo htmlspecialchars($p['kamar']); ?>">
                            <div>👤 Nama: <?php echo htmlspecialchars($namaTampil); ?></div>
                            <div>📞 No. WA 1: <?php echo htmlspecialchars($p['no_wa1'] ?? 'N/A'); ?></div>
                            <?php if ($p['kamar'] === 'Double'): ?>
                                <div>📞 No. WA 2: <?php echo htmlspecialchars($p['no_wa2'] ?? 'N/A'); ?></div>
                            <?php endif; ?>
                            <div>🏢 Lantai: <?php echo (int)$p['lantai']; ?></div>
                            <div>🛏️ Tipe Kamar: <?php echo htmlspecialchars($p['kamar']); ?></div>
                            <div>📅 Mulai Sewa: <?php echo format_tanggal_indonesia($p['tanggal_mulai'] ?? 'N/A'); ?></div>
                            <div>⏰ Sewa Berakhir: <?php echo format_tanggal_indonesia($p['tanggal_berakhir'] ?? 'N/A'); ?></div>
                            <div>⏳ Lama Sewa: <?php echo (int)$p['lama_bulan']; ?> bulan</div>
                            <div>💰 Biaya Total: Rp <?php echo number_format((int)$p['biaya_total'], 0, ',', '.'); ?></div>
                            
                            <div style="margin-top: 10px;">
                                <form action="proses.php" method="post" class="inline" style="display:inline-block;">
                                    <input type="hidden" name="aksi" value="keluarkan" />
                                    <input type="hidden" name="index" value="<?php echo (int)$i; ?>" />
                                    <button type="submit" class="btn danger small">🚪 Keluarkan</button>
                                </form>
                                <form action="proses.php" method="post" class="inline" style="display:inline-block; margin-left: 10px;">
                                    <input type="hidden" name="aksi" value="perpanjang" />
                                    <input type="hidden" name="index" value="<?php echo (int)$i; ?>" />
                                    <input type="number" name="lama_bulan_baru" min="1" max="12" value="1" style="width:80px; padding:5px;" required />
                                    <button type="submit" class="btn primary small">🔄 Perpanjang</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="empty" id="filterEmptyMessage" style="display:none;">Tidak ada yang cocok dengan filter.</p>
            <?php endif; ?>
        </section>

        <section class="card" id="riwayat">
            <h2>📜 Riwayat Keluar</h2>
            <?php if (empty($riwayat)): ?>
                <p class="empty">Belum ada riwayat.</p>
            <?php else: ?>
                <?php $riwayat_tampil = array_reverse($riwayat); ?>
                <ul>
                    <?php foreach ($riwayat_tampil as $p): ?>
                        <?php
                        $namaTampil = ($p['kamar'] === 'Double' && !empty($p['nama2']))
                            ? "{$p['nama1']} & {$p['nama2']}"
                            : ($p['nama1'] ?? '');
                        ?>
                        <li>
                            <div>👤 Nama: <?php echo htmlspecialchars($namaTampil); ?></div>
                            <div>📞 No. WA 1: <?php echo htmlspecialchars($p['no_wa1'] ?? 'N/A'); ?></div>
                            <?php if ($p['kamar'] === 'Double'): ?>
                                <div>📞 No. WA 2: <?php echo htmlspecialchars($p['no_wa2'] ?? 'N/A'); ?></div>
                            <?php endif; ?>
                            <div>🏢 Lantai: <?php echo (int)$p['lantai']; ?></div>
                            <div>🛏️ Tipe Kamar: <?php echo htmlspecialchars($p['kamar']); ?></div>
                            <div>📅 Mulai Sewa: <?php echo format_tanggal_indonesia($p['tanggal_mulai'] ?? 'N/A'); ?></div>
                            <div>⏰ Sewa Berakhir: <?php echo format_tanggal_indonesia($p['tanggal_berakhir'] ?? 'N/A'); ?></div>
                            <div>⏳ Lama Sewa: <?php echo (int)$p['lama_bulan']; ?> bulan</div>
                            <div>💰 Biaya Total: Rp <?php echo number_format((int)$p['biaya_total'], 0, ',', '.'); ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form action="proses.php" method="post" style="margin-top: 20px;">
                <input type="hidden" name="aksi" value="reset_riwayat" />
                <button type="submit" class="btn danger small">🗑️ Reset Riwayat Saja</button>
            </form>
        </section>
    </main>

    <footer class="footer">
        <form action="proses.php" method="post">
            <input type="hidden" name="aksi" value="reset" />
            <button type="submit" class="btn secondary">🔄 Reset Semua Data</button>
        </form>
        <p class="footnote">Bayu Seno Aji</p>
    </footer>

    <script>
    const kamarSelect = document.getElementById('kamarSelect');
    const namaFields = document.getElementById('namaFields');

    function updateNamaFields() {
        const value = kamarSelect.value;
        
        let htmlContent = '';
        
        if (value === 'Single') {
            htmlContent = `
                <label for="nama1">Nama Penghuni
                    <input type="text" id="nama1" name="nama1" required />
                </label>
                <label for="no_wa1">📞 No. WhatsApp
                    <input type="tel" id="no_wa1" name="no_wa1" placeholder="Contoh: 081234567890" required />
                </label>
            `;
        } else if (value === 'Double') {
            htmlContent = `
                <label for="nama1">Nama Penghuni 1
                    <input type="text" id="nama1" name="nama1" required />
                </label>
                <label for="no_wa1">📞 No. WhatsApp 1
                    <input type="tel" id="no_wa1" name="no_wa1" placeholder="Contoh: 081234567890" required />
                </label>
                <label for="nama2">Nama Penghuni 2
                    <input type="text" id="nama2" name="nama2" required />
                </label>
                <label for="no_wa2">📞 No. WhatsApp 2
                    <input type="tel" id="no_wa2" name="no_wa2" placeholder="Contoh: 081234567890" required />
                </label>
            `;
        }

        namaFields.innerHTML = htmlContent;
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateNamaFields();
    });
    kamarSelect.addEventListener('change', updateNamaFields);
    </script>

    <script>
    function filterAktif() {
        const lantaiFilter = document.getElementById('filterLantai').value;
        const kamarFilter = document.getElementById('filterKamar').value;
        const listAktif = document.getElementById('listAktif');
        const filterEmptyMessage = document.getElementById('filterEmptyMessage');

        if (!listAktif) return;

        const listItems = listAktif.querySelectorAll('li');
        let visibleCount = 0;

        listItems.forEach(item => {
            const itemLantai = item.getAttribute('data-lantai');
            const itemKamar = item.getAttribute('data-kamar');
            const matchLantai = (lantaiFilter === 'all' || lantaiFilter === itemLantai);
            const matchKamar = (kamarFilter === 'all' || kamarFilter === itemKamar);

            if (matchLantai && matchKamar) {
                item.style.display = 'block';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        if (filterEmptyMessage) {
            filterEmptyMessage.style.display = (visibleCount === 0) ? 'block' : 'none';
        }
    }
    </script>
</body>
</html>
