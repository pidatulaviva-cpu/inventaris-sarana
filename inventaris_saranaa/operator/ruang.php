<?php
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['admin','operator']);

$db=$conn=(new Database())->getConnection();

if(isset($_GET['del'])){
    $conn->prepare("DELETE FROM inventaris_sarana_ruang WHERE id_ruang=:id")->execute([':id'=>(int)$_GET['del']]);
    flash('success','Ruang berhasil dihapus.');redirect('ruang.php');
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    $id=(int)($_POST['id']??0);$n=trim($_POST['nama_ruang']);$k=(int)$_POST['kode_ruang'];$ket=trim($_POST['keterangan']);
    if($id){
        $conn->prepare("UPDATE inventaris_sarana_ruang SET nama_ruang=:n,kode_ruang=:k,keterangan=:ke WHERE id_ruang=:id")->execute([':n'=>$n,':k'=>$k,':ke'=>$ket,':id'=>$id]);
        flash('success','Ruang diperbarui.');
    }else{
        $conn->prepare("INSERT INTO inventaris_sarana_ruang(nama_ruang,kode_ruang,keterangan)VALUES(:n,:k,:ke)")->execute([':n'=>$n,':k'=>$k,':ke'=>$ket]);
        flash('success','Ruang baru ditambahkan.');
    }
    redirect('ruang.php');
}
$list=$conn->query("SELECT * FROM inventaris_sarana_ruang ORDER BY id_ruang")->fetchAll();
$edit=null;
if(isset($_GET['edit'])){$s=$conn->prepare("SELECT * FROM inventaris_sarana_ruang WHERE id_ruang=:id");$s->execute([':id'=>(int)$_GET['edit']]);$edit=$s->fetch();}
$pageTitle='Kelola Ruang';include '../includes/header.php';
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4"><?=$edit?'Edit Ruang':'Tambah Ruang'?></h3>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="id" value="<?=$edit['id_ruang']??''?>">
      <div><label class="text-sm font-medium text-gray-600 mb-1 block">Nama Ruang *</label>
        <input type="text" name="nama_ruang" value="<?=htmlspecialchars($edit['nama_ruang']??'')?>"
               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none" required></div>
      <div><label class="text-sm font-medium text-gray-600 mb-1 block">Kode Ruang *</label>
        <input type="number" name="kode_ruang" value="<?=$edit['kode_ruang']??''?>"
               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none" required></div>
      <div><label class="text-sm font-medium text-gray-600 mb-1 block">Keterangan</label>
        <input type="text" name="keterangan" value="<?=htmlspecialchars($edit['keterangan']??'')?>"
               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none"></div>
      <div class="flex gap-2">
        <button type="submit" class="flex-1 bg-maroon-700 hover:bg-maroon-800 text-white rounded-xl py-2 text-sm font-semibold">
          <i class="fas fa-save mr-1"></i><?=$edit?'Update':'Simpan'?></button>
        <?php if($edit):?><a href="ruang.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold">Batal</a><?php endif;?>
      </div>
    </form>
  </div>
  <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">Daftar Ruang (<?=count($list)?>)</h3>
    <table class="w-full text-sm">
      <thead><tr class="text-left text-xs text-gray-500 border-b border-gray-100">
        <th class="pb-2 font-semibold">Nama Ruang</th><th class="pb-2 font-semibold">Kode</th>
        <th class="pb-2 font-semibold">Keterangan</th><th class="pb-2 font-semibold text-center">Aksi</th>
      </tr></thead>
      <tbody class="divide-y divide-gray-50">
        <?php foreach($list as $r):?>
        <tr><td class="py-2.5 font-medium"><?=htmlspecialchars($r['nama_ruang'])?></td>
          <td class="py-2.5 font-mono text-gray-500"><?=$r['kode_ruang']?></td>
          <td class="py-2.5 text-gray-500"><?=htmlspecialchars($r['keterangan']??'-')?></td>
          <td class="py-2.5 text-center"><div class="flex justify-center gap-1">
            <a href="?edit=<?=$r['id_ruang']?>" class="px-3 py-1 bg-blue-50 text-blue-600 rounded-lg text-xs hover:bg-blue-100"><i class="fas fa-pen"></i></a>
            <a href="?del=<?=$r['id_ruang']?>" data-confirm="Hapus ruang ini?" class="px-3 py-1 bg-red-50 text-red-600 rounded-lg text-xs hover:bg-red-100"><i class="fas fa-trash"></i></a>
          </div></td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>
</div>
<?php include '../includes/footer.php';?>
