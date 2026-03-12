<?php
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['peminjam']);

$db         = new Database();
$conn       = $db->getConnection();
$id_pegawai = $_SESSION['id_pegawai'];

// Auto-update terlambat
$conn->query(
    "UPDATE inventaris_sarana_peminjaman
     SET status_peminjaman = 'terlambat'
     WHERE status_peminjaman = 'dipinjam'
       AND tanggal_kembali IS NOT NULL
       AND tanggal_kembali < CURDATE()"
);

// Ajukan pengembalian
if (isset($_GET['ajukan'])) {
    $id  = (int)$_GET['ajukan'];
    $cek = $conn->prepare(
        "SELECT id_peminjaman FROM inventaris_sarana_peminjaman
         WHERE id_peminjaman=:id AND id_pegawai=:ip
           AND status_peminjaman IN ('dipinjam','terlambat')"
    );
    $cek->execute([':id'=>$id, ':ip'=>$id_pegawai]);
    if ($cek->fetch()) {
        $conn->prepare(
            "UPDATE inventaris_sarana_peminjaman
             SET status_peminjaman='menunggu_kembali'
             WHERE id_peminjaman=:id"
        )->execute([':id'=>$id]);
        flash('success', 'Pengajuan pengembalian berhasil, menunggu crosscheck operator.');
    } else {
        flash('error', 'Data tidak ditemukan atau sudah diproses.');
    }
    redirect(BASE_URL . 'peminjam/pengembalian.php');
}

// Barang yang sedang dipinjam / terlambat — hanya item yang disetujui
$dipinjam = $conn->prepare(
    "SELECT p.id_peminjaman, p.tanggal_pinjam, p.tanggal_kembali, p.status_peminjaman,
            GROUP_CONCAT(
              CONCAT(i.nama, ' (', dp.jumlah, ' pcs)')
              ORDER BY i.nama SEPARATOR ', '
            ) as barang
     FROM inventaris_sarana_peminjaman p
     JOIN inventaris_sarana_detail_pinjam dp ON p.id_peminjaman = dp.id_peminjaman
     JOIN inventaris_sarana_inventaris i ON dp.id_inventaris = i.id_inventaris
     WHERE p.id_pegawai = :idp
       AND p.status_peminjaman IN ('dipinjam','terlambat')
       AND dp.status_item = 'disetujui'
     GROUP BY p.id_peminjaman"
);
$dipinjam->execute([':idp' => $id_pegawai]);
$dipinjam = $dipinjam->fetchAll();

// Yang sedang menunggu crosscheck operator
$menunggu = $conn->query(
    "SELECT p.id_peminjaman, p.tanggal_pinjam, p.tanggal_kembali,
            GROUP_CONCAT(i.nama SEPARATOR ', ') as barang
     FROM inventaris_sarana_peminjaman p
     JOIN inventaris_sarana_detail_pinjam dp ON p.id_peminjaman = dp.id_peminjaman
     JOIN inventaris_sarana_inventaris i ON dp.id_inventaris = i.id_inventaris
     WHERE p.id_pegawai = $id_pegawai AND p.status_peminjaman = 'menunggu_kembali'
     GROUP BY p.id_peminjaman"
)->fetchAll();

$pageTitle = 'Pengembalian';
include '../includes/header.php';
?>

<div class="space-y-6">

  <!-- Barang aktif dipinjam -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">
      <i class="fas fa-hand-holding text-maroon-600 mr-2"></i>Barang Sedang Dipinjam
    </h3>
    <?php if (empty($dipinjam)): ?>
    <div class="text-center py-8 text-gray-400">
      <i class="fas fa-circle-check text-green-400 text-4xl mb-2 block"></i>
      <p>Tidak ada barang yang sedang Anda pinjam.</p>
    </div>
    <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($dipinjam as $d):
        $terlambat = $d['status_peminjaman'] === 'terlambat';
      ?>
      <div class="border <?= $terlambat ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-gray-50' ?> rounded-xl p-4 flex items-center justify-between gap-4">
        <div class="flex-1">
          <div class="flex items-center gap-2 mb-1">
            <p class="font-medium text-gray-800"><?= htmlspecialchars($d['barang']) ?></p>
            <?php if ($terlambat): ?>
            <span class="text-xs bg-red-100 text-red-700 font-semibold px-2 py-0.5 rounded-full">⚠ Terlambat</span>
            <?php endif; ?>
          </div>
          <p class="text-xs text-gray-500">Dipinjam: <?= formatTanggal($d['tanggal_pinjam']) ?></p>
          <?php if ($d['tanggal_kembali']): ?>
          <p class="text-xs <?= $terlambat ? 'text-red-600 font-semibold' : 'text-gray-500' ?>">
            Batas kembali: <?= formatTanggal($d['tanggal_kembali']) ?>
          </p>
          <?php endif; ?>
        </div>
        <a href="?ajukan=<?= $d['id_peminjaman'] ?>"
           onclick="return confirm('Ajukan pengembalian barang ini? Operator akan melakukan crosscheck.')"
           class="flex-shrink-0 bg-maroon-700 hover:bg-maroon-800 text-white px-4 py-2 rounded-xl text-xs font-semibold">
          <i class="fas fa-paper-plane mr-1"></i>Ajukan Kembali
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Menunggu crosscheck -->
  <?php if (!empty($menunggu)): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-center gap-2 mb-4">
      <h3 class="font-semibold text-gray-800">Menunggu Crosscheck Operator</h3>
      <span class="bg-amber-100 text-amber-700 text-xs font-bold px-2 py-0.5 rounded-full"><?= count($menunggu) ?></span>
    </div>
    <div class="space-y-3">
      <?php foreach ($menunggu as $m): ?>
      <div class="border border-amber-200 bg-amber-50 rounded-xl p-4 flex items-center justify-between">
        <div>
          <p class="font-medium text-gray-800"><?= htmlspecialchars($m['barang']) ?></p>
          <p class="text-xs text-gray-500">Dipinjam: <?= formatTanggal($m['tanggal_pinjam']) ?></p>
        </div>
        <span class="text-xs bg-amber-200 text-amber-800 font-semibold px-3 py-1 rounded-full">
          <i class="fas fa-clock mr-1"></i>Menunggu
        </span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>