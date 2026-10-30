/**
 * catalogue.js – AJAX filtering, sorting & pagination for catalogue.php
 * Talks to /api/produce.php; falls back to normal GET navigation if JS is off.
 */
(function () {
    'use strict';

    const form = document.getElementById('filter-form');
    if (!form) return;

    const grid       = document.getElementById('catalogue-grid');
    const pagination = document.getElementById('catalogue-pagination');
    const summary    = document.getElementById('result-summary');
    const priceRange = document.getElementById('max_price');
    const priceLabel = document.getElementById('price-label');
    const catAll     = form.querySelector('[data-cat-all]');
    const catBoxes   = Array.from(form.querySelectorAll('input[name="cat[]"]'));
    const BASE       = (document.querySelector('meta[name="base-url"]') || {}).content || '';
    const CUR        = (document.querySelector('meta[name="currency"]') || {}).content || 'KSh';
    const PRICE_MAX  = parseInt(priceRange.max, 10);

    let timer = null;
    let controller = null;

    const fmt = (n) => `${CUR} ${Number(n).toLocaleString('en-KE')}`;

    function params(page = 1) {
        const p = new URLSearchParams();
        const q = form.elements.q.value.trim();
        if (q) p.set('q', q);
        catBoxes.filter((b) => b.checked).forEach((b) => p.append('cat[]', b.value));
        if (form.elements.county.value) p.set('county', form.elements.county.value);
        if (parseInt(priceRange.value, 10) < PRICE_MAX) p.set('max_price', priceRange.value);
        if (form.elements.sort.value !== 'popularity') p.set('sort', form.elements.sort.value);
        if (page > 1) p.set('page', page);
        return p;
    }

    async function load(page = 1, { scroll = false } = {}) {
        const p = params(page);
        if (controller) controller.abort();
        controller = new AbortController();
        grid.classList.add('is-loading');

        try {
            const res  = await fetch(`${BASE}/api/produce.php?${p}`, { signal: controller.signal, credentials: 'same-origin' });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to load');
            grid.innerHTML       = data.html;
            pagination.innerHTML = data.pagination;
            summary.innerHTML    = data.total
                ? `Showing <span class="font-bold text-gray-900">${data.from}–${data.to}</span> of ${data.total} results`
                : '0 results';
            history.replaceState(null, '', p.toString() ? `?${p}` : location.pathname);
            if (scroll) grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (err) {
            if (err.name === 'AbortError') return;
            window.AgriMarket && window.AgriMarket.toast('Could not update the produce list. Please try again.', 'error');
        } finally {
            grid.classList.remove('is-loading');
        }
    }

    const debounced = () => { clearTimeout(timer); timer = setTimeout(() => load(1), 280); };

    /* "All categories" behaves like a radio-reset for the individual boxes */
    catAll.addEventListener('change', () => {
        if (catAll.checked) catBoxes.forEach((b) => { b.checked = false; });
        else if (!catBoxes.some((b) => b.checked)) catAll.checked = true;
        load(1);
    });
    catBoxes.forEach((b) => b.addEventListener('change', () => {
        catAll.checked = !catBoxes.some((x) => x.checked);
        load(1);
    }));

    priceRange.addEventListener('input', () => { priceLabel.textContent = fmt(priceRange.value); debounced(); });
    form.elements.q.addEventListener('input', debounced);
    form.elements.county.addEventListener('change', () => load(1));
    form.elements.sort.addEventListener('change', () => load(1));

    form.addEventListener('submit', (e) => { e.preventDefault(); load(1, { scroll: true }); });

    pagination.addEventListener('click', (e) => {
        const a = e.target.closest('a[data-page]');
        if (!a) return;
        e.preventDefault();
        load(parseInt(a.dataset.page, 10), { scroll: true });
    });

    form.querySelector('[data-reset-filters]').addEventListener('click', (e) => {
        e.preventDefault();
        form.reset();
        catBoxes.forEach((b) => { b.checked = false; });
        catAll.checked = true;
        form.elements.q.value = '';
        priceRange.value = PRICE_MAX;
        priceLabel.textContent = fmt(PRICE_MAX);
        load(1);
    });
})();
