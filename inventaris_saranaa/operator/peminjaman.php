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

/* ══════════════════════════════════════════════
   ACC per item barang
   ?acc_item=id_detail_pinjam
══════════════════════════════════════════════ */
if (isset($_GET['acc_item'])) {
    $id_detail = (int)$_GET['acc_item'];

    $qDetail = $conn->prepare(
        "SELECT dp.id_detail_pinjam, dp.id_inventaris, dp.id_peminjaman, dp.jumlah,
                dp.status_item, p.id_pegawai
         FROM inventaris_sarana_detail_pinjam dp
         JOIN inventaris_sarana_peminjaman p ON p.id_peminjaman = dp.id_peminjaman
         WHERE dp.id_detail_pinjam = :id"
    );
    $qDetail->execute([':id' => $id_detail]);
    $det = $qDetail->fetch();

    if (!$det) { flash('error', 'Data item tidak ditemukan.'); redirect('peminjaman.php'); }

    // Cek blokir
    $cekBlokir = $conn->prepare(
        "SELECT id_peminjaman, DATEDIFF(CURDATE(), tanggal_kembali) AS hari_telat
         FROM inventaris_sarana_peminjaman
         WHERE id_pegawai = :idp
           AND status_peminjaman IN ('dipinjam','terlambat')
           AND tanggal_kembali IS NOT NULL
           AND DATEDIFF(CURDATE(), tanggal_kembali) > 10
         ORDER BY hari_telat DESC LIMIT 1"
    );
    $cekBlokir->execute([':idp' => $det['id_pegawai']]);
    $blokir = $cekBlokir->fetch();
    if ($blokir) {
        flash('error', 'Tidak dapat disetujui. Peminjam memiliki keterlambatan ' . $blokir['hari_telat'] . ' hari (ID #' . $blokir['id_peminjaman'] . ').');
        redirect('peminjaman.php');
    }

    try {
        $conn->beginTransaction();

        $qStok = $conn->prepare(
            "SELECT nama, jumlah, jumlah_rusak, (jumlah - jumlah_rusak) AS stok_bisa_pinjam
             FROM inventaris_sarana_inventaris WHERE id_inventaris = :id"
        );
        $qStok->execute([':id' => $det['id_inventaris']]);
        $inv = $qStok->fetch();

        if ($inv['jumlah_rusak'] >= $inv['jumlah']) {
            $conn->rollBack();
            flash('error', '"' . $inv['nama'] . '" kondisi Rusak Berat, tidak dapat dipinjam!');
            redirect('peminjaman.php');
        }
        if ((int)$inv['stok_bisa_pinjam'] < (int)$det['jumlah']) {
            $conn->rollBack();
            flash('error', 'Stok "' . $inv['nama'] . '" tidak mencukupi. Tersedia: ' . $inv['stok_bisa_pinjam'] . ' unit.');
            redirect('peminjaman.php');
        }

        // Kurangi stok
        $conn->prepare(
            "UPDATE inventaris_sarana_inventaris SET jumlah = jumlah - :k WHERE id_inventaris = :iid"
        )->execute([':k' => $det['jumlah'], ':iid' => $det['id_inventaris']]);

        // Tandai item disetujui
        $conn->prepare(
            "UPDATE inventaris_sarana_detail_pinjam SET status_item = 'disetujui' WHERE id_detail_pinjam = :id"
        )->execute([':id' => $id_detail]);

        // Cek sisa item menunggu
        $qSisa = $conn->prepare(
            "SELECT COUNT(*) FROM inventaris_sarana_detail_pinjam
             WHERE id_peminjaman = :idp AND (status_item IS NULL OR status_item = 'menunggu')"
        );
        $qSisa->execute([':idp' => $det['id_peminjaman']]);

        if ((int)$qSisa->fetchColumn() === 0) {
            $qAda = $conn->prepare(
                "SELECT COUNT(*) FROM inventaris_sarana_detail_pinjam
                 WHERE id_peminjaman = :idp AND status_item = 'disetujui'"
            );
            $qAda->execute([':idp' => $det['id_peminjaman']]);
            $statusAkhir = (int)$qAda->fetchColumn() > 0 ? 'dipinjam' : 'ditolak';
            $conn->prepare(
                "UPDATE inventaris_sarana_peminjaman SET status_peminjaman = :s WHERE id_peminjaman = :idp"
            )->execute([':s' => $statusAkhir, ':idp' => $det['id_peminjaman']]);
        } else {
            $conn->prepare(
                "UPDATE inventaris_sarana_peminjaman SET status_peminjaman = 'diproses' WHERE id_peminjaman = :idp"
            )->execute([':idp' => $det['id_peminjaman']]);
        }

        $conn->commit();
        flash('success', '"' . $inv['nama'] . '" berhasil disetujui.');
    } catch (Exception $e) {
        $conn->rollBack();
        flash('error', 'Gagal: ' . $e->getMessage());
    }
    redirect('peminjaman.php');
}

