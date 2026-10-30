const setCount = (selector, count) => {
    document.querySelectorAll(selector).forEach((node) => {
        node.textContent = String(count);
        node.hidden = count < 1;
    });
};

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
    const thumbnail = event.target.closest('[data-product-image]');
    const main = document.querySelector('[data-main-product-image]');
    if (thumbnail && main) main.src = thumbnail.dataset.productImage;
});
