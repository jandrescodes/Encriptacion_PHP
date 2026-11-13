<?php
$flash = \App\Core\Flash::pull();
if ($flash !== null) {
    $message = $flash['message'];
    $icon    = $flash['icon']; ?>
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
} ?>