/* ══════════════════════════════════════════════
   TOLAK per item barang
   ?tolak_item=id_detail_pinjam
══════════════════════════════════════════════ */
if (isset($_GET['tolak_item'])) {
    $id_detail = (int)$_GET['tolak_item'];

    $qDetail = $conn->prepare(
        "SELECT dp.id_detail_pinjam, dp.id_peminjaman, i.nama
         FROM inventaris_sarana_detail_pinjam dp
         JOIN inventaris_sarana_inventaris i ON i.id_inventaris = dp.id_inventaris
         WHERE dp.id_detail_pinjam = :id"
    );
    $qDetail->execute([':id' => $id_detail]);
    $det = $qDetail->fetch();

    if (!$det) { flash('error', 'Data item tidak ditemukan.'); redirect('peminjaman.php'); }

    $conn->prepare(
        "UPDATE inventaris_sarana_detail_pinjam SET status_item = 'ditolak' WHERE id_detail_pinjam = :id"
    )->execute([':id' => $id_detail]);

    $qSisa = $conn->prepare(
        "SELECT COUNT(*) FROM inventaris_sarana_detail_pinjam
         WHERE id_peminjaman = :idp AND (status_item IS NULL OR status_item = 'menunggu')"
    );
    $qSisa->execute([':idp' => $det['id_peminjaman']]);

    if ((int)$qSisa->fetchColumn() === 0) {
        $qAda = $conn->prepare(
            "SELECT COUNT(*) FROM inventaris_sarana_detail_pinjam
             WHERE id_peminjaman = :idp AND status_item = 'disetujui'"
        );
        $qAda->execute([':idp' => $det['id_peminjaman']]);
        $statusAkhir = (int)$qAda->fetchColumn() > 0 ? 'dipinjam' : 'ditolak';
        $conn->prepare(
            "UPDATE inventaris_sarana_peminjaman SET status_peminjaman = :s WHERE id_peminjaman = :idp"
        )->execute([':s' => $statusAkhir, ':idp' => $det['id_peminjaman']]);
    } else {
        $conn->prepare(
            "UPDATE inventaris_sarana_peminjaman SET status_peminjaman = 'diproses' WHERE id_peminjaman = :idp"
        )->execute([':idp' => $det['id_peminjaman']]);
    }

    flash('success', '"' . $det['nama'] . '" ditolak.');
    redirect('peminjaman.php');
}

/* ══════════════════════════════════════════════
   TOLAK SEMUA item menunggu dalam satu pengajuan
   ?tolak_semua=id_peminjaman
══════════════════════════════════════════════ */
if (isset($_GET['tolak_semua'])) {
    $id_pinjam = (int)$_GET['tolak_semua'];
    $conn->prepare(
        "UPDATE inventaris_sarana_detail_pinjam
         SET status_item = 'ditolak'
         WHERE id_peminjaman = :idp AND (status_item IS NULL OR status_item = 'menunggu')"
    )->execute([':idp' => $id_pinjam]);

    // Cek apakah masih ada yg disetujui sebelumnya
    $qAda = $conn->prepare(
        "SELECT COUNT(*) FROM inventaris_sarana_detail_pinjam
         WHERE id_peminjaman = :idp AND status_item = 'disetujui'"
    );
    $qAda->execute([':idp' => $id_pinjam]);
    $statusAkhir = (int)$qAda->fetchColumn() > 0 ? 'dipinjam' : 'ditolak';

    $conn->prepare(
        "UPDATE inventaris_sarana_peminjaman SET status_peminjaman = :s WHERE id_peminjaman = :idp"
    )->execute([':s' => $statusAkhir, ':idp' => $id_pinjam]);

    flash('success', 'Sisa item pada pengajuan #' . $id_pinjam . ' ditolak.');
    redirect('peminjaman.php');
}

