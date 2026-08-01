<div class="theme-toggle" role="group" aria-label="Pilih tema tampilan">
    <button type="button" class="theme-toggle-btn" data-theme-option="a" aria-pressed="{{ $theme === 'a' ? 'true' : 'false' }}" title="Institusional Modern">Modern</button>
    <button type="button" class="theme-toggle-btn" data-theme-option="b" aria-pressed="{{ $theme === 'b' ? 'true' : 'false' }}" title="Cinematic Bold">Bold</button>
</div>
<script>
    (function () {
        var group = document.currentScript.previousElementSibling;
        if (!group || !group.classList.contains('theme-toggle')) return;

        var buttons = group.querySelectorAll('.theme-toggle-btn');
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var value = btn.getAttribute('data-theme-option');
                document.documentElement.setAttribute('data-theme', value);
                document.cookie = 'theme=' + value + ';path=/;max-age=31536000;samesite=Lax';
                buttons.forEach(function (b) {
                    b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
                });
            });
        });
    })();
</script>
