/* Render QR codes into any element carrying data-qr-url. */
(function () {
    'use strict';
    if (typeof QRCode === 'undefined') return;
    document.querySelectorAll('[data-qr-url]').forEach(function (el) {
        var targetUrl = el.getAttribute('data-qr-url');
        if (!targetUrl) return;
        el.innerHTML = '';
        try {
            new QRCode(el, {
                text: targetUrl,
                width: parseInt(el.dataset.qrSize || '180', 10),
                height: parseInt(el.dataset.qrSize || '180', 10),
                colorDark: '#0f172a',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        } catch (err) {
            el.textContent = 'QR unavailable';
        }
    });
})();
