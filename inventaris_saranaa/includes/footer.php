    </main><!-- /page content -->
  </div><!-- /main -->
</div><!-- /flex wrapper -->

<script>
// Auto-hide flash after 4s
setTimeout(() => {
  document.querySelectorAll('[data-flash]').forEach(el => {
    el.style.transition = 'opacity .5s';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 500);
  });
}, 4000);

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm || 'Yakin hapus data ini?')) e.preventDefault();
  });
});
</script>
</body>
</html>
