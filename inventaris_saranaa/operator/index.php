<?php
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['operator','admin']);

$db   = new Database();
$conn = $db->getConnection();

$total_inv    = $conn->query("SELECT COUNT(*) FROM inventaris_sarana_inventaris")->fetchColumn();
$total_jenis  = $conn->query("SELECT COUNT(*) FROM inventaris_sarana_jenis")->fetchColumn();
$total_ruang  = $conn->query("SELECT COUNT(*) FROM inventaris_sarana_ruang")->fetchColumn();
$total_pinjam = $conn->query("SELECT COUNT(*) FROM inventaris_sarana_peminjaman WHERE status_peminjaman='dipinjam'")->fetchColumn();

$recent_inv = $conn->query(
    "SELECT i.nama, i.kondisi, j.nama_jenis, r.nama_ruang, i.tanggal_register
     FROM inventaris_sarana_inventaris i
     JOIN inventaris_sarana_jenis j ON i.id_jenis = j.id_jenis
     JOIN inventaris_sarana_ruang r ON i.id_ruang = r.id_ruang
     ORDER BY i.id_inventaris DESC LIMIT 8"
)->fetchAll();

$pageTitle = 'Dashboard Operator';
include '../includes/header.php';
?>

<div class="space-y-6">
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <?php
    $cards = [
      ['fa-boxes-stacked','Total Inventaris',$total_inv,  'bg-maroon-50','text-maroon-600'],
      ['fa-tags',         'Jenis Barang',    $total_jenis,'bg-blue-50',  'text-blue-600'],
      ['fa-door-open',    'Ruang/Lokasi',    $total_ruang,'bg-amber-50', 'text-amber-600'],
      ['fa-hand-holding', 'Sedang Dipinjam', $total_pinjam,'bg-purple-50','text-purple-600'],
    ];
    foreach ($cards as [$ic,$lbl,$val,$bg,$tc]):
    ?>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 card-hover">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs text-gray-500 font-medium mb-1"><?= $lbl ?></p>
          <p class="text-3xl font-bold text-gray-800"><?= $val ?></p>
        </div>
        <div class="w-11 h-11 <?= $bg ?> rounded-xl flex items-center justify-center">
          <i class="fas <?= $ic ?> <?= $tc ?>"></i>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Quick nav -->
  <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
    <?php
    $links = [
      ['inventaris.php','fa-boxes-stacked','Kelola Inventaris','Tambah & edit barang'],
      ['jenis.php','fa-tags','Kelola Jenis','Kategori barang'],
      ['ruang.php','fa-door-open','Kelola Ruang','Lokasi barang'],
      ['peminjaman.php','fa-hand-holding','Catat Peminjaman','Proses pinjam'],
      ['pengembalian.php','fa-rotate-left','Pengembalian','Konfirmasi kembali'],
    ];
    foreach ($links as [$href,$ic,$title,$desc]):
    ?>
    <a href="<?= $href ?>"
       class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 card-hover flex items-center gap-4">
      <div class="w-12 h-12 bg-maroon-50 rounded-xl flex items-center justify-center flex-shrink-0">
        <i class="fas <?= $ic ?> text-maroon-600"></i>
      </div>
      <div>
        <p class="font-semibold text-gray-800 text-sm"><?= $title ?></p>
        <p class="text-xs text-gray-400"><?= $desc ?></p>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Inventaris terbaru -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">Inventaris Terbaru</h3>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
            <th class="pb-2 font-semibold">Nama Barang</th>
            <th class="pb-2 font-semibold">Jenis</th>
            <th class="pb-2 font-semibold">Ruang</th>
            <th class="pb-2 font-semibold">Kondisi</th>
            <th class="pb-2 font-semibold">Tgl Register</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php foreach ($recent_inv as $i): ?>
          <tr>
            <td class="py-2.5 font-medium"><?= htmlspecialchars($i['nama']) ?></td>
            <td class="py-2.5 text-gray-500"><?= htmlspecialchars($i['nama_jenis']) ?></td>
            <td class="py-2.5 text-gray-500"><?= htmlspecialchars($i['nama_ruang']) ?></td>
            <td class="py-2.5">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium
                <?= str_contains($i['kondisi'],'Rusak') ? 'badge-rusak' : 'badge-baik' ?>">
                <?= htmlspecialchars($i['kondisi']) ?>
              </span>
            </td>
            <td class="py-2.5 text-gray-500"><?= formatTanggal($i['tanggal_register']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
