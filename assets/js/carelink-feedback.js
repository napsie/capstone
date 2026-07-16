(function () {
    'use strict';
    function close() {
        document.getElementById('carelinkResultModal')?.remove();
    }

    window.showCarelinkResult = function (message, success = true) {
        close();
        const color = success ? '#10b981' : '#ef4444';
        const modal = document.createElement('div');
        modal.id = 'carelinkResultModal';
        modal.setAttribute('role', 'alertdialog');
        modal.setAttribute('aria-modal', 'true');
        modal.style.cssText = 'position:fixed;inset:0;z-index:10050;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(15,23,42,.58);backdrop-filter:blur(3px);';
        modal.innerHTML = `<div class="carelink-result-dialog"><div class="carelink-result-icon"></div><h3>${success ? 'Success' : 'Unable to complete'}</h3><p></p><button type="button" class="carelink-result-ok">OK</button></div>`;
        const dialog = modal.firstElementChild;
        dialog.style.cssText = 'width:min(380px,100%);padding:28px 26px 24px;border-radius:16px;background:#fff;text-align:center;box-shadow:0 20px 60px rgba(15,23,42,.28);font-family:Inter,Segoe UI,system-ui,sans-serif;';
        const icon = dialog.querySelector('.carelink-result-icon');
        icon.style.cssText = `width:54px;height:54px;margin:0 auto 12px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.35rem;background:${success ? 'rgba(16,185,129,.12)' : 'rgba(239,68,68,.12)'};color:${color};`;
        icon.innerHTML = `<i class="fas fa-${success ? 'check' : 'exclamation'}"></i>`;
        dialog.querySelector('h3').style.cssText = 'margin:0 0 8px;color:#0f172a;font-size:1.05rem;';
        const text = dialog.querySelector('p');
        text.textContent = String(message || '');
        text.style.cssText = 'margin:0 auto 20px;color:#64748b;font-size:.86rem;line-height:1.5;max-width:310px;';
        const button = dialog.querySelector('button');
        button.style.cssText = `min-width:108px;border:0;border-radius:8px;padding:9px 18px;background:${color};color:#fff;font-weight:700;cursor:pointer;`;
        button.onclick = close;
        document.body.appendChild(modal);
        window.setTimeout(close, 5000);
    };

    function confirmDialog(message, onConfirm) {
        close();
        const modal = document.createElement('div');
        modal.id = 'carelinkConfirmModal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.style.cssText = 'position:fixed;inset:0;z-index:10051;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(15,23,42,.58);backdrop-filter:blur(3px);';
        modal.innerHTML = '<div class="carelink-result-dialog"><div class="carelink-result-icon">!</div><h3>Confirm action</h3><p></p><div style="display:flex;gap:10px;justify-content:center"><button type="button" class="carelink-confirm-cancel">Cancel</button><button type="button" class="carelink-confirm-ok">Continue</button></div></div>';
        const dialog = modal.firstElementChild;
        dialog.style.cssText = 'width:min(400px,100%);padding:28px 26px 24px;border-radius:16px;background:#fff;text-align:center;box-shadow:0 20px 60px rgba(15,23,42,.28);font-family:Inter,Segoe UI,system-ui,sans-serif;';
        const icon = dialog.querySelector('.carelink-result-icon');
        icon.style.cssText = 'width:54px;height:54px;margin:0 auto 12px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.35rem;background:rgba(245,158,11,.14);color:#d97706;font-weight:800;';
        dialog.querySelector('h3').style.cssText = 'margin:0 0 8px;color:#0f172a;font-size:1.05rem;';
        const text = dialog.querySelector('p'); text.textContent = String(message || '');
        text.style.cssText = 'margin:0 auto 20px;color:#64748b;font-size:.86rem;line-height:1.5;max-width:320px;';
        dialog.querySelectorAll('button').forEach(b => b.style.cssText = 'border:0;border-radius:8px;padding:9px 18px;font-weight:700;cursor:pointer;');
        dialog.querySelector('.carelink-confirm-cancel').style.background = '#e2e8f0';
        dialog.querySelector('.carelink-confirm-cancel').style.color = '#334155';
        dialog.querySelector('.carelink-confirm-ok').style.background = '#2563eb';
        dialog.querySelector('.carelink-confirm-ok').style.color = '#fff';
        dialog.querySelector('.carelink-confirm-cancel').onclick = () => modal.remove();
        dialog.querySelector('.carelink-confirm-ok').onclick = () => { modal.remove(); if (typeof onConfirm === 'function') onConfirm(); };
        document.body.appendChild(modal);
    }
    window.showCarelinkConfirm = confirmDialog;
    window.confirmCarelinkSubmit = function (form, message) {
        confirmDialog(message, () => form.submit());
        return false;
    };
})();
