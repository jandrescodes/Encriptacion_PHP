<?php
if ((isset($_SESSION['message'])) && (isset($_SESSION['icon']))) {
    $message = $_SESSION['message'];
    $icon    = $_SESSION['icon']; ?>
    <script src="<?= APP_URL ?>/js/sweetalert2.all.min.js"></script>
    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: "top-end",
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.onmouseenter = Swal.stopTimer;
                toast.onmouseleave = Swal.resumeTimer;
            }
        });
        Toast.fire({
            icon: <?= json_encode($icon) ?>,
            title: <?= json_encode($message) ?>
        });
    </script>
<?php
    unset($_SESSION['message']);
    unset($_SESSION['icon']);
} ?>