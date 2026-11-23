/* ========================================================
   FixTrack Campus - Client-side form validation
   Enhances UX only; the PHP backend always re-validates.
   ======================================================== */
(function () {
    'use strict';

    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

    function fieldValue(field) {
        if (field.type === 'checkbox' || field.type === 'radio') {
            var group = document.querySelectorAll('input[name="' + field.name + '"]');
            for (var i = 0; i < group.length; i++) if (group[i].checked) return group[i].value;
            return '';
        }
        return (field.value || '').trim();
    }

    function validateField(field) {
        var value = fieldValue(field);
        var message = '';
        var label = field.getAttribute('data-label') || field.name.replace(/[_-]/g, ' ');

        if (field.hasAttribute('required') && !value) {
            message = field.dataset.errorRequired || (label.charAt(0).toUpperCase() + label.slice(1) + ' is required.');
        } else if (value && (field.type === 'email' || field.dataset.type === 'email') && !EMAIL_RE.test(value)) {
            message = field.dataset.errorEmail || 'Enter a valid email address.';
        } else if (value && field.hasAttribute('data-min') && value.length < parseInt(field.dataset.min, 10)) {
            message = field.dataset.errorMin || (label + ' must be at least ' + field.dataset.min + ' characters.');
        } else if (value && field.hasAttribute('data-max') && value.length > parseInt(field.dataset.max, 10)) {
            message = field.dataset.errorMax || (label + ' must not exceed ' + field.dataset.max + ' characters.');
        } else if (value && field.dataset.rule === 'password' && !/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(value)) {
            message = field.dataset.errorPassword || 'Password must be at least 8 characters with upper and lower case letters and a number.';
        } else if (value && field.dataset.match) {
            var other = document.querySelector(field.dataset.match);
            if (other && other.value !== value) {
                message = field.dataset.errorMatch || 'The two values do not match.';
            }
        } else if (value && field.pattern) {
            try {
                if (!new RegExp(field.pattern).test(value)) {
                    message = field.dataset.errorPattern || 'The value has an invalid format.';
                }
            } catch (err) { /* ignore invalid author patterns */ }
        }

        var wrapper = field.closest('.form-group') || field.parentElement;
        var feedback = wrapper ? wrapper.querySelector('.invalid-feedback') : null;
        if (!feedback && wrapper) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            wrapper.appendChild(feedback);
        }
        field.classList.toggle('is-invalid', !!message);
        field.setAttribute('aria-invalid', message ? 'true' : 'false');
        if (feedback) feedback.textContent = message;
        return !message;
    }

    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        var fields = Array.prototype.slice.call(form.querySelectorAll('[required], [data-min], [data-max], [data-match], [data-rule], [data-type="email"]'));

        fields.forEach(function (field) {
            var eventName = (field.tagName === 'SELECT' || field.type === 'file' || field.type === 'checkbox') ? 'change' : 'blur';
            field.addEventListener(eventName, function () { validateField(field); });
            field.addEventListener('input', function () {
                if (field.classList.contains('is-invalid')) validateField(field);
            });
        });

        form.addEventListener('submit', function (event) {
            var valid = true;
            var firstInvalid = null;
            fields.forEach(function (field) {
                if (!validateField(field)) {
                    valid = false;
                    if (!firstInvalid) firstInvalid = field;
                }
            });
            if (!valid) {
                event.preventDefault();
                event.stopPropagation();
                if (firstInvalid) firstInvalid.focus();
            }
        });
    });
})();
