window.tgTrapDialog = (event, dialog) => {
    if (event.key !== 'Tab') return;
    const nodes = [...dialog.querySelectorAll('a[href], button, input, textarea, select, [tabindex="0"]')].filter(el => !el.matches(':disabled') && el.getClientRects().length);
    const first = nodes[0], last = nodes[nodes.length - 1];
    if (!first) { event.preventDefault(); dialog.focus(); return; }
    if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog)) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && (document.activeElement === last || document.activeElement === dialog)) { event.preventDefault(); first.focus(); }
};
// Livewire owns Alpine. Keep request feedback independent of response payloads.
let installed = false;
function installFeedback() {
    if (installed || !window.Livewire) return;
    installed = true;
    const dialog = document.getElementById('request-feedback');
    dialog.addEventListener('keydown', event => window.tgTrapDialog(event, dialog));
    let uncertain = false;
    let opener;
    function show(message, unknown = true) {
        uncertain = uncertain || unknown;
        dialog.querySelector('h2').textContent = uncertain ? 'Status belum pasti' : 'Permintaan belum berhasil';
        dialog.querySelector('#request-feedback-description').textContent = message;
        dialog.querySelector('[data-dismiss-feedback]').hidden = uncertain;
        if (!dialog.open) { opener = document.activeElement; dialog.showModal(); }
        dialog.querySelector('[data-check-status]').focus();
    }
    dialog.addEventListener('cancel', event => { if (uncertain) event.preventDefault(); });
    dialog.querySelector('[data-dismiss-feedback]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => opener?.focus());
    dialog.querySelector('[data-check-status]').addEventListener('click', () => {
        window.location.assign(window.location.pathname + window.location.search);
    });
    window.Livewire.interceptMessage(({ cancel }) => { if (uncertain) cancel(); });
    window.Livewire.interceptRequest(({ onSend, onFailure, onError, onFinish }) => {
        let timer;
        onSend(() => { timer = setTimeout(() => show('Respons server belum diterima. Jangan ulangi tindakan sebelum memeriksa status.'), 30000); });
        onFinish(() => clearTimeout(timer));
        onFailure(() => show('Koneksi terputus. Tindakan mungkin telah diproses oleh server.'));
        onError(({ response, preventDefault }) => {
            preventDefault();
            const expired = response.status === 419 || response.status === 401;
            show(expired ? 'Sesi berakhir. Muat ulang halaman dan masuk kembali.' : 'Hasil permintaan belum dapat dipastikan. Periksa status sebelum mencoba kembali.', !expired);
        });
    });
}
if (window.Livewire) installFeedback();
else document.addEventListener('livewire:init', installFeedback, { once: true });
