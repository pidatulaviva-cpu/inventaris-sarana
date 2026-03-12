<?php
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['admin']);

$db   = new Database();
$conn = $db->getConnection();

// Auto-update terlambat
$conn->query(
    "UPDATE inventaris_sarana_peminjaman
     SET status_peminjaman = 'terlambat'
     WHERE status_peminjaman = 'dipinjam'
       AND tanggal_kembali IS NOT NULL
       AND tanggal_kembali < CURDATE()"
);

// Filter
$filter_status = $_GET['status'] ?? '';
$filter_cari   = trim($_GET['cari'] ?? '');

$where  = "WHERE 1=1";
$params = [];
if ($filter_status) {
    $where .= " AND p.status_peminjaman = :st";
    $params[':st'] = $filter_status;
}
if ($filter_cari) {
    $where .= " AND (pg.nama_pegawai LIKE :c OR i.nama LIKE :c)";
    $params[':c'] = "%$filter_cari%";
}

$stmt = $conn->prepare(
    "SELECT p.*, pg.nama_pegawai, pg.nip,
            GROUP_CONCAT(i.nama, ' (', dp.jumlah, ')' SEPARATOR ', ') as barang,
            SUM(dp.jumlah) as total_item
     FROM inventaris_sarana_peminjaman p
     JOIN inventaris_sarana_pegawai pg ON p.id_pegawai = pg.id_pegawai
     JOIN inventaris_sarana_detail_pinjam dp ON p.id_peminjaman = dp.id_peminjaman
     JOIN inventaris_sarana_inventaris i ON dp.id_inventaris = i.id_inventaris
     $where
     GROUP BY p.id_peminjaman
     ORDER BY p.id_peminjaman DESC"
);
$stmt->execute($params);
$daftar = $stmt->fetchAll();

// Ringkasan
$ringkasan = $conn->query(
    "SELECT status_peminjaman, COUNT(*) as total
     FROM inventaris_sarana_peminjaman
     GROUP BY status_peminjaman"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Data Peminjaman';
include '../includes/header.php';
?>

<div class="space-y-6">

  <!-- Ringkasan -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <?php
    $cards = [
      ['menunggu',     'Menunggu Konfirmasi', 'bg-blue-50',  'text-blue-600',  'fa-clock'],
      ['dipinjam',     'Dipinjam',     'bg-amber-50', 'text-amber-600', 'fa-hand-holding'],
      ['terlambat',    'Terlambat',    'bg-red-50',   'text-red-600',   'fa-triangle-exclamation'],
      ['dikembalikan', 'Dikembalikan', 'bg-green-50', 'text-green-600', 'fa-circle-check'],
    ];
    foreach ($cards as [$status, $label, $bg, $tc, $ic]):
    ?>
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 <?= $bg ?> rounded-xl flex items-center justify-center">
          <i class="fas <?= $ic ?> <?= $tc ?> text-sm"></i>
        </div>
        <div>
          <p class="text-2xl font-bold text-gray-800"><?= $ringkasan[$status] ?? 0 ?></p>
          <p class="text-xs text-gray-500"><?= $label ?></p>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Tabel -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <h3 class="font-semibold text-gray-800">Semua Data Peminjaman (<?= count($daftar) ?>)</h3>
      <form method="GET" class="flex gap-2 flex-wrap">
        <input type="text" name="cari" value="<?= htmlspecialchars($filter_cari) ?>"
               placeholder="Cari nama / barang..."
               class="border border-gray-300 rounded-xl px-3 py-2 text-sm w-44 focus:ring-2 focus:ring-maroon-500 outline-none">
        <select name="status" class="border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none">
          <option value="">Semua Status</option>
          <option value="menunggu"     <?= $filter_status==='menunggu'     ? 'selected':''?>>Menunggu</option>
          <option value="dipinjam"     <?= $filter_status==='dipinjam'     ? 'selected':''?>>Dipinjam</option>
          <option value="terlambat"    <?= $filter_status==='terlambat'    ? 'selected':''?>>Terlambat</option>
          <option value="dikembalikan" <?= $filter_status==='dikembalikan' ? 'selected':''?>>Dikembalikan</option>
          <option value="ditolak"      <?= $filter_status==='ditolak'      ? 'selected':''?>>Ditolak</option>
        </select>
        <button type="submit" class="bg-maroon-700 text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-maroon-800">
          <i class="fas fa-search"></i>
        </button>
        <a href="peminjaman.php" class="bg-gray-100 text-gray-600 px-4 py-2 rounded-xl text-sm hover:bg-gray-200">
          <i class="fas fa-times"></i>
        </a>
      </form>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
            <th class="pb-2 font-semibold">ID</th>
            <th class="pb-2 font-semibold">Peminjam</th>
            <th class="pb-2 font-semibold">NIP</th>
            <th class="pb-2 font-semibold">Barang</th>
            <th class="pb-2 font-semibold">Tgl Pinjam</th>
            <th class="pb-2 font-semibold">Rencana Kembali</th>
            <th class="pb-2 font-semibold">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($daftar)): ?>
          <tr><td colspan="7" class="py-8 text-center text-gray-400">
            <i class="fas fa-inbox text-3xl mb-2 block"></i>Tidak ada data
          </td></tr>
          <?php else: foreach ($daftar as $d):
            $badge = match($d['status_peminjaman']) {
              'menunggu'     => 'bg-blue-100 text-blue-700',
              'dipinjam'     => 'badge-dipinjam',
              'terlambat'    => 'bg-red-100 text-red-700',
              'dikembalikan' => 'badge-dikembalikan',
              'ditolak'      => 'bg-gray-100 text-gray-500',
              default        => 'bg-gray-100 text-gray-500',
            };
          ?>
          <tr class="hover:bg-gray-50">
            <td class="py-3 font-mono text-xs">#<?= $d['id_peminjaman'] ?></td>
            <td class="py-3 font-medium"><?= htmlspecialchars($d['nama_pegawai']) ?></td>
            <td class="py-3 text-xs text-gray-400"><?= $d['nip'] ?></td>
            <td class="py-3 text-gray-500 max-w-[200px]">
              <p class="truncate"><?= htmlspecialchars($d['barang']) ?></p>
              <span class="text-xs text-gray-400"><?= $d['total_item'] ?> item</span>
            </td>
            <td class="py-3 text-gray-500"><?= formatTanggal($d['tanggal_pinjam']) ?></td>
            <td class="py-3 text-gray-500"><?= $d['tanggal_kembali'] ? formatTanggal($d['tanggal_kembali']) : '-' ?></td>
            <td class="py-3">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $badge ?>">
                <?= ucfirst($d['status_peminjaman']) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>