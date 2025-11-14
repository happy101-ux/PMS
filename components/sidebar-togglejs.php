
<!-- Sidebar toggle JS -->
<script>
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('sidebarToggle');
const mainContent = document.querySelector('.main-content');

toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('d-none');
    mainContent.classList.toggle('full-width');
});
</script>