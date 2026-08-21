<section class="container mb-3" style="max-width: 720px;">
    <h2 class="mb-3"><i class="fas fa-user-plus mr-2"></i>Create User</h2>
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">User Details</div>
        <form action="<?= APP_URL ?>/users/create" method="post">
            <input type="hidden" name="_csrf" value="<?= \App\Core\Csrf::token() ?>">
            <div class="card-body">
                <div class="mb-3">
                    <label for="first_name" class="form-label">First Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" name="first_name" id="first_name"
                            value="<?= htmlspecialchars($old['first_name'] ?? '') ?>"
                            placeholder="e.g. John" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="last_name" class="form-label">Last Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" name="last_name" id="last_name"
                            value="<?= htmlspecialchars($old['last_name'] ?? '') ?>"
                            placeholder="e.g. Doe" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="email" id="email"
                            value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                            placeholder="e.g. john@gmail.com">
                    </div>
                    <small class="form-text text-muted">Required for password recovery</small>
                </div>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" name="username" id="username"
                            value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                            placeholder="e.g. john10" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        </div>
                        <input type="password" class="form-control" name="password" id="password"
                            placeholder="Password" autocomplete="new-password" required>
                    </div>
                    <small class="form-text text-muted">Required. At least 8 characters.</small>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="is_admin" id="is_admin" value="1"
                        <?= !empty($old['is_admin']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_admin">Administrator</label>
                </div>
            </div>

            <div class="card-footer">
                <div class="row">
                    <div class="col-12 col-sm-auto mb-2 mb-sm-0">
                        <button type="submit" name="add_user" class="btn btn-primary w-100">
                            <i class="fas fa-user-plus"></i> Add User
                        </button>
                    </div>
                    <div class="col-12 col-sm-auto">
                        <a class="btn btn-light border w-100" href="<?= APP_URL ?>/users" role="button">
                            <i class="fas fa-undo"></i> Cancel
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>