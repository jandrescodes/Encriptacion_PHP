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
    <title>Reset Password</title>
</head>

<body>
    <div class="container d-flex justify-content-center align-items-center">
        <img class="wave" src="<?= APP_URL ?>/img/wave.png" alt="" aria-hidden="true">
        <div class="login-content">
            <form method="post" action="<?= APP_URL ?>/reset-password">
                <input type="hidden" name="_csrf" value="<?= \App\Core\Csrf::token() ?>">
                <img src="<?= APP_URL ?>/img/avatar.svg" alt="" aria-hidden="true">
                <h1 class="title">New Password</h1>
                <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">
                <input type="text" name="username" value="" autocomplete="username" aria-hidden="true" tabindex="-1"
                       style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;border:0;clip:rect(0 0 0 0);overflow:hidden;white-space:nowrap">

                <div class="input-div pass">
                    <div class="i" aria-hidden="true"><i class="fas fa-lock"></i></div>
                    <div class="div">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" class="input" name="new_password" autocomplete="new-password" required>
                    </div>
                </div>
                <div class="input-div pass">
                    <div class="i" aria-hidden="true"><i class="fas fa-lock"></i></div>
                    <div class="div">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" class="input" name="confirm_password" autocomplete="new-password" required>
                    </div>
                </div>
                <button name="btnactualizar" class="btn mt-5" type="submit">UPDATE PASSWORD</button>
            </form>
        </div>
    </div>

    <script src="<?= APP_URL ?>/js/main.js"></script>
    <script src="<?= APP_URL ?>/js/main2.js"></script>

    <?php include __DIR__ . '/../layouts/messages.php'; ?>
</body>

</html>