<?php
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['peminjam']);

$db         = new Database();
$conn       = $db->getConnection();
$id_pegawai = $_SESSION['id_pegawai'];

// Cek keterlambatan lebih dari 10 hari
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Blokir jika masih ada keterlambatan > 10 hari
    if ($diblokir) {
        flash('error', 'Pengajuan diblokir! Anda memiliki peminjaman terlambat lebih dari 10 hari (ID #' . $dataTelat['id_peminjaman'] . ', telat ' . $dataTelat['hari_telat'] . ' hari). Kembalikan barang terlebih dahulu.');
        redirect(BASE_URL . 'peminjam/peminjaman.php');
    }

    $tgl_pinjam  = $_POST['tanggal_pinjam'];
    $tgl_kembali = $_POST['tanggal_kembali'];
    $items       = $_POST['id_inventaris'] ?? [];
    $jmls        = $_POST['jumlah_pinjam'] ?? [];

    if (empty($items)) {
        flash('error', 'Pilih minimal satu barang.');
        redirect(BASE_URL . 'peminjam/peminjaman.php');
    }

    try {
        $conn->beginTransaction();

        // Validasi server-side: jumlah tidak boleh melebihi stok baik
        foreach ($items as $idx => $id_inv) {
            $jml = max(1, (int)($jmls[$idx] ?? 1));
            $cek = $conn->prepare(
                "SELECT nama, (jumlah - jumlah_rusak) AS stok_bisa_pinjam
                 FROM inventaris_sarana_inventaris WHERE id_inventaris = :id"
            );
            $cek->execute([':id' => (int)$id_inv]);
            $inv = $cek->fetch();
            if ($jml > (int)$inv['stok_bisa_pinjam']) {
                $conn->rollBack();
                flash('error', 'Jumlah pinjam "'.$inv['nama'].'" melebihi stok tersedia ('.$inv['stok_bisa_pinjam'].' unit).');
                redirect(BASE_URL . 'peminjam/peminjaman.php');
            }
        }

        // Status 'menunggu' — menunggu acc operator
        $conn->prepare(
            "INSERT INTO inventaris_sarana_peminjaman
             (tanggal_pinjam, tanggal_kembali, status_peminjaman, id_pegawai)
             VALUES(:tp, :tk, 'menunggu', :ip)"
        )->execute([':tp'=>$tgl_pinjam, ':tk'=>$tgl_kembali, ':ip'=>$id_pegawai]);
        $id_pinjam = $conn->lastInsertId();

        $s = $conn->prepare(
            "INSERT INTO inventaris_sarana_detail_pinjam (id_inventaris, id_peminjaman, jumlah)
             VALUES(:ii, :ip, :j)"
        );
        foreach ($items as $idx => $id_inv) {
            $s->execute([':ii'=>(int)$id_inv, ':ip'=>$id_pinjam, ':j'=>max(1,(int)($jmls[$idx]??1))]);
        }

        $conn->commit();
        flash('success', 'Permintaan peminjaman berhasil diajukan, menunggu persetujuan operator.');
    } catch (Exception $e) {
        $conn->rollBack();
        flash('error', 'Gagal: ' . $e->getMessage());
    }
    redirect(BASE_URL . 'peminjam/peminjaman.php');
}

// Auto-update terlambat
$conn->query(
    "UPDATE inventaris_sarana_peminjaman
     SET status_peminjaman = 'terlambat'
     WHERE status_peminjaman = 'dipinjam'
       AND tanggal_kembali IS NOT NULL
       AND tanggal_kembali < CURDATE()"
);

$inventaris = $conn->query(
    "SELECT id_inventaris, nama, (jumlah - jumlah_rusak) AS stok_bisa_pinjam
     FROM inventaris_sarana_inventaris
     WHERE (jumlah - jumlah_rusak) > 0
     ORDER BY nama"
)->fetchAll();

$mineRaw = $conn->prepare(
    "SELECT p.id_peminjaman, p.tanggal_pinjam, p.tanggal_kembali, p.status_peminjaman,
            dp.jumlah, dp.status_item,
            i.nama AS nama_barang
     FROM inventaris_sarana_peminjaman p
     JOIN inventaris_sarana_detail_pinjam dp ON dp.id_peminjaman = p.id_peminjaman
     JOIN inventaris_sarana_inventaris i ON i.id_inventaris = dp.id_inventaris
     WHERE p.id_pegawai = :idp
     ORDER BY p.id_peminjaman DESC, dp.id_detail_pinjam"
);
$mineRaw->execute([':idp' => $id_pegawai]);
$mine = [];
foreach ($mineRaw->fetchAll() as $row) {
    $pid = $row['id_peminjaman'];
    if (!isset($mine[$pid])) {
        $mine[$pid] = [
            'id_peminjaman'     => $pid,
            'tanggal_pinjam'    => $row['tanggal_pinjam'],
            'tanggal_kembali'   => $row['tanggal_kembali'],
            'status_peminjaman' => $row['status_peminjaman'],
            'items'             => [],
        ];
    }
    $mine[$pid]['items'][] = [
        'nama_barang' => $row['nama_barang'],
        'jumlah'      => $row['jumlah'],
        'status_item' => $row['status_item'] ?? 'menunggu',
    ];
}

