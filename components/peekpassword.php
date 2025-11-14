<!-- ✅ Toggle Password Script -->
<script>
document.getElementById('togglePwd').addEventListener('click', function () {
    const pwdInput = document.getElementById('pwd');
    if (pwdInput.type === 'password') {
        pwdInput.type = 'text';
        this.textContent = '🙈';
    } else {
        pwdInput.type = 'password';
        this.textContent = '👁️';
    }
});
</script>