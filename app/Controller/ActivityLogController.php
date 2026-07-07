<?php

namespace App\Controller;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Model\ActivityLog;
use App\Model\User;

class ActivityLogController extends Controller
{
    private ActivityLog $model;

    private const EVENT_BADGES = [
        ActivityLog::EVENT_LOGIN_SUCCESS    => ['label' => 'Login exitoso',           'class' => 'success'],
        ActivityLog::EVENT_LOGIN_FAILED     => ['label' => 'Login fallido',           'class' => 'danger'],
        ActivityLog::EVENT_LOGOUT           => ['label' => 'Logout',                  'class' => 'secondary'],
        ActivityLog::EVENT_PASSWORD_CHANGED => ['label' => 'Contraseña cambiada',     'class' => 'warning'],
        ActivityLog::EVENT_PASSWORD_RESET   => ['label' => 'Contraseña restablecida', 'class' => 'info'],
        ActivityLog::EVENT_USER_CREATED     => ['label' => 'Usuario creado',          'class' => 'primary'],
        ActivityLog::EVENT_USER_UPDATED     => ['label' => 'Usuario actualizado',     'class' => 'primary'],
        ActivityLog::EVENT_USER_DELETED     => ['label' => 'Usuario eliminado',       'class' => 'dark'],
        ActivityLog::EVENT_SESSION_REVOKED  => ['label' => 'Sesión revocada',         'class' => 'warning'],
    ];

    private const VALID_EVENTS = [
        ActivityLog::EVENT_LOGIN_SUCCESS,
        ActivityLog::EVENT_LOGIN_FAILED,
        ActivityLog::EVENT_LOGOUT,
        ActivityLog::EVENT_PASSWORD_CHANGED,
        ActivityLog::EVENT_PASSWORD_RESET,
        ActivityLog::EVENT_USER_CREATED,
        ActivityLog::EVENT_USER_UPDATED,
        ActivityLog::EVENT_USER_DELETED,
        ActivityLog::EVENT_SESSION_REVOKED,
    ];

    public function __construct(\mysqli $connection)
    {
        parent::__construct($connection);
        $this->model = new ActivityLog($connection);
    }

    public function index(): void
    {
        AuthMiddleware::timeout($this->connection);
        AuthMiddleware::admin();

        $activeFilters = $this->sanitizeFilters($_GET);
        $users = (new User($this->connection))->getAll();

        $this->render('activity-log/index.php', [
            'pageTitle'       => 'Activity Log — SecureAuth',
            'useDataTables'   => true,
            'pageScripts'     => ['js/activity-logs-table.js'],
            'eventOptions'    => self::VALID_EVENTS,
            'userOptions'     => $users,
            'activeFilters'   => $activeFilters,
            'hasActiveFilters' => !empty($activeFilters),
        ], protected: true);
    }

    public function data(): void
    {
        AuthMiddleware::timeout($this->connection);
        AuthMiddleware::admin();

        $draw   = (int)($_GET['draw'] ?? 1);
        $start  = max(0, (int)($_GET['start'] ?? 0));
        $length = in_array((int)($_GET['length'] ?? 25), [10, 25, 50, 100], true)
            ? (int)$_GET['length'] : 25;

        $filters  = $this->sanitizeFilters($_GET);
        $total    = $this->model->getTotalCount([]);
        $filtered = $this->model->getTotalCount($filters);
        $logs     = $this->model->getAll($filters, $length, $start);

        $data = array_map(function ($row) {
            $badge = self::EVENT_BADGES[$row['event']] ?? null;
            $eventHtml = $badge
                ? '<span class="badge badge-' . $badge['class'] . ' badge-pill p-2">' . $badge['label'] . '</span>'
                : '<span class="badge badge-light badge-pill p-2">' . htmlspecialchars($row['event'], ENT_QUOTES, 'UTF-8') . '</span>';

            return [
                htmlspecialchars((string)$row['id'],             ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($row['created_at'],              ENT_QUOTES, 'UTF-8'),
                $eventHtml,
                htmlspecialchars($row['description'],             ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($row['user_name'] ?? 'Anónimo', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($row['ip_address'],              ENT_QUOTES, 'UTF-8'),
            ];
        }, $logs);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
        exit;
    }

    private function sanitizeFilters(array $input): array
    {
        $filters = [];

        if (!empty($input['event']) && in_array($input['event'], self::VALID_EVENTS, true)) {
            $filters['event'] = $input['event'];
        }

        if (!empty($input['user_id']) && (int)$input['user_id'] > 0) {
            $filters['user_id'] = (int)$input['user_id'];
        }

        foreach (['date_from', 'date_to'] as $key) {
            if (!empty($input[$key])) {
                $d = \DateTime::createFromFormat('Y-m-d', $input[$key]);
                if ($d && $d->format('Y-m-d') === $input[$key]) {
                    $filters[$key] = $input[$key];
                }
            }
        }

        return $filters;
    }
}
