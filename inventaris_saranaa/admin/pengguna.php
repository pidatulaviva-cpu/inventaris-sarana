<?php
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['admin']);

$db   = new Database();
$conn = $db->getConnection();

/* ── TAMBAH OPERATOR ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'tambah_operator') {
    $username = trim($_POST['username']);
    $nama     = trim($_POST['nama_petugas']);
    $pw       = trim($_POST['password']);

    $lvl = $conn->prepare("SELECT id_level FROM level WHERE nama_level = 'operator' LIMIT 1");
    $lvl->execute();
    $id_level = $lvl->fetchColumn();

    if (!$id_level) {
        flash('error', 'Level operator tidak ditemukan di database.');
    } else {
        $cek = $conn->prepare("SELECT id_petugas FROM inventaris_sarana_petugas WHERE username=:u");
        $cek->execute([':u' => $username]);
        if ($cek->fetch()) {
            flash('error', 'Username sudah digunakan, pilih yang lain.');
        } else {
            $conn->prepare(
                "INSERT INTO inventaris_sarana_petugas (username, password, nama_petugas, id_level)
                 VALUES(:u, MD5(:p), :n, :l)"
            )->execute([':u'=>$username, ':p'=>$pw, ':n'=>$nama, ':l'=>$id_level]);
            flash('success', 'Operator ' . $nama . ' berhasil ditambahkan.');
        }
    }
    redirect(BASE_URL . 'admin/pengguna.php');
}

/* ── EDIT OPERATOR ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'edit_operator') {
    $id   = (int)$_POST['id_petugas'];
    $nama = trim($_POST['nama_petugas']);
    $user = trim($_POST['username']);
    $pw   = trim($_POST['password']);

    // Pastikan hanya operator yang bisa diedit (bukan admin)
    $cekLevel = $conn->prepare(
        "SELECT p.id_petugas FROM inventaris_sarana_petugas p
         JOIN level l ON p.id_level = l.id_level
         WHERE p.id_petugas = :id AND l.nama_level = 'operator'"
    );
    $cekLevel->execute([':id' => $id]);
    if (!$cekLevel->fetch()) {
        flash('error', 'Hanya akun operator yang dapat diedit.');
        redirect(BASE_URL . 'admin/pengguna.php');
    }

    // Cek username duplikat (kecuali milik sendiri)
    $cekUser = $conn->prepare(
        "SELECT id_petugas FROM inventaris_sarana_petugas WHERE username=:u AND id_petugas != :id"
    );
    $cekUser->execute([':u' => $user, ':id' => $id]);
    if ($cekUser->fetch()) {
        flash('error', 'Username sudah digunakan akun lain.');
        redirect(BASE_URL . 'admin/pengguna.php');
    }

    if (!empty($pw)) {
        $conn->prepare(
            "UPDATE inventaris_sarana_petugas
             SET nama_petugas=:n, username=:u, password=MD5(:p)
             WHERE id_petugas=:id"
        )->execute([':n'=>$nama, ':u'=>$user, ':p'=>$pw, ':id'=>$id]);
    } else {
        $conn->prepare(
            "UPDATE inventaris_sarana_petugas
             SET nama_petugas=:n, username=:u
             WHERE id_petugas=:id"
        )->execute([':n'=>$nama, ':u'=>$user, ':id'=>$id]);
    }
    flash('success', 'Data operator berhasil diperbarui.');
    redirect(BASE_URL . 'admin/pengguna.php');
}

/* ── HAPUS OPERATOR ── */
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];

    // Pastikan hanya operator yang bisa dihapus
    $cekLevel = $conn->prepare(
        "SELECT p.id_petugas FROM inventaris_sarana_petugas p
         JOIN level l ON p.id_level = l.id_level
         WHERE p.id_petugas = :id AND l.nama_level = 'operator'"
    );
    $cekLevel->execute([':id' => $id]);
    if (!$cekLevel->fetch()) {
        flash('error', 'Hanya akun operator yang dapat dihapus.');
    } else {
        $conn->prepare("DELETE FROM inventaris_sarana_petugas WHERE id_petugas=:id")
             ->execute([':id' => $id]);
        flash('success', 'Operator berhasil dihapus.');
    }
    redirect(BASE_URL . 'admin/pengguna.php');
}

