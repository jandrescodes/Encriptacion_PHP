<!-- Hero -->
<section class="hero text-white text-center">
    <div class="container py-5">
        <i class="fas fa-user-circle fa-4x mb-3 text-light" aria-hidden="true"></i>
        <h1 class="display-4 font-weight-bold mb-2">
            Welcome, <?= htmlspecialchars($name) ?>
        </h1>
        <p class="lead mb-4 text-light">
            Custom PHP MVC authentication system built with Composer,<br>
            a lightweight router, and role-based access control.
        </p>
        <?php if ($isAdmin): ?>
            <a href="<?= APP_URL ?>/users" class="btn btn-app-primary btn-lg px-4">
                <i class="fas fa-users mr-2"></i> Manage Users
            </a>
        <?php endif; ?>
    </div>
</section>

<!-- Stat cards -->
<section class="container py-5">
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card h-100 shadow-sm text-center">
                <div class="card-body py-4">
                    <div class="feature-icon text-white mx-auto mb-3" style="background-color: var(--color-dark);">
                        <i class="fas fa-users" aria-hidden="true"></i>
                    </div>
                    <h2 class="font-weight-bold mb-1"><?= (int) $totalUsers ?></h2>
                    <p class="text-muted mb-0">Usuarios registrados</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100 shadow-sm text-center">
                <div class="card-body py-4">
                    <div class="feature-icon text-white mx-auto mb-3 bg-success">
                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                    </div>
                    <h2 class="font-weight-bold text-success mb-1"><?= (int) $loginsToday ?></h2>
                    <p class="text-muted mb-0">Logins exitosos hoy</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100 shadow-sm text-center">
                <div class="card-body py-4">
                    <div class="feature-icon text-white mx-auto mb-3 bg-warning">
                        <i class="fas fa-times-circle" aria-hidden="true"></i>
                    </div>
                    <h2 class="font-weight-bold text-warning mb-1"><?= (int) $failedToday ?></h2>
                    <p class="text-muted mb-0">Intentos fallidos hoy</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100 shadow-sm text-center">
                <div class="card-body py-4">
                    <div class="feature-icon text-white mx-auto mb-3 bg-danger">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                    </div>
                    <h2 class="font-weight-bold text-danger mb-1"><?= (int) $lockedNow ?></h2>
                    <p class="text-muted mb-0">Cuentas bloqueadas</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent activity -->
    <div class="card shadow-sm mt-2">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-clock mr-2" aria-hidden="true"></i>Actividad reciente</span>
            <?php if ($isAdmin): ?>
                <a href="<?= APP_URL ?>/activity-logs" class="btn btn-sm btn-app-primary">Ver todo</a>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Evento</th>
                            <th>Descripción</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentEvents)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Sin actividad registrada</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentEvents as $event): ?>
                                <tr>
                                    <td><?= htmlspecialchars($event['created_at']) ?></td>
                                    <td><?= htmlspecialchars($event['user_name']) ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = match ($event['event']) {
                                            'login_success'  => 'badge-success',
                                            'login_failed'   => 'badge-warning',
                                            'logout'         => 'badge-secondary',
                                            'user_created'   => 'badge-primary',
                                            'user_updated'   => 'badge-info',
                                            'user_deleted'   => 'badge-danger',
                                            'password_changed' => 'badge-dark',
                                            default          => 'badge-light',
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= htmlspecialchars($event['event']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($event['description']) ?></td>
                                    <td><?= $event['ip_address'] !== null ? htmlspecialchars($event['ip_address']) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>