/* ══════════════════════════════════════════════
   Query: pengajuan menunggu + sedang diproses
   Ambil per detail item (bukan GROUP_CONCAT)
══════════════════════════════════════════════ */
$menungguRaw = $conn->query(
    "SELECT p.id_peminjaman, p.tanggal_pinjam, p.tanggal_kembali,
            p.status_peminjaman, pg.nama_pegawai, pg.id_pegawai,
            dp.id_detail_pinjam, dp.id_inventaris, dp.jumlah AS jml_pinjam,
            dp.status_item,
            i.nama AS nama_barang,
            (i.jumlah - i.jumlah_rusak) AS stok_tersedia,
            (SELECT MAX(DATEDIFF(CURDATE(), p2.tanggal_kembali))
             FROM inventaris_sarana_peminjaman p2
             WHERE p2.id_pegawai = p.id_pegawai
               AND p2.status_peminjaman IN ('dipinjam','terlambat')
               AND p2.tanggal_kembali IS NOT NULL
               AND DATEDIFF(CURDATE(), p2.tanggal_kembali) > 10
            ) AS hari_telat_blokir
     FROM inventaris_sarana_peminjaman p
     JOIN inventaris_sarana_pegawai pg ON p.id_pegawai = pg.id_pegawai
     JOIN inventaris_sarana_detail_pinjam dp ON dp.id_peminjaman = p.id_peminjaman
     JOIN inventaris_sarana_inventaris i ON i.id_inventaris = dp.id_inventaris
     WHERE p.status_peminjaman IN ('menunggu','diproses')
     ORDER BY p.tanggal_pinjam ASC, p.id_peminjaman, dp.id_detail_pinjam"
)->fetchAll();

// Kelompokkan per id_peminjaman
$menunggu = [];
foreach ($menungguRaw as $row) {
    $pid = $row['id_peminjaman'];
    if (!isset($menunggu[$pid])) {
        $menunggu[$pid] = [
            'id_peminjaman'     => $pid,
            'tanggal_pinjam'    => $row['tanggal_pinjam'],
            'tanggal_kembali'   => $row['tanggal_kembali'],
            'status_peminjaman' => $row['status_peminjaman'],
            'nama_pegawai'      => $row['nama_pegawai'],
            'id_pegawai'        => $row['id_pegawai'],
            'hari_telat_blokir' => $row['hari_telat_blokir'],
            'items'             => [],
        ];
    }
    $menunggu[$pid]['items'][] = [
        'id_detail_pinjam' => $row['id_detail_pinjam'],
        'nama_barang'      => $row['nama_barang'],
        'jml_pinjam'       => $row['jml_pinjam'],
        'stok_tersedia'    => $row['stok_tersedia'],
        'status_item'      => $row['status_item'] ?? 'menunggu',
    ];
}

// Riwayat (sudah selesai diproses)
$semua = $conn->query(
    "SELECT p.*, pg.nama_pegawai,
            GROUP_CONCAT(i.nama, ' (', dp.jumlah, ')' SEPARATOR ', ') as barang,
            SUM(dp.jumlah) as total_item
     FROM inventaris_sarana_peminjaman p
     JOIN inventaris_sarana_pegawai pg ON p.id_pegawai = pg.id_pegawai
     JOIN inventaris_sarana_detail_pinjam dp ON p.id_peminjaman = dp.id_peminjaman
     JOIN inventaris_sarana_inventaris i ON dp.id_inventaris = i.id_inventaris
     WHERE p.status_peminjaman NOT IN ('menunggu','diproses')
     GROUP BY p.id_peminjaman
     ORDER BY p.id_peminjaman DESC LIMIT 30"
)->fetchAll();

$pageTitle = 'ACC Peminjaman';
include '../includes/header.php';
?>

