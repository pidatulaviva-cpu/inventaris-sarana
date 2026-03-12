<?php
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['admin']);

$db   = new Database();
$conn = $db->getConnection();

$filter_status = $_GET['status']  ?? '';
$filter_dari   = $_GET['dari']    ?? date('Y-01-01');
$filter_sampai = $_GET['sampai']  ?? date('Y-m-d');
$jenis_laporan = $_GET['jenis']   ?? 'peminjaman';

// Laporan Peminjaman
$where = "WHERE p.tanggal_pinjam BETWEEN :dari AND :sampai";
$params = [':dari'=>$filter_dari, ':sampai'=>$filter_sampai];
if ($filter_status) { $where .= " AND p.status_peminjaman=:st"; $params[':st']=$filter_status; }

$lapPinjam = $conn->prepare(
    "SELECT p.id_peminjaman, p.tanggal_pinjam, p.tanggal_kembali, p.status_peminjaman,
            pg.nama_pegawai, pg.nip,
            GROUP_CONCAT(i.nama SEPARATOR ', ') as barang,
            SUM(dp.jumlah) as total_item
     FROM inventaris_sarana_peminjaman p
     JOIN inventaris_sarana_pegawai pg ON p.id_pegawai = pg.id_pegawai
     JOIN inventaris_sarana_detail_pinjam dp ON p.id_peminjaman = dp.id_peminjaman
     JOIN inventaris_sarana_inventaris i ON dp.id_inventaris = i.id_inventaris
     $where GROUP BY p.id_peminjaman ORDER BY p.tanggal_pinjam DESC"
);
$lapPinjam->execute($params);
$dataPinjam = $lapPinjam->fetchAll();

// Laporan Inventaris
$lapInv = $conn->query(
    "SELECT i.*, j.nama_jenis, r.nama_ruang
     FROM inventaris_sarana_inventaris i
     JOIN inventaris_sarana_jenis j ON i.id_jenis=j.id_jenis
     JOIN inventaris_sarana_ruang r ON i.id_ruang=r.id_ruang
     ORDER BY j.nama_jenis, i.nama"
)->fetchAll();

// Summary
$totalPinjam    = count($dataPinjam);
$totalDipinjam  = count(array_filter($dataPinjam, fn($r)=>$r['status_peminjaman']==='dipinjam'));
$totalKembali   = $totalPinjam - $totalDipinjam;
$totalInvItem   = array_sum(array_column($lapInv,'jumlah'));

$pageTitle = 'Laporan';
include '../includes/header.php';

// Mode PRINT
if (isset($_GET['print'])):
?>
<script>window.addEventListener('load',()=>window.print())</script>
<style>
  @media print {
    aside, header, .no-print { display:none!important; }
    main { padding:0!important; }
    body { background:white; }
    .print-title { display:block!important; }
  }
  .print-title { display:none; }
</style>
<?php endif; ?>

