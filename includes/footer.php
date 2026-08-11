<script src="../assets/script/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropdown = document.getElementById('roomDropdown');
    if (!dropdown) return;

    const state = localStorage.getItem('roomDropdownState');

    if (state === null) {
        dropdown.open = true;
    } else {
        dropdown.open = state === 'open';
    }

    dropdown.addEventListener('toggle', function() {
        localStorage.setItem('roomDropdownState', dropdown.open ? 'open' : 'closed');
    });
});
</script>
</body>
</html>