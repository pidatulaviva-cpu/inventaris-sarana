<?php
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['operator']);

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

/* ── KONFIRMASI CROSSCHECK dengan dukungan RUSAK SEBAGIAN ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_konfirmasi'])) {
    $id = (int)$_POST['id_peminjaman'];

    try {
        $conn->beginTransaction();

        // Ambil detail barang yang dipinjam — hanya yang disetujui
        $detail = $conn->prepare(
            "SELECT dp.id_inventaris, dp.jumlah
             FROM inventaris_sarana_detail_pinjam dp
             WHERE dp.id_peminjaman = :id AND dp.status_item = 'disetujui'"
        );
        $detail->execute([':id' => $id]);
        $rows = $detail->fetchAll();

        $ada_rusak = false;

        foreach ($rows as $row) {
            $inv_id       = $row['id_inventaris'];
            $jml_dipinjam = $row['jumlah'];

            // Ambil jumlah rusak yang diinput operator untuk barang ini
            $jml_rusak = (int)($_POST["rusak_{$inv_id}"] ?? 0);
            $jml_rusak = max(0, min($jml_rusak, $jml_dipinjam)); // clamp 0..jumlah_dipinjam
            $jml_baik  = $jml_dipinjam - $jml_rusak;

            // Kembalikan stok total
            $conn->prepare(
                "UPDATE inventaris_sarana_inventaris
                 SET jumlah = jumlah + :j
                 WHERE id_inventaris = :id"
            )->execute([':j' => $jml_dipinjam, ':id' => $inv_id]);

            // Update jumlah_rusak saja (jumlah_baik = jumlah - jumlah_rusak, dihitung otomatis)
            $conn->prepare(
                "UPDATE inventaris_sarana_inventaris
                 SET jumlah_rusak = jumlah_rusak + :rusak
                 WHERE id_inventaris = :id"
            )->execute([':rusak' => $jml_rusak, ':id' => $inv_id]);

            // Auto-update kolom kondisi: hitung jumlah_baik = jumlah - jumlah_rusak
            $s = $conn->prepare(
                "SELECT jumlah, jumlah_rusak
                 FROM inventaris_sarana_inventaris WHERE id_inventaris = :id"
            );
            $s->execute([':id' => $inv_id]);
            $inv = $s->fetch();

            $jumlah_baik_terkini = $inv['jumlah'] - $inv['jumlah_rusak'];

            if ($inv['jumlah_rusak'] == 0) {
                $kondisi_baru = 'Baik';
            } elseif ($jumlah_baik_terkini <= 0) {
                $kondisi_baru = 'Rusak Berat';   // semua rusak
            } else {
                $kondisi_baru = 'Rusak Ringan';   // sebagian rusak
            }

            $conn->prepare(
                "UPDATE inventaris_sarana_inventaris
                 SET kondisi = :k WHERE id_inventaris = :id"
            )->execute([':k' => $kondisi_baru, ':id' => $inv_id]);

            if ($jml_rusak > 0) $ada_rusak = true;
        }

        // Selesaikan status peminjaman
        $conn->prepare(
            "UPDATE inventaris_sarana_peminjaman
             SET status_peminjaman = 'dikembalikan', tanggal_kembali = CURDATE()
             WHERE id_peminjaman = :id"
        )->execute([':id' => $id]);

        $conn->commit();

        $msg = $ada_rusak
            ? 'Pengembalian dikonfirmasi. Sebagian/semua barang dicatat rusak & kondisi inventaris diperbarui.'
            : 'Pengembalian dikonfirmasi. Semua barang dalam kondisi baik.';
        flash('success', $msg);

    } catch (Exception $e) {
        $conn->rollBack();
        flash('error', 'Gagal: ' . $e->getMessage());
    }
    redirect('pengembalian.php');
}

/* ── TOLAK pengajuan kembali (misal barang belum dibawa) ── */
if (isset($_GET['tolak'])) {
    $id = (int)$_GET['tolak'];
    $conn->prepare(
        "UPDATE inventaris_sarana_peminjaman
         SET status_peminjaman = 'dipinjam'
         WHERE id_peminjaman = :id"
    )->execute([':id' => $id]);
    flash('error', 'Pengajuan pengembalian #'.$id.' dikembalikan ke status dipinjam.');
    redirect(BASE_URL . 'operator/pengembalian.php');
}

