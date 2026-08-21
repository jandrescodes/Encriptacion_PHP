<?php
$eventLabels = [
    'login_success'    => 'Login exitoso',
    'login_failed'     => 'Login fallido',
    'logout'           => 'Logout',
    'password_changed' => 'Contraseña cambiada',
    'password_reset'   => 'Contraseña restablecida',
    'user_created'     => 'Usuario creado',
    'user_updated'     => 'Usuario actualizado',
    'user_deleted'     => 'Usuario eliminado',
];
?>
<section class="container-fluid mb-3">
    <h2 class="mb-3"><i class="fas fa-clipboard-list mr-2"></i>Activity Log</h2>

    <!-- Filtros -->
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center js-filters-toggle"
            data-toggle="collapse" data-target="#filtersCollapse"
            role="button" tabindex="0"
            aria-expanded="<?= $hasActiveFilters ? 'true' : 'false' ?>" aria-controls="filtersCollapse">
            <span><i class="fas fa-filter mr-1" aria-hidden="true"></i> Filtros</span>
            <span>
                <?php if ($hasActiveFilters): ?>
                    <span class="badge badge-warning mr-2">Activos</span>
                <?php endif; ?>
                <i class="fas fa-chevron-down" aria-hidden="true"></i>
            </span>
        </div>
        <div id="filtersCollapse" class="collapse<?= $hasActiveFilters ? ' show' : '' ?>">
            <div class="card-body">
                <form id="filtersForm" method="GET">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="filter-event">Evento</label>
                            <select id="filter-event" name="event" class="form-control form-control-sm">
                                <option value="">Todos</option>
                                <?php foreach ($eventOptions as $value): ?>
                                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= ($activeFilters['event'] ?? '') === $value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($eventLabels[$value] ?? $value, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="filter-user">Usuario</label>
                            <select id="filter-user" name="user_id" class="form-control form-control-sm">
                                <option value="">Todos</option>
                                <?php foreach ($userOptions as $u): ?>
                                    <option value="<?= (int)$u['id'] ?>"
                                        <?= ((int)($activeFilters['user_id'] ?? 0)) === (int)$u['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="filter-date-from">Desde</label>
                            <input type="date" id="filter-date-from" name="date_from" class="form-control form-control-sm"
                                value="<?= htmlspecialchars($activeFilters['date_from'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="filter-date-to">Hasta</label>
                            <input type="date" id="filter-date-to" name="date_to" class="form-control form-control-sm"
                                value="<?= htmlspecialchars($activeFilters['date_to'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <button type="button" id="btn-filter" class="btn btn-app-primary btn-sm mr-2">
                            <i class="fas fa-search mr-1" aria-hidden="true"></i>
                            <span class="btn-filter-label">Filtrar</span>
                        </button>
                        <a href="<?= APP_URL ?>/activity-logs" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times mr-1"></i>Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <i class="fas fa-history mr-1"></i> All Events
        </div>
        <div class="card-body">
            <div id="activity_log_status" class="sr-only" role="status" aria-live="polite" aria-atomic="true"></div>
            <table class="table table-hover table-bordered" id="activity_log_table" style="visibility: hidden;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Evento</th>
                        <th>Descripción</th>
                        <th>Usuario</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</section>