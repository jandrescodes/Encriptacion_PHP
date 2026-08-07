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
    <title>Forgot Password</title>
</head>

<body>
    <div class="container d-flex justify-content-center align-items-center">
        <img class="wave" src="<?= APP_URL ?>/img/wave.png" alt="" aria-hidden="true">
        <div class="login-content">
            <form method="post" action="<?= APP_URL ?>/forgot-password">
                <input type="hidden" name="_csrf" value="<?= \App\Core\Csrf::token() ?>">
                <img src="<?= APP_URL ?>/img/avatar.svg" alt="" aria-hidden="true">
                <h1 class="title">Forgot Password</h1>
                <div class="input-div one">
                    <div class="i" aria-hidden="true"><i class="fas fa-lock"></i></div>
                    <div class="div">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" class="input" name="email" autocomplete="email" required>
                    </div>
                </div>
                <button name="btnrecuperar" class="btn mt-5" type="submit">SEND LINK</button>
                <a href="<?= APP_URL ?>/login" class="btn btn-anchor">Back to Login</a>
            </form>
        </div>
    </div>

    <script src="<?= APP_URL ?>/js/main.js"></script>
    <script src="<?= APP_URL ?>/js/main2.js"></script>

    <?php include __DIR__ . '/../layouts/messages.php'; ?>
</body>

</html>