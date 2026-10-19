/**
 * The Requests panel's behaviour (from the admin kit): no dependencies, no jQuery.
 *
 *   Navigation without reloads: links, GET forms (search, filters, range) and
 *   POST forms (save, delete, tests) go through fetch and only the content of
 *   .szw-admin is swapped; a progress bar, browser history (back/forward),
 *   prefetch on hover, and action results as a toast. Any error falls back to
 *   the browser's ordinary navigation.
 *
 *   Page behaviours (init): clickable rows, bulk selection, auto-submitting
 *   filters, chart tips, copy, shortcuts, the new-request notice, the
 *   unsaved-changes warning, dialogs.
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

    /* ---- Toast ----------------------------------------------------------- */

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

    /* ---- Progress bar ---------------------------------------------------- */

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

    /* ---- Navigation without reloads -------------------------------------- */

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
            return window.confirm('You have unsaved changes. Leave this page without saving?');
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

            // where we end up: the response's final URL (a POST goes to the form's own URL)
            var landed = new URL(res.url || key);
            if (opts.push !== false) {
                // the current scroll position, for Back
                history.replaceState({szw: 1, y: window.scrollY}, '', location.href);
                history.pushState({szw: 1, y: 0}, '', landed.toString());
            }

            teardown();
            root.innerHTML = fresh.innerHTML;
            document.title = doc.title;

            // the WordPress menu: counters and the current item
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

            // an action's result: a toast (the notice stays on the page)
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
            // any problem: the ordinary way
            if (method === 'GET') {
                location.href = key;
            } else if (opts.form) {
                opts.form.setAttribute('data-plain', '1');
                opts.form.requestSubmit ? opts.form.requestSubmit(opts.submitter || undefined) : opts.form.submit();
            }
        });
    }

    /** run the scripts inside the new content */
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

    // links: inside the panel and its WordPress submenu
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

    // clickable rows
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

    // prefetch: when the pointer rests on a link
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

    // forms
    root.addEventListener('submit', function (e) {
        var form = e.target;
        var submitter = e.submitter || null;

        if (form.hasAttribute('data-plain')) {
            form.removeAttribute('data-plain');
            return;   // the ordinary way (after a fetch error)
        }

        var ask = form.getAttribute('data-confirm') || (submitter && submitter.getAttribute('data-confirm-click'));
        if (ask && !window.confirm(ask)) {
            e.preventDefault();
            return;
        }

        var action = form.getAttribute('action') || location.href;
        var method = (form.getAttribute('method') || 'get').toUpperCase();

        if (!ours(action)) {
            return;   // admin-post and the like: ordinary
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
            dirtyForm = null;   // saving that very form; no leave warning needed
        }
        go(action, {method: 'POST', body: data, form: form, submitter: submitter});
    });

    /* ---- Copy ------------------------------------------------------------ */

    function copy(text, el) {
        var done = function () {
            say('Copied', 'ok');
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

    /* ---- Chart tips ------------------------------------------------------ */

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

    /* ---- Shortcuts ------------------------------------------------------- */

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

    /* ---- Bulk selection (events on the root, bound once) ----------------- */

    function bulkSync() {
        var bulk = root.querySelector('form[data-bulk]');
        if (!bulk) { return; }
        var boxes = Array.prototype.slice.call(root.querySelectorAll('input[form="za-bulk"]'));
        var all = root.querySelector('input[data-bulk-all]');
        var n = boxes.filter(function (b) { return b.checked; }).length;
        bulk.classList.toggle('is-on', n > 0);
        bulk.querySelector('.za-bulk__count').textContent = n.toLocaleString('en') + ' selected';
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

    /* ---- Unsaved changes ------------------------------------------------- */

    function serialize(form) {
        return new URLSearchParams(new FormData(form)).toString();
    }

    function dirtyCheck() {
        if (!dirtyForm) { return false; }
        var dirty = serialize(dirtyForm) !== dirtyInitial;
        dirtyForm.classList.toggle('is-dirty', dirty);
        var state = dirtyForm.querySelector('.za-savebar__state');
        if (state) { state.textContent = dirty ? 'Unsaved changes' : ''; }
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

    // Ctrl+S / ⌘S: save the settings form
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's' && dirtyForm) {
            e.preventDefault();
            var btn = dirtyForm.querySelector('.za-savebar button[type="submit"]');
            if (btn) { btn.click(); }
        }
    });

    /* ---- Dialogs, filtering inside a list -------------------------------- */

    // <button data-dialog="id"> opens a dialog; [data-dialog-close], a click outside or Esc closes it
    // and the form inside goes back to how it was (unsaved edits are dropped).
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

        // a click on the dialog's backdrop
        if (e.target.matches('dialog.za-dialog')) {
            var r = e.target.getBoundingClientRect();
            if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) {
                var form = e.target.querySelector('form');
                if (form) { form.reset(); }
                e.target.close();
            }
        }
    });

    // Esc (the cancel event does not bubble)
    root.addEventListener('cancel', function (e) {
        var form = e.target.matches && e.target.matches('dialog.za-dialog') ? e.target.querySelector('form') : null;
        if (form) { form.reset(); }
    }, true);

    // <input data-filter-list="ul id">: li elements whose data-text lacks the text are hidden
    root.addEventListener('input', function (e) {
        var list = e.target.getAttribute && e.target.getAttribute('data-filter-list');
        if (!list) { return; }
        var term = e.target.value.trim().toLowerCase();
        Array.prototype.forEach.call(document.querySelectorAll('#' + list + ' > li'), function (li) {
            li.hidden = term !== '' && li.getAttribute('data-text').indexOf(term) === -1;
        });
    });

    /* ---- After every content swap ---------------------------------------- */

    function teardown() {
        timers.forEach(clearInterval);
        timers = [];
        tip.hidden = true;
    }

    /*
     * Two columns of uneven cards (System status): each card goes to the
     * shorter column and each column's last card stretches so both end level.
     * Without script, CSS columns stand in.
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


    /* ---- Settings: Telegram chats ----------------------------------------- */

    // Rows are ordinary form fields (saved with Save); find and test go
    // through admin-ajax and work before saving, with the token as typed.
    var CHAT_ID = /^-?\d{3,20}$|^@[A-Za-z0-9_]{4,32}$/;
    var chatSeq = 0;

    function chatsBox(el) { return el && el.closest('[data-chats]'); }

    function chatsPost(box, fields) {
        var body = new FormData();
        body.append('action', 'szw_tg');
        body.append('_ajax_nonce', box.getAttribute('data-nonce'));
        var token = root.querySelector('input[name="tg_token"]');
        body.append('token', token && token.value.trim() !== '__clear__' ? token.value.trim() : '');
        Object.keys(fields).forEach(function (k) { body.append(k, fields[k]); });
        return fetch(box.getAttribute('data-ajax'), {method: 'POST', body: body, credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) { throw new Error(res && res.data && res.data.text ? res.data.text : 'Something went wrong; try again.'); }
                return res.data;
            });
    }

    function chatsBusy(btn, on) {
        btn.disabled = on;
        btn.classList.toggle('is-loading', on);
    }

    function chatsChanged(box) {
        var rows = box.querySelectorAll('[data-chat]');
        var off  = box.querySelectorAll('[data-chat].is-off').length;
        box.querySelector('.za-chats__empty').hidden = rows.length > 0;
        box.querySelector('.za-chats__count').textContent = rows.length
            ? rows.length + (rows.length === 1 ? ' chat' : ' chats') + (off ? ' · ' + off + ' paused' : '')
            : '';
        markFound(box);
        dirtyCheck();
    }

    function chatRowById(box, id) {
        var hit = null;
        Array.prototype.forEach.call(box.querySelectorAll('[data-chat]'), function (row) {
            if (row.querySelector('[data-chat-id]').textContent === id) { hit = row; }
        });
        return hit;
    }

    var KINDS = {person: ['user', 'Person'], group: ['users', 'Group'], channel: ['megaphone', 'Channel']};

    function chatKind(id, kind) {
        if (KINDS[kind]) { return kind; }
        return id.charAt(0) === '@' ? 'channel' : (id.charAt(0) === '-' ? 'group' : 'person');
    }

    function addChat(box, c) {
        var have = chatRowById(box, c.id);
        if (have) {
            flash(have);
            return have;
        }
        var tpl  = box.querySelector('template[data-chats-row]');
        var html = tpl.innerHTML.replace(/__i__/g, 'n' + Date.now() + (chatSeq++));
        var list = box.querySelector('.za-chats__list');
        list.insertAdjacentHTML('beforeend', html);
        var row  = list.lastElementChild;
        var kind = chatKind(c.id, c.kind);
        row.querySelector('input[name$="[id]"]').value = c.id;
        row.querySelector('input[name$="[kind]"]').value = c.kind || '';
        row.querySelector('input[name$="[name]"]').value = c.name || '';
        row.querySelector('[data-chat-id]').textContent = c.id;
        row.querySelector('[data-chat-kind]').textContent = c.kind ? KINDS[kind][1] : (kind === 'person' ? 'Person' : (kind === 'channel' ? 'Channel' : 'Group or channel'));
        var icons = box.querySelector('[data-chats-icons]');
        var ico   = icons && icons.querySelector('[data-k="' + kind + '"]');
        if (ico) { row.querySelector('.za-chat__ava').innerHTML = ico.innerHTML; }
        row.classList.add('is-new');
        setTimeout(function () { row.classList.remove('is-new'); }, 900);
        chatsChanged(box);
        return row;
    }

    function flash(row) {
        row.classList.remove('is-flash');
        void row.offsetWidth;
        row.classList.add('is-flash');
        row.scrollIntoView({block: 'nearest', behavior: 'smooth'});
    }

    function markFound(box) {
        Array.prototype.forEach.call(box.querySelectorAll('[data-found-add]'), function (b) {
            var have = !!chatRowById(box, b.getAttribute('data-id'));
            b.disabled = have;
            b.classList.toggle('is-added', have);
            b.querySelector('span').textContent = have ? 'Added' : 'Add';
        });
    }

    function renderFound(box, data) {
        var out  = box.querySelector('.za-chats__found');
        var bot  = data.bot || 'your bot';
        out.hidden = false;
        out.textContent = '';
        var head = document.createElement('p');
        head.className = 'za-found__head';
        out.appendChild(head);
        if (!data.chats.length) {
            head.innerHTML = '<b>Nobody has written to ' + esc(bot) + ' yet.</b> Open it in Telegram and press Start (in a group, send any message), then press Find chats again. Telegram only keeps the last 24 hours.';
            return;
        }
        // a chat already listed without a name (added by id, or from before) gets its name and kind now
        data.chats.forEach(function (c) {
            var row = chatRowById(box, c.id);
            var nm  = row && row.querySelector('.za-chat__name');
            if (nm && !nm.value.trim() && c.name) {
                nm.value = c.name;
                row.querySelector('input[name$="[kind]"]').value = c.kind;
                row.querySelector('[data-chat-kind]').textContent = KINDS[chatKind(c.id, c.kind)][1];
                var ico = box.querySelector('[data-chats-icons] [data-k="' + chatKind(c.id, c.kind) + '"]');
                if (ico) { row.querySelector('.za-chat__ava').innerHTML = ico.innerHTML; }
                flash(row);
                dirtyCheck();
            }
        });
        head.innerHTML = '<b>' + data.chats.length + (data.chats.length === 1 ? ' chat has' : ' chats have') + ' written to ' + esc(bot) + '</b> (newest first):';
        var ul = document.createElement('ul');
        ul.className = 'za-found';
        data.chats.forEach(function (c, i) {
            var kind  = chatKind(c.id, c.kind);
            var icons = box.querySelector('[data-chats-icons] [data-k="' + kind + '"]');
            var li = document.createElement('li');
            li.style.animationDelay = (i * 40) + 'ms';
            li.innerHTML = '<span class="za-chat__ava">' + (icons ? icons.innerHTML : '') + '</span>'
                + '<span class="za-chat__text"><b>' + esc(c.name || c.id) + '</b><small>' + KINDS[kind][1] + ' · ' + esc(c.handle || c.id) + '</small></span>'
                + '<button type="button" class="za-btn za-btn--sm" data-found-add data-id="' + esc(c.id) + '"><span>Add</span></button>';
            li.querySelector('[data-found-add]').szwChat = c;
            ul.appendChild(li);
        });
        out.appendChild(ul);
        markFound(box);
    }

    function esc(t) {
        var d = document.createElement('div');
        d.textContent = String(t);
        return d.innerHTML;
    }

    function setRowState(row, tone, text) {
        var st = row.querySelector('.za-chat__state');
        st.className = 'za-chat__state' + (tone ? ' is-' + tone : '');
        st.textContent = text;
        row.classList.toggle('is-error', tone === 'error');
    }

    root.addEventListener('click', function (e) {
        var t = e.target.closest('[data-chats-find],[data-chats-manual],[data-chats-add-manual],[data-chat-test],[data-chat-remove],[data-found-add]');
        var box = chatsBox(t);
        if (!t || !box) { return; }

        if (t.hasAttribute('data-chats-find')) {
            chatsBusy(t, true);
            chatsPost(box, {'do': 'find'})
                .then(function (data) { renderFound(box, data); })
                .catch(function (err) {
                    var out = box.querySelector('.za-chats__found');
                    out.hidden = false;
                    out.innerHTML = '<p class="za-found__head is-error">' + esc(err.message) + '</p>';
                })
                .then(function () { chatsBusy(t, false); });
            return;
        }

        if (t.hasAttribute('data-chats-manual')) {
            var m = box.querySelector('.za-chats__manual');
            m.hidden = !m.hidden;
            if (!m.hidden) { m.querySelector('input').focus(); }
            return;
        }

        if (t.hasAttribute('data-chats-add-manual')) {
            var input = box.querySelector('.za-chats__manual input');
            var id = input.value.replace(/\s+/g, '');
            if (!CHAT_ID.test(id)) {
                say('A chat id is a number (groups and channels start with -100) or a channel\'s @username.', 'error');
                input.focus();
                return;
            }
            var row = addChat(box, {id: id, name: '', kind: ''});
            input.value = '';
            box.querySelector('.za-chats__manual').hidden = true;
            row.querySelector('.za-chat__name').focus();
            return;
        }

        if (t.hasAttribute('data-found-add')) {
            addChat(box, t.szwChat);
            return;
        }

        var row2 = t.closest('[data-chat]');

        if (t.hasAttribute('data-chat-remove')) {
            row2.classList.add('is-leaving');
            setTimeout(function () {
                row2.remove();
                chatsChanged(box);
            }, 220);
            say('Removed. Save to keep the change.');
            return;
        }

        if (t.hasAttribute('data-chat-test')) {
            chatsBusy(t, true);
            setRowState(row2, '', 'Sending…');
            chatsPost(box, {'do': 'test', chat: row2.querySelector('input[name$="[id]"]').value, name: row2.querySelector('.za-chat__name').value})
                .then(function (data) {
                    setRowState(row2, 'ok', 'Arrived');
                    say(data.text, 'ok');
                })
                .catch(function (err) {
                    setRowState(row2, 'error', err.message);
                })
                .then(function () { chatsBusy(t, false); });
        }
    });

    root.addEventListener('change', function (e) {
        var row = e.target.closest && e.target.closest('[data-chat]');
        if (row && e.target.type === 'checkbox') {
            row.classList.toggle('is-off', !e.target.checked);
            chatsChanged(chatsBox(row));
        }
    });

    root.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.closest && e.target.closest('.za-chats__manual')) {
            e.preventDefault();
            e.target.closest('.za-chats__manual').querySelector('[data-chats-add-manual]').click();
        }
        if (e.key === 'Enter' && e.target.classList && e.target.classList.contains('za-chat__name')) {
            e.preventDefault();   // naming a chat should not submit the form
            e.target.blur();
        }
    });

    function chats() {
        Array.prototype.forEach.call(root.querySelectorAll('[data-chats]'), chatsChanged);
    }

    /* ---- Settings: reply previews ----------------------------------------- */

    var SAMPLE_MESSAGE = 'AI key visuals for a sneaker drop, about 20 images in two weeks.';

    function fillTemplate(text, sample) {
        return text.replace(/\{first\}/g, sample.first).replace(/\{name\}/g, sample.name).replace(/\{needs\}/g, sample.needs);
    }

    function preview(box) {
        var control = box.closest('.za-field__control');
        var area    = control && control.querySelector('textarea');
        var bubble  = box.querySelector('.za-preview__bubble');
        if (!area || !bubble) { return; }
        var sample = JSON.parse(box.getAttribute('data-sample') || '{}');
        var text   = fillTemplate(area.value, sample).trim();
        bubble.textContent = '';
        if (box.getAttribute('data-preview') === 'reply_email') {
            var subj = root.querySelector('input[name="reply_subject"]');
            var line = document.createElement('p');
            line.className = 'za-preview__subject';
            line.textContent = (subj && (subj.value.trim() || subj.placeholder)) || '';
            bubble.appendChild(line);
            var body = document.createElement('p');
            body.textContent = text || '(nothing: only their message, quoted)';
            body.classList.toggle('is-empty', !text);
            bubble.appendChild(body);
            var q = document.createElement('blockquote');
            q.textContent = 'You wrote: ' + SAMPLE_MESSAGE;
            bubble.appendChild(q);
        } else {
            var p = document.createElement('p');
            p.textContent = text || '(nothing: an empty chat)';
            p.classList.toggle('is-empty', !text);
            bubble.appendChild(p);
        }
    }

    function previews() {
        Array.prototype.forEach.call(root.querySelectorAll('[data-preview]'), preview);
    }

    root.addEventListener('input', function (e) {
        if (e.target.matches && e.target.matches('.za-field__control textarea, input[name="reply_subject"]')) {
            previews();
        }
    });

    function init() {
        bulkSync();
        masonry();
        chats();
        previews();

        dirtyForm = root.querySelector('form[data-dirty-guard]');
        dirtyInitial = dirtyForm ? serialize(dirtyForm) : '';

        // a success notice on the page fades after a few seconds
        Array.prototype.forEach.call(root.querySelectorAll('[data-flash="ok"]'), function (el) {
            setTimeout(function () { el.classList.add('is-fading'); }, 5000);
        });

        // a new item arrived (optional: <div data-live="ajax URL" data-after="last id" hidden>)
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
                            a.textContent = (n === 1 ? '1 new request' : n.toLocaleString('en') + ' new requests') + ' arrived. Show';
                            live.appendChild(a);
                            document.title = '(' + n.toLocaleString('en') + ') ' + document.title.replace(/^\(.+?\) /, '');
                        }
                    })
                    .catch(function () { /* network: next time */ });
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