// Menunggu crosscheck dari pegawai — hanya barang yang disetujui
$menunggu = $conn->query(
    "SELECT p.*, pg.nama_pegawai, pg.nip,
            GROUP_CONCAT(i.nama, ' — ', dp.jumlah, ' pcs' ORDER BY i.nama SEPARATOR '\n') as barang_detail,
            GROUP_CONCAT(i.nama ORDER BY i.nama SEPARATOR ', ') as barang,
            COUNT(dp.id_detail_pinjam) as jml_item
     FROM inventaris_sarana_peminjaman p
     JOIN inventaris_sarana_pegawai pg ON p.id_pegawai = pg.id_pegawai
     JOIN inventaris_sarana_detail_pinjam dp ON p.id_peminjaman = dp.id_peminjaman
     JOIN inventaris_sarana_inventaris i ON dp.id_inventaris = i.id_inventaris
     WHERE p.status_peminjaman = 'menunggu_kembali'
       AND dp.status_item = 'disetujui'
     GROUP BY p.id_peminjaman
     ORDER BY p.tanggal_pinjam ASC"
)->fetchAll();

// Riwayat sudah dikembalikan
$kembali = $conn->query(
    "SELECT p.*, pg.nama_pegawai,
            GROUP_CONCAT(i.nama SEPARATOR ', ') as barang
     FROM inventaris_sarana_peminjaman p
     JOIN inventaris_sarana_pegawai pg ON p.id_pegawai = pg.id_pegawai
     JOIN inventaris_sarana_detail_pinjam dp ON p.id_peminjaman = dp.id_peminjaman
     JOIN inventaris_sarana_inventaris i ON dp.id_inventaris = i.id_inventaris
     WHERE p.status_peminjaman = 'dikembalikan'
     GROUP BY p.id_peminjaman
     ORDER BY p.tanggal_kembali DESC LIMIT 20"
)->fetchAll();

$pageTitle = 'Crosscheck Pengembalian';
include '../includes/header.php';
?>

