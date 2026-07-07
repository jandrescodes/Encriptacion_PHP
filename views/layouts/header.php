<!DOCTYPE html>
<html lang="en">

<head>
    <title><?= htmlspecialchars($pageTitle ?? 'SecureAuth') ?></title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link rel="stylesheet" href="<?= APP_URL ?>/css/bootstrap.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/css/estilo.css">
    <link rel="preload" href="<?= APP_URL ?>/webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= APP_URL ?>/webfonts/fa-brands-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= APP_URL ?>/css/all.min.css">
    <link href="<?= APP_URL ?>/img/<?= htmlspecialchars($favicon ?? 'usuario.png') ?>" rel="shortcut icon">
    <?php if ($useDataTables ?? false): ?>
        <link href="<?= APP_URL ?>/DataTables/dataTables.bootstrap4.min.css" rel="stylesheet">
        <link href="<?= APP_URL ?>/DataTables/responsive.bootstrap4.min.css" rel="stylesheet">
        <link href="<?= APP_URL ?>/DataTables/buttons.bootstrap4.min.css" rel="stylesheet">
    <?php endif; ?>
    <?php foreach ($pageStyles ?? [] as $style): ?>
        <link rel="stylesheet" href="<?= APP_URL . '/' . ltrim($style, '/') ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= APP_URL ?>/css/layout-protected.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/css/sweetalert2.min.css">
    <script src="<?= APP_URL ?>/js/sweetalert2.all.min.js"></script>
    <script src="<?= APP_URL ?>/js/jquery.min.js"></script>

    <script>
        const APP_URL = '<?= APP_URL ?>';
    </script>
</head>

<body<?= isset($bodyClass) ? ' class="' . htmlspecialchars($bodyClass) . '"' : '' ?>>
    <header>
        <nav class="navbar navbar-dark bg-dark navbar-expand-lg">
            <a class="navbar-brand ml-2" href="<?= APP_URL ?>/">
                <i class="fas fa-shield-alt mr-1"></i> SecureAuth
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item">
                        <a class="nav-link ml-2" href="<?= APP_URL ?>/">
                            <i class="fas fa-home mr-1"></i> Home
                        </a>
                    </li>
                    <?php if (!empty($_SESSION['is_admin'])): ?>
                        <li class="nav-item">
                            <a class="nav-link ml-2" href="<?= APP_URL ?>/users">
                                <i class="fas fa-users mr-1"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link ml-2" href="<?= APP_URL ?>/activity-logs">
                                <i class="fas fa-clipboard-list mr-1"></i> Activity Log
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav mr-2">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/sessions">
                            <i class="fas fa-desktop mr-1"></i> Sessions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/profile">
                            <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($_SESSION['name'] ?? '') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <form method="post" action="<?= APP_URL ?>/logout" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?= \App\Core\Csrf::token() ?>">
                            <button type="submit" class="btn-logout-nav nav-link text-danger">
                                <i class="fas fa-sign-out-alt mr-1"></i> Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </nav>
    </header>

    <main<?= empty($bodyClass) ? ' class="mt-3"' : '' ?>>