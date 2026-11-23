/* ========================================================
   FixTrack Campus - Shared client-side behaviour
   Vanilla JavaScript, no framework dependencies.
   ======================================================== */
(function () {
    'use strict';

    /* ---------- Mobile navigation ---------- */
    var toggle = document.getElementById('navToggle');
    var menu = document.getElementById('navMenu');
    if (toggle && menu) {
        toggle.addEventListener('click', function () {
            var open = menu.classList.toggle('show');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (event) {
            if (!menu.contains(event.target) && !toggle.contains(event.target)) {
                menu.classList.remove('show');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    /* ---------- Confirmation prompts for destructive actions ---------- */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form.tagName !== 'FORM') return;
        var message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
            return;
        }
        // Prevent accidental double submission of state-changing forms.
        var submit = form.querySelector('[type="submit"]');
        if (submit && !form.dataset.noDisable) {
            submit.disabled = true;
            window.setTimeout(function () { submit.disabled = false; }, 4000);
        }
    });

    document.addEventListener('click', function (event) {
        var confirmEl = event.target.closest('[data-confirm]');
        if (confirmEl && (confirmEl.tagName === 'A' || (confirmEl.tagName === 'BUTTON' && confirmEl.type === 'submit'))) {
            var message = confirmEl.getAttribute('data-confirm');
            if (message && !window.confirm(message)) event.preventDefault();
        }
    });

    /* ---------- Auto-dismiss non-error alerts ---------- */
    window.setTimeout(function () {
        document.querySelectorAll('.alert-success, .alert-info, .alert-warning').forEach(function (alert) {
            if (alert.classList.contains('alert-auto')) {
                alert.style.transition = 'opacity .4s ease';
                alert.style.opacity = '0';
                window.setTimeout(function () { alert.remove(); }, 400);
            }
        });
    }, 8000);

    /* ---------- Modal helpers ---------- */
    window.openModal = function (id) {
        var modal = document.getElementById(id);
        if (modal) modal.classList.add('show');
    };
    window.closeModal = function (id) {
        var modal = document.getElementById(id);
        if (modal) modal.classList.remove('show');
    };
    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-modal-open]');
        if (opener) {
            event.preventDefault();
            window.openModal(opener.getAttribute('data-modal-open'));
        }
        if (event.target.classList && event.target.classList.contains('modal-backdrop')) {
            event.target.classList.remove('show');
        }
        var closer = event.target.closest('[data-modal-close]');
        if (closer) {
            event.preventDefault();
            var backdrop = closer.closest('.modal-backdrop');
            if (backdrop) backdrop.classList.remove('show');
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop.show').forEach(function (m) { m.classList.remove('show'); });
        }
    });

    /* ---------- Image upload dropzone with live previews ---------- */
    var MAX_FILES = 5;
    var MAX_BYTES = 5 * 1024 * 1024;
    var ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

    document.querySelectorAll('.dropzone-box').forEach(function (zone) {
        var input = zone.querySelector('input[type="file"]');
        if (!input) return;
        var preview = zone.parentElement.querySelector('.preview-container');
        var errorBox = zone.parentElement.querySelector('[data-upload-error]');
        var store = (typeof DataTransfer !== 'undefined') ? new DataTransfer() : null;

        // Keep a copy of initially selected files so re-rendering does not lose them.
        var selected = [];

        function reportError(msg) {
            if (errorBox) { errorBox.textContent = msg; errorBox.classList.remove('d-none'); }
        }
        function clearError() {
            if (errorBox) { errorBox.textContent = ''; errorBox.classList.add('d-none'); }
        }

        function render() {
            if (!preview) return;
            preview.innerHTML = '';
            selected.forEach(function (file, index) {
                var thumb = document.createElement('div');
                thumb.className = 'image-preview-thumb';
                var img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.alt = 'Preview of ' + file.name;
                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'remove-img-btn';
                remove.setAttribute('aria-label', 'Remove ' + file.name);
                remove.innerHTML = '&times;';
                remove.addEventListener('click', function () {
                    selected.splice(index, 1);
                    syncInput();
                    render();
                });
                thumb.appendChild(img);
                thumb.appendChild(remove);
                preview.appendChild(thumb);
            });
        }

        function syncInput() {
            if (!store) return;
            store.items.clear();
            selected.forEach(function (file) { store.items.add(file); });
            input.files = store.files;
        }

        input.addEventListener('change', function () {
            clearError();
            var incoming = Array.prototype.slice.call(input.files || []);
            incoming.forEach(function (file) {
                if (ALLOWED.indexOf(file.type) === -1) {
                    reportError('Only JPG, PNG and WebP images are allowed.');
                    return;
                }
                if (file.size > MAX_BYTES) {
                    reportError('Each image must be smaller than 5 MB.');
                    return;
                }
                if (selected.length >= MAX_FILES) {
                    reportError('You can attach a maximum of ' + MAX_FILES + ' images.');
                    return;
                }
                selected.push(file);
            });
            syncInput();
            render();
        });

        zone.addEventListener('click', function (e) {
            if (e.target !== input) input.click();
        });

        ['dragenter', 'dragover'].forEach(function (type) {
            zone.addEventListener(type, function (e) { e.preventDefault(); zone.classList.add('dragover'); });
        });
        ['dragleave', 'drop'].forEach(function (type) {
            zone.addEventListener(type, function (e) { e.preventDefault(); zone.classList.remove('dragover'); });
        });
        zone.addEventListener('drop', function (e) {
            var dropped = Array.prototype.slice.call(e.dataTransfer.files || []);
            if (!dropped.length) return;
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        });
    });

    /* ---------- Duplicate-report warning ( AJAX ) ---------- */
    var titleInput = document.getElementById('issue_title');
    if (titleInput) {
        var timer;
        var warning = document.getElementById('duplicate-warning');
        titleInput.addEventListener('input', function () {
            window.clearTimeout(timer);
            var value = titleInput.value.trim();
            if (value.length < 8) { if (warning) warning.classList.add('d-none'); return; }
            timer = window.setTimeout(function () {
                fetch('api/duplicate-check.php?q=' + encodeURIComponent(value), { headers: { 'X-Requested-With': 'fetch' } })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) {
                        if (!warning || !data) return;
                        if (data.duplicates && data.duplicates.length) {
                            warning.innerHTML = '<i class="fas fa-triangle-exclamation mr-2"></i><strong>Possible duplicate:</strong> '
                                + data.duplicates.map(function (d) {
                                    return '<a href="' + d.url + '" target="_blank" rel="noopener">' + d.code + ' &ndash; ' + d.title + '</a>';
                                }).join(', ') + '. Please check before submitting.';
                            warning.classList.remove('d-none');
                        } else {
                            warning.classList.add('d-none');
                        }
                    })
                    .catch(function () { /* silently ignore network errors */ });
            }, 500);
        });
    }

    /* ---------- Live category/facility dependent hints ---------- */
    var categorySelect = document.getElementById('category_id');
    if (categorySelect) {
        categorySelect.addEventListener('change', function () {
            var opt = categorySelect.options[categorySelect.selectedIndex];
            var hint = document.getElementById('sla-hint');
            var priority = document.getElementById('priority');
            if (hint && opt && opt.dataset.sla) {
                hint.textContent = 'Target response time for this category: ' + opt.dataset.sla + ' hours.';
            }
            if (priority && opt && opt.dataset.priority) {
                // Only auto-select if the user has not deliberately changed it.
                if (!priority.dataset.touched) priority.value = opt.dataset.priority;
            }
        });
        categorySelect.addEventListener('change', function () { /* placeholder for future rules */ });
        var prio = document.getElementById('priority');
        if (prio) prio.addEventListener('change', function () { prio.dataset.touched = '1'; });
    }

    /* ---------- Simple client-side table search ---------- */
    document.querySelectorAll('[data-table-search]').forEach(function (input) {
        var target = document.getElementById(input.getAttribute('data-table-search'));
        if (!target) return;
        input.addEventListener('input', function () {
            var term = input.value.toLowerCase().trim();
            target.querySelectorAll('tbody tr').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().indexOf(term) !== -1 ? '' : 'none';
            });
        });
    });
})();