<div class="space-y-6">

  <!-- Antrian Crosscheck -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-center gap-2 mb-4">
      <h3 class="font-semibold text-gray-800">Antrian Crosscheck Barang</h3>
      <?php if (count($menunggu) > 0): ?>
      <span class="bg-amber-100 text-amber-700 text-xs font-bold px-2 py-0.5 rounded-full animate-pulse">
        <?= count($menunggu) ?> menunggu
      </span>
      <?php endif; ?>
    </div>

    <?php if (empty($menunggu)): ?>
    <div class="text-center py-8 text-gray-400">
      <i class="fas fa-circle-check text-green-400 text-3xl mb-2 block"></i>
      Tidak ada barang yang menunggu crosscheck
    </div>
    <?php else: ?>
    <div class="space-y-4">
      <?php foreach ($menunggu as $m):
        $terlambat = $m['tanggal_kembali'] && $m['tanggal_kembali'] < date('Y-m-d');
      ?>
      <div class="border border-amber-200 bg-amber-50 rounded-xl p-5">
        <div class="flex flex-col md:flex-row gap-4">

          <!-- Info peminjam -->
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-2">
              <div class="w-8 h-8 bg-maroon-100 rounded-full flex items-center justify-center">
                <i class="fas fa-user text-maroon-600 text-xs"></i>
              </div>
              <div>
                <p class="font-semibold text-gray-800"><?= htmlspecialchars($m['nama_pegawai']) ?></p>
                <p class="text-xs text-gray-400">NIP: <?= $m['nip'] ?> · ID Pinjam #<?= $m['id_peminjaman'] ?></p>
              </div>
              <?php if ($terlambat): ?>
              <span class="text-xs bg-red-100 text-red-700 font-semibold px-2 py-0.5 rounded-full ml-1">⚠ Terlambat</span>
              <?php endif; ?>
            </div>

            <!-- Detail barang per baris -->
            <div class="bg-white border border-amber-100 rounded-lg p-3 mb-3">
              <p class="text-xs font-semibold text-gray-500 mb-2">
                <i class="fas fa-boxes-stacked mr-1"></i>Daftar Barang (<?= $m['jml_item'] ?> item):
              </p>
              <?php
              $barisBarang = explode("\n", $m['barang_detail']);
              foreach ($barisBarang as $baris): ?>
              <div class="flex items-center gap-2 py-1 border-b border-gray-50 last:border-0">
                <i class="fas fa-box text-amber-400 text-xs"></i>
                <span class="text-sm text-gray-700"><?= htmlspecialchars(trim($baris)) ?></span>
              </div>
              <?php endforeach; ?>
            </div>

            <div class="flex gap-4 text-xs text-gray-500">
              <span><i class="fas fa-calendar-plus mr-1"></i>Pinjam: <?= formatTanggal($m['tanggal_pinjam']) ?></span>
              <span class="<?= $terlambat ? 'text-red-600 font-semibold' : '' ?>">
                <i class="fas fa-calendar-check mr-1"></i>Batas: <?= $m['tanggal_kembali'] ? formatTanggal($m['tanggal_kembali']) : '-' ?>
              </span>
            </div>
          </div>

          <!-- Aksi crosscheck dengan input rusak per unit -->
          <div class="flex flex-col gap-2 justify-start min-w-[220px]">
            <p class="text-xs font-semibold text-gray-600 mb-1">
              <i class="fas fa-clipboard-check mr-1"></i>Input Jumlah Rusak per Barang:
            </p>
            <form method="POST" onsubmit="return confirm('Konfirmasi pengembalian dengan data kerusakan yang diinput?')">
              <input type="hidden" name="aksi_konfirmasi" value="1">
              <input type="hidden" name="id_peminjaman" value="<?= $m['id_peminjaman'] ?>">
              <?php
              $detailItems = $conn->prepare(
                  "SELECT dp.id_inventaris, dp.jumlah, i.nama
                   FROM inventaris_sarana_detail_pinjam dp
                   JOIN inventaris_sarana_inventaris i ON dp.id_inventaris = i.id_inventaris
                   WHERE dp.id_peminjaman = :id AND dp.status_item = 'disetujui'"
              );
              $detailItems->execute([':id' => $m['id_peminjaman']]);
              $items = $detailItems->fetchAll();
              foreach ($items as $item): ?>
              <div class="bg-white border border-gray-200 rounded-lg px-3 py-2 mb-2">
                <p class="text-xs font-semibold text-gray-700 mb-1 truncate"><?= htmlspecialchars($item['nama']) ?></p>
                <div class="flex items-center gap-2">
                  <label class="text-xs text-gray-500 whitespace-nowrap">Rusak:</label>
                  <input type="number"
                         name="rusak_<?= $item['id_inventaris'] ?>"
                         min="0" max="<?= $item['jumlah'] ?>" value="0"
                         class="w-14 border border-gray-300 rounded-lg px-2 py-1 text-xs text-center focus:ring-2 focus:ring-orange-400 outline-none">
                  <span class="text-xs text-gray-400">/ <?= $item['jumlah'] ?> unit</span>
                </div>
              </div>
              <?php endforeach; ?>
              <button type="submit"
                      class="w-full flex items-center justify-center gap-2 bg-maroon-700 hover:bg-maroon-800 text-white px-4 py-2.5 rounded-xl text-xs font-semibold mt-1">
                <i class="fas fa-circle-check"></i> Konfirmasi Pengembalian
              </button>
            </form>
            <a href="?tolak=<?= $m['id_peminjaman'] ?>"
               onclick="return confirm('Kembalikan ke status dipinjam? (misal barang belum dibawa)')"
               class="flex items-center justify-center gap-2 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2.5 rounded-xl text-xs font-semibold">
              <i class="fas fa-arrow-rotate-left"></i> Batalkan
            </a>
          </div>

        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Riwayat dikembalikan -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">Riwayat Pengembalian Terbaru</h3>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
            <th class="pb-2 font-semibold">ID</th>
            <th class="pb-2 font-semibold">Pegawai</th>
            <th class="pb-2 font-semibold">Barang</th>
            <th class="pb-2 font-semibold">Tgl Pinjam</th>
            <th class="pb-2 font-semibold">Tgl Kembali</th>
            <th class="pb-2 font-semibold">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($kembali)): ?>
          <tr><td colspan="6" class="py-6 text-center text-gray-400">Belum ada riwayat</td></tr>
          <?php else: foreach ($kembali as $k): ?>
          <tr class="hover:bg-gray-50">
            <td class="py-2.5 font-mono text-xs">#<?= $k['id_peminjaman'] ?></td>
            <td class="py-2.5 font-medium"><?= htmlspecialchars($k['nama_pegawai']) ?></td>
            <td class="py-2.5 text-gray-500 max-w-[180px] truncate"><?= htmlspecialchars($k['barang']) ?></td>
            <td class="py-2.5 text-gray-500"><?= formatTanggal($k['tanggal_pinjam']) ?></td>
            <td class="py-2.5 text-gray-500"><?= formatTanggal($k['tanggal_kembali']) ?></td>
            <td class="py-2.5">
              <span class="px-2 py-0.5 rounded-full text-xs badge-dikembalikan">Dikembalikan</span>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>