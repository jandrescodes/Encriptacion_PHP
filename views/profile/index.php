<section class="container mb-4" style="max-width: 720px;">
    <h1 class="h2 mb-4"><i class="fas fa-id-card mr-2" aria-hidden="true"></i>My Profile</h1>

    <!-- Form 1: Profile info -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white">Personal Information</div>
        <form action="<?= APP_URL ?>/profile" method="post">
            <input type="hidden" name="_csrf" value="<?= \App\Core\Csrf::token() ?>">
            <div class="card-body">
                <div class="mb-3">
                    <label for="first_name" class="form-label">First Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" name="first_name" id="first_name"
                            value="<?= htmlspecialchars($user['first_name']) ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="last_name" class="form-label">Last Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" name="last_name" id="last_name"
                            value="<?= htmlspecialchars($user['last_name']) ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="email" id="email"
                            value="<?= htmlspecialchars($user['email']) ?>" autocomplete="email" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-at"></i></span>
                        </div>
                        <input type="text" class="form-control" name="username" id="username"
                            value="<?= htmlspecialchars($user['username']) ?>" autocomplete="username" required>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" name="update_profile" class="btn btn-app-primary">
                    <i class="fas fa-save mr-1"></i> Save Changes
                </button>
            </div>
        </form>
    </div>

    <!-- Form 2: Change password -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <?= !empty($user['oauth_provider']) ? 'Set a Password' : 'Change Password' ?>
        </div>
        <form action="<?= APP_URL ?>/profile/password" method="post">
            <input type="hidden" name="_csrf" value="<?= \App\Core\Csrf::token() ?>">
            <div class="card-body">
                <?php if (empty($user['oauth_provider'])): ?>
                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        </div>
                        <input type="password" class="form-control" name="current_password"
                            id="current_password" autocomplete="current-password" required>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-muted mb-3">
                    Your account uses Google sign-in. You can set a password to also log in with your username.
                </p>
                <?php endif; ?>
                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                        </div>
                        <input type="password" class="form-control" name="new_password"
                            id="new_password" minlength="8" autocomplete="new-password" required>
                    </div>
                    <small class="form-text text-muted">Minimum 8 characters</small>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                        </div>
                        <input type="password" class="form-control" name="confirm_password"
                            id="confirm_password" minlength="8" autocomplete="new-password" required>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" name="change_password" class="btn btn-app-primary">
                    <i class="fas fa-shield-alt mr-1"></i>
                    <?= !empty($user['oauth_provider']) ? 'Set Password' : 'Update Password' ?>
                </button>
            </div>
        </form>
    </div>
</section>
