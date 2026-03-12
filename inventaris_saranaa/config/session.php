<?php
/**
 * config/session.php
 * Helper session, autentikasi, dan utilitas umum
 */

function requireLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

function requireRole(array $roles): void {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: ' . BASE_URL . 'login.php?err=akses');
        exit;
    }
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function flash(string $key, string $msg = ''): string {
    if ($msg) {
        $_SESSION['flash'][$key] = $msg;
        return '';
    }
    $val = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $val;
}

function formatTanggal(string $date): string {
    if (!$date) return '-';
    $bulan = ['', 'Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    [$y, $m, $d] = explode('-', $date);
    return (int)$d . ' ' . $bulan[(int)$m] . ' ' . $y;
}

// Auto-detect BASE_URL berdasarkan lokasi file ini
// Contoh: jika di /var/www/html/inventaris_sarana/config/ → BASE_URL = /inventaris_sarana/
$_scriptPath = str_replace('\\', '/', dirname(dirname(__FILE__)));
$_docRoot    = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/'));
$_basePath   = substr($_scriptPath, strlen($_docRoot));
$_basePath   = rtrim($_basePath, '/') . '/';
if (!defined('BASE_URL')) define('BASE_URL', $_basePath);
