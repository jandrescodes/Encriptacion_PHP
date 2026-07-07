<section class="container-fluid mb-3">
    <h2 class="mb-3"><i class="fas fa-desktop mr-2"></i>Active Sessions</h2>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-laptop mr-1"></i> Your sessions</span>
            <?php if (count($sessions) > 1): ?>
                <form method="post" action="<?= APP_URL ?>/sessions/revoke-others" class="mb-0">
                    <input type="hidden" name="_csrf" value="<?= \App\Core\Csrf::token() ?>">
                    <button type="submit" name="btnrevokeothers" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-power-off mr-1"></i>Close all other sessions
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <table class="table table-hover table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Device / Browser</th>
                        <th>IP</th>
                        <th>Created</th>
                        <th>Last activity</th>
                        <th>Status</th>
                        <th class="no-export">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($session['user_agent'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                <?php if ((int) $session['via_remember'] === 1): ?>
                                    <span class="badge badge-info ml-1">Remember-me</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($session['ip_address'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($session['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($session['last_activity'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-center">
                                <?php if ((int) $session['is_current'] === 1): ?>
                                    <span class="badge badge-success">Current session</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Active</span>
                                <?php endif; ?>
                            </td>
                            <td class="no-export">
                                <?php if ((int) $session['is_current'] === 1): ?>
                                    <button type="button" class="btn btn-outline-danger btn-sm" disabled>
                                        <i class="fas fa-times mr-1"></i>Revoke
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-danger btn-sm js-revoke-session"
                                        data-session-id="<?= (int) $session['id'] ?>"
                                        data-csrf="<?= \App\Core\Csrf::token() ?>">
                                        <i class="fas fa-times mr-1"></i>Revoke
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>