$petugas  = $conn->query(
    "SELECT p.*, l.nama_level
     FROM inventaris_sarana_petugas p
     JOIN level l ON p.id_level = l.id_level
     ORDER BY l.id_level, p.nama_petugas"
)->fetchAll();

$pegawais = $conn->query(
    "SELECT pg.*, COUNT(pm.id_peminjaman) as total_pinjam_aktif
     FROM inventaris_sarana_pegawai pg
     LEFT JOIN inventaris_sarana_peminjaman pm
       ON pg.id_pegawai = pm.id_pegawai
       AND pm.status_peminjaman IN ('dipinjam','terlambat','menunggu_kembali','menunggu')
     GROUP BY pg.id_pegawai
     ORDER BY pg.nama_pegawai"
)->fetchAll();

$pageTitle = 'Kelola Pengguna';
include '../includes/header.php';
?>

<!-- Tab Switch -->
<div class="flex gap-2 mb-6">
  <button onclick="showTab('petugas')" id="btn-petugas"
          class="tab-btn px-5 py-2 rounded-xl text-sm font-semibold bg-maroon-700 text-white shadow">
    <i class="fas fa-user-shield mr-2"></i>Admin / Operator
  </button>
  <button onclick="showTab('pegawai')" id="btn-pegawai"
          class="tab-btn px-5 py-2 rounded-xl text-sm font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200">
    <i class="fas fa-user-tie mr-2"></i>Peminjam
  </button>
</div>

<!-- ═══════════ TAB PETUGAS ═══════════ -->
<div id="tab-petugas" class="space-y-6">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Form Tambah Operator -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h3 class="font-semibold text-gray-800 mb-1">
        <i class="fas fa-user-plus text-maroon-600 mr-2"></i>Tambah Operator
      </h3>
      <p class="text-xs text-gray-400 mb-5">Admin hanya dapat menambahkan akun operator.</p>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="form_type" value="tambah_operator">
        <div>
          <label class="text-sm text-gray-600 font-medium mb-1 block">Nama Lengkap *</label>
          <input type="text" name="nama_petugas"
                 class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none"
                 placeholder="Nama operator" required>
        </div>
        <div>
          <label class="text-sm text-gray-600 font-medium mb-1 block">Nama Pengguna *</label>
          <input type="text" name="username"
                 class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none"
                 placeholder="Masukkan nama pengguna" required>
        </div>
        <div>
          <label class="text-sm text-gray-600 font-medium mb-1 block">Kata Sandi *</label>
          <input type="password" name="password"
                 class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none"
                 placeholder="Masukkan kata sandi" required>
        </div>
        <button type="submit"
                class="w-full bg-maroon-700 hover:bg-maroon-800 text-white rounded-xl py-2.5 text-sm font-semibold">
          <i class="fas fa-save mr-1"></i> Tambah Operator
        </button>
      </form>
    </div>

    <!-- Tabel Petugas (view only) -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <div class="flex items-center gap-2 mb-4">
        <h3 class="font-semibold text-gray-800">Daftar Admin & Operator</h3>
        <span class="bg-gray-100 text-gray-500 text-xs px-2 py-0.5 rounded-full">
          <?= count($petugas) ?> akun
        </span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
              <th class="pb-2 font-semibold">Nama</th>
              <th class="pb-2 font-semibold">Username</th>
              <th class="pb-2 font-semibold">Level</th>
              <th class="pb-2 font-semibold">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php foreach ($petugas as $p): ?>
            <tr class="hover:bg-gray-50">
              <td class="py-3 font-medium">
                <?= htmlspecialchars($p['nama_petugas']) ?>
                <?php if ($p['id_petugas'] == $_SESSION['user_id']): ?>
                <span class="ml-1 text-xs text-maroon-400">(Anda)</span>
                <?php endif; ?>
              </td>
              <td class="py-3 font-mono text-xs text-gray-500"><?= htmlspecialchars($p['username']) ?></td>
              <td class="py-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                  <?= $p['nama_level']==='admin' ? 'bg-maroon-100 text-maroon-700' : 'bg-amber-100 text-amber-700' ?>">
                  <?= ucfirst($p['nama_level']) ?>
                </span>
              </td>
              <td class="py-3">
                <?php if ($p['nama_level'] === 'operator'): ?>
                <div class="flex gap-1">
                  <button onclick="bukaEdit(<?= $p['id_petugas'] ?>, '<?= htmlspecialchars(addslashes($p['nama_petugas'])) ?>', '<?= htmlspecialchars(addslashes($p['username'])) ?>')"
                          class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-600 px-2.5 py-1 rounded-lg">
                    <i class="fas fa-pencil mr-1"></i>Edit
                  </button>
                  <a href="?hapus=<?= $p['id_petugas'] ?>"
                     onclick="return confirm('Hapus operator <?= htmlspecialchars(addslashes($p['nama_petugas'])) ?>?')"
                     class="text-xs bg-red-50 hover:bg-red-100 text-red-600 px-2.5 py-1 rounded-lg">
                    <i class="fas fa-trash mr-1"></i>Hapus
                  </a>
                </div>
                <?php else: ?>
                <span class="text-xs text-gray-300">–</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- ═══════════ TAB PEGAWAI ═══════════ -->
