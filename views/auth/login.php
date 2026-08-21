<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <link rel="stylesheet" href="<?= APP_URL ?>/css/bootstrap.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/css/style.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/css/all.min.css">
    <link href="<?= APP_URL ?>/img/candado.png" rel="shortcut icon">
    <link rel="stylesheet" href="<?= APP_URL ?>/css/sweetalert2.min.css">
    <title>Login</title>
</head>

<body>
    <div class="container d-flex justify-content-center align-items-center">
        <img class="wave" src="<?= APP_URL ?>/img/wave.png" alt="" aria-hidden="true">
        <div class="login-content">
            <form method="post" action="<?= APP_URL ?>/login">
                <input type="hidden" name="_csrf" value="<?= \App\Core\Csrf::token() ?>">
                <img src="<?= APP_URL ?>/img/avatar.svg" alt="" aria-hidden="true">
                <h1 class="title">WELCOME</h1>
                <div class="input-div one">
                    <div class="i" aria-hidden="true"><i class="fas fa-user"></i></div>
                    <div class="div">
                        <label for="usuario">Username</label>
                        <input id="usuario" type="text" class="input" name="usuario" autocomplete="username" required>
                    </div>
                </div>
                <div class="input-div pass">
                    <div class="i" aria-hidden="true"><i class="fas fa-lock"></i></div>
                    <div class="div">
                        <label for="password">Password</label>
                        <input type="password" id="password" class="input" name="password" autocomplete="current-password" required>
                    </div>
                    <button type="button" id="verPassword" class="verPassword" aria-label="Show password" aria-pressed="false">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <label class="remember-label" for="remember">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    Remember me
                </label>
                <a href="<?= APP_URL ?>/forgot-password" class="forgot-password">Forgot your password?</a>
                <button name="btningresar" class="btn mt-5" type="submit">LOG IN</button>
            </form>
        </div>
    </div>

    <script src="<?= APP_URL ?>/js/main.js"></script>
    <script src="<?= APP_URL ?>/js/main2.js"></script>

    <?php include __DIR__ . '/../layouts/messages.php'; ?>
</body>

</html>