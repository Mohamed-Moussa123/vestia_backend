  </div><!-- /content -->
</div><!-- /main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-dismiss alerts
document.querySelectorAll('.alert[data-auto-dismiss]').forEach(el => {
  setTimeout(() => { el.style.transition='opacity .5s'; el.style.opacity=0; setTimeout(()=>el.remove(),500); }, 3000);
});
// Confirm deletes
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault();
  });
});
</script>
</body>
</html>