<div id="tab-pegawai" class="space-y-6 hidden">
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <div class="flex flex-wrap items-center gap-2 mb-4">
      <h3 class="font-semibold text-gray-800">Daftar Peminjam</h3>
      <span class="bg-gray-100 text-gray-500 text-xs px-2 py-0.5 rounded-full">
        <?= count($pegawais) ?> pengguna
      </span>
      <div class="ml-auto flex gap-2">
        <button onclick="filterTipe('semua')" id="filter-semua"
                class="filter-tipe text-xs px-3 py-1.5 rounded-lg font-semibold bg-maroon-700 text-white">
          Semua
        </button>
        <button onclick="filterTipe('guru')" id="filter-guru"
                class="filter-tipe text-xs px-3 py-1.5 rounded-lg font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200">
          <i class="fas fa-chalkboard-teacher mr-1"></i>Guru
        </button>
        <button onclick="filterTipe('staff')" id="filter-staff"
                class="filter-tipe text-xs px-3 py-1.5 rounded-lg font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200">
          <i class="fas fa-briefcase mr-1"></i>Staff
        </button>
        <button onclick="filterTipe('siswa')" id="filter-siswa"
                class="filter-tipe text-xs px-3 py-1.5 rounded-lg font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200">
          <i class="fas fa-user-graduate mr-1"></i>Siswa
        </button>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
            <th class="pb-2 font-semibold">Nama</th>
            <th class="pb-2 font-semibold">NIP / NIS</th>
            <th class="pb-2 font-semibold">Tipe</th>
            <th class="pb-2 font-semibold">Username</th>
            <th class="pb-2 font-semibold">Alamat</th>
            <th class="pb-2 font-semibold">Pinjaman Aktif</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50" id="tabel-pegawai">
          <?php if (empty($pegawais)): ?>
          <tr><td colspan="6" class="py-8 text-center text-gray-400">Belum ada data peminjam</td></tr>
          <?php else: foreach ($pegawais as $pg):
            $tipeBadge = match($pg['tipe_peminjam']) {
              'guru'  => 'bg-blue-100 text-blue-700',
              'staff' => 'bg-purple-100 text-purple-700',
              'siswa' => 'bg-green-100 text-green-700',
              default => 'bg-gray-100 text-gray-500',
            };
            $tipeIcon = match($pg['tipe_peminjam']) {
              'guru'  => 'fa-chalkboard-teacher',
              'staff' => 'fa-briefcase',
              'siswa' => 'fa-user-graduate',
              default => 'fa-user',
            };
          ?>
          <tr class="hover:bg-gray-50 baris-pegawai" data-tipe="<?= $pg['tipe_peminjam'] ?>">
            <td class="py-3 font-medium"><?= htmlspecialchars($pg['nama_pegawai']) ?></td>
            <td class="py-3 text-gray-500 text-xs font-mono"><?= $pg['nip'] ?: '-' ?></td>
            <td class="py-3">
              <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $tipeBadge ?>">
                <i class="fas <?= $tipeIcon ?> mr-1"></i><?= ucfirst($pg['tipe_peminjam']) ?>
              </span>
            </td>
            <td class="py-3 font-mono text-xs text-gray-500"><?= htmlspecialchars($pg['username'] ?? '-') ?></td>
            <td class="py-3 text-gray-500 max-w-[150px] truncate"><?= $pg['alamat'] ?: '-' ?></td>
            <td class="py-3">
              <?php if ($pg['total_pinjam_aktif'] > 0): ?>
              <span class="px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700 font-semibold">
                <?= $pg['total_pinjam_aktif'] ?> aktif
              </span>
              <?php else: ?>
              <span class="text-xs text-gray-400">–</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <!-- Counter hasil filter -->
    <p class="text-xs text-gray-400 mt-3" id="filter-info"></p>
  </div>
