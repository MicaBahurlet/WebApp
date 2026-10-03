function openModal(id) {
    var modal = document.getElementById(id);
    if (modal) {
        modal.classList.add("active");
    }
}

function closeModal(id) {
    var modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove("active");
    }
}

function toggleMobileMenu() {
    var nav = document.getElementById("navLinks");
    if (nav) {
        nav.classList.toggle("is-open");
    }
}

window.addEventListener("click", function (event) {
    if (event.target.classList && event.target.classList.contains("modal")) {
        event.target.classList.remove("active");
    }
});

document.addEventListener("DOMContentLoaded", function () {
    var openId = document.body.getAttribute("data-open-modal");
    if (openId) {
        openModal(openId);
    }
});
