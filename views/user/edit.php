<section class="container mb-3" style="max-width: 720px;">
    <h2 class="mb-3"><i class="fas fa-user-edit mr-2"></i>Edit User</h2>
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">User Details</div>
        <form action="<?= APP_URL ?>/users/edit" method="post">
            <input type="hidden" name="_csrf" value="<?= \App\Core\Csrf::token() ?>">
            <div class="card-body">
                <input type="hidden" name="id" value="<?= $user['id'] ?>">
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
                            value="<?= htmlspecialchars($user['email']) ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" name="username" id="username"
                            value="<?= htmlspecialchars($user['username']) ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        </div>
                        <input type="password" class="form-control" name="password" id="password"
                            placeholder="Leave blank to keep current password" autocomplete="new-password">
                    </div>
                    <small class="form-text text-muted">Leave blank to keep current password</small>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="is_admin" id="is_admin" value="1"
                        <?= $user['is_admin'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_admin">Administrator</label>
                </div>
            </div>

            <div class="card-footer">
                <div class="row">
                    <div class="col-12 col-sm-auto mb-2 mb-sm-0">
                        <button type="submit" name="update_user" class="btn btn-success w-100">
                            <i class="fas fa-pen"></i> Save Changes
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