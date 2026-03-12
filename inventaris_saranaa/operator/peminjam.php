<?php
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['operator']);

$db   = new Database();
$conn = $db->getConnection();

/* ── TAMBAH ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $nama     = trim($_POST['nama_pegawai']);
    $nip      = trim($_POST['nip']);
    $alamat   = trim($_POST['alamat']);
    $tipe     = $_POST['tipe_peminjam'] ?? 'guru';
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!in_array($tipe, ['guru','staff','siswa'])) $tipe = 'guru';

    $cek = $conn->prepare("SELECT id_pegawai FROM inventaris_sarana_pegawai WHERE username=:u");
    $cek->execute([':u' => $username]);
    if ($cek->fetch()) {
        flash('error', 'Username sudah digunakan, pilih yang lain.');
    } else {
        $conn->prepare(
            "INSERT INTO inventaris_sarana_pegawai (nama_pegawai, nip, alamat, tipe_peminjam, username, password)
             VALUES(:n, :nip, :a, :tp, :u, :p)"
        )->execute([
            ':n'   => $nama,
            ':nip' => $nip,
            ':a'   => $alamat,
            ':tp'  => $tipe,
            ':u'   => $username,
            ':p'   => md5($password),
        ]);
        flash('success', 'Peminjam ' . $nama . ' (' . ucfirst($tipe) . ') berhasil ditambahkan.');
    }
    redirect(BASE_URL . 'operator/peminjam.php');
}

/* ── EDIT ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    $id       = (int)$_POST['id_pegawai'];
    $nama     = trim($_POST['nama_pegawai']);
    $nip      = trim($_POST['nip']);
    $alamat   = trim($_POST['alamat']);
    $tipe     = $_POST['tipe_peminjam'] ?? 'guru';
    $username = trim($_POST['username']);

    if (!in_array($tipe, ['guru','staff','siswa'])) $tipe = 'guru';

    $cek = $conn->prepare("SELECT id_pegawai FROM inventaris_sarana_pegawai WHERE username=:u AND id_pegawai!=:id");
    $cek->execute([':u' => $username, ':id' => $id]);
    if ($cek->fetch()) {
        flash('error', 'Username sudah digunakan peminjam lain.');
    } else {
        if (!empty($_POST['password'])) {
            $conn->prepare(
                "UPDATE inventaris_sarana_pegawai
                 SET nama_pegawai=:n, nip=:nip, alamat=:a, tipe_peminjam=:tp, username=:u, password=:p
                 WHERE id_pegawai=:id"
            )->execute([':n'=>$nama, ':nip'=>$nip, ':a'=>$alamat, ':tp'=>$tipe, ':u'=>$username, ':p'=>md5($_POST['password']), ':id'=>$id]);
        } else {
            $conn->prepare(
                "UPDATE inventaris_sarana_pegawai
                 SET nama_pegawai=:n, nip=:nip, alamat=:a, tipe_peminjam=:tp, username=:u
                 WHERE id_pegawai=:id"
            )->execute([':n'=>$nama, ':nip'=>$nip, ':a'=>$alamat, ':tp'=>$tipe, ':u'=>$username, ':id'=>$id]);
        }
        flash('success', 'Data peminjam berhasil diperbarui.');
    }
    redirect(BASE_URL . 'operator/peminjam.php');
}

/* ── HAPUS ── */
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $cek = $conn->prepare(
        "SELECT COUNT(*) FROM inventaris_sarana_peminjaman
         WHERE id_pegawai=:id AND status_peminjaman IN ('dipinjam','terlambat','menunggu_kembali','menunggu')"
    );
    $cek->execute([':id' => $id]);
    if ((int)$cek->fetchColumn() > 0) {
        flash('error', 'Peminjam masih memiliki peminjaman aktif, tidak bisa dihapus.');
    } else {
        $conn->prepare("DELETE FROM inventaris_sarana_pegawai WHERE id_pegawai=:id")->execute([':id'=>$id]);
        flash('success', 'Data peminjam berhasil dihapus.');
    }
    redirect(BASE_URL . 'operator/peminjam.php');
}

$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM inventaris_sarana_pegawai WHERE id_pegawai=:id");
    $stmt->execute([':id' => (int)$_GET['edit']]);
    $editData = $stmt->fetch();
}

$filterTipe = $_GET['tipe'] ?? '';

$query = "SELECT pg.*, COUNT(p.id_peminjaman) as total_pinjam
     FROM inventaris_sarana_pegawai pg
     LEFT JOIN inventaris_sarana_peminjaman p ON pg.id_pegawai = p.id_pegawai
       AND p.status_peminjaman IN ('dipinjam','terlambat','menunggu_kembali','menunggu')
     WHERE 1=1";
