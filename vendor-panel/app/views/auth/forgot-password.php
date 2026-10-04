<div class="forgot-password-container">
    <div class="card shadow p-5" style="max-width: 400px; margin: 50px auto;">
        <h2 class="text-center mb-4">Reset Password</h2>
        
        <form method="POST" action="<?php echo htmlspecialchars(vendor_url('/ajax/forgot-password')); ?>">
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
        </form>
        
        <div class="text-center mt-3">
            <a href="<?php echo htmlspecialchars(vendor_url('/login')); ?>">Back to Login</a>
        </div>
    </div>
</div>
