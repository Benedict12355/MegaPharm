  </div><!-- .content -->
</main>
</div>

<!-- Logout Modal -->
<div class="modal-overlay" id="logoutModal">
  <div class="modal modal-sm">
    <div class="modal-icon-lg">🚪</div>
    <h3 class="text-center">Confirm Logout</h3>
    <p class="text-center text-muted">Are you sure you want to logout from the system?</p>
    <div class="modal-actions center">
      <button class="btn btn-light" onclick="closeModal('logoutModal')">Cancel</button>
      <a class="btn btn-primary" href="<?= base_url('supplier/logout.php') ?>">Logout</a>
    </div>
  </div>
</div>

<script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>