<div class="space-y-6">

  <!-- ── Menunggu ACC ── -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-center gap-2 mb-4">
      <h3 class="font-semibold text-gray-800">Menunggu Persetujuan</h3>
      <?php if (count($menunggu) > 0): ?>
      <span class="bg-blue-100 text-blue-700 text-xs font-bold px-2 py-0.5 rounded-full animate-pulse">
        <?= count($menunggu) ?> pengajuan
      </span>
      <?php endif; ?>
    </div>

    <?php if (empty($menunggu)): ?>
    <div class="text-center py-8 text-gray-400">
      <i class="fas fa-inbox text-3xl mb-2 block"></i>
      Tidak ada permintaan peminjaman baru
    </div>

    <?php else: ?>
    <div class="space-y-5">

      <?php foreach ($menunggu as $m):
        $isBlokir  = !empty($m['hari_telat_blokir']);
        $diproses  = $m['status_peminjaman'] === 'diproses';
        $sisaItem  = array_filter($m['items'], fn($it) => $it['status_item'] === 'menunggu');
        $totalItem = count($m['items']);
        $sudahItem = $totalItem - count($sisaItem);
      ?>

      <div class="border <?= $isBlokir ? 'border-red-300' : 'border-blue-200' ?> rounded-xl overflow-hidden">

        <!-- Header card pengajuan -->
        <div class="px-4 py-3 flex flex-wrap items-center gap-x-3 gap-y-1
                    <?= $isBlokir ? 'bg-red-50 border-b border-red-200' : 'bg-blue-50 border-b border-blue-200' ?>">

          <i class="fas fa-user-circle text-gray-400"></i>
          <span class="font-semibold text-gray-800"><?= htmlspecialchars($m['nama_pegawai']) ?></span>
          <span class="text-xs font-mono text-gray-400">#<?= $m['id_peminjaman'] ?></span>

          <?php if ($diproses): ?>
          <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">
            <i class="fas fa-spinner mr-1"></i>Sedang diproses
            <span class="font-normal">(<?= $sudahItem ?>/<?= $totalItem ?> item)</span>
          </span>
          <?php else: ?>
          <span class="text-xs bg-blue-200 text-blue-800 px-2 py-0.5 rounded-full">
            Menunggu · <?= $totalItem ?> item
          </span>
          <?php endif; ?>

          <?php if ($isBlokir): ?>
          <span class="text-xs bg-red-200 text-red-800 px-2 py-0.5 rounded-full font-semibold">
            <i class="fas fa-ban mr-1"></i>Diblokir – telat <?= $m['hari_telat_blokir'] ?> hari
          </span>
          <?php endif; ?>

          <!-- Tanggal di kanan -->
          <div class="ml-auto flex items-center gap-4 text-xs text-gray-500">
            <span><i class="fas fa-calendar-plus mr-1"></i><?= formatTanggal($m['tanggal_pinjam']) ?></span>
            <span><i class="fas fa-calendar-check mr-1"></i><?= $m['tanggal_kembali'] ? formatTanggal($m['tanggal_kembali']) : '-' ?></span>
            <?php if (!$isBlokir && !empty($sisaItem)): ?>
            <a href="?tolak_semua=<?= $m['id_peminjaman'] ?>"
               onclick="return confirm('Tolak SEMUA barang yang masih menunggu pada pengajuan ini?')"
               class="text-xs bg-red-100 hover:bg-red-200 text-red-600 px-3 py-1 rounded-lg font-semibold">
              <i class="fas fa-circle-xmark mr-1"></i>Tolak Semua
            </a>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($isBlokir): ?>
        <div class="px-4 py-2 text-xs text-red-600 font-medium bg-red-50/60">
          <i class="fas fa-exclamation-triangle mr-1"></i>
          Peminjam memiliki barang terlambat >10 hari. Item tidak dapat disetujui sampai barang dikembalikan.
        </div>
        <?php endif; ?>

        <!-- Baris per item barang -->
        <div class="divide-y divide-gray-100 bg-white">
          <?php foreach ($m['items'] as $item):
            $stokCukup  = $item['stok_tersedia'] >= $item['jml_pinjam'];
            $statusItem = $item['status_item'] ?? 'menunggu';
            $selesai    = in_array($statusItem, ['disetujui','ditolak']);
          ?>
          <div class="px-4 py-3 flex flex-wrap md:flex-nowrap items-center gap-3
                      <?= $statusItem === 'ditolak' ? 'bg-gray-50' : '' ?>">

            <!-- Nama barang -->
            <div class="flex-1 min-w-0 flex items-start gap-2">
              <i class="fas fa-box text-gray-300 mt-0.5 text-xs flex-shrink-0"></i>
              <div>
                <span class="font-medium text-sm <?= $statusItem === 'ditolak' ? 'line-through text-gray-400' : 'text-gray-800' ?>">
                  <?= htmlspecialchars($item['nama_barang']) ?>
                </span>
                <span class="text-xs text-gray-400 ml-1">× <?= $item['jml_pinjam'] ?> unit</span>
                <div class="text-xs mt-0.5 text-gray-400">
                  Stok tersedia:
                  <strong class="<?= $stokCukup ? 'text-green-600' : 'text-red-500' ?>">
                    <?= $item['stok_tersedia'] ?>
                  </strong>
                  <?php if (!$stokCukup && $statusItem === 'menunggu'): ?>
                  <span class="text-red-500 ml-1"><i class="fas fa-exclamation-circle"></i> Stok tidak cukup</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Tombol aksi / badge status -->
            <div class="flex items-center gap-2 flex-shrink-0">

              <?php if ($statusItem === 'disetujui'): ?>
                <span class="px-3 py-1 rounded-lg text-xs font-semibold bg-green-100 text-green-700">
                  <i class="fas fa-circle-check mr-1"></i>Disetujui
                </span>

              <?php elseif ($statusItem === 'ditolak'): ?>
                <span class="px-3 py-1 rounded-lg text-xs font-semibold bg-gray-200 text-gray-500">
                  <i class="fas fa-circle-xmark mr-1"></i>Ditolak
                </span>

              <?php elseif ($isBlokir): ?>
                <span class="px-3 py-1 rounded-lg text-xs font-semibold bg-gray-200 text-gray-400 cursor-not-allowed">
                  <i class="fas fa-ban mr-1"></i>Diblokir
                </span>

              <?php elseif (!$stokCukup): ?>
                <span class="px-3 py-1 rounded-lg text-xs font-semibold bg-orange-100 text-orange-600">
                  <i class="fas fa-exclamation-triangle mr-1"></i>Stok kurang
                </span>
                <a href="?tolak_item=<?= $item['id_detail_pinjam'] ?>"
                   onclick="return confirm('Tolak item ini karena stok tidak mencukupi?')"
                   class="px-3 py-1 rounded-lg text-xs font-semibold bg-red-500 hover:bg-red-600 text-white">
                  <i class="fas fa-circle-xmark mr-1"></i>Tolak
                </a>

              <?php else: ?>
                <a href="?acc_item=<?= $item['id_detail_pinjam'] ?>"
                   onclick="return confirm('Setujui peminjaman <?= htmlspecialchars(addslashes($item['nama_barang'])) ?> (<?= $item['jml_pinjam'] ?> unit)?')"
                   class="px-3 py-1 rounded-lg text-xs font-semibold bg-green-600 hover:bg-green-700 text-white">
                  <i class="fas fa-circle-check mr-1"></i>ACC
                </a>
                <a href="?tolak_item=<?= $item['id_detail_pinjam'] ?>"
                   onclick="return confirm('Tolak item <?= htmlspecialchars(addslashes($item['nama_barang'])) ?>?')"
                   class="px-3 py-1 rounded-lg text-xs font-semibold bg-red-500 hover:bg-red-600 text-white">
                  <i class="fas fa-circle-xmark mr-1"></i>Tolak
                </a>
              <?php endif; ?>

            </div>
          </div>
          <?php endforeach; ?>
        </div><!-- end divide -->

      </div><!-- end card -->
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Riwayat ── -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">Riwayat Peminjaman</h3>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
            <th class="pb-2 font-semibold">ID</th>
            <th class="pb-2 font-semibold">Pegawai</th>
            <th class="pb-2 font-semibold">Barang</th>
            <th class="pb-2 font-semibold">Tgl Pinjam</th>
            <th class="pb-2 font-semibold">Rencana Kembali</th>
            <th class="pb-2 font-semibold">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($semua)): ?>
          <tr><td colspan="6" class="py-6 text-center text-gray-400">Belum ada data</td></tr>
          <?php else: foreach ($semua as $d):
            $badge = match($d['status_peminjaman']) {
              'dipinjam'     => 'badge-dipinjam',
              'terlambat'    => 'bg-red-100 text-red-700',
              'dikembalikan' => 'badge-dikembalikan',
              'ditolak'      => 'bg-gray-100 text-gray-500',
              default        => 'bg-gray-100 text-gray-500',
            };
          ?>
          <tr class="hover:bg-gray-50">
            <td class="py-2.5 font-mono text-xs">#<?= $d['id_peminjaman'] ?></td>
            <td class="py-2.5 font-medium"><?= htmlspecialchars($d['nama_pegawai']) ?></td>
            <td class="py-2.5 text-gray-500 max-w-[200px] truncate"><?= htmlspecialchars($d['barang']) ?></td>
            <td class="py-2.5 text-gray-500"><?= formatTanggal($d['tanggal_pinjam']) ?></td>
            <td class="py-2.5 text-gray-500"><?= $d['tanggal_kembali'] ? formatTanggal($d['tanggal_kembali']) : '-' ?></td>
            <td class="py-2.5">
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