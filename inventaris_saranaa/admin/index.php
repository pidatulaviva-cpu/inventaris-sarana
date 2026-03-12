<?php
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['admin']);

$db   = new Database();
$conn = $db->getConnection();

$total_inventaris = $conn->query("SELECT COUNT(*) FROM inventaris_sarana_inventaris")->fetchColumn();
$total_pengguna   = $conn->query("SELECT COUNT(*) FROM inventaris_sarana_petugas")->fetchColumn()
                  + $conn->query("SELECT COUNT(*) FROM inventaris_sarana_pegawai")->fetchColumn();
$total_pinjam     = $conn->query("SELECT COUNT(*) FROM inventaris_sarana_peminjaman WHERE status_peminjaman='dipinjam'")->fetchColumn();
$total_kembali    = $conn->query("SELECT COUNT(*) FROM inventaris_sarana_peminjaman WHERE status_peminjaman='dikembalikan'")->fetchColumn();

// Peminjaman terbaru
$recent = $conn->query(
    "SELECT p.id_peminjaman, p.tanggal_pinjam, p.status_peminjaman,
            pg.nama_pegawai, GROUP_CONCAT(i.nama SEPARATOR ', ') as barang
     FROM inventaris_sarana_peminjaman p
     JOIN inventaris_sarana_pegawai pg ON p.id_pegawai = pg.id_pegawai
     JOIN inventaris_sarana_detail_pinjam dp ON p.id_peminjaman = dp.id_peminjaman
     JOIN inventaris_sarana_inventaris i ON dp.id_inventaris = i.id_inventaris
     GROUP BY p.id_peminjaman
     ORDER BY p.tanggal_pinjam DESC LIMIT 6"
)->fetchAll();

// Inventaris kondisi rusak
$rusak = $conn->query(
    "SELECT nama, kondisi, id_inventaris FROM inventaris_sarana_inventaris
     WHERE kondisi LIKE '%Rusak%' LIMIT 5"
)->fetchAll();

$pageTitle = 'Beranda Admin';
include '../includes/header.php';
?>

<div class="space-y-6">
  <!-- Stats Cards -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <?php
    $stats = [
      ['icon'=>'fa-boxes-stacked',     'label'=>'Total Inventaris', 'value'=>$total_inventaris, 'color'=>'maroon', 'bg'=>'bg-maroon-50',  'ic'=>'text-maroon-600'],
      ['icon'=>'fa-users',             'label'=>'Total Pengguna',   'value'=>$total_pengguna,   'color'=>'blue',   'bg'=>'bg-blue-50',   'ic'=>'text-blue-600'],
      ['icon'=>'fa-hand-holding',      'label'=>'Sedang Dipinjam',  'value'=>$total_pinjam,     'color'=>'amber',  'bg'=>'bg-amber-50',  'ic'=>'text-amber-600'],
      ['icon'=>'fa-rotate-left',       'label'=>'Dikembalikan',     'value'=>$total_kembali,    'color'=>'green',  'bg'=>'bg-green-50',  'ic'=>'text-green-600'],
    ];
    foreach ($stats as $s):
    ?>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 card-hover">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs text-gray-500 font-medium mb-1"><?= $s['label'] ?></p>
          <p class="text-3xl font-bold text-gray-800"><?= $s['value'] ?></p>
        </div>
        <div class="w-11 h-11 <?= $s['bg'] ?> rounded-xl flex items-center justify-center">
          <i class="fas <?= $s['icon'] ?> <?= $s['ic'] ?>"></i>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Peminjaman Terbaru -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-gray-800">Transaksi Peminjaman Terbaru</h3>
        <a href="peminjaman.php" class="text-xs text-maroon-600 hover:underline">Lihat semua →</a>
      </div>
      <?php if (empty($recent)): ?>
        <p class="text-gray-400 text-sm text-center py-6">Belum ada data peminjaman</p>
      <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
              <th class="pb-2 font-semibold">Peminjam</th>
              <th class="pb-2 font-semibold">Barang</th>
              <th class="pb-2 font-semibold">Tanggal</th>
              <th class="pb-2 font-semibold">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php foreach ($recent as $r): ?>
            <tr>
              <td class="py-2.5 font-medium"><?= htmlspecialchars($r['nama_pegawai']) ?></td>
              <td class="py-2.5 text-gray-500 max-w-[160px] truncate"><?= htmlspecialchars($r['barang']) ?></td>
              <td class="py-2.5 text-gray-500"><?= formatTanggal($r['tanggal_pinjam']) ?></td>
              <td class="py-2.5">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                  <?= $r['status_peminjaman'] === 'dipinjam' ? 'badge-dipinjam' : 'badge-dikembalikan' ?>">
                  <?= ucfirst($r['status_peminjaman']) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Inventaris Rusak -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-gray-800">Perlu Perhatian</h3>
        <span class="text-xs text-red-500 bg-red-50 px-2 py-0.5 rounded-full">Rusak</span>
      </div>
      <?php if (empty($rusak)): ?>
        <div class="flex flex-col items-center justify-center py-8 text-center">
          <i class="fas fa-circle-check text-green-400 text-3xl mb-2"></i>
          <p class="text-sm text-gray-500">Semua kondisi baik!</p>
        </div>
      <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($rusak as $r): ?>
        <div class="flex items-start gap-3">
          <div class="w-8 h-8 bg-red-50 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
            <i class="fas fa-triangle-exclamation text-red-500 text-xs"></i>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($r['nama']) ?></p>
            <p class="text-xs text-red-500"><?= htmlspecialchars($r['kondisi']) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <h3 class="font-semibold text-gray-800 mb-4">Aksi Cepat</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <?php
      $actions = [
        ['href'=>'pengguna.php',           'icon'=>'fa-user-plus',      'label'=>'Tambah Pengguna',  'color'=>'maroon'],
        ['href'=>'peminjaman.php',          'icon'=>'fa-hand-holding',   'label'=>'Catat Pinjam',    'color'=>'amber'],
        ['href'=>'laporan.php',             'icon'=>'fa-file-lines',     'label'=>'Laporan','color'=>'green'],
      ];
      $colorMap = [
        'maroon'=>['bg'=>'bg-maroon-50','ic'=>'text-maroon-600','border'=>'border-maroon-100'],
        'blue'  =>['bg'=>'bg-blue-50',  'ic'=>'text-blue-600',  'border'=>'border-blue-100'],
        'amber' =>['bg'=>'bg-amber-50', 'ic'=>'text-amber-600', 'border'=>'border-amber-100'],
        'green' =>['bg'=>'bg-green-50', 'ic'=>'text-green-600', 'border'=>'border-green-100'],
      ];
      foreach ($actions as $a):
        $c = $colorMap[$a['color']];
      ?>
      <a href="<?= $a['href'] ?>"
         class="flex items-center gap-3 p-4 rounded-xl border <?= $c['border'] ?> <?= $c['bg'] ?>
                hover:shadow-md transition-all group">
        <i class="fas <?= $a['icon'] ?> <?= $c['ic'] ?>"></i>
        <span class="text-sm font-medium text-gray-700"><?= $a['label'] ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
