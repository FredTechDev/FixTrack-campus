/* FixTrack Campus - shared interactions (vanilla JS) */
(function () {
    'use strict';

    /* Mobile menu */
    var toggle = document.getElementById('navToggle');
    var menu = document.getElementById('navMenu');
    if (toggle && menu) {
        toggle.addEventListener('click', function () {
            var open = menu.classList.toggle('show');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!menu.contains(e.target) && !toggle.contains(e.target)) {
                menu.classList.remove('show');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    /* Confirmations + double-submit guard */
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-confirm]');
        if (el && (el.tagName === 'A' || (el.tagName === 'BUTTON' && el.type === 'submit'))) {
            if (!window.confirm(el.getAttribute('data-confirm'))) e.preventDefault();
        }
    });
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) { e.preventDefault(); return; }
        var submit = form.querySelector('[type="submit"]');
        if (submit) {
            submit.disabled = true;
            window.setTimeout(function () { submit.disabled = false; }, 4000);
        }
    });

    /* Photo previews */
    var ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];
    document.querySelectorAll('.dropzone-box').forEach(function (zone) {
        var input = zone.querySelector('input[type="file"]');
        if (!input) return;
        var preview = zone.parentElement.querySelector('.preview-container');
        var errorBox = zone.parentElement.querySelector('[data-upload-error]');
        var selected = [];
        var store = typeof DataTransfer !== 'undefined' ? new DataTransfer() : null;

        function showError(msg) {
            if (!errorBox) return;
            errorBox.textContent = msg;
            errorBox.classList.remove('d-none');
        }
        function render() {
            if (!preview) return;
            preview.innerHTML = '';
            selected.forEach(function (file, i) {
                var thumb = document.createElement('div');
                thumb.className = 'image-preview-thumb';
                var img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.alt = file.name;
                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'remove-img-btn';
                remove.innerHTML = '&times;';
                remove.onclick = function () { selected.splice(i, 1); sync(); render(); };
                thumb.appendChild(img);
                thumb.appendChild(remove);
                preview.appendChild(thumb);
            });
        }
        function sync() {
            if (!store) return;
            store.items.clear();
            selected.forEach(function (f) { store.items.add(f); });
            input.files = store.files;
        }
        input.addEventListener('change', function () {
            var error = '';
            Array.prototype.slice.call(input.files || []).forEach(function (file) {
                if (ALLOWED.indexOf(file.type) === -1) error = 'Only JPG, PNG and WebP are allowed.';
                else if (file.size > 5 * 1024 * 1024) error = 'Each image must be under 5 MB.';
                else if (selected.length >= 5) error = 'Maximum 5 images.';
                else selected.push(file);
            });
            if (error) showError(error);
            else if (errorBox) errorBox.classList.add('d-none');
            sync();
            render();
        });
        zone.addEventListener('click', function () { input.click(); });
        zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.classList.add('dragover'); });
        zone.addEventListener('dragleave', function () { zone.classList.remove('dragover'); });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('dragover');
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        });
    });

    /* Duplicate-report hint */
    var title = document.getElementById('issue_title');
    if (title) {
        var warning = document.getElementById('duplicate-warning');
        var timer;
        title.addEventListener('input', function () {
            window.clearTimeout(timer);
            if (title.value.trim().length < 8) { warning.classList.add('d-none'); return; }
            timer = window.setTimeout(function () {
                fetch('api/duplicate-check.php?q=' + encodeURIComponent(title.value.trim()))
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) {
                        if (!data || !data.duplicates.length) { warning.classList.add('d-none'); return; }
                        warning.innerHTML = '<strong>Possible duplicate:</strong> ' + data.duplicates.map(function (d) {
                            return '<a href="' + d.url + '" target="_blank" rel="noopener">' + d.code + '</a>';
                        }).join(', ');
                        warning.classList.remove('d-none');
                    })
                    .catch(function () {});
            }, 500);
        });
    }

    /* Category -> default priority / SLA hint */
    var category = document.getElementById('category_id');
    if (category) category.addEventListener('change', function () {
        var opt = category.options[category.selectedIndex];
        var hint = document.getElementById('sla-hint');
        var priority = document.getElementById('priority');
        if (hint && opt.dataset.sla) hint.textContent = 'Target response: ' + opt.dataset.sla + ' hours.';
        if (priority && opt.dataset.priority && !priority.dataset.touched) priority.value = opt.dataset.priority;
    });
    var priorityField = document.getElementById('priority');
    if (priorityField) priorityField.addEventListener('change', function () { priorityField.dataset.touched = '1'; });
})();
