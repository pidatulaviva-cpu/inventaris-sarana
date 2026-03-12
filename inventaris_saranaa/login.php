<?php
session_start();
require_once 'config/database.php';
require_once 'config/session.php';

// Sudah login → redirect
if (isset($_SESSION['user_id'])) {
    redirect(BASE_URL . $_SESSION['role'] . '/index.php');
}

$error = '';
$active_role = $_POST['role'] ?? $_GET['role'] ?? 'admin';
if (!in_array($active_role, ['admin', 'operator', 'peminjam'])) {
    $active_role = 'admin';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username    = trim($_POST['username'] ?? '');
    $password    = trim($_POST['password'] ?? '');
    $login_role  = $_POST['role'] ?? 'admin';

    if ($username && $password) {
        $db   = new Database();
        $conn = $db->getConnection();
        $found = false;

        if ($login_role === 'admin' || $login_role === 'operator') {
            // Cek tabel petugas (admin / operator)
            $stmt = $conn->prepare(
                "SELECT p.*, l.nama_level
                 FROM inventaris_sarana_petugas p
                 JOIN level l ON p.id_level = l.id_level
                 WHERE p.username = :u AND p.password = MD5(:pw)
                   AND l.nama_level = :role
                 LIMIT 1"
            );
            $stmt->execute([':u' => $username, ':pw' => $password, ':role' => $login_role]);
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION['user_id']  = $user['id_petugas'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama']     = $user['nama_petugas'];
                $_SESSION['role']     = $user['nama_level'];
                $found = true;
            } else {
                $error = 'Username atau password salah!';
            }
        } elseif ($login_role === 'peminjam') {
            // Cek tabel pegawai (peminjam: guru / staff / siswa)
            $stmt = $conn->prepare(
                "SELECT * FROM inventaris_sarana_pegawai
                 WHERE username = :u AND password = MD5(:pw)
                 LIMIT 1"
            );
            $stmt->execute([':u' => $username, ':pw' => $password]);
            $peminjam = $stmt->fetch();

            if ($peminjam) {
                $_SESSION['user_id']    = $peminjam['id_pegawai'];
                $_SESSION['username']   = $peminjam['username'];
                $_SESSION['nama']       = $peminjam['nama_pegawai'];
                $_SESSION['role']       = 'peminjam';
                $_SESSION['id_pegawai'] = $peminjam['id_pegawai'];
                $_SESSION['tipe']       = $peminjam['tipe_peminjam'];
                $found = true;
            } else {
                $error = 'Username atau password salah!';
            }
        }

        if ($found) {
            redirect(BASE_URL . $_SESSION['role'] . '/index.php');
        }
    } else {
        $error = 'Harap isi username dan password!';
    }
}