</div>

<script>
function showTab(tab) {
  ['petugas','pegawai'].forEach(t => {
    document.getElementById('tab-'+t).classList.toggle('hidden', t !== tab);
    const btn = document.getElementById('btn-'+t);
    btn.className = t === tab
      ? 'tab-btn px-5 py-2 rounded-xl text-sm font-semibold bg-maroon-700 text-white shadow'
      : 'tab-btn px-5 py-2 rounded-xl text-sm font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200';
  });
}
const urlTab = new URLSearchParams(location.search).get('tab');
if (urlTab === 'pegawai') showTab('pegawai');

function bukaEdit(id, nama, username) {
  document.getElementById('edit_id_petugas').value   = id;
  document.getElementById('edit_nama_petugas').value = nama;
  document.getElementById('edit_username').value     = username;
  document.getElementById('edit_password').value     = '';
  document.getElementById('modalEdit').classList.remove('hidden');
}
function tutupEdit() {
  document.getElementById('modalEdit').classList.add('hidden');
}

function filterTipe(tipe) {
  const baris  = document.querySelectorAll('.baris-pegawai');
  let tampil   = 0;
  baris.forEach(tr => {
    const cocok = tipe === 'semua' || tr.dataset.tipe === tipe;
    tr.classList.toggle('hidden', !cocok);
    if (cocok) tampil++;
  });
  document.querySelectorAll('.filter-tipe').forEach(btn => {
    const aktif = btn.id === 'filter-' + tipe;
    btn.className = 'filter-tipe text-xs px-3 py-1.5 rounded-lg font-semibold ' +
      (aktif ? 'bg-maroon-700 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200');
  });
  const info = document.getElementById('filter-info');
  info.textContent = tipe === 'semua' ? '' : `Menampilkan ${tampil} ${tipe}`;
}
</script>

<!-- Modal Edit Operator -->
<div id="modalEdit" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
    <div class="flex items-center justify-between mb-5">
      <h3 class="font-semibold text-gray-800">
        <i class="fas fa-user-edit text-maroon-600 mr-2"></i>Edit Operator
      </h3>
      <button onclick="tutupEdit()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
    </div>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="form_type" value="edit_operator">
      <input type="hidden" name="id_petugas" id="edit_id_petugas">
      <div>
        <label class="text-sm text-gray-600 font-medium mb-1 block">Nama Lengkap *</label>
        <input type="text" name="nama_petugas" id="edit_nama_petugas"
               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none" required>
      </div>
      <div>
        <label class="text-sm text-gray-600 font-medium mb-1 block">Username *</label>
        <input type="text" name="username" id="edit_username"
               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none" required>
      </div>
      <div>
        <label class="text-sm text-gray-600 font-medium mb-1 block">Kata Sandi Baru
          <span class="text-gray-400 font-normal">(kosongkan jika tidak diubah)</span>
        </label>
        <input type="password" name="password" id="edit_password"
               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none"
               placeholder="••••••••">
      </div>
      <div class="flex gap-3 pt-1">
        <button type="submit"
                class="flex-1 bg-maroon-700 hover:bg-maroon-800 text-white rounded-xl py-2.5 text-sm font-semibold">
          <i class="fas fa-save mr-1"></i>Simpan Perubahan
        </button>
        <button type="button" onclick="tutupEdit()"
                class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl py-2.5 text-sm font-semibold">
          Batal
        </button>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/footer.php'; ?>