<div class="space-y-6">

  <!-- Tab -->
  <div class="flex gap-2">
    <a href="?jenis=peminjaman&dari=<?=$filter_dari?>&sampai=<?=$filter_sampai?>"
       class="px-5 py-2 rounded-xl text-sm font-semibold <?= $jenis_laporan==='peminjaman' ? 'bg-maroon-700 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
      <i class="fas fa-hand-holding mr-1"></i>Laporan Peminjaman
    </a>
    <a href="?jenis=inventaris&dari=<?=$filter_dari?>&sampai=<?=$filter_sampai?>"
       class="px-5 py-2 rounded-xl text-sm font-semibold <?= $jenis_laporan==='inventaris' ? 'bg-maroon-700 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
      <i class="fas fa-boxes-stacked mr-1"></i>Laporan Inventaris
    </a>
  </div>

  <?php if ($jenis_laporan === 'peminjaman'): ?>
  <!-- FILTER Peminjaman -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 no-print">
    <form method="GET" class="flex flex-wrap items-end gap-4">
      <input type="hidden" name="jenis" value="peminjaman">
      <div>
        <label class="text-xs text-gray-500 font-medium mb-1 block">Dari Tanggal</label>
        <input type="date" name="dari" value="<?= $filter_dari ?>"
               class="border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none">
      </div>
      <div>
        <label class="text-xs text-gray-500 font-medium mb-1 block">Sampai Tanggal</label>
        <input type="date" name="sampai" value="<?= $filter_sampai ?>"
               class="border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none">
      </div>
      <div>
        <label class="text-xs text-gray-500 font-medium mb-1 block">Status</label>
        <select name="status" class="border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none">
          <option value="">Semua Status</option>
          <option value="dipinjam"      <?= $filter_status==='dipinjam'      ? 'selected':''?>>Dipinjam</option>
          <option value="dikembalikan"  <?= $filter_status==='dikembalikan'  ? 'selected':''?>>Dikembalikan</option>
        </select>
      </div>
      <button type="submit" class="bg-maroon-700 text-white px-5 py-2 rounded-xl text-sm font-semibold hover:bg-maroon-800">
        <i class="fas fa-filter mr-1"></i>Filter
      </button>
      <a href="?jenis=peminjaman&dari=<?=$filter_dari?>&sampai=<?=$filter_sampai?>&print=1"
         target="_blank"
         class="bg-green-700 text-white px-5 py-2 rounded-xl text-sm font-semibold hover:bg-green-800">
        <i class="fas fa-print mr-1"></i>Cetak
      </a>
    </form>
  </div>

  <!-- Summary Badges -->
  <div class="grid grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm text-center">
      <p class="text-2xl font-bold text-gray-800"><?= $totalPinjam ?></p>
      <p class="text-xs text-gray-500">Total Transaksi</p>
    </div>
    <div class="bg-amber-50 rounded-2xl p-4 border border-amber-100 shadow-sm text-center">
      <p class="text-2xl font-bold text-amber-700"><?= $totalDipinjam ?></p>
      <p class="text-xs text-amber-600">Masih Dipinjam</p>
    </div>
    <div class="bg-green-50 rounded-2xl p-4 border border-green-100 shadow-sm text-center">
      <p class="text-2xl font-bold text-green-700"><?= $totalKembali ?></p>
      <p class="text-xs text-green-600">Dikembalikan</p>
    </div>
  </div>

  <!-- Print Header (hanya muncul saat print) -->
  <div class="print-title bg-white p-6 rounded-2xl text-center border mb-4" style="display:none">
    <h2 class="text-xl font-bold">LAPORAN PEMINJAMAN SARPRAS SMK</h2>
    <p class="text-sm text-gray-600">Periode: <?= formatTanggal($filter_dari) ?> s/d <?= formatTanggal($filter_sampai) ?></p>
    <p class="text-sm text-gray-400">Dicetak: <?= date('d/m/Y H:i') ?></p>
  </div>

  <!-- Tabel Laporan -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold text-gray-800">Data Peminjaman</h3>
      <span class="text-xs text-gray-500">Periode: <?= formatTanggal($filter_dari) ?> — <?= formatTanggal($filter_sampai) ?></span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
            <th class="pb-2 font-semibold">No</th>
            <th class="pb-2 font-semibold">Pegawai</th>
            <th class="pb-2 font-semibold">NIP</th>
            <th class="pb-2 font-semibold">Barang</th>
            <th class="pb-2 font-semibold">Tgl Pinjam</th>
            <th class="pb-2 font-semibold">Tgl Kembali</th>
            <th class="pb-2 font-semibold">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($dataPinjam)): ?>
          <tr><td colspan="7" class="py-8 text-center text-gray-400">Tidak ada data pada periode ini</td></tr>
          <?php else: $no=1; foreach ($dataPinjam as $d): ?>
          <tr>
            <td class="py-2.5 text-gray-400"><?= $no++ ?></td>
            <td class="py-2.5 font-medium"><?= htmlspecialchars($d['nama_pegawai']) ?></td>
            <td class="py-2.5 text-xs text-gray-500"><?= $d['nip'] ?></td>
            <td class="py-2.5 text-gray-600 max-w-[200px] truncate"><?= htmlspecialchars($d['barang']) ?></td>
            <td class="py-2.5 text-gray-500"><?= formatTanggal($d['tanggal_pinjam']) ?></td>
            <td class="py-2.5 text-gray-500"><?= $d['tanggal_kembali'] ? formatTanggal($d['tanggal_kembali']) : '-' ?></td>
            <td class="py-2.5">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium
                <?= $d['status_peminjaman']==='dipinjam' ? 'badge-dipinjam' : 'badge-dikembalikan' ?>">
                <?= ucfirst($d['status_peminjaman']) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php else: /* INVENTARIS */ ?>
  <!-- Laporan Inventaris -->
  <div class="flex justify-end no-print">
    <a href="?jenis=inventaris&print=1" target="_blank"
       class="bg-green-700 text-white px-5 py-2 rounded-xl text-sm font-semibold hover:bg-green-800">
      <i class="fas fa-print mr-1"></i>Cetak Laporan Inventaris
    </a>
  </div>

  <div class="grid grid-cols-2 gap-4">
    <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm text-center">
      <p class="text-2xl font-bold text-gray-800"><?= count($lapInv) ?></p>
      <p class="text-xs text-gray-500">Jenis Barang</p>
    </div>
    <div class="bg-maroon-50 rounded-2xl p-4 border border-maroon-100 shadow-sm text-center">
      <p class="text-2xl font-bold text-maroon-700"><?= $totalInvItem ?></p>
      <p class="text-xs text-maroon-600">Total Unit</p>
    </div>
  </div>

  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">Daftar Inventaris Lengkap</h3>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
            <th class="pb-2 font-semibold">No</th>
            <th class="pb-2 font-semibold">Nama Barang</th>
            <th class="pb-2 font-semibold">Jenis</th>
            <th class="pb-2 font-semibold">Ruang</th>
            <th class="pb-2 font-semibold text-center">Jumlah</th>
            <th class="pb-2 font-semibold">Kondisi</th>
            <th class="pb-2 font-semibold">Tgl Register</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php $no=1; foreach ($lapInv as $i): ?>
          <tr>
            <td class="py-2.5 text-gray-400"><?= $no++ ?></td>
            <td class="py-2.5 font-medium"><?= htmlspecialchars($i['nama']) ?></td>
            <td class="py-2.5 text-gray-500"><?= htmlspecialchars($i['nama_jenis']) ?></td>
            <td class="py-2.5 text-gray-500"><?= htmlspecialchars($i['nama_ruang']) ?></td>
            <td class="py-2.5 text-center"><?= $i['jumlah'] ?></td>
            <td class="py-2.5">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium
                <?= str_contains($i['kondisi'],'Rusak') ? 'badge-rusak' : 'badge-baik' ?>">
                <?= htmlspecialchars($i['kondisi']) ?>
              </span>
            </td>
            <td class="py-2.5 text-gray-500 text-xs"><?= formatTanggal($i['tanggal_register']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
