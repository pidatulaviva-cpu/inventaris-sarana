<?php
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['peminjam']);

$db         = new Database();
$conn       = $db->getConnection();
$id_pegawai = $_SESSION['id_pegawai'];

// Ambil semua pengajuan milik peminjam ini, beserta detail per item
$riwayatRaw = $conn->prepare(
    "SELECT p.id_peminjaman, p.tanggal_pinjam, p.tanggal_kembali, p.status_peminjaman,
            dp.id_detail_pinjam, dp.jumlah, dp.status_item,
            i.nama AS nama_barang
     FROM inventaris_sarana_peminjaman p
     JOIN inventaris_sarana_detail_pinjam dp ON dp.id_peminjaman = p.id_peminjaman
     JOIN inventaris_sarana_inventaris i ON i.id_inventaris = dp.id_inventaris
     WHERE p.id_pegawai = :idp
     ORDER BY p.id_peminjaman DESC, dp.id_detail_pinjam"
);
$riwayatRaw->execute([':idp' => $id_pegawai]);
$rows = $riwayatRaw->fetchAll();

// Kelompokkan per pengajuan
$riwayat = [];
foreach ($rows as $row) {
    $pid = $row['id_peminjaman'];
    if (!isset($riwayat[$pid])) {
        $riwayat[$pid] = [
            'id_peminjaman'     => $pid,
            'tanggal_pinjam'    => $row['tanggal_pinjam'],
            'tanggal_kembali'   => $row['tanggal_kembali'],
            'status_peminjaman' => $row['status_peminjaman'],
            'items'             => [],
        ];
    }
    $riwayat[$pid]['items'][] = [
        'nama_barang' => $row['nama_barang'],
        'jumlah'      => $row['jumlah'],
        'status_item' => $row['status_item'] ?? 'menunggu',
    ];
}

$pageTitle = 'Riwayat Peminjaman';
include '../includes/header.php';
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
  <h3 class="font-semibold text-gray-800 mb-4">Riwayat Peminjaman Saya (<?= count($riwayat) ?>)</h3>

  <?php if (empty($riwayat)): ?>
  <div class="text-center py-10 text-gray-400">
    <i class="fas fa-clock-rotate-left text-3xl mb-2 block"></i>
    Belum ada riwayat peminjaman
  </div>
  <?php else: ?>
  <div class="space-y-4">
    <?php foreach ($riwayat as $r):
      $badgeHeader = match($r['status_peminjaman']) {
        'menunggu'     => 'bg-blue-100 text-blue-700',
        'diproses'     => 'bg-amber-100 text-amber-700',
        'dipinjam'     => 'badge-dipinjam',
        'terlambat'    => 'bg-red-100 text-red-700',
        'dikembalikan' => 'badge-dikembalikan',
        'ditolak'      => 'bg-gray-100 text-gray-500',
        default        => 'bg-gray-100 text-gray-500',
      };
      $labelHeader = match($r['status_peminjaman']) {
        'menunggu'     => 'Menunggu',
        'diproses'     => 'Sedang Diproses',
        'dipinjam'     => 'Dipinjam',
        'terlambat'    => 'Terlambat',
        'dikembalikan' => 'Dikembalikan',
        'ditolak'      => 'Ditolak',
        default        => ucfirst($r['status_peminjaman']),
      };
    ?>
    <div class="border border-gray-200 rounded-xl overflow-hidden">

      <!-- Header pengajuan -->
      <div class="bg-gray-50 px-4 py-2.5 flex flex-wrap items-center gap-2 border-b border-gray-100">
        <span class="text-xs font-mono text-gray-400">#<?= $r['id_peminjaman'] ?></span>
        <span class="text-xs text-gray-500">
          <i class="fas fa-calendar-plus mr-1"></i><?= formatTanggal($r['tanggal_pinjam']) ?>
        </span>
        <?php if ($r['tanggal_kembali']): ?>
        <span class="text-xs text-gray-500">
          <i class="fas fa-calendar-check mr-1"></i><?= formatTanggal($r['tanggal_kembali']) ?>
        </span>
        <?php endif; ?>
        <span class="ml-auto px-2 py-0.5 rounded-full text-xs font-semibold <?= $badgeHeader ?>">
          <?= $labelHeader ?>
        </span>
      </div>

      <!-- Daftar item per baris -->
      <div class="divide-y divide-gray-50">
        <?php foreach ($r['items'] as $item):
          $si = $item['status_item'];
          $badgeItem = match($si) {
            'disetujui'  => 'bg-green-100 text-green-700',
            'ditolak'    => 'bg-red-100 text-red-600',
            'menunggu'   => 'bg-blue-50 text-blue-600',
            default      => 'bg-gray-100 text-gray-500',
          };
          $labelItem = match($si) {
            'disetujui' => '<i class="fas fa-circle-check mr-1"></i>Disetujui',
            'ditolak'   => '<i class="fas fa-circle-xmark mr-1"></i>Ditolak',
            'menunggu'  => '<i class="fas fa-clock mr-1"></i>Menunggu',
            default     => ucfirst($si),
          };
        ?>
        <div class="px-4 py-2.5 flex items-center gap-3 <?= $si === 'ditolak' ? 'bg-gray-50/60' : '' ?>">
          <i class="fas fa-box text-gray-300 text-xs flex-shrink-0"></i>
          <span class="flex-1 text-sm <?= $si === 'ditolak' ? 'line-through text-gray-400' : 'text-gray-700' ?>">
            <?= htmlspecialchars($item['nama_barang']) ?>
          </span>
          <span class="text-xs text-gray-400">× <?= $item['jumlah'] ?></span>
          <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $badgeItem ?>">
            <?= $labelItem ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>

    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>