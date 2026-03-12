<?php
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['peminjam']);

$db   = new Database();
$conn = $db->getConnection();
$id_pegawai = $_SESSION['id_pegawai'];

$dipinjam = $conn->query(
    "SELECT COUNT(*) FROM inventaris_sarana_peminjaman
     WHERE id_pegawai=$id_pegawai AND status_peminjaman='dipinjam'"
)->fetchColumn();

$total_pinjam = $conn->query(
    "SELECT COUNT(*) FROM inventaris_sarana_peminjaman WHERE id_pegawai=$id_pegawai"
)->fetchColumn();

$aktif = $conn->query(
    "SELECT p.id_peminjaman, p.tanggal_pinjam, p.tanggal_kembali,
            GROUP_CONCAT(i.nama SEPARATOR ', ') as barang
     FROM inventaris_sarana_peminjaman p
     JOIN inventaris_sarana_detail_pinjam dp ON p.id_peminjaman = dp.id_peminjaman
     JOIN inventaris_sarana_inventaris i ON dp.id_inventaris = i.id_inventaris
     WHERE p.id_pegawai = $id_pegawai AND p.status_peminjaman IN ('dipinjam','terlambat')
     GROUP BY p.id_peminjaman ORDER BY p.tanggal_pinjam DESC"
)->fetchAll();

// Cek apakah ada keterlambatan > 10 hari
$cekTelat = $conn->prepare(
    "SELECT id_peminjaman, tanggal_kembali,
            DATEDIFF(CURDATE(), tanggal_kembali) AS hari_telat
     FROM inventaris_sarana_peminjaman
     WHERE id_pegawai = :id
       AND status_peminjaman IN ('dipinjam','terlambat')
       AND tanggal_kembali IS NOT NULL
       AND DATEDIFF(CURDATE(), tanggal_kembali) > 10
     ORDER BY hari_telat DESC
     LIMIT 1"
);
$cekTelat->execute([':id' => $id_pegawai]);
$dataTelat = $cekTelat->fetch();
$diblokir  = !empty($dataTelat);

$pageTitle = 'Dashboard';
include '../includes/header.php';
?>
<div class="space-y-6">
  <?php if ($diblokir): ?>
  <div class="bg-red-50 border border-red-300 rounded-2xl p-5">
    <div class="flex items-start gap-3">
      <i class="fas fa-ban text-red-500 text-xl mt-0.5"></i>
      <div>
        <p class="font-semibold text-red-800 mb-1">⛔ Akun Peminjaman Diblokir Sementara</p>
        <p class="text-sm text-red-700">Peminjaman <strong>#<?= $dataTelat['id_peminjaman'] ?></strong> sudah terlambat
          <strong><?= $dataTelat['hari_telat'] ?> hari</strong>
          (rencana kembali: <?= formatTanggal($dataTelat['tanggal_kembali']) ?>).</p>
        <p class="text-sm text-red-700 mt-1">Anda tidak dapat mengajukan peminjaman baru sampai barang dikembalikan.</p>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <div class="grid grid-cols-2 gap-4">
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
      <p class="text-xs text-gray-500 mb-1">Sedang Dipinjam</p>
      <p class="text-3xl font-bold text-amber-600"><?= $dipinjam ?></p>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
      <p class="text-xs text-gray-500 mb-1">Total Peminjaman</p>
      <p class="text-3xl font-bold text-gray-800"><?= $total_pinjam ?></p>
    </div>
  </div>

  <?php if (!empty($aktif)): ?>
  <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5">
    <h3 class="font-semibold text-amber-800 mb-3"><i class="fas fa-triangle-exclamation mr-2"></i>Barang Sedang Anda Pinjam</h3>
    <div class="space-y-3">
      <?php foreach ($aktif as $a): $terlambat = $a['tanggal_kembali'] && $a['tanggal_kembali'] < date('Y-m-d'); ?>
      <div class="bg-white rounded-xl p-4 border border-amber-100">
        <p class="font-medium text-gray-800 text-sm"><?= htmlspecialchars($a['barang']) ?></p>
        <p class="text-xs text-gray-500 mt-1">Dipinjam: <?= formatTanggal($a['tanggal_pinjam']) ?></p>
        <?php if ($a['tanggal_kembali']): ?>
        <p class="text-xs <?= $terlambat ? 'text-red-600 font-semibold' : 'text-gray-500' ?> mt-0.5">
          Rencana kembali: <?= formatTanggal($a['tanggal_kembali']) ?>
          <?= $terlambat ? ' ⚠️ TERLAMBAT' : '' ?>
        </p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="grid grid-cols-2 gap-4">
    <a href="peminjaman.php" class="bg-white rounded-2xl p-5 shadow-sm border <?= $diblokir ? 'border-red-200 opacity-60' : 'border-gray-100 card-hover' ?> flex items-center gap-4">
      <div class="w-12 h-12 <?= $diblokir ? 'bg-red-50' : 'bg-maroon-50' ?> rounded-xl flex items-center justify-center">
        <i class="fas <?= $diblokir ? 'fa-ban text-red-400' : 'fa-hand-holding text-maroon-600' ?>"></i>
      </div>
      <div>
        <p class="font-semibold text-gray-800 text-sm">Pinjam Barang</p>
        <p class="text-xs <?= $diblokir ? 'text-red-400' : 'text-gray-400' ?>"><?= $diblokir ? 'Diblokir – ada keterlambatan' : 'Ajukan peminjaman' ?></p>
      </div>
    </a>
    <a href="riwayat.php" class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 card-hover flex items-center gap-4">
      <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
        <i class="fas fa-clock-rotate-left text-blue-600"></i>
      </div>
      <div>
        <p class="font-semibold text-gray-800 text-sm">Riwayat Saya</p>
        <p class="text-xs text-gray-400">Lihat riwayat pinjam</p>
      </div>
    </a>
  </div>
</div>
<?php include '../includes/footer.php'; ?>