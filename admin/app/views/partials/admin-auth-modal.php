<!-- Admin authentication modal (reusable partial) -->
<div class="modal fade" id="adminAuthModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Admin Authentication</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="adminAuthPassword" class="form-label">Enter your admin password</label>
          <input type="password" id="adminAuthPassword" class="form-control" autocomplete="current-password">
        </div>
        <div id="adminAuthError" class="text-danger small" style="display:none;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="adminAuthSubmit" class="btn btn-primary">Authenticate & Resend</button>
      </div>
    </div>
  </div>
</div>
