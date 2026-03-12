<?php
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
requireRole(['admin','operator']);

$db=$conn=(new Database())->getConnection();

if(isset($_GET['del'])){
    $conn->prepare("DELETE FROM inventaris_sarana_jenis WHERE id_jenis=:id")->execute([':id'=>(int)$_GET['del']]);
    flash('success','Jenis berhasil dihapus.');redirect('jenis.php');
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    $id=(int)($_POST['id']??0);$n=trim($_POST['nama_jenis']);$k=(int)$_POST['kode_jenis'];$ket=trim($_POST['keterangan']);
    if($id){
        $conn->prepare("UPDATE inventaris_sarana_jenis SET nama_jenis=:n,kode_jenis=:k,keterangan=:ke WHERE id_jenis=:id")->execute([':n'=>$n,':k'=>$k,':ke'=>$ket,':id'=>$id]);
        flash('success','Jenis diperbarui.');
    }else{
        $conn->prepare("INSERT INTO inventaris_sarana_jenis(nama_jenis,kode_jenis,keterangan)VALUES(:n,:k,:ke)")->execute([':n'=>$n,':k'=>$k,':ke'=>$ket]);
        flash('success','Jenis baru ditambahkan.');
    }
    redirect('jenis.php');
}
$list=$conn->query("SELECT * FROM inventaris_sarana_jenis ORDER BY id_jenis")->fetchAll();
$edit=null;
if(isset($_GET['edit'])){$s=$conn->prepare("SELECT * FROM inventaris_sarana_jenis WHERE id_jenis=:id");$s->execute([':id'=>(int)$_GET['edit']]);$edit=$s->fetch();}
$pageTitle='Kelola Jenis';include '../includes/header.php';
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4"><?=$edit?'Edit Jenis':'Tambah Jenis'?></h3>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="id" value="<?=$edit['id_jenis']??''?>">
      <div><label class="text-sm font-medium text-gray-600 mb-1 block">Nama Jenis *</label>
        <input type="text" name="nama_jenis" value="<?=htmlspecialchars($edit['nama_jenis']??'')?>"
               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none" required></div>
      <div><label class="text-sm font-medium text-gray-600 mb-1 block">Kode Jenis *</label>
        <input type="number" name="kode_jenis" value="<?=$edit['kode_jenis']??''?>"
               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none" required></div>
      <div><label class="text-sm font-medium text-gray-600 mb-1 block">Keterangan</label>
        <input type="text" name="keterangan" value="<?=htmlspecialchars($edit['keterangan']??'')?>"
               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-500 outline-none"></div>
      <div class="flex gap-2">
        <button type="submit" class="flex-1 bg-maroon-700 hover:bg-maroon-800 text-white rounded-xl py-2 text-sm font-semibold">
          <i class="fas fa-save mr-1"></i><?=$edit?'Update':'Simpan'?></button>
        <?php if($edit):?><a href="jenis.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold">Batal</a><?php endif;?>
      </div>
    </form>
  </div>
  <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h3 class="font-semibold text-gray-800 mb-4">Daftar Jenis (<?=count($list)?>)</h3>
    <table class="w-full text-sm">
      <thead><tr class="text-left text-xs text-gray-500 border-b border-gray-100">
        <th class="pb-2 font-semibold">Nama Jenis</th><th class="pb-2 font-semibold">Kode</th>
        <th class="pb-2 font-semibold">Keterangan</th><th class="pb-2 font-semibold text-center">Aksi</th>
      </tr></thead>
      <tbody class="divide-y divide-gray-50">
        <?php foreach($list as $j):?>
        <tr><td class="py-2.5 font-medium"><?=htmlspecialchars($j['nama_jenis'])?></td>
          <td class="py-2.5 font-mono text-gray-500"><?=$j['kode_jenis']?></td>
          <td class="py-2.5 text-gray-500"><?=htmlspecialchars($j['keterangan']??'-')?></td>
          <td class="py-2.5 text-center"><div class="flex justify-center gap-1">
            <a href="?edit=<?=$j['id_jenis']?>" class="px-3 py-1 bg-blue-50 text-blue-600 rounded-lg text-xs hover:bg-blue-100"><i class="fas fa-pen"></i></a>
            <a href="?del=<?=$j['id_jenis']?>" data-confirm="Hapus jenis ini?" class="px-3 py-1 bg-red-50 text-red-600 rounded-lg text-xs hover:bg-red-100"><i class="fas fa-trash"></i></a>
          </div></td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>
</div>
<?php include '../includes/footer.php';?>
