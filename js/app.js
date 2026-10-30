/**
 * app.js – global dynamic UI logic
 * Cart badge + add-to-cart, toasts, modals, qty steppers, product gallery,
 * cart page actions, checkout (M-Pesa) flow, small UI helpers.
 */
(function () {
    'use strict';

    const meta = (name) => (document.querySelector(`meta[name="${name}"]`) || {}).content || '';
    const BASE = meta('base-url');
    const CSRF = meta('csrf-token');
    const $  = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /* ------------------------------------------------------------ *
     * Fetch helper (always sends the CSRF token on POST)
     * ------------------------------------------------------------ */
    async function api(path, { method = 'GET', data = null } = {}) {
        const opts = { method, headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'fetch' }, credentials: 'same-origin' };
        if (data) {
            const body = new URLSearchParams();
            Object.entries(data).forEach(([k, v]) => body.append(k, v));
            opts.body = body;
        }
        const res = await fetch(BASE + path, opts);
        let json = {};
        try { json = await res.json(); } catch (_) { /* non-JSON error page */ }
        if (!res.ok && json.success === undefined) json = { success: false, message: 'Something went wrong. Please try again.' };
        json._status = res.status;
        return json;
    }

    /* ------------------------------------------------------------ *
     * Toasts
     * ------------------------------------------------------------ */
    function toast(message, type = 'success') {
        const root = $('#toast-root');
        if (!root) return;
        const el = document.createElement('div');
        el.className = 'toast' + (type === 'error' ? ' is-error' : '');
        el.setAttribute('role', 'status');
        el.innerHTML = `<i class="fa-solid ${type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check text-brand-500'}"></i><span>${esc(message)}</span>`;
        root.appendChild(el);
        setTimeout(() => { el.classList.add('is-leaving'); setTimeout(() => el.remove(), 260); }, 3200);
    }

    /* ------------------------------------------------------------ *
     * Modal (confirm dialogs, payment waiting, etc.)
     * ------------------------------------------------------------ */
    function openModal(html, { dismissible = true } = {}) {
        const root = $('#modal-root');
        const wrap = document.createElement('div');
        wrap.className = 'modal-backdrop';
        wrap.innerHTML = `<div class="modal-panel" role="dialog" aria-modal="true">${html}</div>`;
        root.appendChild(wrap);
        const prevFocus = document.activeElement;
        const close = () => { wrap.remove(); document.removeEventListener('keydown', onKey); if (prevFocus && prevFocus.focus) prevFocus.focus(); };
        const onKey = (e) => { if (e.key === 'Escape' && dismissible) close(); };
        document.addEventListener('keydown', onKey);
        if (dismissible) wrap.addEventListener('click', (e) => { if (e.target === wrap) close(); });
        const first = $('button, a, input', wrap); if (first) first.focus();
        return { el: wrap.firstElementChild, close };
    }

    function confirmModal({ title = 'Are you sure?', message = '', confirmText = 'Confirm', danger = false } = {}) {
        return new Promise((resolve) => {
            const m = openModal(`
                <h3 class="text-lg font-bold text-gray-900">${esc(title)}</h3>
                <p class="text-sm text-gray-600 mt-2">${esc(message)}</p>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" data-no class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-100 hover:bg-gray-200">Cancel</button>
                    <button type="button" data-yes class="px-4 py-2 rounded-lg text-sm font-semibold text-white ${danger ? 'bg-red-600 hover:bg-red-700' : 'bg-brand-600 hover:bg-brand-700'}">${esc(confirmText)}</button>
                </div>`);
            $('[data-no]', m.el).onclick  = () => { m.close(); resolve(false); };
            $('[data-yes]', m.el).onclick = () => { m.close(); resolve(true); };
        });
    }

    window.AgriMarket = { api, toast, openModal, confirmModal };

    /* ------------------------------------------------------------ *
     * Cart badge
     * ------------------------------------------------------------ */
    function setCartCount(n) {
        $$('[data-cart-count]').forEach((b) => {
            b.textContent = n;
            b.classList.toggle('hidden', n < 1);
            b.classList.remove('cart-bump'); void b.offsetWidth; b.classList.add('cart-bump');
        });
        $$('[data-cart-heading-count]').forEach((h) => { h.textContent = n; });
    }

    /* ------------------------------------------------------------ *
     * Add to cart (delegated – works for AJAX-rendered cards too)
     * ------------------------------------------------------------ */
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-add-to-cart]');
        if (!btn) return;
        e.preventDefault();
        if (btn.disabled) return;

        const srcSel = btn.dataset.qtySource;
        const qty = srcSel ? parseInt(($(srcSel) || {}).textContent, 10) || 1 : 1;
        const label = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span>';
        const res = await api('/api/cart.php', { method: 'POST', data: { action: 'add', product_id: btn.dataset.productId, qty } });
        btn.disabled = false;
        btn.innerHTML = label;

        if (!res.success) { toast(res.message || 'Could not add to cart.', 'error'); return; }
        setCartCount(res.count);
        if (btn.hasAttribute('data-buy-now')) { window.location.href = BASE + '/cart.php'; return; }
        toast(res.message || `Added ${btn.dataset.name} to your cart.`);
    });

    /* ------------------------------------------------------------ *
     * Product page: qty stepper + gallery
     * ------------------------------------------------------------ */
    document.addEventListener('click', (e) => {
        const step = e.target.closest('[data-qty-step]');
        if (step) {
            const out = $('#product-qty');
            const max = parseInt(out.dataset.max, 10) || 99;
            out.textContent = Math.min(max, Math.max(1, (parseInt(out.textContent, 10) || 1) + parseInt(step.dataset.qtyStep, 10)));
        }
        const thumb = e.target.closest('.gallery-thumb');
        if (thumb) {
            const g = thumb.closest('[data-gallery]');
            $$('.gallery-thumb', g).forEach((t) => t.classList.toggle('is-active', t === thumb));
            const img = $('[data-gallery-main] img', g);
            img.style.transformOrigin = thumb.dataset.origin;
            img.style.transform = `scale(${thumb.dataset.scale})`;
        }
    });
    $$('[data-gallery-main]').forEach((box) => {
        const img = $('img', box);
        box.addEventListener('mousemove', (e) => {
            const r = box.getBoundingClientRect();
            img.style.transformOrigin = `${((e.clientX - r.left) / r.width) * 100}% ${((e.clientY - r.top) / r.height) * 100}%`;
            img.style.transform = 'scale(1.8)';
        });
        box.addEventListener('mouseleave', () => {
            const active = $('.gallery-thumb.is-active', box.closest('[data-gallery]'));
            img.style.transformOrigin = active ? active.dataset.origin : '50% 50%';
            img.style.transform = `scale(${active ? active.dataset.scale : 1})`;
        });
    });

    /* ------------------------------------------------------------ *
     * Cart page
     * ------------------------------------------------------------ */
    function applyCartSummary(res) {
        setCartCount(res.count);
        const set = (id, v) => { const n = $(id); if (n) n.textContent = v; };
        set('#sum-subtotal', res.subtotal_fmt); set('#sum-delivery', res.delivery_fmt); set('#sum-total', res.total_fmt);
        (res.lines || []).forEach((l) => {
            const row = $(`[data-cart-line="${l.id}"]`);
            if (!row) return;
            $('[data-line-qty]', row).textContent = l.qty;
            $('[data-line-total]', row).textContent = l.line_total_fmt;
        });
        if (res.count < 1) { $('#cart-layout')?.classList.add('hidden'); $('#cart-empty')?.classList.remove('hidden'); }
    }

    document.addEventListener('click', async (e) => {
        const row = e.target.closest('[data-cart-line]');
        const pid = row && row.dataset.cartLine;

        const stepBtn = e.target.closest('[data-line-step]');
        if (stepBtn && row) {
            const qty = (parseInt($('[data-line-qty]', row).textContent, 10) || 1) + parseInt(stepBtn.dataset.lineStep, 10);
            if (qty < 1) return;
            const res = await api('/api/cart.php', { method: 'POST', data: { action: 'update', product_id: pid, qty } });
            if (!res.success) return toast(res.message, 'error');
            applyCartSummary(res);
            if (res.message && /Only/.test(res.message)) toast(res.message, 'error');
            return;
        }

        if (e.target.closest('[data-remove-line]') && row) {
            const ok = await confirmModal({ title: 'Remove item?', message: `Remove ${row.dataset.name} from your cart?`, confirmText: 'Remove', danger: true });
            if (!ok) return;
            const res = await api('/api/cart.php', { method: 'POST', data: { action: 'remove', product_id: pid } });
            if (!res.success) return toast(res.message, 'error');
            row.remove();
            applyCartSummary(res);
            toast('Item removed from your cart.');
            return;
        }

        if (e.target.closest('[data-clear-cart]')) {
            const ok = await confirmModal({ title: 'Clear your cart?', message: 'All items will be removed.', confirmText: 'Clear cart', danger: true });
            if (!ok) return;
            const res = await api('/api/cart.php', { method: 'POST', data: { action: 'clear' } });
            if (res.success) { $$('[data-cart-line]').forEach((r) => r.remove()); applyCartSummary(res); }
        }
    });

    /* ------------------------------------------------------------ *
     * Checkout – create order, send STK push, poll for payment
     * ------------------------------------------------------------ */
    const checkoutForm = $('#checkout-form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            $$('.field-error', checkoutForm).forEach((p) => { p.textContent = ''; });
            const errBox = $('#checkout-error'); errBox.classList.add('hidden');
            const submit = $('#checkout-submit'); const label = submit.textContent;
            submit.disabled = true; submit.innerHTML = '<span class="spinner"></span> Sending M-Pesa prompt…';

            const res = await api('/api/checkout.php', { method: 'POST', data: Object.fromEntries(new FormData(checkoutForm)) });
            if (!res.success) {
                submit.disabled = false; submit.textContent = label;
                Object.entries(res.errors || {}).forEach(([k, v]) => { const p = $(`[data-error-for="${k}"]`); if (p) p.textContent = v; });
                if (res.login_url) { window.location.href = res.login_url; return; }
                errBox.textContent = res.message || 'Checkout failed.'; errBox.classList.remove('hidden');
                return;
            }

            setCartCount(0);
            const modal = openModal(`
                <div class="text-center space-y-3">
                    <div class="w-14 h-14 mx-auto rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-2xl"><i class="fa-solid fa-mobile-screen"></i></div>
                    <h3 class="text-lg font-bold text-gray-900">Check your phone</h3>
                    <p class="text-sm text-gray-600">${esc(res.message)}</p>
                    <p class="text-xs text-gray-400 flex items-center justify-center gap-2"><span class="spinner spinner-dark"></span> Waiting for confirmation…</p>
                </div>`, { dismissible: false });
            pollPayment(res.status_url, res.redirect, modal);
        });
    }

    function pollPayment(statusUrl, redirect, modal) {
        let tries = 0;
        const tick = async () => {
            tries++;
            try {
                const r = await fetch(statusUrl, { credentials: 'same-origin' });
                const j = await r.json();
                if (j.success && j.payment_status !== 'pending') { modal.close(); window.location.href = j.redirect || redirect; return; }
            } catch (_) { /* keep polling */ }
            if (tries >= 40) { modal.close(); window.location.href = redirect; return; }   // ~2 min, then show the status page
            setTimeout(tick, 3000);
        };
        setTimeout(tick, 3000);
    }

    // Order confirmation page still pending → keep polling until paid / failed
    const orderView = $('#order-view');
    if (orderView && orderView.dataset.payment === 'pending') {
        const id = orderView.dataset.orderId;
        const poll = async () => {
            try {
                const j = await (await fetch(`${BASE}/api/checkout.php?action=status&order=${id}`, { credentials: 'same-origin' })).json();
                if (j.success && j.payment_status !== 'pending') { window.location.reload(); return; }
            } catch (_) {}
            setTimeout(poll, 3000);
        };
        setTimeout(poll, 3000);
    }

    /* ------------------------------------------------------------ *
     * Generic helpers: confirm-on-submit forms, dropdowns, mobile nav,
     * register role toggle, image upload preview
     * ------------------------------------------------------------ */
    document.addEventListener('submit', async (e) => {
        const form = e.target.closest('form[data-confirm]');
        if (!form || form.dataset.confirmed) return;
        e.preventDefault();
        const ok = await confirmModal({ title: 'Please confirm', message: form.dataset.confirm, confirmText: 'Delete', danger: true });
        if (ok) { form.dataset.confirmed = '1'; form.submit(); }
    });

    document.addEventListener('click', (e) => {
        const tgl = e.target.closest('[data-dropdown-toggle]');
        $$('[data-dropdown-menu]').forEach((m) => { if (!tgl || m !== tgl.parentElement.querySelector('[data-dropdown-menu]')) m.classList.add('hidden'); });
        if (tgl) tgl.parentElement.querySelector('[data-dropdown-menu]').classList.toggle('hidden');

        const nav = e.target.closest('[data-nav-toggle]');
        if (nav) {
            const menu = $('[data-mobile-menu]');
            const open = menu.classList.toggle('hidden') === false;
            nav.setAttribute('aria-expanded', open);
            nav.firstElementChild.className = open ? 'fa-solid fa-xmark text-xl' : 'fa-solid fa-bars text-xl';
        }
    });

    const roleSel = $('[data-role-select]');
    if (roleSel) {
        const fields = $('[data-farmer-fields]');
        roleSel.addEventListener('change', () => fields.classList.toggle('hidden', roleSel.value !== 'farmer'));
    }

    const imgInput = $('[data-image-input]');
    if (imgInput) {
        imgInput.addEventListener('change', () => {
            const prev = $('[data-image-preview]'); const f = imgInput.files[0];
            if (!f) { prev.classList.add('hidden'); return; }
            prev.src = URL.createObjectURL(f); prev.classList.remove('hidden');
        });
    }
})();
