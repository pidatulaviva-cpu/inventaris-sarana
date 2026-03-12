<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?? 'Inventaris SMK' ?> — SARPRAS</title>
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
            sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui'],
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
    .sidebar-link { transition: all .18s; }
    .sidebar-link:hover, .sidebar-link.active {
      background: rgba(255,255,255,.12);
      padding-left: 1.25rem;
    }
    .card-hover { transition: box-shadow .2s, transform .2s; }
    .card-hover:hover { box-shadow: 0 8px 30px rgba(127,29,29,.15); transform: translateY(-2px); }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: #f5f5f5; }
    ::-webkit-scrollbar-thumb { background: #9b1c1c; border-radius: 3px; }
    .badge-baik        { background:#dcfce7; color:#166534; }
    .badge-rusak       { background:#fee2e2; color:#991b1b; }
    .badge-dipinjam    { background:#fef9c3; color:#854d0e; }
    .badge-dikembalikan{ background:#dcfce7; color:#166534; }
  </style>
</head>
<body class="bg-gray-50 text-gray-800">

<?php
$role      = $_SESSION['role'] ?? '';
$nama_user = $_SESSION['nama'] ?? 'User';

// Semua href pakai absolute path agar tidak terpengaruh letak file saat ini
$menus = [];
if ($role === 'admin') {
    $menus = [
        ['icon'=>'fa-gauge',            'label'=>'Beranda',      'href'=>BASE_URL . 'admin/index.php'],
        ['icon'=>'fa-users-cog',        'label'=>'Kelola Pengguna','href'=>BASE_URL . 'admin/pengguna.php'],
        ['icon'=>'fa-hand-holding',     'label'=>'Peminjaman',     'href'=>BASE_URL . 'admin/peminjaman.php'],
        ['icon'=>'fa-rotate-left',      'label'=>'Pengembalian',   'href'=>BASE_URL . 'admin/pengembalian.php'],
        ['icon'=>'fa-file-chart-column','label'=>'Laporan',        'href'=>BASE_URL . 'admin/laporan.php'],
    ];
} elseif ($role === 'operator') {
    $menus = [
        ['icon'=>'fa-gauge',        'label'=>'Beranda',    'href'=>BASE_URL . 'operator/index.php'],
        ['icon'=>'fa-boxes-stacked','label'=>'Inventaris',   'href'=>BASE_URL . 'operator/inventaris.php'],
        ['icon'=>'fa-tags',         'label'=>'Jenis',        'href'=>BASE_URL . 'operator/jenis.php'],
        ['icon'=>'fa-door-open',    'label'=>'Ruang',        'href'=>BASE_URL . 'operator/ruang.php'],
        ['icon'=>'fa-users',        'label'=>'Peminjam',      'href'=>BASE_URL . 'operator/peminjam.php'],
        ['icon'=>'fa-hand-holding', 'label'=>'Peminjaman',   'href'=>BASE_URL . 'operator/peminjaman.php'],
        ['icon'=>'fa-rotate-left',  'label'=>'Pengembalian', 'href'=>BASE_URL . 'operator/pengembalian.php'],
    ];
} elseif ($role === 'peminjam') {
    $menus = [
        ['icon'=>'fa-gauge',             'label'=>'Beranda',    'href'=>BASE_URL . 'peminjam/index.php'],
        ['icon'=>'fa-hand-holding',      'label'=>'Pinjam Barang','href'=>BASE_URL . 'peminjam/peminjaman.php'],
        ['icon'=>'fa-rotate-left',       'label'=>'Pengembalian', 'href'=>BASE_URL . 'peminjam/pengembalian.php'],
        ['icon'=>'fa-clock-rotate-left', 'label'=>'Riwayat',      'href'=>BASE_URL . 'peminjam/riwayat.php'],
    ];
}
?>

<div class="flex min-h-screen">
  <aside class="w-64 bg-gradient-to-b from-maroon-800 to-maroon-950 text-white flex flex-col shadow-2xl flex-shrink-0">
    <div class="px-6 py-5 border-b border-maroon-700">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow">
          <i class="fas fa-school text-maroon-700 text-lg"></i>
        </div>
        <div>
          <p class="font-display font-bold text-base leading-tight">SARPRAS</p>
          <p class="text-maroon-300 text-xs">Inventaris SMK</p>
        </div>
      </div>
    </div>
    <div class="px-6 py-4 border-b border-maroon-700 bg-maroon-900/40">
      <p class="text-xs text-maroon-300 mb-1">Login sebagai</p>
      <p class="font-semibold text-sm truncate"><?= htmlspecialchars($nama_user) ?></p>
      <?php
      $role_labels = ['admin'=>'Admin','operator'=>'Operator','peminjam'=>'Peminjam'];
      $role_display = $role_labels[$role] ?? ucfirst($role);
      if ($role === 'peminjam' && isset($_SESSION['tipe'])) {
          $tipe_labels = ['guru'=>'Guru','staff'=>'Staff','siswa'=>'Siswa'];
          $tipe_display = $tipe_labels[$_SESSION['tipe']] ?? ucfirst($_SESSION['tipe']);
      }
      ?>
      <span class="inline-block mt-1 px-2 py-0.5 text-xs rounded-full bg-maroon-600 text-maroon-100 capitalize">
        <?= $role_display ?>
      </span>
      <?php if ($role === 'peminjam' && isset($tipe_display)): ?>
      <span class="inline-block mt-1 ml-1 px-2 py-0.5 text-xs rounded-full bg-emerald-700 text-emerald-100">
        <?= $tipe_display ?>
      </span>
      <?php endif; ?>
    </div>
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
      <?php foreach ($menus as $m):
        $isActive = ($_SERVER['PHP_SELF'] === $m['href'] || basename($_SERVER['PHP_SELF']) === basename($m['href']));
      ?>
      <a href="<?= htmlspecialchars($m['href']) ?>"
         class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium
                text-maroon-100 <?= $isActive ? 'active bg-white/15' : '' ?>">
        <i class="fas <?= $m['icon'] ?> w-5 text-maroon-300 text-sm"></i>
        <?= $m['label'] ?>
      </a>
      <?php endforeach; ?>
    </nav>
    <div class="px-3 py-4 border-t border-maroon-700">
      <a href="<?= BASE_URL ?>logout.php"
         class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium
                text-maroon-200 hover:bg-red-900/40 transition-colors">
        <i class="fas fa-sign-out-alt w-5 text-maroon-400 text-sm"></i>
        Logout
      </a>
    </div>
  </aside>

  <div class="flex-1 flex flex-col min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 px-6 py-3.5 flex items-center justify-between sticky top-0 z-10">
      <div>
        <h1 class="font-display font-bold text-maroon-800 text-xl"><?= $pageTitle ?? 'Dashboard' ?></h1>
        <p class="text-xs text-gray-400"><?= date('l, d F Y') ?></p>
      </div>
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-maroon-100 rounded-full flex items-center justify-center">
          <i class="fas fa-user text-maroon-600 text-xs"></i>
        </div>
        <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($nama_user) ?></span>
      </div>
    </header>

    <?php
    $success = flash('success');
    $error   = flash('error');
    if ($success || $error):
    ?>
    <div class="px-6 pt-4">
      <?php if ($success): ?>
      <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm mb-1">
        <i class="fas fa-circle-check text-green-500"></i> <?= $success ?>
      </div>
      <?php endif; ?>
      <?php if ($error): ?>
      <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm mb-1">
        <i class="fas fa-circle-xmark text-red-500"></i> <?= $error ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <main class="flex-1 p-6 overflow-auto">