$pageTitle = 'Pinjam Barang';
include '../includes/header.php';
?>

<div class="space-y-6">
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-5">
      <i class="fas fa-hand-holding text-maroon-600 mr-2"></i>Ajukan Peminjaman
    </h3>

    <?php if ($diblokir): ?>
    <div class="bg-red-50 border border-red-300 rounded-xl px-4 py-4 text-sm text-red-700 mb-5">
      <div class="flex items-start gap-3">
        <i class="fas fa-ban text-red-500 text-lg mt-0.5"></i>
        <div>
          <p class="font-semibold text-red-800 mb-1">Pengajuan Peminjaman Diblokir</p>
          <p>Anda memiliki peminjaman <strong>#<?= $dataTelat['id_peminjaman'] ?></strong> yang sudah terlambat
             <strong><?= $dataTelat['hari_telat'] ?> hari</strong> (rencana kembali:
             <strong><?= formatTanggal($dataTelat['tanggal_kembali']) ?></strong>).</p>
          <p class="mt-1">Harap kembalikan barang tersebut terlebih dahulu sebelum mengajukan peminjaman baru.</p>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-sm text-blue-700 mb-5">
      <i class="fas fa-info-circle mr-2"></i>
      Pengajuan akan diproses oleh operator. Stok berkurang setelah disetujui.
    </div>
    <?php endif; ?>
    <form method="POST" class="space-y-5 <?= $diblokir ? 'opacity-40 pointer-events-none select-none' : '' ?>">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="text-sm font-medium text-gray-600 mb-1 block">Tanggal Pinjam *</label>
          <input type="date" name="tanggal_pinjam" value="<?= date('Y-m-d') ?>"
                 class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none" required>
        </div>
        <div>
          <label class="text-sm font-medium text-gray-600 mb-1 block">Rencana Kembali *</label>
          <input type="date" name="tanggal_kembali"
                 class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none" required>
        </div>
      </div>

      <div>
        <div class="flex justify-between mb-2">
          <label class="text-sm font-medium text-gray-600">Barang yang Dipinjam *</label>
          <button type="button" onclick="tambahBaris()"
                  class="text-xs bg-maroon-700 text-white px-3 py-1.5 rounded-lg hover:bg-maroon-800">
            <i class="fas fa-plus mr-1"></i>Tambah
          </button>
        </div>
        <div id="barisContainer" class="space-y-2">
          <div class="baris flex gap-2 items-center">
            <select name="id_inventaris[]" onchange="updateMax(this)"
                    class="flex-1 border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none" required>
              <option value="">-- Pilih Barang --</option>
              <?php foreach ($inventaris as $inv): ?>
              <option value="<?= $inv['id_inventaris'] ?>" data-max="<?= $inv['stok_bisa_pinjam'] ?>">
                <?= htmlspecialchars($inv['nama']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <input type="number" name="jumlah_pinjam[]" value="1" min="1" max="1"
                   oninput="clampMax(this)"
                   class="w-24 border border-gray-300 rounded-xl px-3 py-2 text-sm text-center focus:ring-2 focus:ring-maroon-500 outline-none">
            <button type="button" onclick="hapusBaris(this)" class="text-red-400 hover:text-red-600 px-2">
              <i class="fas fa-times"></i>
            </button>
          </div>
        </div>
      </div>

      <button type="submit" class="bg-maroon-700 hover:bg-maroon-800 text-white px-6 py-2.5 rounded-xl text-sm font-semibold">
        <i class="fas fa-paper-plane mr-1"></i>Ajukan Peminjaman
      </button>
    </form>
  </div>

  <!-- Riwayat milik pegawai ini -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">Peminjaman Saya</h3>

    <?php if (empty($mine)): ?>
    <div class="text-center py-6 text-gray-400 text-sm">Belum ada pengajuan</div>
    <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($mine as $m):
        $badgeHeader = match($m['status_peminjaman']) {
          'menunggu'     => 'bg-blue-100 text-blue-700',
          'diproses'     => 'bg-amber-100 text-amber-700',
          'dipinjam'     => 'badge-dipinjam',
          'terlambat'    => 'bg-red-100 text-red-700',
          'dikembalikan' => 'badge-dikembalikan',
          'ditolak'      => 'bg-gray-100 text-gray-500',
          default        => 'bg-gray-100 text-gray-500',
        };
        $labelHeader = match($m['status_peminjaman']) {
          'menunggu'     => 'Menunggu',
          'diproses'     => 'Sedang Diproses',
          'dipinjam'     => 'Dipinjam',
          'terlambat'    => 'Terlambat',
          'dikembalikan' => 'Dikembalikan',
          'ditolak'      => 'Ditolak',
          default        => ucfirst($m['status_peminjaman']),
        };
      ?>
      <div class="border border-gray-200 rounded-xl overflow-hidden">
        <!-- Header -->
        <div class="bg-gray-50 px-3 py-2 flex flex-wrap items-center gap-2 border-b border-gray-100">
          <span class="text-xs font-mono text-gray-400">#<?= $m['id_peminjaman'] ?></span>
          <span class="text-xs text-gray-500"><i class="fas fa-calendar-plus mr-1"></i><?= formatTanggal($m['tanggal_pinjam']) ?></span>
          <?php if ($m['tanggal_kembali']): ?>
          <span class="text-xs text-gray-500"><i class="fas fa-calendar-check mr-1"></i><?= formatTanggal($m['tanggal_kembali']) ?></span>
          <?php endif; ?>
          <span class="ml-auto px-2 py-0.5 rounded-full text-xs font-semibold <?= $badgeHeader ?>"><?= $labelHeader ?></span>
        </div>
        <!-- Item per baris -->
        <div class="divide-y divide-gray-50">
          <?php foreach ($m['items'] as $item):
            $si = $item['status_item'];
            $badgeItem = match($si) {
              'disetujui' => 'bg-green-100 text-green-700',
              'ditolak'   => 'bg-red-100 text-red-500',
              default     => 'bg-blue-50 text-blue-600',
            };
            $labelItem = match($si) {
              'disetujui' => '<i class="fas fa-circle-check mr-1"></i>Disetujui',
              'ditolak'   => '<i class="fas fa-circle-xmark mr-1"></i>Ditolak',
              default     => '<i class="fas fa-clock mr-1"></i>Menunggu',
            };
          ?>
          <div class="px-3 py-2 flex items-center gap-2 <?= $si === 'ditolak' ? 'bg-gray-50/70' : '' ?>">
            <i class="fas fa-box text-gray-300 text-xs flex-shrink-0"></i>
            <span class="flex-1 text-sm <?= $si === 'ditolak' ? 'line-through text-gray-400' : 'text-gray-700' ?>">
              <?= htmlspecialchars($item['nama_barang']) ?>
            </span>
            <span class="text-xs text-gray-400">× <?= $item['jumlah'] ?></span>
            <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $badgeItem ?>"><?= $labelItem ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
// Data stok dari PHP — key: id_inventaris, value: stok_bisa_pinjam
const stokData = {
  <?php foreach ($inventaris as $inv): ?>
  <?= $inv['id_inventaris'] ?>: <?= $inv['stok_bisa_pinjam'] ?>,
  <?php endforeach; ?>
};

// Saat barang dipilih, update max input jumlah dan reset ke 1
function updateMax(sel) {
  const id  = parseInt(sel.value);
  const max = stokData[id] || 1;
  const input = sel.closest('.baris').querySelector('input[type=number]');
  input.max   = max;
  input.value = 1;
}

// Paksa nilai tidak melebihi max saat diketik manual
function clampMax(input) {
  const max = parseInt(input.max) || 1;
  const min = parseInt(input.min) || 1;
  if (parseInt(input.value) > max) input.value = max;
  if (parseInt(input.value) < min) input.value = min;
}

// Template option untuk baris baru
const invOpts = `<?php foreach($inventaris as $inv): ?><option value="<?= $inv['id_inventaris'] ?>" data-max="<?= $inv['stok_bisa_pinjam'] ?>"><?= htmlspecialchars($inv['nama']) ?></option><?php endforeach; ?>`;

function tambahBaris() {
  const c = document.getElementById('barisContainer');
  const d = document.createElement('div');
  d.className = 'baris flex gap-2 items-center';
  d.innerHTML = `
    <select name="id_inventaris[]" onchange="updateMax(this)"
            class="flex-1 border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none" required>
      <option value="">-- Pilih Barang --</option>${invOpts}
    </select>
    <input type="number" name="jumlah_pinjam[]" value="1" min="1" max="1"
           oninput="clampMax(this)"
           class="w-24 border border-gray-300 rounded-xl px-3 py-2 text-sm text-center focus:ring-2 focus:ring-maroon-500 outline-none">
    <button type="button" onclick="hapusBaris(this)" class="text-red-400 hover:text-red-600 px-2">
      <i class="fas fa-times"></i>
    </button>`;
  c.appendChild(d);
}

function hapusBaris(btn) {
  if (document.querySelectorAll('.baris').length > 1) btn.closest('.baris').remove();
}
</script>

<?php include '../includes/footer.php'; ?>