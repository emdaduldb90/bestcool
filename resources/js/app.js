import './tracking.js';

const setCount = (selector, count) => {
    document.querySelectorAll(selector).forEach((node) => {
        node.textContent = String(count);
        node.hidden = count < 1;
    });
};

document.querySelectorAll('[data-banner-slider]').forEach((slider) => {
    const slides = [...slider.querySelectorAll('[data-banner-slide]')];
    const dots = [...slider.querySelectorAll('[data-banner-dot]')];
    if (slides.length < 2) return;
    let active = 0;
    const show = (index) => {
        active = (index + slides.length) % slides.length;
        slides.forEach((slide, i) => {
            slide.classList.toggle('hidden', i !== active);
            slide.setAttribute('aria-hidden', i === active ? 'false' : 'true');
        });
        dots.forEach((dot, i) => dot.setAttribute('aria-current', i === active ? 'true' : 'false'));
    };
    slider.querySelector('[data-banner-prev]')?.addEventListener('click', () => show(active - 1));
    slider.querySelector('[data-banner-next]')?.addEventListener('click', () => show(active + 1));
    dots.forEach((dot) => dot.addEventListener('click', () => show(Number(dot.dataset.bannerDot))));
});

document.querySelectorAll('[data-block-type]').forEach((select) => {
    const form = select.closest('form');
    const updateFields = () => form?.querySelectorAll('[data-block-fields]').forEach((group) => {
        const enabled = group.dataset.blockFields === select.value;
        group.classList.toggle('hidden', !enabled);
        group.querySelectorAll('input, select, textarea, button').forEach((field) => { field.disabled = !enabled; });
    });
    select.addEventListener('change', updateFields);
    updateFields();
});

const updateDiscountPreview = (form) => {
    const regular = Number(form.querySelector('[name="regular_price"]')?.value || 0);
    const saleField = form.querySelector('[name="sale_price"]');
    const sale = Number(saleField?.value || 0);
    const preview = form.querySelector('[data-discount-preview]');
    if (!preview) return;
    if (regular > 0 && sale > 0 && sale < regular) {
        const percent = Math.round(((regular - sale) / regular) * 100);
        preview.textContent = `Save ${percent}% (BDT ${(regular - sale).toFixed(2)} off).`;
    } else if (regular > 0 && saleField?.value !== '') {
        preview.textContent = 'No discount: sale price must be below the regular price.';
    } else {
        preview.textContent = 'Enter regular and sale prices to preview the discount.';
    }
};

document.querySelectorAll('[data-discount-preview]').forEach((preview) => {
    const form = preview.closest('form');
    if (!form) return;
    form.querySelectorAll('[name="regular_price"], [name="sale_price"]').forEach((field) => {
        field.addEventListener('input', () => updateDiscountPreview(form));
    });
    updateDiscountPreview(form);
});

document.querySelectorAll('[data-category-image-input]').forEach((input) => {
    input.addEventListener('change', () => {
        const file = input.files?.[0];
        const image = input.closest('form')?.querySelector('[data-category-image-preview]');
        if (!file || !image) return;
        image.src = URL.createObjectURL(file);
        image.classList.remove('hidden');
        input.closest('form')?.querySelector('[data-category-image-empty]')?.classList.add('hidden');
    });
});

document.querySelector('[data-admin-menu-toggle]')?.addEventListener('click', (event) => {
    const button = event.currentTarget;
    const menu = document.getElementById(button.getAttribute('aria-controls'));
    const open = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(open));
    menu?.classList.toggle('hidden', !open);
});

document.querySelectorAll('[data-admin-group-toggle]').forEach((button) => {
    const panel = document.getElementById(button.getAttribute('aria-controls'));
    button.addEventListener('click', () => {
        const open = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', String(open));
        panel?.classList.toggle('hidden', !open);
        button.querySelector('[data-admin-chevron]')?.classList.toggle('rotate-180', open);
    });
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-confirm]');
    const message = event.submitter?.dataset.confirm || form?.dataset.confirm;
    if (message && !window.confirm(message)) event.preventDefault();
});

document.addEventListener('input', (event) => {
    const filter = event.target.closest('[data-option-filter]');
    if (!filter) return;
    const select = filter.closest('form')?.querySelector(filter.dataset.optionFilter);
    if (!select) return;
    const needle = filter.value.trim().toLowerCase();
    select.querySelectorAll('option').forEach((option) => { option.hidden = needle !== '' && !option.dataset.search?.includes(needle); });
});

