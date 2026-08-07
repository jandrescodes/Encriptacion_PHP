(function () {
    var toggle = document.getElementById("verPassword");
    var input = document.getElementById("password");

    if (!toggle || !input) {
        return;
    }

    var icon = toggle.querySelector("i");
    var showing = false;

    toggle.addEventListener("click", function () {
        showing = !showing;

        input.type = showing ? "text" : "password";

        toggle.setAttribute("aria-pressed", showing ? "true" : "false");
        toggle.setAttribute("aria-label", showing ? "Hide password" : "Show password");

        if (icon) {
            icon.className = showing ? "fas fa-eye-slash" : "fas fa-eye";
        }
    });
})();
