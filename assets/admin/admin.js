/**
 * کیت پیشخوان — بی‌وابستگی، بدون jQuery.
 *
 *   ناوبری بی‌بارگذاری: پیوندها، فرم‌های GET (جست‌وجو، فیلتر، بازه) و
 *   فرم‌های POST (ذخیره، حذف، آزمایش‌ها) با fetch انجام
 *   می‌شوند و فقط محتوای .szw-admin عوض می‌شود؛ نوار پیشرفت، تاریخچه
 *   مرورگر (عقب/جلو)، پیش‌بارگذاری با نگه‌داشتن ماوس، و نتیجه اقدام به
 *   شکل پیام کوتاه. هر خطایی ← همان ناوبری معمولی مرورگر.
 *
 *   رفتارهای صفحه (init): کلیک ردیف، انتخاب گروهی، فیلتر خودکار، راهنمای
 *   نمودار، کپی، میان‌برها، اعلان مورد تازه، هشدار تغییرات ذخیره‌نشده، پنجره.
 *
 *   برای افزونه خودتان فقط سه چیز را عوض کنید: کلاس ریشه (.szw-admin)،
 *   پیشوند نامک صفحه‌ها (/^szw/) و شناسه منوی وردپرس (#toplevel_page_szw).
 */
(function () {
    'use strict';

    var root = document.querySelector('.szw-admin');
    if (!root) {
        return;
    }

    var timers = [];
    var dirtyForm = null;
    var dirtyInitial = '';
    var prefetched = {};

    /* ---- پیام کوتاه ------------------------------------------------------- */

    var toast = document.createElement('div');
    toast.className = 'za-toast';
    toast.setAttribute('role', 'status');
    document.body.appendChild(toast);
    var toastTimer = null;

    function say(text, tone) {
        toast.textContent = text;
        toast.className = 'za-toast is-on' + (tone ? ' za-toast--' + tone : '');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toast.classList.remove('is-on'); }, tone === 'error' ? 6000 : 3200);
    }

    /* ---- نوار پیشرفت ------------------------------------------------------ */

    var bar = document.createElement('div');
    bar.className = 'za-progress';
    document.body.appendChild(bar);
    var barTimer = null;

    function progress(on) {
        clearTimeout(barTimer);
        if (on) {
            bar.className = 'za-progress is-on';
            void bar.offsetWidth;
            bar.classList.add('is-run');
            root.classList.add('is-busy');
        } else {
            bar.classList.add('is-done');
            root.classList.remove('is-busy');
            barTimer = setTimeout(function () { bar.className = 'za-progress'; }, 350);
        }
    }

    /* ---- ناوبری بی‌بارگذاری ----------------------------------------------- */

    function ours(href) {
        var u;
        try { u = new URL(href, location.href); } catch (e) { return null; }
        if (u.origin !== location.origin || !/\/wp-admin\/admin\.php$/.test(u.pathname) || !/^szw/.test(u.searchParams.get('page') || '')) {
            return null;
        }
        return u;
    }

    function samePage(a, b) {
        return a.searchParams.get('page') === b.searchParams.get('page')
            && (a.searchParams.get('item') || '') === (b.searchParams.get('item') || '')
            && (a.searchParams.get('tab') || '') === (b.searchParams.get('tab') || '');
    }

    function leaveOk() {
        if (dirtyForm && serialize(dirtyForm) !== dirtyInitial) {
            return window.confirm('تغییرات ذخیره نشده‌اند. بدون ذخیره از این صفحه خارج شوید؟');
        }
        return true;
    }

    /**
     * @param {string} url
     * @param {{method?:string, body?:FormData, push?:boolean, keepScroll?:boolean}} opts
     */
    function go(url, opts) {
        opts = opts || {};
        var method = opts.method || 'GET';
        var target = ours(url);

        if (!target) {
            location.href = url;
            return;
        }

        var key = target.toString();
        var request = method === 'GET' && prefetched[key] && Date.now() - prefetched[key].at < 15000
            ? prefetched[key].promise
            : fetch(key, {method: method, body: opts.body || null, credentials: 'same-origin', headers: {'X-Akit-Nav': '1'}})
                .then(function (r) { return r.text().then(function (html) { return {ok: r.ok, url: r.url, html: html}; }); });
        delete prefetched[key];

        progress(true);
        var from = new URL(location.href);

        request.then(function (res) {
            var doc = new DOMParser().parseFromString(res.html, 'text/html');
            var fresh = doc.querySelector('.szw-admin');
            if (!res.ok || !fresh) {
                throw new Error('no content');
            }

            // مقصد: نشانی نهایی پاسخ (POST به همان نشانی فرم)
            var landed = new URL(res.url || key);
            if (opts.push !== false) {
                // جای اسکرول صفحه فعلی برای «عقب»
                history.replaceState({szw: 1, y: window.scrollY}, '', location.href);
                history.pushState({szw: 1, y: 0}, '', landed.toString());
            }

            teardown();
            root.innerHTML = fresh.innerHTML;
            document.title = doc.title;

            // منوی وردپرس: شمارنده‌ها و گزینه جاری
            var menu = doc.querySelector('#toplevel_page_szw');
            var mine = document.querySelector('#toplevel_page_szw');
            if (menu && mine) { mine.innerHTML = menu.innerHTML; }

            runScripts(root);
            init();
            progress(false);

            var keep = opts.keepScroll || (method !== 'GET' && samePage(from, landed));
            if (typeof opts.restoreY === 'number') {
                window.scrollTo(0, opts.restoreY);
            } else if (!keep) {
                window.scrollTo(0, 0);
            }

            // نتیجه اقدام: پیام کوتاه (و اعلان در صفحه می‌ماند)
            if (method !== 'GET') {
                var flash = root.querySelector('[data-flash]');
                if (flash) {
                    say(flash.textContent.trim(), flash.getAttribute('data-flash'));
                }
            }

            var focus = root.querySelector('[autofocus]');
            if (focus) { focus.focus(); }
        }).catch(function () {
            progress(false);
            // هر مشکلی: همان راه معمولی
            if (method === 'GET') {
                location.href = key;
            } else if (opts.form) {
                opts.form.setAttribute('data-plain', '1');
                opts.form.requestSubmit ? opts.form.requestSubmit(opts.submitter || undefined) : opts.form.submit();
            }
        });
    }

    /** اسکریپت‌های درون محتوای تازه اجرا شوند */
    function runScripts(scope) {
        Array.prototype.forEach.call(scope.querySelectorAll('script'), function (old) {
            var s = document.createElement('script');
            Array.prototype.forEach.call(old.attributes, function (a) { s.setAttribute(a.name, a.value); });
            s.textContent = old.textContent;
            old.replaceWith(s);
        });
    }

    window.addEventListener('popstate', function (e) {
        if (ours(location.href)) {
            go(location.href, {push: false, restoreY: e.state && typeof e.state.y === 'number' ? e.state.y : 0});
        }
    });
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }
    history.replaceState({szw: 1}, '', location.href);

    // پیوندها: درون پیشخوان افزونه و زیرمنوی وردپرس آن
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }
        var a = e.target.closest('a[href]');
        if (!a || a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-plain')) {
            return;
        }
        if (!root.contains(a) && !a.closest('#toplevel_page_szw')) {
            return;
        }
        if (!ours(a.href) || !leaveOk()) {
            if (ours(a.href)) { e.preventDefault(); }
            return;
        }
        e.preventDefault();
        dirtyForm = null;
        go(a.href);
    });

    // ردیف کلیک‌پذیر
    root.addEventListener('click', function (e) {
        var row = e.target.closest('tr[data-href]');
        if (!row || e.target.closest('a, button, input, label, form, summary, .za-check-col')) {
            return;
        }
        if (e.metaKey || e.ctrlKey) {
            window.open(row.getAttribute('data-href'), '_blank');
        } else {
            go(row.getAttribute('data-href'));
        }
    });

    // پیش‌بارگذاری: ماوس کمی روی پیوند بماند
    var hoverTimer = null;
    root.addEventListener('mouseover', function (e) {
        var a = e.target.closest('a[href], tr[data-href]');
        if (!a) { return; }
        var href = a.getAttribute('href') || a.getAttribute('data-href');
        var u = ours(href);
        if (!u || u.searchParams.has('_wpnonce') || a.target === '_blank') { return; }
        clearTimeout(hoverTimer);
        hoverTimer = setTimeout(function () {
            var key = u.toString();
            if (prefetched[key] && Date.now() - prefetched[key].at < 15000) { return; }
            prefetched[key] = {
                at: Date.now(),
                promise: fetch(key, {credentials: 'same-origin', headers: {'X-Akit-Nav': '1'}})
                    .then(function (r) { return r.text().then(function (html) { return {ok: r.ok, url: r.url, html: html}; }); })
            };
        }, 90);
    });
    root.addEventListener('mouseout', function () { clearTimeout(hoverTimer); });

    // فرم‌ها
    root.addEventListener('submit', function (e) {
        var form = e.target;
        var submitter = e.submitter || null;

        if (form.hasAttribute('data-plain')) {
            form.removeAttribute('data-plain');
            return;   // راه معمولی (پس از خطای fetch)
        }

        var ask = form.getAttribute('data-confirm') || (submitter && submitter.getAttribute('data-confirm-click'));
        if (ask && !window.confirm(ask)) {
            e.preventDefault();
            return;
        }

        var action = form.getAttribute('action') || location.href;
        var method = (form.getAttribute('method') || 'get').toUpperCase();

        if (!ours(action)) {
            return;   // admin-post و مانند آن: معمولی
        }

        e.preventDefault();

        var data = new FormData(form);
        if (submitter && submitter.name) {
            data.append(submitter.name, submitter.value);
        }

        if (submitter && submitter.classList) {
            submitter.classList.add('is-loading');
            submitter.disabled = true;
        }

        if (method === 'GET') {
            var u = new URL(action, location.href);
            u.search = '';
            data.forEach(function (v, k) { if (v !== '') { u.searchParams.append(k, v); } });
            go(u.toString(), {keepScroll: form.hasAttribute('data-autosubmit') || form.classList.contains('za-search')});
            return;
        }

        if (form === dirtyForm) {
            dirtyForm = null;   // ذخیره همان فرم؛ هشدار خروج لازم نیست
        }
        go(action, {method: 'POST', body: data, form: form, submitter: submitter});
    });

    /* ---- کپی ------------------------------------------------------------- */

    function copy(text, el) {
        var done = function () {
            say('کپی شد', 'ok');
            if (el) {
                el.classList.add('is-copied');
                setTimeout(function () { el.classList.remove('is-copied'); }, 1200);
            }
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, function () { fallback(text) && done(); });
        } else if (fallback(text)) {
            done();
        }
    }

    function fallback(text) {
        var area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.cssText = 'position:fixed;opacity:0;top:0';
        document.body.appendChild(area);
        area.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (err) { ok = false; }
        area.remove();
        return ok;
    }

    root.addEventListener('click', function (e) {
        var el = e.target.closest('[data-copy], [data-copy-text], [data-copy-target]');
        if (!el) {
            return;
        }
        if (el.hasAttribute('data-copy-target')) {
            var target = document.getElementById(el.getAttribute('data-copy-target'));
            if (target) { copy(target.value || target.textContent, el); }
            return;
        }
        copy(el.getAttribute('data-copy') || el.getAttribute('data-copy-text'), el);
    });

    /* ---- راهنمای نمودار ---------------------------------------------------- */

    var tip = document.createElement('div');
    tip.className = 'za-tip';
    tip.hidden = true;
    document.body.appendChild(tip);

    function showTip(el) {
        var r = el.getBoundingClientRect();
        tip.textContent = el.getAttribute('data-tip');
        tip.hidden = false;
        tip.style.left = (r.left + r.width / 2 - tip.offsetWidth) + 'px';
        tip.style.top = r.top + 'px';
    }

    root.addEventListener('mouseover', function (e) {
        var el = e.target.closest('[data-tip]');
        if (el) { showTip(el); }
    });
    root.addEventListener('mouseout', function (e) {
        if (e.target.closest('[data-tip]')) { tip.hidden = true; }
    });
    root.addEventListener('focusin', function (e) {
        var el = e.target.closest('[data-tip]');
        if (el) { showTip(el); }
    });
    root.addEventListener('focusout', function () { tip.hidden = true; });
    window.addEventListener('scroll', function () { tip.hidden = true; }, {passive: true});

    /* ---- کلیدهای میان‌بر --------------------------------------------------- */

    document.addEventListener('keydown', function (e) {
        var el = document.activeElement || {};
        var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName || '') || el.isContentEditable;
        if (e.key === 'Escape' && typing && el.closest && el.closest('.za-search')) {
            el.blur();
            return;
        }
        if (typing || e.ctrlKey || e.metaKey || e.altKey) {
            return;
        }
        if (e.key === '/') {
            var search = root.querySelector('input[data-hotkey="/"]');
            if (search) { e.preventDefault(); search.focus(); search.select(); }
            return;
        }
        var link = root.querySelector('a[data-hotkey="' + e.key + '"]');
        if (link) { go(link.href); }
    });

    /* ---- انتخاب گروهی (رویدادها روی ریشه، یک بار) --------------------------- */

    function bulkSync() {
        var bulk = root.querySelector('form[data-bulk]');
        if (!bulk) { return; }
        var boxes = Array.prototype.slice.call(root.querySelectorAll('input[form="za-bulk"]'));
        var all = root.querySelector('input[data-bulk-all]');
        var n = boxes.filter(function (b) { return b.checked; }).length;
        bulk.classList.toggle('is-on', n > 0);
        bulk.querySelector('.za-bulk__count').textContent = n.toLocaleString('fa-IR') + ' انتخاب‌شده';
        if (all) {
            all.checked = n > 0 && n === boxes.length;
            all.indeterminate = n > 0 && n < boxes.length;
        }
        boxes.forEach(function (b) { b.closest('tr').classList.toggle('is-selected', b.checked); });
    }

    root.addEventListener('change', function (e) {
        if (e.target.matches('input[data-bulk-all]')) {
            Array.prototype.forEach.call(root.querySelectorAll('input[form="za-bulk"]'), function (b) { b.checked = e.target.checked; });
        }
        if (e.target.matches('input[form="za-bulk"], input[data-bulk-all]')) {
            bulkSync();
        }
        var auto = e.target.closest('form[data-autosubmit]');
        if (auto) {
            auto.requestSubmit ? auto.requestSubmit() : auto.dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}));
        }
    });

    root.addEventListener('click', function (e) {
        if (e.target.closest('[data-bulk-clear]')) {
            Array.prototype.forEach.call(root.querySelectorAll('input[form="za-bulk"]'), function (b) { b.checked = false; });
            bulkSync();
        }
    });

    /* ---- تغییرات ذخیره‌نشده ------------------------------------------------- */

    function serialize(form) {
        return new URLSearchParams(new FormData(form)).toString();
    }

    function dirtyCheck() {
        if (!dirtyForm) { return false; }
        var dirty = serialize(dirtyForm) !== dirtyInitial;
        dirtyForm.classList.toggle('is-dirty', dirty);
        var state = dirtyForm.querySelector('.za-savebar__state');
        if (state) { state.textContent = dirty ? 'تغییرات ذخیره نشده' : ''; }
        return dirty;
    }

    root.addEventListener('input', dirtyCheck);
    root.addEventListener('change', dirtyCheck);
    window.addEventListener('beforeunload', function (e) {
        if (dirtyCheck()) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // Ctrl+S / ⌘S: ذخیره فرم تنظیمات
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's' && dirtyForm) {
            e.preventDefault();
            var btn = dirtyForm.querySelector('.za-savebar button[type="submit"]');
            if (btn) { btn.click(); }
        }
    });

    /* ---- پنجره (dialog)، جست‌وجوی درون فهرست ------------------------------ */

    // <button data-dialog="شناسه"> پنجره را باز می‌کند؛ [data-dialog-close] یا کلیک بیرون یا Esc می‌بندد
    // و فرم داخل پنجره به حالت اول برمی‌گردد (تغییر ذخیره‌نشده دور ریخته می‌شود).
    root.addEventListener('click', function (e) {
        var open = e.target.closest('[data-dialog]');
        if (open) {
            var dialog = document.getElementById(open.getAttribute('data-dialog'));
            if (dialog && dialog.showModal) { dialog.showModal(); }
            return;
        }

        var close = e.target.closest('[data-dialog-close]');
        if (close) {
            var dlg = close.closest('dialog');
            var f = dlg && dlg.querySelector('form');
            if (f) { f.reset(); }
            if (dlg) { dlg.close(); }
            return;
        }

        // کلیک روی پس‌زمینه پنجره
        if (e.target.matches('dialog.za-dialog')) {
            var r = e.target.getBoundingClientRect();
            if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) {
                var form = e.target.querySelector('form');
                if (form) { form.reset(); }
                e.target.close();
            }
        }
    });

    // Esc (رویداد cancel حباب نمی‌زند)
    root.addEventListener('cancel', function (e) {
        var form = e.target.matches && e.target.matches('dialog.za-dialog') ? e.target.querySelector('form') : null;
        if (form) { form.reset(); }
    }, true);

    // <input data-filter-list="شناسه ul"> ← li هایی که data-text شان شامل متن نیست پنهان می‌شوند
    root.addEventListener('input', function (e) {
        var list = e.target.getAttribute && e.target.getAttribute('data-filter-list');
        if (!list) { return; }
        var term = e.target.value.trim().toLowerCase();
        Array.prototype.forEach.call(document.querySelectorAll('#' + list + ' > li'), function (li) {
            li.hidden = term !== '' && li.getAttribute('data-text').indexOf(term) === -1;
        });
    });

    /* ---- هر بار پس از تعویض محتوا ------------------------------------------ */

    function teardown() {
        timers.forEach(clearInterval);
        timers = [];
        tip.hidden = true;
    }

    /*
     * چیدمان دوستونه کارت‌های هم‌قدنشده (صفحه وضعیت): هر کارت به ستون
     * کوتاه‌تر می‌رود و آخرین کارت هر ستون کش می‌آید تا دو ستون هم‌تراز تمام
     * شوند. بی‌جاوااسکریپت، چیدمان ستونی CSS جایگزین است.
     */
    function masonry() {
        var box = root.querySelector('[data-masonry]');
        if (!box) { return; }

        var cards = box.szwCards || (box.szwCards = Array.prototype.slice.call(box.querySelectorAll(':scope > .za-card')));
        var two   = !window.matchMedia('(max-width: 1200px)').matches;

        box.textContent = '';

        if (!two) {
            box.classList.remove('is-masonry');
            cards.forEach(function (c) { box.appendChild(c); });
            return;
        }

        box.classList.add('is-masonry', 'is-placing');
        var cols = [0, 1].map(function () {
            var col = document.createElement('div');
            col.className = 'za-status__col';
            box.appendChild(col);
            return col;
        });

        cards.forEach(function (c) {
            (cols[0].offsetHeight <= cols[1].offsetHeight ? cols[0] : cols[1]).appendChild(c);
        });
        box.classList.remove('is-placing');
    }

    var masonryTimer = 0;
    window.addEventListener('resize', function () {
        clearTimeout(masonryTimer);
        masonryTimer = setTimeout(masonry, 150);
    });

    function init() {
        bulkSync();
        masonry();

        dirtyForm = root.querySelector('form[data-dirty-guard]');
        dirtyInitial = dirtyForm ? serialize(dirtyForm) : '';

        // اعلان موفقیت در صفحه بعد از چند ثانیه کم‌رنگ می‌شود
        Array.prototype.forEach.call(root.querySelectorAll('[data-flash="ok"]'), function (el) {
            setTimeout(function () { el.classList.add('is-fading'); }, 5000);
        });

        // مورد تازه رسید (اختیاری: <div data-live="نشانی ajax" data-after="آخرین شناسه" hidden>)
        var live = root.querySelector('[data-live]');
        if (live && window.fetch) {
            var poll = function () {
                if (document.hidden || !root.contains(live)) { return; }
                fetch(live.getAttribute('data-live') + '&after=' + live.getAttribute('data-after'), {credentials: 'same-origin'})
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        var n = res && res.success ? res.data.count : 0;
                        if (n > 0 && root.contains(live)) {
                            live.hidden = false;
                            live.textContent = '';
                            var a = document.createElement('a');
                            a.href = location.href;
                            a.textContent = n.toLocaleString('fa-IR') + ' مورد تازه رسید — نمایش';
                            live.appendChild(a);
                            document.title = '(' + n.toLocaleString('fa-IR') + ') ' + document.title.replace(/^\(.+?\) /, '');
                        }
                    })
                    .catch(function () { /* شبکه: دفعه بعد */ });
            };
            timers.push(setInterval(poll, 30000));
            live.addEventListener('szw-poll', poll);
        }
    }

    document.addEventListener('visibilitychange', function () {
        var live = root.querySelector('[data-live]');
        if (live && !document.hidden) { live.dispatchEvent(new Event('szw-poll')); }
    });

    init();
})();
