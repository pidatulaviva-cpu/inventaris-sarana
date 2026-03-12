<?php
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['admin','operator']);

$db   = new Database();
$conn = $db->getConnection();

/* ── HAPUS ── */
if (isset($_GET['del'])) {
    $conn->prepare("DELETE FROM inventaris_sarana_inventaris WHERE id_inventaris=:id")
         ->execute([':id' => (int)$_GET['del']]);
    flash('success', 'Inventaris berhasil dihapus.');
    redirect('inventaris.php');
}

/* ── SIMPAN / UPDATE ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id           = (int)($_POST['id'] ?? 0);
    $nama         = trim($_POST['nama']);
    $ket          = trim($_POST['keterangan']);
    $jumlah       = (int)$_POST['jumlah'];
    $jumlah_rusak = (int)($_POST['jumlah_rusak'] ?? 0);
    $jumlah_rusak = max(0, min($jumlah_rusak, $jumlah)); // clamp agar tidak melebihi total
    $jenis        = (int)$_POST['id_jenis'];
    $tgl          = $_POST['tanggal_register'];
    $ruang        = (int)$_POST['id_ruang'];
    $kode         = (int)$_POST['kode_inventaris'];
    $petugas      = (int)$_SESSION['user_id'];

    // Kondisi dihitung otomatis dari jumlah_rusak
    $jumlah_baik = $jumlah - $jumlah_rusak;
    if ($jumlah_rusak === 0) {
        $kondisi = 'Baik';
    } elseif ($jumlah_baik <= 0) {
        $kondisi = 'Rusak Berat';
    } else {
        $kondisi = 'Rusak Ringan';
    }

    if ($id) {
        $conn->prepare(
            "UPDATE inventaris_sarana_inventaris
             SET nama=:n, kondisi=:k, keterangan=:ke, jumlah=:j, jumlah_rusak=:jr,
                 id_jenis=:jn, tanggal_register=:t, id_ruang=:r,
                 kode_inventaris=:ko, id_petugas=:p
             WHERE id_inventaris=:id"
        )->execute([':n'=>$nama,':k'=>$kondisi,':ke'=>$ket,':j'=>$jumlah,
                    ':jr'=>$jumlah_rusak,':jn'=>$jenis,':t'=>$tgl,
                    ':r'=>$ruang,':ko'=>$kode,':p'=>$petugas,':id'=>$id]);
        flash('success', 'Inventaris berhasil diperbarui.');
    } else {
        $conn->prepare(
            "INSERT INTO inventaris_sarana_inventaris
             (nama,kondisi,keterangan,jumlah,jumlah_rusak,id_jenis,tanggal_register,id_ruang,kode_inventaris,id_petugas)
             VALUES(:n,:k,:ke,:j,:jr,:jn,:t,:r,:ko,:p)"
        )->execute([':n'=>$nama,':k'=>$kondisi,':ke'=>$ket,':j'=>$jumlah,
                    ':jr'=>$jumlah_rusak,':jn'=>$jenis,':t'=>$tgl,
                    ':r'=>$ruang,':ko'=>$kode,':p'=>$petugas]);
        flash('success', 'Inventaris baru berhasil ditambahkan.');
    }
    redirect('inventaris.php');
}

$jenisList = $conn->query("SELECT * FROM inventaris_sarana_jenis ORDER BY nama_jenis")->fetchAll();
$ruangList = $conn->query("SELECT * FROM inventaris_sarana_ruang ORDER BY nama_ruang")->fetchAll();

// Filter & Search
$search     = trim($_GET['search'] ?? '');
$filter_jenis = (int)($_GET['jenis'] ?? 0);
$filter_ruang = (int)($_GET['ruang'] ?? 0);

$where = "WHERE 1=1";
$params = [];
if ($search) {
    $where .= " AND (i.nama LIKE :s OR i.kondisi LIKE :s OR i.keterangan LIKE :s)";
    $params[':s'] = "%$search%";
}
if ($filter_jenis) { $where .= " AND i.id_jenis=:jn"; $params[':jn']=$filter_jenis; }
if ($filter_ruang) { $where .= " AND i.id_ruang=:ru"; $params[':ru']=$filter_ruang; }

$stmt = $conn->prepare(
    "SELECT i.*, j.nama_jenis, r.nama_ruang, p.nama_petugas
     FROM inventaris_sarana_inventaris i
     JOIN inventaris_sarana_jenis j   ON i.id_jenis = j.id_jenis
     JOIN inventaris_sarana_ruang r   ON i.id_ruang = r.id_ruang
     JOIN inventaris_sarana_petugas p ON i.id_petugas = p.id_petugas
     $where ORDER BY i.id_inventaris DESC"
);
$stmt->execute($params);
$list = $stmt->fetchAll();

$editItem = null;
if (isset($_GET['edit'])) {
    $s = $conn->prepare("SELECT * FROM inventaris_sarana_inventaris WHERE id_inventaris=:id");
    $s->execute([':id' => (int)$_GET['edit']]);
    $editItem = $s->fetch();
}

$pageTitle = 'Inventaris Sarana & Prasarana';
include '../includes/header.php';
?>

<div class="space-y-6">

  <!-- FORM -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">
      <i class="fas fa-<?= $editItem ? 'pen' : 'plus-circle' ?> text-maroon-600 mr-2"></i>
      <?= $editItem ? 'Edit Inventaris' : 'Tambah Inventaris' ?>
    </h3>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <input type="hidden" name="id" value="<?= $editItem['id_inventaris'] ?? '' ?>">

      <div>
        <label class="text-sm text-gray-600 font-medium mb-1 block">Nama Barang *</label>
        <input type="text" name="nama" value="<?= htmlspecialchars($editItem['nama'] ?? '') ?>"
               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none"
               required placeholder="Nama barang inventaris">
      </div>
      <div>
        <label class="text-sm text-gray-600 font-medium mb-1 block">Kode Inventaris *</label>
        <input type="number" name="kode_inventaris" value="<?= $editItem['kode_inventaris'] ?? '' ?>"
               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none"
               required placeholder="Kode unik">
      </div>
      <div>
        <label class="text-sm text-gray-600 font-medium mb-1 block">Jenis *</label>
        <select name="id_jenis" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none" required>
          <option value="">-- Pilih Jenis --</option>
          <?php foreach ($jenisList as $j): ?>
          <option value="<?= $j['id_jenis'] ?>" <?= ($editItem['id_jenis'] ?? 0) == $j['id_jenis'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($j['nama_jenis']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="text-sm text-gray-600 font-medium mb-1 block">Ruang *</label>
        <select name="id_ruang" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none" required>
          <option value="">-- Pilih Ruang --</option>
          <?php foreach ($ruangList as $r): ?>
          <option value="<?= $r['id_ruang'] ?>" <?= ($editItem['id_ruang'] ?? 0) == $r['id_ruang'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($r['nama_ruang']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="text-sm text-gray-600 font-medium mb-1 block">Jumlah Total *</label>
        <input type="number" name="jumlah" value="<?= $editItem['jumlah'] ?? 1 ?>" min="1"
               id="input_jumlah"
               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none" required>
      </div>
      <div>
        <label class="text-sm text-gray-600 font-medium mb-1 block">Jumlah Rusak</label>
        <input type="number" name="jumlah_rusak" value="<?= $editItem['jumlah_rusak'] ?? 0 ?>" min="0"
               id="input_rusak"
               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none">
        <p class="text-xs text-gray-400 mt-1">
          Baik: <span id="preview_baik" class="font-semibold text-green-600">
            <?= ($editItem['jumlah'] ?? 1) - ($editItem['jumlah_rusak'] ?? 0) ?>
          </span> unit
          &nbsp;|&nbsp; Kondisi: <span id="preview_kondisi" class="font-semibold text-gray-700">
            <?php
              $jr = $editItem['jumlah_rusak'] ?? 0;
              $jt = $editItem['jumlah'] ?? 1;
              echo $jr == 0 ? 'Baik' : ($jr >= $jt ? 'Rusak Berat' : 'Rusak Ringan');
            ?>
          </span>
        </p>
      </div>
      <div>
        <label class="text-sm text-gray-600 font-medium mb-1 block">Tanggal Register *</label>
        <input type="date" name="tanggal_register" value="<?= $editItem['tanggal_register'] ?? date('Y-m-d') ?>"
               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none" required>
      </div>
      <div>
        <label class="text-sm text-gray-600 font-medium mb-1 block">Keterangan</label>
        <input type="text" name="keterangan" value="<?= htmlspecialchars($editItem['keterangan'] ?? '') ?>"
               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none"
               placeholder="Keterangan tambahan">
      </div>
      <div class="flex items-end gap-2">
        <button type="submit"
                class="flex-1 bg-maroon-700 hover:bg-maroon-800 text-white rounded-xl py-2 text-sm font-semibold">
          <i class="fas fa-save mr-1"></i> <?= $editItem ? 'Update' : 'Simpan' ?>
        </button>
        <?php if ($editItem): ?>
        <a href="inventaris.php"
           class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-sm font-semibold">Batal</a>
        <?php endif; ?>
      </div>
    </form>
    <script>
    // Live preview jumlah_baik dan kondisi saat input diketik
    function updatePreview() {
      const jumlah = parseInt(document.getElementById('input_jumlah').value) || 0;
      const rusak  = parseInt(document.getElementById('input_rusak').value)  || 0;
      const baik   = Math.max(0, jumlah - rusak);
      let kondisi  = rusak === 0 ? 'Baik' : (baik <= 0 ? 'Rusak Berat' : 'Rusak Ringan');
      document.getElementById('preview_baik').textContent    = baik;
      document.getElementById('preview_kondisi').textContent = kondisi;
      document.getElementById('preview_kondisi').className   =
        rusak === 0 ? 'font-semibold text-green-600' :
        (baik <= 0  ? 'font-semibold text-red-600'   : 'font-semibold text-orange-500');
    }
    document.getElementById('input_jumlah').addEventListener('input', updatePreview);
    document.getElementById('input_rusak').addEventListener('input', updatePreview);
    </script>
  </div>

  <!-- FILTER & TABLE -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
      <h3 class="font-semibold text-gray-800">Daftar Inventaris (<?= count($list) ?> item)</h3>
      <form method="GET" class="flex flex-wrap gap-2">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
               class="border border-gray-300 rounded-xl px-3 py-2 text-sm w-48 focus:ring-2 focus:ring-maroon-500 outline-none"
               placeholder="Cari nama, kondisi...">
        <select name="jenis" class="border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none">
          <option value="">Semua Jenis</option>
          <?php foreach ($jenisList as $j): ?>
          <option value="<?= $j['id_jenis'] ?>" <?= $filter_jenis==$j['id_jenis']?'selected':''?>><?= htmlspecialchars($j['nama_jenis']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="ruang" class="border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none">
          <option value="">Semua Ruang</option>
          <?php foreach ($ruangList as $r): ?>
          <option value="<?= $r['id_ruang'] ?>" <?= $filter_ruang==$r['id_ruang']?'selected':''?>><?= htmlspecialchars($r['nama_ruang']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="bg-maroon-700 text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-maroon-800">
          <i class="fas fa-search"></i>
        </button>
        <a href="inventaris.php" class="bg-gray-100 text-gray-600 px-4 py-2 rounded-xl text-sm font-semibold hover:bg-gray-200">
          <i class="fas fa-times"></i>
        </a>
      </form>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
            <th class="pb-2 font-semibold">Kode</th>
            <th class="pb-2 font-semibold">Nama Barang</th>
            <th class="pb-2 font-semibold">Jenis</th>
            <th class="pb-2 font-semibold">Ruang</th>
            <th class="pb-2 font-semibold text-center">Total</th>
            <th class="pb-2 font-semibold text-center text-green-600">Baik</th>
            <th class="pb-2 font-semibold text-center text-red-500">Rusak</th>
            <th class="pb-2 font-semibold">Status Kondisi</th>
            <th class="pb-2 font-semibold">Tgl Register</th>
            <th class="pb-2 font-semibold text-center">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($list)): ?>
          <tr><td colspan="10" class="py-8 text-center text-gray-400">
            <i class="fas fa-box-open text-3xl mb-2 block"></i>Tidak ada data
          </td></tr>
          <?php else: foreach ($list as $item): ?>
          <tr class="hover:bg-gray-50">
            <td class="py-3 font-mono text-xs text-gray-500"><?= $item['kode_inventaris'] ?></td>
            <td class="py-3 font-medium max-w-[180px]">
              <p class="truncate"><?= htmlspecialchars($item['nama']) ?></p>
              <?php if ($item['keterangan']): ?>
              <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($item['keterangan']) ?></p>
              <?php endif; ?>
            </td>
            <td class="py-3 text-gray-600"><?= htmlspecialchars($item['nama_jenis']) ?></td>
            <td class="py-3 text-gray-600 text-xs"><?= htmlspecialchars($item['nama_ruang']) ?></td>
            <td class="py-3 text-center font-semibold"><?= $item['jumlah'] ?></td>
            <?php
              $jml_baik  = $item['jumlah'] - $item['jumlah_rusak'];
              $jml_rusak = $item['jumlah_rusak'];
              if ($jml_rusak == 0)        { $kondisi_auto = 'Baik';         $badge = 'bg-green-100 text-green-700'; }
              elseif ($jml_baik <= 0)     { $kondisi_auto = 'Rusak Berat';  $badge = 'bg-red-100 text-red-700'; }
              else                        { $kondisi_auto = 'Rusak Ringan'; $badge = 'bg-orange-100 text-orange-700'; }
            ?>
            <td class="py-3 text-center font-semibold text-green-600"><?= $jml_baik ?></td>
            <td class="py-3 text-center font-semibold <?= $jml_rusak > 0 ? 'text-red-500' : 'text-gray-300' ?>">
              <?= $jml_rusak ?>
            </td>
            <td class="py-3">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $badge ?>">
                <?= $kondisi_auto ?>
              </span>
            </td>
            <td class="py-3 text-gray-500 text-xs"><?= formatTanggal($item['tanggal_register']) ?></td>
            <td class="py-3 text-center">
              <div class="flex justify-center gap-1">
                <a href="?edit=<?= $item['id_inventaris'] ?>"
                   class="px-3 py-1 bg-blue-50 text-blue-600 rounded-lg text-xs font-medium hover:bg-blue-100">
                  <i class="fas fa-pen"></i>
                </a>
                <a href="?del=<?= $item['id_inventaris'] ?>"
                   data-confirm="Hapus inventaris '<?= htmlspecialchars($item['nama']) ?>'?"
                   class="px-3 py-1 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100">
                  <i class="fas fa-trash"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>