$params = [];
if ($filterTipe && in_array($filterTipe, ['guru','staff','siswa'])) {
    $query .= " AND pg.tipe_peminjam = :tipe";
    $params[':tipe'] = $filterTipe;
}
$query .= " GROUP BY pg.id_pegawai ORDER BY pg.tipe_peminjam, pg.nama_pegawai";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$peminjams = $stmt->fetchAll();

$counts = $conn->query(
    "SELECT tipe_peminjam, COUNT(*) as jml FROM inventaris_sarana_pegawai GROUP BY tipe_peminjam"
)->fetchAll(\PDO::FETCH_KEY_PAIR);

$pageTitle = 'Kelola Peminjam';
include '../includes/header.php';
?>

<div class="space-y-6">

  <div class="grid grid-cols-3 gap-4">
    <?php
    $tipe_info = [
      'guru'  => ['icon'=>'fa-chalkboard-teacher', 'color'=>'blue',    'label'=>'Guru'],
      'staff' => ['icon'=>'fa-briefcase',           'color'=>'purple',  'label'=>'Staff'],
      'siswa' => ['icon'=>'fa-graduation-cap',      'color'=>'emerald', 'label'=>'Siswa'],
    ];
    foreach ($tipe_info as $tk => $tv):
      $jml = $counts[$tk] ?? 0;
    ?>
    <a href="?tipe=<?= $tk ?>" class="bg-white rounded-2xl p-4 shadow-sm border <?= $filterTipe===$tk ? 'border-'.$tv['color'].'-400 ring-2 ring-'.$tv['color'].'-200' : 'border-gray-100' ?> card-hover flex items-center gap-3">
      <div class="w-10 h-10 bg-<?= $tv['color'] ?>-50 rounded-xl flex items-center justify-center">
        <i class="fas <?= $tv['icon'] ?> text-<?= $tv['color'] ?>-600 text-sm"></i>
      </div>
      <div>
        <p class="text-2xl font-bold text-gray-800"><?= $jml ?></p>
        <p class="text-xs text-gray-500"><?= $tv['label'] ?></p>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if ($filterTipe): ?>
  <div class="flex items-center gap-2">
    <span class="text-sm text-gray-500">Filter:</span>
    <span class="px-3 py-1 bg-gray-200 text-gray-700 rounded-full text-xs font-semibold capitalize"><?= $filterTipe ?></span>
    <a href="peminjam.php" class="text-xs text-red-500 hover:underline">x Hapus filter</a>
  </div>
  <?php endif; ?>

  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-5">
      <i class="fas fa-<?= $editData ? 'pen' : 'user-plus' ?> text-maroon-600 mr-2"></i>
      <?= $editData ? 'Edit Data Peminjam' : 'Tambah Peminjam Baru' ?>
    </h3>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="aksi" value="<?= $editData ? 'edit' : 'tambah' ?>">
      <?php if ($editData): ?>
      <input type="hidden" name="id_pegawai" value="<?= $editData['id_pegawai'] ?>">
      <?php endif; ?>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="text-sm font-medium text-gray-600 mb-1 block">Nama Lengkap *</label>
          <input type="text" name="nama_pegawai" value="<?= htmlspecialchars($editData['nama_pegawai'] ?? '') ?>"
                 class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none" required>
        </div>
        <div>
          <label class="text-sm font-medium text-gray-600 mb-1 block">
            <span id="nipLabel"><?= isset($editData['tipe_peminjam']) && $editData['tipe_peminjam']==='siswa' ? 'NIS / No. Induk' : 'NIP / NIS' ?></span>
          </label>
          <input type="text" name="nip" value="<?= htmlspecialchars($editData['nip'] ?? '') ?>"
                 class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none"
                 placeholder="Nomor induk / NIP">
        </div>
        <div>
          <label class="text-sm font-medium text-gray-600 mb-1 block">Tipe Peminjam *</label>
          <select name="tipe_peminjam" id="tipePeminjam"
                  class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none" required>
            <option value="guru"  <?= ($editData['tipe_peminjam'] ?? '') === 'guru'  ? 'selected' : '' ?>>Guru</option>
            <option value="staff" <?= ($editData['tipe_peminjam'] ?? '') === 'staff' ? 'selected' : '' ?>>Staff</option>
            <option value="siswa" <?= ($editData['tipe_peminjam'] ?? '') === 'siswa' ? 'selected' : '' ?>>Siswa</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium text-gray-600 mb-1 block">Alamat</label>
          <input type="text" name="alamat" value="<?= htmlspecialchars($editData['alamat'] ?? '') ?>"
                 class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none">
        </div>
        <div>
          <label class="text-sm font-medium text-gray-600 mb-1 block">Username *</label>
          <input type="text" name="username" value="<?= htmlspecialchars($editData['username'] ?? '') ?>"
                 class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none" required>
        </div>
        <div>
          <label class="text-sm font-medium text-gray-600 mb-1 block">
            Password <?= $editData ? '<span class="text-gray-400 font-normal">(kosongkan jika tidak diubah)</span>' : '*' ?>
          </label>
          <input type="password" name="password"
                 class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none"
                 <?= $editData ? '' : 'required' ?>
                 placeholder="<?= $editData ? 'Biarkan kosong jika tidak diubah' : 'Buat password' ?>">
        </div>
      </div>

      <div class="flex gap-2">
        <button type="submit"
                class="bg-maroon-700 hover:bg-maroon-800 text-white px-6 py-2.5 rounded-xl text-sm font-semibold">
          <i class="fas fa-save mr-1"></i> <?= $editData ? 'Simpan Perubahan' : 'Tambah Peminjam' ?>
        </button>
        <?php if ($editData): ?>
        <a href="peminjam.php" class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-6 py-2.5 rounded-xl text-sm font-semibold">
          <i class="fas fa-times mr-1"></i> Batal
        </a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">
      Daftar Peminjam
      <span class="text-gray-400 font-normal text-sm">(<?= count($peminjams) ?> orang<?= $filterTipe ? ' · '.ucfirst($filterTipe) : '' ?>)</span>
    </h3>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
            <th class="pb-3 font-semibold">Nama</th>
            <th class="pb-3 font-semibold">Tipe</th>
            <th class="pb-3 font-semibold">NIP/NIS</th>
            <th class="pb-3 font-semibold">Username</th>
            <th class="pb-3 font-semibold">Alamat</th>
            <th class="pb-3 font-semibold">Pinjaman Aktif</th>
            <th class="pb-3 font-semibold">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($peminjams)): ?>
          <tr><td colspan="7" class="py-8 text-center text-gray-400">Belum ada data peminjam</td></tr>
          <?php else:
          $tipe_badge = [
            'guru'  => 'bg-blue-50 text-blue-700',
            'staff' => 'bg-purple-50 text-purple-700',
            'siswa' => 'bg-emerald-50 text-emerald-700',
          ];
          $tipe_icon = [
            'guru'  => '<i class="fas fa-chalkboard-teacher"></i>',
            'staff' => '<i class="fas fa-briefcase"></i>',
            'siswa' => '<i class="fas fa-graduation-cap"></i>',
          ];
          foreach ($peminjams as $pg): ?>
          <tr class="hover:bg-gray-50">
            <td class="py-3 font-medium"><?= htmlspecialchars($pg['nama_pegawai']) ?></td>
            <td class="py-3">
              <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $tipe_badge[$pg['tipe_peminjam']] ?? 'bg-gray-100 text-gray-600' ?>">
                <?= $tipe_icon[$pg['tipe_peminjam']] ?? '' ?> <?= ucfirst($pg['tipe_peminjam']) ?>
              </span>
            </td>
            <td class="py-3 text-gray-500 text-xs font-mono"><?= $pg['nip'] ?: '-' ?></td>
            <td class="py-3 font-mono text-xs text-gray-500"><?= htmlspecialchars($pg['username']) ?></td>
            <td class="py-3 text-gray-500 max-w-[150px] truncate text-xs"><?= $pg['alamat'] ?: '-' ?></td>
            <td class="py-3">
              <?php if ($pg['total_pinjam'] > 0): ?>
              <span class="px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700 font-semibold">
                <?= $pg['total_pinjam'] ?> aktif
              </span>
              <?php else: ?>
              <span class="text-xs text-gray-400">-</span>
              <?php endif; ?>
            </td>
            <td class="py-3">
              <div class="flex gap-2">
                <a href="?edit=<?= $pg['id_pegawai'] ?>"
                   class="text-xs bg-blue-50 text-blue-600 hover:bg-blue-100 px-3 py-1.5 rounded-lg font-medium">
                  <i class="fas fa-pen mr-1"></i>Edit
                </a>
                <a href="?hapus=<?= $pg['id_pegawai'] ?>"
                   onclick="return confirm('Hapus peminjam <?= htmlspecialchars($pg['nama_pegawai']) ?>?')"
                   class="text-xs bg-red-50 text-red-500 hover:bg-red-100 px-3 py-1.5 rounded-lg font-medium">
                  <i class="fas fa-trash mr-1"></i>Hapus
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

<script>
  document.getElementById('tipePeminjam').addEventListener('change', function() {
    const labels = { guru: 'NIP', staff: 'NIP / No. Pegawai', siswa: 'NIS / No. Induk' };
    document.getElementById('nipLabel').textContent = labels[this.value] || 'NIP / NIS';
  });
</script>

<?php include '../includes/footer.php'; ?>