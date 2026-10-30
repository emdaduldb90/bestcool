(() => {
    const configUrl = document.querySelector('meta[name="tracking-config-url"]')?.content;
    const consentUrl = document.querySelector('meta[name="tracking-consent-url"]')?.content;
    const eventsUrl = document.querySelector('meta[name="tracking-events-url"]')?.content;
    if (!configUrl || !consentUrl || !eventsUrl || window.__siteTrackingInitialized) return;
    window.__siteTrackingInitialized = true;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const banner = document.querySelector('[data-tracking-consent]');
    const privatePage = /\/(admin|account|order-success|password|reset)(\/|$)/i.test(location.pathname);
    const sensitiveUrl = (() => {
        let query = location.search || '';
        try { query = decodeURIComponent(query); } catch { /* keep the encoded query for pattern checks */ }
        query = query.toLowerCase();
        return /(?:^|[?&])(token|secret|password|email|phone|payment|card)(?:=|&|$)/i.test(location.search || '')
            || /[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i.test(query)
            || /(?<!\d)\+?\d[\d\s().-]{6,}\d(?!\d)/.test(query);
    })();
    let config = null;
    let initialized = false;
    const id = () => window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    const consentAllowed = () => !!(config?.consent?.analytics || config?.consent?.advertising);
    const clearCookies = (names) => names.forEach((name) => {
        document.cookie = `${name}=; Max-Age=0; Path=/; SameSite=Lax`;
        document.cookie = `${name}=; Max-Age=0; Path=${location.pathname}; SameSite=Lax`;
    });

    const scrubTerm = (value) => String(value || '').replace(/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/gi, '[redacted]')
        .replace(/(?<!\d)\+?\d[\d\s().-]{6,}\d(?!\d)/g, '[redacted]').replace(/\s+/g, ' ').trim().slice(0, 80);

    const cleanProperties = (name, input = {}) => {
        let referrerUrl = '';
        try { const referrer = new URL(document.referrer); referrerUrl = `${referrer.origin}/`; } catch { /* omit an invalid or empty referrer */ }
        const result = { page_path: `${location.pathname}`.slice(0, 512), referrer_url: referrerUrl };
        if (input.product_id) result.product_id = String(input.product_id).slice(0, 64);
        if (input.product_sku) result.product_sku = String(input.product_sku).slice(0, 100);
        if (Number.isFinite(Number(input.quantity)) && Number(input.quantity) > 0) result.quantity = Math.min(99, Math.trunc(Number(input.quantity)));
        if (Number.isFinite(Number(input.value)) && input.value !== '') result.value = Math.max(0, Number(input.value));
        if (/^[A-Za-z]{3}$/.test(String(input.currency || ''))) result.currency = String(input.currency).toUpperCase();
        if (name === 'Search') {
            result.search_term = scrubTerm(input.search_term);
            if (Number.isFinite(Number(input.result_count))) result.result_count = Math.max(0, Math.trunc(Number(input.result_count)));
        }
        if (['LinkClick', 'PhoneClick', 'EmailClick'].includes(name)) {
            result.link_url = String(input.link_url || '').slice(0, 1000);
            result.link_label = scrubTerm(String(input.link_label || '').replace(/\s+/g, ' ').trim()).slice(0, 120);
        }
        return result;
    };

    const loadScript = (src, idName) => {
        if (document.getElementById(idName)) return;
        const script = document.createElement('script');
        script.id = idName;
        script.async = true;
        script.src = src;
        document.head.append(script);
    };

    const initMeta = (meta) => {
        if (!meta?.pixel_id || window.fbq) return;
        const fbq = window.fbq = function () { fbq.callMethod ? fbq.callMethod.apply(fbq, arguments) : fbq.queue.push(arguments); };
        fbq.push = fbq; fbq.loaded = true; fbq.version = '2.0'; fbq.queue = [];
        window._fbq = window._fbq || fbq;
        loadScript('https://connect.facebook.net/en_US/fbevents.js', 'bce-meta-pixel');
        fbq('init', meta.pixel_id);
    };

    const initGa4 = (ga4) => {
        if (!ga4?.measurement_id || window.gtag) return;
        window.dataLayer = window.dataLayer || [];
        window.gtag = function () { window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        window.gtag('config', ga4.measurement_id, { send_page_view: false });
        loadScript(`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(ga4.measurement_id)}`, 'bce-ga4-tag');
    };

    const initTikTok = (tiktok) => {
        if (!tiktok?.pixel_id || window.ttq) return;
        const ttq = window.ttq = window.ttq || [];
        ttq.methods = ['page', 'track', 'identify', 'instances', 'debug', 'on', 'off', 'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie'];
        ttq.setAndDefer = function (target, method) { target[method] = function () { target.push([method].concat(Array.prototype.slice.call(arguments, 0))); }; };
        for (let i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
        ttq.load = function (pixelId) {
            const script = document.createElement('script'); script.async = true;
            script.src = `https://analytics.tiktok.com/i18n/pixel/events.js?sdkid=${encodeURIComponent(pixelId)}&lib=ttq`;
            const first = document.getElementsByTagName('script')[0]; first?.parentNode?.insertBefore(script, first);
        };
        ttq.load(tiktok.pixel_id);
    };

    const providerEvent = (name, properties, eventId) => {
        const destinations = config?.destinations || {};
        const meta = destinations.meta;
        if (meta && meta.events.includes(name) && window.fbq) {
            const custom = ['LinkClick', 'PhoneClick', 'EmailClick'].includes(name);
            const metaProperties = { ...properties };
            if (properties.product_id) { metaProperties.content_ids = [properties.product_id]; metaProperties.content_type = 'product'; }
            if (name === 'Search') { metaProperties.search_string = properties.search_term; delete metaProperties.search_term; }
            window.fbq(custom ? 'trackCustom' : 'track', name, metaProperties, { eventID: eventId });
        }
        const tiktok = destinations.tiktok;
        if (tiktok && tiktok.events.includes(name) && window.ttq) {
            const tiktokProperties = { value: properties.value, currency: properties.currency };
            if (properties.product_id) {
                tiktokProperties.content_id = properties.product_id;
                tiktokProperties.quantity = properties.quantity || 1;
                tiktokProperties.contents = [{ content_id: properties.product_id, quantity: properties.quantity || 1, price: properties.value }];
            }
            if (name === 'Search') tiktokProperties.query = properties.search_term;
            if (['LinkClick', 'PhoneClick', 'EmailClick'].includes(name)) { tiktokProperties.link_url = properties.link_url; tiktokProperties.link_label = properties.link_label; }
            window.ttq.track(name === 'PageView' ? 'Pageview' : name, tiktokProperties, { event_id: eventId });
        }
        const ga4 = destinations.ga4;
        if (ga4 && ga4.events.includes(name) && window.gtag) {
            const names = { PageView: 'page_view', ViewContent: 'view_item', AddToCart: 'add_to_cart', InitiateCheckout: 'begin_checkout', Purchase: 'purchase', Search: 'search', LinkClick: 'link_click', PhoneClick: 'phone_click', EmailClick: 'email_click' };
            const params = { ...properties };
            delete params.page_path; delete params.referrer_url;
            params.page_location = `${location.origin}${location.pathname}`;
            if (properties.product_id) params.items = [{ item_id: properties.product_id, quantity: properties.quantity || 1, price: properties.value }];
            if (name === 'Search') { params.search_term = properties.search_term; }
            window.gtag('event', names[name] || name.toLowerCase(), params);
        }
    };

    const postEvent = (name, properties, eventId) => {
        if (!consentAllowed()) return;
        const body = JSON.stringify({ event_id: eventId, event_name: name, properties });
        fetch(eventsUrl, { method: 'POST', credentials: 'same-origin', keepalive: true, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body }).catch(() => {});
    };

    const track = (name, input = {}, eventId = null) => {
        if (!consentAllowed() || privatePage || name === 'Purchase') return null;
        const eventKey = eventId || id();
        const properties = cleanProperties(name, input);
        providerEvent(name, properties, eventKey);
        postEvent(name, properties, eventKey);
        return eventKey;
    };

    const initialize = (state, pageView = true) => {
        config = state;
        if (!config?.consent) { clearCookies(['_ga', '_gid', '_gat', '_fbp', '_fbc', '_ttp']); if (banner) banner.hidden = false; return; }
        if (!config.consent.analytics) clearCookies(['_ga', '_gid', '_gat']);
        if (!config.consent.advertising) clearCookies(['_fbp', '_fbc', '_ttp']);
        if (banner) banner.hidden = true;
        if (!consentAllowed()) return;
        if (!privatePage && !sensitiveUrl) {
            initMeta(config.destinations?.meta);
            initGa4(config.destinations?.ga4);
            initTikTok(config.destinations?.tiktok);
        }
        if (!initialized && pageView) {
            initialized = true;
            track('PageView');
            const product = document.querySelector('[data-track-view-content]');
            if (product) track('ViewContent', { product_id: product.dataset.productId, product_sku: product.dataset.productSku, value: product.dataset.value, currency: product.dataset.currency });
            const search = document.querySelector('[data-track-search]');
            if (search && search.dataset.searchTerm) track('Search', { search_term: search.dataset.searchTerm, result_count: search.dataset.resultCount });
            const checkout = document.querySelector('[data-track-initiate-checkout]');
            if (checkout) track('InitiateCheckout', { value: checkout.dataset.value, currency: checkout.dataset.currency }, checkout.dataset.eventId || id());
        }
    };

    const refresh = () => fetch(configUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then((response) => response.ok ? response.json() : null).then((state) => initialize(state, true)).catch(() => {});

    document.addEventListener('click', (event) => {
        const privacyButton = event.target.closest('[data-open-consent]');
        if (privacyButton && banner) { banner.hidden = false; return; }
        const closeButton = event.target.closest('[data-close-consent]');
        if (closeButton && banner && config?.consent) { banner.hidden = true; return; }
        const choice = event.target.closest('[data-consent-choice]');
        if (choice) {
            const value = choice.dataset.consentChoice;
            const consent = { analytics: value === 'all' || value === 'analytics', advertising: value === 'all' };
            choice.disabled = true;
            fetch(consentUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(consent) })
                .then((response) => { if (!response.ok) throw new Error('Could not save privacy choice.'); return response.json(); })
                .then(refresh).catch(() => { choice.disabled = false; });
            return;
        }

        const anchor = event.target.closest('a[href]');
        if (!anchor || !consentAllowed()) return;
        const href = anchor.getAttribute('href') || '';
        const label = (anchor.getAttribute('aria-label') || anchor.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 120);
        if (href.toLowerCase().startsWith('tel:')) track('PhoneClick', { link_url: 'tel:[redacted]', link_label: label });
        else if (href.toLowerCase().startsWith('mailto:')) track('EmailClick', { link_url: 'mailto:[redacted]', link_label: label });
        else {
            let url;
            try { url = new URL(href, location.href); } catch { return; }
            if (!['http:', 'https:'].includes(url.protocol)) return;
            url.search = ''; url.hash = '';
            if (/\/(admin|account|checkout|order-success|login|logout|password|reset)(\/|$)/i.test(url.pathname)) return;
            if (/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i.test(url.pathname) || /\d{7,}/.test(url.pathname) || /\/(?:[a-f0-9]{32,}|[a-z0-9_-]{40,})(?:\/|$)/i.test(url.pathname)) return;
            track('LinkClick', { link_url: url.href, link_label: label });
        }
    });

    window.SiteTracking = { track, refresh };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', refresh, { once: true });
    else refresh();
})();