document.addEventListener('click', (event) => {
    const addRow = event.target.closest('[data-add-row]');
    if (addRow) {
        const form = addRow.closest('form');
        const scope = addRow.closest('[data-block-fields]') || form;
        const list = scope?.querySelector('[data-row-list]');
        const template = scope?.querySelector('template[data-row-template]');
        if (list && template) {
            const index = Number(addRow.dataset.nextIndex || 0);
            const row = template.content.cloneNode(true);
            row.querySelectorAll('[name]').forEach((field) => { field.name = field.name.replaceAll('__INDEX__', String(index)); });
            list.append(row);
            addRow.dataset.nextIndex = String(index + 1);
        }
        return;
    }
    const filter = event.target.closest('[data-option-filter]');
    if (filter) {
        const select = filter.closest('form')?.querySelector(filter.dataset.optionFilter);
        if (!select) return;
        const needle = filter.value.trim().toLowerCase();
        select.querySelectorAll('option').forEach((option) => {
            option.hidden = needle !== '' && !option.dataset.search?.includes(needle);
        });
    }
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-cart-form], [data-wishlist-form]');
    if (!form) return;

    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    const previousText = button?.textContent;
    if (button) button.disabled = true;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Please try again.');

        if (form.matches('[data-cart-form]')) {
            setCount('[data-cart-count]', data.cartCount);
            if (button) button.textContent = 'Added to Cart';
            if (data.tracking?.event_id) window.SiteTracking?.track('AddToCart', data.tracking, data.tracking.event_id);
        } else {
            setCount('[data-wishlist-count]', data.wishlistCount);
            const wishlistButton = form.querySelector('[data-wishlist-button]');
            if (wishlistButton) {
                wishlistButton.setAttribute('aria-pressed', String(data.saved));
                wishlistButton.dataset.saved = String(data.saved);
                wishlistButton.classList.toggle('text-rose-500', data.saved);
            }
            if (button) button.setAttribute('aria-pressed', String(data.saved));
        }

        const notice = document.querySelector('[data-live-notice]');
        if (notice) {
            notice.textContent = data.message || (data.saved ? 'Saved to wishlist.' : 'Removed from wishlist.');
            notice.hidden = false;
            window.setTimeout(() => { notice.hidden = true; }, 1800);
        }
    } catch (error) {
        if (!navigator.onLine) form.submit();
        else {
            const notice = document.querySelector('[data-live-notice]');
            if (notice) {
                notice.textContent = error.message;
                notice.hidden = false;
                window.setTimeout(() => { notice.hidden = true; }, 2400);
            }
        }
    } finally {
        if (button) {
            button.disabled = false;
            window.setTimeout(() => { button.textContent = previousText; }, 1500);
        }
    }
});

document.addEventListener('click', (event) => {
    const addFaq = event.target.closest('[data-add-faq]');
    if (addFaq) {
        const form = addFaq.closest('form');
        const list = form?.querySelector('[data-faq-list]');
        const template = form?.querySelector('[data-faq-template]');
        if (list && template) {
            const index = Number(addFaq.dataset.nextIndex || 0);
            const row = template.content.cloneNode(true);
            row.querySelectorAll('[name]').forEach((field) => { field.name = field.name.replaceAll('__INDEX__', String(index)); });
            list.append(row);
            addFaq.dataset.nextIndex = String(index + 1);
        }
        return;
    }

    const removeFaq = event.target.closest('[data-remove-faq]');
    if (removeFaq) {
        removeFaq.closest('[data-faq-row]')?.remove();
        return;
    }

    const selectAll = event.target.closest('[data-select-all]');
    if (selectAll) {
        const target = selectAll.dataset.selectTarget;
        const selector = target === 'orders' ? 'input[data-order-selection]' : 'input[name="ids[]"][form="bulk-products"]';
        document.querySelectorAll(selector).forEach((checkbox) => { checkbox.checked = selectAll.checked; });
        return;
    }

    const exportOrders = event.target.closest('[data-order-export]');
    if (exportOrders) {
        const selected = [...document.querySelectorAll('input[data-order-selection]:checked')];
        const form = document.getElementById('orders-export');
        if (!selected.length) { window.alert('Select at least one order to export.'); return; }
        form?.querySelectorAll('input[name="order_ids[]"]').forEach((input) => input.remove());
        selected.forEach((checkbox) => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'order_ids[]'; input.value = checkbox.value;
            form?.append(input);
        });
        form?.requestSubmit();
        return;
    }

    const thumbnail = event.target.closest('[data-product-image]');
    const main = document.querySelector('[data-main-product-image]');
    if (thumbnail && main) main.src = thumbnail.dataset.productImage;
});