// Label & icon per role
$role_config = [
    'admin'    => ['label'=>'Admin',    'icon'=>'fa-user-shield',  'color'=>'maroon'],
    'operator' => ['label'=>'Operator', 'icon'=>'fa-user-gear',    'color'=>'maroon'],
    'peminjam' => ['label'=>'Peminjam', 'icon'=>'fa-user',         'color'=>'maroon'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Inventaris SARPRAS SMK</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            maroon: {
              50:'#fdf2f2',100:'#fce7e7',200:'#f8c9c9',300:'#f39a9a',
              400:'#eb6060',500:'#dc2626',600:'#9b1c1c',700:'#7f1d1d',
              800:'#6b1313',900:'#450a0a',950:'#2d0505'
            }
          },
          fontFamily: {
            sans:    ['"Plus Jakarta Sans"', 'ui-sans-serif'],
            display: ['"Playfair Display"', 'Georgia', 'serif'],
          }
        }
      }
    }
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .bg-pattern {
      background-color: #7f1d1d;
      background-image:
        radial-gradient(circle at 20% 50%, rgba(255,255,255,.04) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(255,255,255,.06) 0%, transparent 40%);
    }
    .tab-btn {
      transition: all .2s;
    }
    .tab-btn.active-admin    { background:#7f1d1d; color:#fff; }
    .tab-btn.active-operator { background:#7f1d1d; color:#fff; }
    .tab-btn.active-peminjam { background:#7f1d1d; color:#fff; }
    .tab-btn:not([class*="active"]) {
      background: #f3f4f6;
      color: #6b7280;
    }
    .tab-btn:not([class*="active"]):hover {
      background: #e5e7eb;
    }
    .btn-admin    { background:#7f1d1d; }
    .btn-admin:hover { background:#6b1313; }
    .btn-operator { background:#7f1d1d; }
    .btn-operator:hover { background:#6b1313; }
    .btn-peminjam { background:#7f1d1d; }
    .btn-peminjam:hover { background:#6b1313; }
    .ring-admin    { --tw-ring-color: #7f1d1d; }
    .ring-operator { --tw-ring-color: #7f1d1d; }
    .ring-peminjam { --tw-ring-color: #7f1d1d; }
  </style>
</head>
<body class="min-h-screen bg-pattern flex items-center justify-center p-4">

  <div class="w-full max-w-4xl flex rounded-3xl overflow-hidden shadow-2xl">

    <!-- Left Panel -->
    <div class="hidden md:flex flex-col justify-between bg-maroon-900 text-white p-10 w-5/12">
      <div>
        <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center mb-6 shadow-lg">
          <i class="fas fa-school text-maroon-700 text-2xl"></i>
        </div>
        <h1 class="font-display text-3xl font-bold leading-tight mb-3">
          Inventaris<br>SARPRAS SMK
        </h1>
        <p class="text-maroon-300 text-sm leading-relaxed">
          Sistem informasi inventaris sarana dan prasarana sekolah.
          Kelola, pantau, dan laporkan aset sekolah dengan mudah.
        </p>
      </div>
      <!-- Role cards -->
      <div class="space-y-3 mt-8">
        <p class="text-xs text-maroon-400 uppercase tracking-wider font-semibold mb-3">Akses Tersedia</p>
        <div class="flex items-center gap-3 bg-white/10 rounded-xl px-4 py-3">
          <span class="w-8 h-8 bg-maroon-700 rounded-lg flex items-center justify-center text-xs">
            <i class="fas fa-user-shield"></i>
          </span>
          <div>
            <p class="text-sm font-semibold">Admin</p>
            <p class="text-xs text-maroon-300">Kelola pengguna & laporan</p>
          </div>
        </div>
        <div class="flex items-center gap-3 bg-white/10 rounded-xl px-4 py-3">
          <span class="w-8 h-8 bg-blue-700 rounded-lg flex items-center justify-center text-xs">
            <i class="fas fa-user-gear"></i>
          </span>
          <div>
            <p class="text-sm font-semibold">Operator</p>
            <p class="text-xs text-maroon-300">Kelola inventaris & peminjaman</p>
          </div>
        </div>
        <div class="flex items-center gap-3 bg-white/10 rounded-xl px-4 py-3">
          <span class="w-8 h-8 bg-emerald-700 rounded-lg flex items-center justify-center text-xs">
            <i class="fas fa-user"></i>
          </span>
          <div>
            <p class="text-sm font-semibold">Peminjam</p>
            <p class="text-xs text-maroon-300">Guru, Staff &amp; Siswa</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Panel (Form) -->
    <div class="flex-1 bg-white p-8 md:p-10 flex flex-col justify-center">
      <div class="max-w-sm mx-auto w-full">
        <h2 class="font-display text-2xl font-bold text-gray-800 mb-1">Selamat Datang</h2>
        <p class="text-gray-500 text-sm mb-6">Pilih role dan masukkan akun Anda</p>

        <!-- Role Tabs -->
        <div class="flex gap-2 mb-6 p-1 bg-gray-100 rounded-2xl">
          <?php foreach ($role_config as $rkey => $rcfg): ?>
          <button type="button"
                  onclick="setRole('<?= $rkey ?>')"
                  id="tab-<?= $rkey ?>"
                  class="tab-btn flex-1 flex flex-col items-center gap-1 py-2.5 px-2 rounded-xl text-xs font-semibold <?= $active_role === $rkey ? 'active-'.$rkey : '' ?>">
            <i class="fas <?= $rcfg['icon'] ?> text-base"></i>
            <?= $rcfg['label'] ?>
          </button>
          <?php endforeach; ?>
        </div>

        <!-- Role badge -->
        <div id="role-badge" class="text-xs text-center mb-5 font-medium text-gray-500">
          <?php
            $badges = [
              'admin'    => '<span class="inline-flex items-center gap-1.5 bg-red-50 text-red-700 px-3 py-1 rounded-full"><i class="fas fa-user-shield text-xs"></i> Login sebagai <b>Admin</b></span>',
              'operator' => '<span class="inline-flex items-center gap-1.5 bg-red-50 text-red-700 px-3 py-1 rounded-full"><i class="fas fa-user-gear text-xs"></i> Login sebagai <b>Operator</b></span>',
              'peminjam' => '<span class="inline-flex items-center gap-1.5 bg-red-50 text-red-700 px-3 py-1 rounded-full"><i class="fas fa-user text-xs"></i> Login sebagai <b>Peminjam</b> (Guru/Staff/Siswa)</span>',
            ];
            echo $badges[$active_role];
          ?>
        </div>

        <?php if ($error): ?>
        <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm mb-5">
          <i class="fas fa-circle-exclamation flex-shrink-0"></i>
          <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['err']) && $_GET['err'] === 'akses'): ?>
        <div class="flex items-center gap-3 bg-yellow-50 border border-yellow-200 text-yellow-700 rounded-xl px-4 py-3 text-sm mb-5">
          <i class="fas fa-triangle-exclamation flex-shrink-0"></i>
          Anda tidak memiliki akses ke halaman tersebut.
        </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4" id="loginForm">
          <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($active_role) ?>">

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Username</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                <i class="fas fa-user text-sm"></i>
              </span>
              <input type="text" name="username"
                     value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                     id="usernameInput"
                     class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl text-sm
                            focus:outline-none focus:ring-2 focus:border-transparent transition"
                     placeholder="Masukkan username" required autofocus>
            </div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Password</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                <i class="fas fa-lock text-sm"></i>
              </span>
              <input type="password" name="password" id="password"
                     class="w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-xl text-sm
                            focus:outline-none focus:ring-2 focus:border-transparent transition"
                     placeholder="Masukkan password" required>
              <button type="button" onclick="togglePwd()"
                      class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-gray-600">
                <i class="fas fa-eye text-sm" id="eyeIcon"></i>
              </button>
            </div>
          </div>

          <button type="submit" id="submitBtn"
                  class="w-full btn-admin text-white font-semibold py-2.5 rounded-xl transition text-sm shadow-md mt-2">
            <i class="fas fa-right-to-bracket mr-2"></i>
            <span id="btnLabel">Masuk sebagai Admin</span>
          </button>
        </form>
      </div>
    </div>

  </div>

  <script>
    const roleData = {
      admin:    { badge: '<span class="inline-flex items-center gap-1.5 bg-red-50 text-red-700 px-3 py-1 rounded-full"><i class="fas fa-user-shield text-xs"></i>&nbsp;Login sebagai <b>Admin</b></span>', label: 'Masuk sebagai Admin',    ring: '#7f1d1d', btnClass: 'btn-admin' },
      operator: { badge: '<span class="inline-flex items-center gap-1.5 bg-red-50 text-red-700 px-3 py-1 rounded-full"><i class="fas fa-user-gear text-xs"></i>&nbsp;Login sebagai <b>Operator</b></span>', label: 'Masuk sebagai Operator', ring: '#7f1d1d', btnClass: 'btn-operator' },
      peminjam: { badge: '<span class="inline-flex items-center gap-1.5 bg-red-50 text-red-700 px-3 py-1 rounded-full"><i class="fas fa-user text-xs"></i>&nbsp;Login sebagai <b>Peminjam</b> (Guru/Staff/Siswa)</span>', label: 'Masuk sebagai Peminjam', ring: '#7f1d1d', btnClass: 'btn-peminjam' },
    };

    let currentRole = '<?= $active_role ?>';

    function setRole(role) {
      currentRole = role;
      document.getElementById('roleInput').value = role;

      // Update tabs
      ['admin','operator','peminjam'].forEach(r => {
        const btn = document.getElementById('tab-' + r);
        btn.className = btn.className.replace(/active-\w+/, '').trim();
        if (r === role) btn.classList.add('active-' + r);
      });

      // Update badge
      document.getElementById('role-badge').innerHTML = roleData[role].badge;

      // Update button
      const submitBtn = document.getElementById('submitBtn');
      submitBtn.className = submitBtn.className.replace(/btn-\w+/, '').trim();
      submitBtn.classList.add(roleData[role].btnClass);
      document.getElementById('btnLabel').textContent = roleData[role].label;

      // Update ring color on inputs
      const inputs = document.querySelectorAll('input[type="text"], input[type="password"]');
      inputs.forEach(inp => {
        inp.style.outline = 'none';
        inp.addEventListener('focus', function() {
          this.style.boxShadow = '0 0 0 2px ' + roleData[role].ring;
        });
        inp.addEventListener('blur', function() {
          this.style.boxShadow = '';
        });
      });

      document.getElementById('usernameInput').focus();
    }

    // Init ring on load
    setRole(currentRole);

    function togglePwd() {
      const p = document.getElementById('password');
      const i = document.getElementById('eyeIcon');
      if (p.type === 'password') { p.type = 'text'; i.className = 'fas fa-eye-slash text-sm'; }
      else { p.type = 'password'; i.className = 'fas fa-eye text-sm'; }
    }
  </script>
</body>
</html>
