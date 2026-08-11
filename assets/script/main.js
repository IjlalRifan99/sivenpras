document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('roomDropdownToggle');
    const dropdown = document.querySelector('.room-dropdown');

    if (toggle && dropdown) {
        toggle.addEventListener('click', function() {
            dropdown.classList.toggle('open');
        });
    }
});