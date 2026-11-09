/* ============================================================
   Karga-Ments — km.js  (easy-to-use features)
   No libraries, no internet needed. Loaded on every page by includes/header.php.

   WHERE TO EDIT WHAT (search for these headings):
     HELP TEXT       the "Need help?" steps for each page
     GUIDE RULES     what Guided mode highlights on each page
     CONFIRM TEXT    the wording of the "Are you sure?" pop-ups
     DEMO TOUR       the chapters and steps of the presenting tour
   ============================================================ */
(function () {
    'use strict';

    var KM = window.KM || { base: '', page: '', role: '', guided: false, state: {}, demo: {} };
    var MASCOT = KM.mascot || ((KM.base || '') + '/assets/img/chanelly.png');
    var NAME = 'Chanelly';

    /* ---------- small helpers ---------- */
    function $(sel, root) { return (root || document).querySelector(sel); }
    function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function url(p) { return (KM.base || '') + '/' + String(p).replace(/^\/+/, ''); }
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function visible(el) {
        if (!el) return false;
        var r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden';
    }
    function firstVisible(sel) {
        var list = $$(sel);
        for (var i = 0; i < list.length; i++) if (visible(list[i])) return list[i];
        return null;
    }
    function firstName() { return String(KM.name || '').split(' ')[0] || 'there'; }
    function store(kind) { try { return window[kind]; } catch (e) { return null; } }
    function getJSON(kind, key, def) {
        var s = store(kind); if (!s) return def;
        try { var v = JSON.parse(s.getItem(key)); return v == null ? def : v; } catch (e) { return def; }
    }
    function setJSON(kind, key, val) {
        var s = store(kind); if (!s) return;
        try { if (val == null) s.removeItem(key); else s.setItem(key, JSON.stringify(val)); } catch (e) { /* full or blocked */ }
    }
    function peso(n) { return '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function dateIn(days) {
        var d = new Date(); d.setDate(d.getDate() + days);
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function post(path, data) {
        var body = new URLSearchParams();
        body.append('csrf_token', KM.csrf || '');
        Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
        return fetch(url(path), {
            method: 'POST', body: body, credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false, message: 'Unexpected reply from the server.' }; });
        }).then(function (j) {
            if (j && j.csrf) KM.csrf = j.csrf;
            return j;
        }).catch(function () { return { ok: false, message: 'Could not reach the server. Check the internet connection.' }; });
    }

    function toast(msg, ms) {
        var t = document.createElement('div');
        t.className = 'km-toast'; t.setAttribute('role', 'status'); t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(function () { t.remove(); }, ms || 2600);
    }

    /* ---------- generic pop-up (modal) with the mascot ---------- */
    var openModal = null;
    function modal(opts) {
        closeModal();
        if (typeof Guide !== 'undefined') Guide.clear();
        var back = document.createElement('div');
        back.className = 'km-modal-back';
        back.innerHTML =
            '<div class="km-modal ' + (opts.cls || '') + '" role="dialog" aria-modal="true" aria-labelledby="km-modal-title">' +
            '<div class="km-modal-top">' + (opts.mascot === false ? '' : '<img src="' + esc(MASCOT) + '" alt="" class="km-wiggle">') +
            '<div><h2 id="km-modal-title">' + esc(opts.title || '') + '</h2>' + (opts.html || '') + '</div></div>' +
            '<div class="km-modal-actions"></div></div>';
        var actions = $('.km-modal-actions', back);
        (opts.buttons || []).forEach(function (b) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn ' + (b.cls || '');
            btn.textContent = b.label;
            if (b.yes) btn.setAttribute('data-km-yes', '');
            if (b.no) btn.setAttribute('data-km-no', '');
            btn.addEventListener('click', function () { if (b.keep !== true) closeModal(); if (b.onClick) b.onClick(); });
            actions.appendChild(btn);
        });
        if (opts.footer) actions.insertAdjacentHTML('beforeend', opts.footer);
        back.addEventListener('click', function (e) {
            if (e.target === back && opts.dismissable !== false) { closeModal(); if (opts.onCancel) opts.onCancel(); }
        });
        document.body.appendChild(back);
        openModal = { el: back, onCancel: opts.onCancel, last: document.activeElement };
        var focusEl = $('[data-km-yes]', back) || $('.km-modal-actions .btn', back) || $('.km-modal', back);
        setTimeout(function () { if (focusEl) focusEl.focus(); }, 30);
        if (opts.onOpen) opts.onOpen(back);
        return back;
    }
    function closeModal() {
        if (!openModal) return;
        var m = openModal; openModal = null;
        m.el.remove();
        if (m.last && m.last.focus) { try { m.last.focus({ preventScroll: true }); } catch (e) { /* gone */ } }
        // Bring the guided tip back once the pop-up is gone (unless the page is changing).
        setTimeout(function () { if (!openModal && !Tour.leaving && !Guide.bubble) Guide.start(); }, 600);
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && openModal) { var c = openModal.onCancel; closeModal(); if (c) c(); }
    });

    /* ============================================================
       1. "Aa" DISPLAY SETTINGS — saved on this device (localStorage).
          The inline script in includes/prepaint.php applies them before drawing.
       ============================================================ */
    var display = getJSON('localStorage', 'km_display', {});
    function applyDisplay() {
        var c = document.documentElement.classList;
        c.toggle('km-text-lg', display.size === 1);
        c.toggle('km-text-xl', display.size === 2);
        c.toggle('km-simple', !!display.simple);
        c.toggle('km-contrast', !!display.contrast);
        setJSON('localStorage', 'km_display', display);
    }

    function openAaPanel() {
        var loggedIn = !!KM.role;
        var html =
            '<div class="km-aa-panel">' +
            '<div class="km-aa-group"><strong>Text size</strong><div class="km-seg" role="group" aria-label="Text size">' +
            '<button type="button" data-size="0">A</button><button type="button" data-size="1">A+</button><button type="button" data-size="2">A++</button></div></div>' +
            '<label class="km-switch"><span><strong>Simple view</strong><small>Hides extra details and charts</small></span><input type="checkbox" id="km-opt-simple"></label>' +
            '<label class="km-switch"><span><strong>High contrast</strong><small>Black and white, easier to see</small></span><input type="checkbox" id="km-opt-contrast"></label>' +
            (loggedIn ? '<label class="km-switch"><span><strong>Guided mode</strong><small>' + NAME + ' points to what to do next (saved to your account)</small></span><input type="checkbox" id="km-opt-guided"></label>' : '') +
            '<p class="text-muted">Text size, Simple view and High contrast are saved on this device.</p>' +
            '</div>';
        var buttons = [];
        if (KM.demo && KM.demo.enabled && !KM.demo.active) {
            buttons.push({ label: 'Start the demo tour', cls: 'btn-secondary', onClick: startDemo });
        }
        if (KM.demo && KM.demo.active) {
            buttons.push({ label: 'Demo chapters', cls: 'btn-secondary', onClick: function () { Tour.chapters(); } });
        }
        buttons.push({ label: 'Done', yes: true });
        modal({ title: 'Make it easier to read', html: html, buttons: buttons, mascot: false, cls: 'km-aa-modal', onOpen: function (box) {
            function sync() {
                $$('.km-seg button', box).forEach(function (b) { b.setAttribute('aria-pressed', String(Number(b.dataset.size) === (display.size || 0))); });
                $('#km-opt-simple', box).checked = !!display.simple;
                $('#km-opt-contrast', box).checked = !!display.contrast;
                var g = $('#km-opt-guided', box); if (g) g.checked = KM.guided === true;
            }
            $$('.km-seg button', box).forEach(function (b) {
                b.addEventListener('click', function () { display.size = Number(b.dataset.size); applyDisplay(); sync(); });
            });
            $('#km-opt-simple', box).addEventListener('change', function () { display.simple = this.checked; applyDisplay(); });
            $('#km-opt-contrast', box).addEventListener('change', function () { display.contrast = this.checked; applyDisplay(); });
            var g = $('#km-opt-guided', box);
            if (g) g.addEventListener('change', function () { setGuided(this.checked); });
            sync();
        } });
    }

    /* ============================================================
       2. CONFIRM TEXT — friendly "Are you sure?" before anything that changes
          stock or money, or cannot be undone.
          In a page:  <form data-confirm="Delete this product?">  or
                      <button data-confirm="Cancel this order?" data-confirm-yes="Yes, cancel it">
          Smart wording for some forms: data-confirm-builder="stock" (see BUILDERS).
       ============================================================ */
    var BUILDERS = {
        // stock/add_transaction.php — only asks for Sales (stock goes down).
        stock: function (form) {
            var type = (form.elements.transaction_type || {}).value;
            var sel = form.elements.product_id;
            var qty = Number((form.elements.quantity || {}).value || 0);
            var name = sel && sel.selectedIndex > 0 ? sel.options[sel.selectedIndex].text.replace(/\s*\(.*\)\s*$/, '').replace(/\s*—\s*\d+ in stock$/, '') : 'this item';
            if (type === 'Sale') return { title: 'Record this sale?', text: 'This takes ' + qty + ' × ' + name + ' out of stock.', yes: 'Yes, record the sale' };
            if (type === 'Stock-In') return { title: 'Add this delivery?', text: 'This adds ' + qty + ' × ' + name + ' to stock.', yes: 'Yes, add it' };
            return null;
        },
        // purchase_orders/create_order.php — shows the order total.
        order: function (form) {
            var total = 0, lines = 0;
            $$('#itemsTable tbody tr', form).forEach(function (tr) {
                var q = Number(($('input[name="item_quantity[]"]', tr) || {}).value || 0);
                var p = Number(($('input[name="item_unit_price[]"]', tr) || {}).value || 0);
                var s = $('select', tr);
                if (s && s.value && q > 0) { total += q * p; lines++; }
            });
            var sup = form.elements.supplier_id;
            var supName = sup && sup.selectedIndex > 0 ? sup.options[sup.selectedIndex].text.trim() : 'the supplier';
            return { title: 'Send this order?', text: lines + ' item' + (lines === 1 ? '' : 's') + ' from ' + supName + '. Total: ' + peso(total) + '.', yes: 'Yes, create the order' };
        },
        // supplier_portal/submit.php
        submission: function (form) {
            var sel = form.elements.product_id;
            var name = sel && sel.selectedIndex > 0 ? sel.options[sel.selectedIndex].text.trim() : 'this item';
            return { title: 'Send this update to the store?', text: (form.elements.quantity.value || 0) + ' × ' + name + ' at ' + peso(form.elements.unit_price.value) + ' each. The store will review it.', yes: 'Yes, send it' };
        }
    };

    function askConfirm(c) {
        return new Promise(function (resolve) {
            modal({
                title: c.title || 'Are you sure?',
                html: '<p>' + esc(c.text || '') + '</p>' + (c.warn ? '<p><strong>' + esc(c.warn) + '</strong></p>' : ''),
                buttons: [
                    { label: c.yes || 'Yes', yes: true, cls: c.danger ? 'btn-danger' : '', onClick: function () { resolve(true); } },
                    { label: c.no || 'No, go back', no: true, cls: 'btn-secondary', onClick: function () { resolve(false); } }
                ],
                onCancel: function () { resolve(false); }
            });
        });
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.kmConfirmed === '1') { form.dataset.kmConfirmed = ''; return; }
        var sub = e.submitter || null;
        var c = null;
        if (sub && sub.dataset.confirm) {
            c = { text: sub.dataset.confirm, title: sub.dataset.confirmTitle, yes: sub.dataset.confirmYes, danger: sub.classList.contains('btn-danger'), warn: sub.dataset.confirmWarn };
        } else if (form.dataset.confirmBuilder && BUILDERS[form.dataset.confirmBuilder]) {
            if (!form.checkValidity()) return;     // let the browser show "please fill in"
            c = BUILDERS[form.dataset.confirmBuilder](form);
        } else if (form.dataset.confirm) {
            c = { text: form.dataset.confirm, title: form.dataset.confirmTitle, yes: form.dataset.confirmYes, danger: !!(sub && sub.classList.contains('btn-danger')), warn: form.dataset.confirmWarn };
        }
        if (!c) return;
        e.preventDefault();
        askConfirm(c).then(function (yes) {
            if (!yes) return;
            form.dataset.kmConfirmed = '1';
            if (form.requestSubmit && sub) { form.requestSubmit(sub); return; }
            if (sub && sub.name) {
                var h = document.createElement('input');
                h.type = 'hidden'; h.name = sub.name; h.value = sub.value; form.appendChild(h);
            }
            if (form.requestSubmit) form.requestSubmit(); else form.submit();
        });
    }, true);

    // Stop double-clicks from sending a form twice.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (e.defaultPrevented || !(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') return;
        setTimeout(function () {
            $$('button[type="submit"], button:not([type])', form).forEach(function (b) { b.disabled = true; });
        }, 0);
        setTimeout(function () {
            $$('button[type="submit"], button:not([type])', form).forEach(function (b) { b.disabled = false; });
        }, 8000);
    });

    /* ============================================================
       3. HELP TEXT — "Need help?" steps for each page (2 to 3 short steps).
          Key = page path. A function can give different steps per role.
       ============================================================ */
    var HELP = {
        'auth/login.php': { title: 'Signing in', steps: [
            'Type your username (your manager gives you one).',
            'Type your password. Tick “Show password” to check what you typed.',
            'Tap the big Log in button.'] },
        'index.php': function () {
            if (KM.role === 'Employee') return { title: 'Your home page', steps: [
                'When a customer buys something, tap “Record a sale”.',
                'When a delivery arrives, tap “Received a delivery”.',
                'Red numbers mean items are running low. Tell your manager.'] };
            return { title: 'Your home page', steps: [
                'Each big tile is one job. Red numbers need your attention first.',
                '“Order low items” starts an order with the amounts filled in.',
                'When a delivery arrives, open Orders and tap “Mark as delivered”.'] };
        },
        'products/list.php': { title: 'Products', steps: [
            'Type part of a name in the search box, then tap Search.',
            'Tick “Low stock only” to see what is running out (shown in red).',
            'Tap “+ Add product” to add a new item.'] },
        'products/add.php': { title: 'Adding a product', steps: [
            'Fill in the name, category and supplier.',
            'Type the price and how many you have now.',
            'Tap Save. The starting amount is recorded as a delivery.'] },
        'products/edit.php': { title: 'Changing a product', steps: [
            'Change the name, price or low-stock level.',
            'To change how many you have, record a sale or delivery instead.',
            'Tap Save changes.'] },
        'categories/list.php': { title: 'Categories', steps: [
            'Categories group products, like “Paint” or “Tools”.',
            'Managers can add, rename or delete a category.',
            'A category that still has products cannot be deleted.'] },
        'categories/add.php': { title: 'New category', steps: ['Type a short name, like “Plumbing”.', 'Tap Save.'] },
        'categories/edit.php': { title: 'Rename category', steps: ['Change the name.', 'Tap Save changes.'] },
        'suppliers/list.php': { title: 'Suppliers', steps: [
            'These are the companies you buy from.',
            'Tap Edit to update their phone or address.',
            'A supplier with products cannot be deleted.'] },
        'suppliers/add.php': { title: 'New supplier', steps: ['Type the company name.', 'Add a phone number or email so you can reach them.', 'Tap Save.'] },
        'suppliers/edit.php': { title: 'Edit supplier', steps: ['Change the details.', 'Tap Save changes.'] },
        'stock/transaction_history.php': { title: 'Sales & deliveries', steps: [
            'This list shows every sale and every delivery, newest first.',
            'Tap “+ Record a sale or delivery” to add one.',
            'Use the dates and filters to find older ones.'] },
        'stock/add_transaction.php': { title: 'Record a sale or delivery', steps: [
            'Choose the product.',
            'Choose “Sold to a customer” or “Received a delivery”.',
            'Type how many, then tap Save. I will ask you to check before saving.'] },
        'purchase_orders/view_orders.php': { title: 'Orders from suppliers', steps: [
            '“Waiting for delivery” means the items have not arrived yet.',
            'Tap View to see an order.',
            'Tap “+ New order” to buy more stock.'] },
        'purchase_orders/create_order.php': { title: 'Making an order', steps: [
            'Choose the supplier. Their low items appear in yellow. Tap Add.',
            'Check the amounts and prices.',
            'Tap Create order. I will show you the total first.'] },
        'purchase_orders/order_detail.php': { title: 'One order', steps: [
            'Check the items and the total.',
            'When everything arrives, tap “Mark as delivered”. Stock goes up by itself.',
            'If the order will not come, tap “Cancel order”.'] },
        'suppliers/review_submissions.php': { title: 'Supplier updates', steps: [
            'Suppliers send prices, amounts and delivery dates here.',
            'Add a short note if you like, then tap Approve or Not approve.',
            'After approving, you can make an order for that item.'] },
        'reports/low_stock_report.php': { title: 'Low stock report', steps: ['These items are at or below their low-stock level.', 'Tap “Order” to buy more.', 'Download CSV opens in Excel.'] },
        'reports/stock_levels_report.php': { title: 'Stock levels', steps: ['Shows how many of each item you have and what it is worth.', 'Filter by category if you like.', 'Download CSV opens in Excel.'] },
        'reports/sales_history_report.php': { title: 'Sales report', steps: ['Pick the start and end dates.', 'Tap Apply to see the totals.', 'Download CSV opens in Excel.'] },
        'reports/audit_log_report.php': { title: 'Activity log', steps: ['Every change is listed here with who did it and when.', 'Use the filters to find something.', 'Nobody can edit this list.'] },
        'users/list.php': { title: 'Users', steps: ['Each person gets their own login.', 'Tap Edit to change a role or reset a password.', 'Tap Disable when someone leaves. You can turn them back on later.'] },
        'users/add.php': { title: 'New user', steps: ['Type a username and the person\'s full name.', 'Choose a password (8 characters or more) and a role.', 'For a Supplier login, also choose their company.'] },
        'users/edit.php': { title: 'Edit user', steps: ['Change the name, role or company.', 'Leave the password empty to keep it.', 'Tap Save changes.'] },
        'admin/backup.php': { title: 'Backup', steps: ['Tap the button to download a copy of all data.', 'Keep the file somewhere safe, like Google Drive.', 'Do this at least once a week.'] },
        'supplier_portal/dashboard.php': { title: 'Your supplier page', steps: [
            'These are the items you supply to the store. Red means the store is running low.',
            'Tap “Send an update” to offer stock, a price or a delivery date.',
            'Your updates show “Waiting for review” until the store decides.'] },
        'supplier_portal/submit.php': { title: 'Sending an update', steps: [
            'Choose the item and type how many you can deliver.',
            'Type your price and the delivery date.',
            'Tap Send. The store will approve or reply.'] }
    };

    function openHelp() {
        var h = HELP[KM.page];
        if (typeof h === 'function') h = h();
        if (!h) h = { title: 'This page', steps: ['Look for the orange button. It does the main job of the page.', 'Tap Aa at the top to make text bigger.', 'Tap Home to go back to the start.'] };
        var guidedBtn = (KM.role && KM.guided !== true) ? { label: 'Turn on Guided mode', cls: 'btn-secondary', onClick: function () { setGuided(true); } } : null;
        var buttons = [{ label: 'Got it', yes: true }];
        if (guidedBtn) buttons.push(guidedBtn);
        if (KM.demo && KM.demo.enabled && !KM.demo.active && KM.page === 'index.php') {
            buttons.push({ label: 'Start the demo tour', cls: 'btn-secondary', onClick: startDemo });
        }
        modal({
            title: h.title,
            html: '<p>How to use this page:</p><ol>' + h.steps.map(function (s) { return '<li>' + esc(s) + '</li>'; }).join('') + '</ol>',
            buttons: buttons
        });
    }

    /* ============================================================
       4. GUIDED MODE — gently highlights ONE next thing, based on real account
          numbers (KM.state comes from includes/guide_state.php). No dark overlay,
          nothing is clicked for the user.
       ============================================================ */
    function plural(n, one, many) { return n + ' ' + (n === 1 ? one : (many || one + 's')); }

    // Finds the first empty required field of the page's main form, else its Save button.
    function formTip() {
        var form = $('.content form.form-box');
        if (!form) return null;
        var fields = $$('input[required], select[required], textarea[required]', form).filter(visible);
        for (var i = 0; i < fields.length; i++) {
            if (!String(fields[i].value || '').trim()) {
                var lab = form.querySelector('label[for="' + fields[i].id + '"]');
                return { el: fields[i], text: 'Next: fill in “' + (lab ? lab.textContent.trim() : 'this box') + '”.' };
            }
        }
        var save = $('[data-km="save"]', form);
        return save ? { el: save, text: 'Everything is filled in. Check it, then tap here.' } : null;
    }

    // GUIDE RULES: each returns a list of tips, most important first. "Next tip" moves along.
    var GUIDE = {
        'index.php': function (s) {
            var t = [];
            if (KM.role === 'Employee') {
                t.push({ sel: '[data-km="tile-sale"]', text: 'When a customer buys something, tap here to record the sale.' });
                if (s.low) t.push({ sel: '[data-km="tile-receive"]', text: plural(s.low, 'item is', 'items are') + ' running low. When the delivery comes, record it here.' });
                t.push({ sel: '[data-km="tile-products"]', text: 'Look up any product and how many are left.' });
                return t;
            }
            if (s.pendingSubs) t.push({ sel: '[data-km="tile-review"]', text: 'Suppliers sent ' + plural(s.pendingSubs, 'update') + '. Tap here to check them.' });
            if (s.lowNoOrder) t.push({ sel: '[data-km="tile-order"]', text: plural(s.lowNoOrder, 'item is', 'items are') + ' running low and not ordered yet. Tap here to order.' });
            if (s.pendingPO) t.push({ sel: '[data-km="tile-orders"]', text: plural(s.pendingPO, 'order is', 'orders are') + ' waiting for delivery. When it arrives, open it and tap “Mark as delivered”.' });
            if (KM.role === 'Admin' && (s.daysSinceBackup < 0 || s.daysSinceBackup > 7)) t.push({ sel: '[data-km="tile-backup"]', text: s.daysSinceBackup < 0 ? 'No backup yet. Tap here to save a copy of all data.' : 'Last backup was ' + s.daysSinceBackup + ' days ago. Time for a new one.' });
            t.push({ sel: '[data-km="tile-reports"]', text: 'No pending tasks. Check the reports to see how the store is doing.' });
            return t;
        },
        'products/list.php': function (s) {
            var t = [];
            if (s.low) t.push({ sel: 'input[name="low"]', text: plural(s.low, 'item is', 'items are') + ' low. Tick this box, then Search, to see them.' });
            t.push({ sel: 'input[name="q"]', text: 'Type part of a product name here to find it.' });
            t.push({ sel: '[data-km="main-action"]', text: 'Got a new item in the store? Add it here.' });
            return t;
        },
        'stock/transaction_history.php': function () { return [{ sel: '[data-km="main-action"]', text: 'Record a sale or a delivery here.' }]; },
        'purchase_orders/view_orders.php': function (s) {
            var t = [];
            if (s.pendingPO) t.push({ sel: 'tr[data-status="Pending"] a', text: 'This order is waiting for delivery. Open it when the items arrive.' });
            t.push({ sel: '[data-km="main-action"]', text: 'Need more stock? Start a new order here.' });
            return t;
        },
        'purchase_orders/order_detail.php': function () {
            return [{ sel: '[data-km="receive"]', text: 'When all these items arrive, tap here. Stock goes up by itself.' }];
        },
        'suppliers/review_submissions.php': function () {
            return [{ sel: '[data-km^="approve-"]', text: 'Read the offer, then tap Approve or Not approve.' }];
        },
        'reports/low_stock_report.php': function () { return [{ sel: 'a[href*="create_order.php"]', text: 'Tap “Order” next to an item to buy more.' }]; },
        'users/list.php': function () { return [{ sel: '[data-km="main-action"]', text: 'Add a login for each new staff member here.' }]; },
        'admin/backup.php': function () { return [{ sel: '[data-km="save"]', text: 'Tap here to download a copy of all data.' }]; },
        'supplier_portal/dashboard.php': function (s) {
            var t = [];
            if (s.myLow) t.push({ sel: '[data-km="tile-submit"]', text: 'The store is low on ' + plural(s.myLow, 'of your items', 'of your items') + '. Send them an offer here.' });
            if (s.myWaiting) t.push({ sel: '[data-km="my-updates"]', text: plural(s.myWaiting, 'update is', 'updates are') + ' waiting for the store to review.' });
            if (!t.length) t.push({ sel: '[data-km="tile-submit"]', text: 'Have stock, a new price or a delivery date? Send an update here.' });
            return t;
        }
    };

    var Guide = {
        tips: [], idx: 0, bubble: null, target: null,
        start: function () {
            if (KM.guided !== true || !KM.role || Tour.running()) return;
            var dismissed = getJSON('sessionStorage', 'km_guide_dismissed', {});
            if (dismissed[KM.page]) return;
            var rule = GUIDE[KM.page];
            var list = rule ? rule(KM.state || {}) : [];
            var ft = formTip();
            if (ft) list.unshift(ft);
            this.tips = list.map(function (t) { return { el: t.el || firstVisible(t.sel), text: t.text }; }).filter(function (t) { return t.el; });
            this.idx = 0;
            this.show();
        },
        show: function () {
            this.clear();
            var tip = this.tips[this.idx];
            if (!tip) return;
            var self = this;
            this.target = tip.el;
            tip.el.classList.add('km-guide-target');
            tip.el.scrollIntoView({ block: 'center', behavior: 'smooth' });
            var b = document.createElement('div');
            b.className = 'km-bubble';
            b.setAttribute('role', 'status');
            b.innerHTML = '<div class="km-bubble-top"><img src="' + esc(MASCOT) + '" alt="" class="km-wiggle"><div>' +
                '<div class="km-bubble-title">' + NAME + ' · Guide</div><p>' + esc(tip.text) + '</p></div></div>' +
                '<div class="km-bubble-actions">' +
                '<button type="button" class="btn btn-sm" data-g="ok">Got it</button>' +
                (this.tips.length > 1 ? '<button type="button" class="btn btn-sm btn-secondary" data-g="next">Next tip</button>' : '') +
                '<button type="button" class="btn btn-sm btn-secondary" data-g="off">Turn off</button></div>';
            document.body.appendChild(b);
            this.bubble = b;
            $('[data-g="ok"]', b).addEventListener('click', function () {
                var d = getJSON('sessionStorage', 'km_guide_dismissed', {}); d[KM.page] = 1;
                setJSON('sessionStorage', 'km_guide_dismissed', d); self.clear();
            });
            var nx = $('[data-g="next"]', b);
            if (nx) nx.addEventListener('click', function () { self.idx = (self.idx + 1) % self.tips.length; self.show(); });
            $('[data-g="off"]', b).addEventListener('click', function () { setGuided(false); });
            // When the highlighted field gets filled, move on to the next empty one.
            if (/^(INPUT|SELECT|TEXTAREA)$/.test(tip.el.tagName)) {
                tip.el.addEventListener('change', function onCh() { tip.el.removeEventListener('change', onCh); setTimeout(function () { self.start(); }, 150); });
            }
            this.place();
        },
        place: function () {
            if (!this.bubble || !this.target) return;
            var r = this.target.getBoundingClientRect();
            var bw = this.bubble.offsetWidth, bh = this.bubble.offsetHeight;
            var top = r.bottom + window.scrollY + 14;
            if (r.bottom + bh + 30 > window.innerHeight && r.top - bh - 14 > 0) top = r.top + window.scrollY - bh - 14;
            var left = Math.min(Math.max(12, r.left + window.scrollX + r.width / 2 - bw / 2), window.scrollX + document.documentElement.clientWidth - bw - 12);
            this.bubble.style.top = top + 'px';
            this.bubble.style.left = left + 'px';
        },
        clear: function () {
            if (this.bubble) this.bubble.remove();
            if (this.target) this.target.classList.remove('km-guide-target');
            this.bubble = null; this.target = null;
        }
    };
    window.addEventListener('resize', function () { Guide.place(); });

    function setGuided(on) {
        post('api/prefs.php', { guided: on ? 1 : 0 }).then(function (j) {
            if (!j.ok) { toast(j.message || 'Sorry, that did not save.'); return; }
            KM.guided = on;
            setJSON('sessionStorage', 'km_guide_dismissed', null);
            if (on) {
                toast(NAME + ' will point to what to do next.');
                // Guided tips need the account numbers, which are only sent when it is on.
                if (!KM.state || !Object.keys(KM.state).length) { location.reload(); return; }
                Guide.start();
            } else {
                Guide.clear();
                toast('Guided mode is off. Turn it on again with the Aa button.');
            }
        });
    }

    // First login: the mascot asks once (saved to the account).
    var ASK = {
        Employee: 'I can point to the next thing to do, like recording a sale or a delivery.',
        Manager:  'I can point to the next thing to do, like ordering low items or checking supplier updates.',
        Admin:    'I can point to the next thing to do, like checking orders, users and backups.',
        Supplier: 'I can point to the next thing to do, like sending the store an update.'
    };
    function askGuided() {
        modal({
            title: 'Welcome, ' + firstName() + '. I\'m ' + NAME + ', your guide.',
            html: '<p>Want me to guide you?</p><p>' + esc(ASK[KM.role] || ASK.Employee) + '</p><p class="text-muted">You can change this anytime with the Aa button.</p>',
            dismissable: false,
            buttons: [
                { label: 'Yes, guide me (' + KM.role + ')', yes: true, onClick: function () { setGuided(true); } },
                { label: 'No thanks', cls: 'btn-secondary', onClick: function () { setGuided(false); } }
            ]
        });
    }

    /* ============================================================
       5. DEMO TOUR — for presenting. Uses the real screens and the demo accounts.
          Step fields:
            as     demo account to switch to (username)
            page   page the step happens on
            el     what to spotlight (CSS selector; the first visible match is used)
            say    what the mascot says
            do     'none' (Continue) | 'click' | 'type' | 'select'
            value  what "Do it for me" types or selects (can be a function)
            cp     true = checkpoint ("go back to the last good step" returns here)
       ============================================================ */
    var CHAPTERS = [
        { title: 'Welcome', as: 'admin', steps: [
            { page: 'index.php', el: '.km-home-hero', say: 'Good day! I\'m Chanelly, your guide. Let me show you Karga-Ments, the inventory system for a hardware store. This is the Admin\'s home page.', cp: true },
            { page: 'index.php', el: '.km-tiles', say: 'Each big tile is one job. A red number means something needs attention.' },
            { page: 'index.php', el: '#km-aa-btn', say: 'Tap Aa anytime for bigger text, a simpler view or high contrast. It is saved on each phone or computer.' },
            { page: 'index.php', el: '#km-help-btn', say: 'Stuck? Tap me on any page and I will explain it in three short steps.' }
        ] },
        { title: 'Record a sale (Employee)', as: 'employee1', steps: [
            { page: 'index.php', el: '.km-home-hero', say: 'Now I\'m Ethan, a store employee. A customer is buying 10 bags of cement.', cp: true },
            { page: 'index.php', el: '[data-km="tile-sale"]', do: 'click', say: 'Tap “Record a sale”.' },
            { page: 'stock/add_transaction.php', el: '#product_id', do: 'select', value: '1', say: 'Choose the product. I\'ll pick Portland Cement.' },
            { page: 'stock/add_transaction.php', el: '#quantity', do: 'type', value: '10', say: 'Type how many were sold.' },
            { page: 'stock/add_transaction.php', el: '[data-km="save"]', do: 'click', say: 'Tap Save.' },
            { page: 'stock/add_transaction.php', el: '.km-modal [data-km-yes]', do: 'click', say: 'I always ask before stock goes down, so mistakes are rare. Tap Yes.' },
            { page: 'stock/transaction_history.php', el: '.data-table tbody tr', say: 'Saved. The sale is at the top with the time and who did it. Stock went down by 10 by itself.', cp: true }
        ] },
        { title: 'Order low items (Manager)', as: 'manager1', steps: [
            { page: 'index.php', el: '[data-km="tile-order"]', say: 'This is Grace, the manager. Some items are running low and nobody has ordered them yet.', cp: true },
            { page: 'index.php', el: '[data-km="tile-order"]', do: 'click', say: 'Tap “Reorder low-stock items”.' },
            { page: 'purchase_orders/create_order.php', el: '#itemsTable', say: 'I already chose the supplier and the item, with a suggested amount.', cp: true },
            { page: 'purchase_orders/create_order.php', el: '[data-km="add-low-2"]', do: 'click', say: 'Nails from the same supplier are low too. Tap Add.' },
            { page: 'purchase_orders/create_order.php', el: '#itemsTable tbody tr:first-child input[name="item_quantity[]"]', do: 'type', value: '50', say: 'You can change any amount. Let\'s order 50 steel bars.' },
            { page: 'purchase_orders/create_order.php', el: '[data-km="save"]', do: 'click', say: 'Tap “Create order”.' },
            { page: 'purchase_orders/create_order.php', el: '.km-modal [data-km-yes]', do: 'click', say: 'I show the total before anything is sent. Tap Yes.' },
            { page: 'purchase_orders/order_detail.php', el: '.badge', say: 'Done. The order now says “Waiting for delivery”.', cp: true }
        ] },
        { title: 'Supplier sends an update', as: 'supplier_pacific', steps: [
            { page: 'supplier_portal/dashboard.php', el: '.km-home-hero', say: 'Now I\'m Pacific Hardware & Paint, a supplier. Suppliers only see their own items, nothing else.', cp: true },
            { page: 'supplier_portal/dashboard.php', el: '[data-km="tile-submit"]', do: 'click', say: 'The store is low on white paint. Tap “Send an update”.' },
            { page: 'supplier_portal/submit.php', el: '#product_id', do: 'select', value: '13', say: 'Choose the item.' },
            { page: 'supplier_portal/submit.php', el: '#quantity', do: 'type', value: '50', say: 'How many can we deliver?' },
            { page: 'supplier_portal/submit.php', el: '#unit_price', do: 'type', value: '735', say: 'Our price for each one.' },
            { page: 'supplier_portal/submit.php', el: '#delivery_date', do: 'type', value: function () { return dateIn(4); }, say: 'When we can deliver.' },
            { page: 'supplier_portal/submit.php', el: '#notes', do: 'type', value: 'Can deliver Tuesday morning.', say: 'A short note for the store.' },
            { page: 'supplier_portal/submit.php', el: '[data-km="save"]', do: 'click', say: 'Tap Send.' },
            { page: 'supplier_portal/submit.php', el: '.km-modal [data-km-yes]', do: 'click', say: 'Check and tap Yes.' },
            { page: 'supplier_portal/dashboard.php', el: '[data-km="my-updates"]', say: 'Sent. It shows “Waiting for review” until the store decides.', cp: true }
        ] },
        { title: 'Approve and receive (Manager)', as: 'manager1', steps: [
            { page: 'index.php', el: '[data-km="tile-review"]', do: 'click', say: 'Back to Grace. Suppliers sent updates. Tap here.', cp: true },
            { page: 'suppliers/review_submissions.php', el: '#notes_1', do: 'type', value: 'Good price, thank you!', say: 'BuildMax offers nails at a good price. Add a short note.' },
            { page: 'suppliers/review_submissions.php', el: '[data-km="approve-1"]', do: 'click', say: 'Tap Approve.' },
            { page: 'suppliers/review_submissions.php', el: '.km-modal [data-km-yes]', do: 'click', say: 'Tap Yes.' },
            { page: 'suppliers/review_submissions.php', el: '.alert', say: 'Approved. The supplier will see the note on their page.', cp: true },
            { page: 'suppliers/review_submissions.php', el: '[data-km="nav-orders"]', do: 'click', say: 'Now a delivery truck arrived! Open Orders.' },
            { page: 'purchase_orders/view_orders.php', el: '[data-km="view-order-2"]', do: 'click', say: 'This is the VoltPro order with the drills and light bulbs. Tap View.' },
            { page: 'purchase_orders/order_detail.php', el: '[data-km="receive"]', do: 'click', say: 'Everything arrived. Tap “Mark as delivered”.' },
            { page: 'purchase_orders/order_detail.php', el: '.km-modal [data-km-yes]', do: 'click', say: 'This adds the items to stock and cannot be undone. Tap Yes.' },
            { page: 'purchase_orders/order_detail.php', el: '.alert', say: 'Stock went up by itself. No manual counting needed.', cp: true }
        ] },
        { title: 'Reports', as: 'manager1', steps: [
            { page: 'index.php', el: '[data-km="tile-reports"]', do: 'click', say: 'Last stop: reports.', cp: true },
            { page: 'reports/low_stock_report.php', el: '.report-tabs', say: 'Switch between low stock, stock levels and sales.' },
            { page: 'reports/low_stock_report.php', el: 'a[href*="export=csv"]', say: 'Download CSV opens the report in Excel, ready to print.' },
            { page: 'reports/low_stock_report.php', el: '#km-help-btn', say: 'That concludes the tour. Thank you for watching. You can start over or try it yourself.', end: true }
        ] }
    ];

    var Tour = {
        st: null, leaving: false, blocks: [], ring: null, bubble: null, target: null, raf: 0,
        running: function () { return !!(KM.demo && KM.demo.active && this.st); },
        load: function () { this.st = getJSON('sessionStorage', 'km_tour', null); },
        save: function () { setJSON('sessionStorage', 'km_tour', this.st); },
        step: function () {
            var ch = CHAPTERS[this.st.ch];
            return ch ? ch.steps[this.st.st] : null;
        },
        onPage: function (step) { return KM.page === step.page; },

        boot: function () {
            this.load();
            if (!KM.demo || !KM.demo.active) { if (this.st) { this.st = null; this.save(); } return; }
            this.bar();
            if (!this.st) { this.chapters(); return; }
            this.render();
        },

        bar: function () {
            var b = document.createElement('div');
            b.className = 'km-demo-bar';
            b.innerHTML = '<span>Demo mode</span><button type="button" data-t="ch">Chapters</button><button type="button" data-t="exit">Exit demo</button>';
            document.body.appendChild(b);
            document.body.classList.add('km-demo-on');
            var self = this;
            $('[data-t="ch"]', b).addEventListener('click', function () { self.chapters(); });
            $('[data-t="exit"]', b).addEventListener('click', function () { self.exit(); });
        },

        render: function () {
            var self = this;
            this.clear();
            var step = this.step();
            if (!step) { this.st = null; this.save(); return; }
            var ch = CHAPTERS[this.st.ch];
            if (ch.as && ch.as !== KM.username) {
                this.say('Switching account…', 'One moment, I\'m signing in as the next person.');
                post('api/demo.php', { action: 'switch', as: ch.as }).then(function (j) {
                    if (!j.ok) { self.problem(j.message); return; }
                    self.st.nav = true; self.save();
                    location.href = url(step.page);
                });
                return;
            }
            if (!this.onPage(step)) {
                if (this.st.nav) { this.st.nav = false; this.save(); location.href = url(step.page); return; }
                setTimeout(function () { if (!self.leaving) self.offScript(); }, 900);
                return;
            }
            this.st.nav = false; this.save();
            this.waitFor(step.el, 3000).then(function (el) {
                if (self.leaving) return;
                if (!el) { self.offScript(); return; }
                if (step.cp) { self.st.good = { ch: self.st.ch, st: self.st.st }; self.save(); }
                self.spot(el, step);
            });
        },

        waitFor: function (sel, ms) {
            return new Promise(function (resolve) {
                var t0 = Date.now();
                (function poll() {
                    var el = firstVisible(sel);
                    if (el) return resolve(el);
                    if (Date.now() - t0 > ms) return resolve(null);
                    setTimeout(poll, 120);
                })();
            });
        },

        advance: function () {
            var ch = CHAPTERS[this.st.ch];
            this.st.st++;
            if (this.st.st >= ch.steps.length) { this.st.ch++; this.st.st = 0; this.st.nav = true; }
            this.save();
            var self = this;
            setTimeout(function () { if (!self.leaving) self.render(); }, 350);
        },

        spot: function (el, step) {
            var self = this;
            this.target = el;
            el.scrollIntoView({ block: 'center' });
            var pad = 6;
            for (var i = 0; i < 4; i++) {
                var d = document.createElement('div');
                d.className = 'km-block';
                d.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); self.nudge(); });
                document.body.appendChild(d);
                this.blocks.push(d);
            }
            this.ring = document.createElement('div');
            this.ring.className = 'km-spot-ring';
            document.body.appendChild(this.ring);

            var b = document.createElement('div');
            b.className = 'km-bubble';
            b.style.position = 'fixed';
            var chap = CHAPTERS[this.st.ch];
            var lastInChapter = this.st.st === chap.steps.length - 1;
            var actions = '';
            if (step.do && step.do !== 'none') actions += '<button type="button" class="btn btn-sm" data-t="do">Do it for me</button>';
            if (!step.do || step.do === 'none' || step.do === 'type' || step.do === 'select') {
                actions += '<button type="button" class="btn btn-sm ' + (step.do && step.do !== 'none' ? 'btn-secondary' : '') + '" data-t="go">' +
                    (step.end ? 'Start over' : (lastInChapter ? 'Next chapter ›' : 'Continue ›')) + '</button>';
            }
            if (step.end) actions += '<button type="button" class="btn btn-sm btn-secondary" data-t="exit">Exit demo</button>';
            b.innerHTML = '<div class="km-bubble-top"><img src="' + esc(MASCOT) + '" alt="" class="km-wiggle"><div>' +
                '<div class="km-bubble-title">' + esc(chap.title) + ' · ' + (this.st.st + 1) + '/' + chap.steps.length + '</div>' +
                '<p>' + esc(step.say) + '</p></div></div><div class="km-bubble-actions">' + actions + '</div>';
            document.body.appendChild(b);
            this.bubble = b;

            var doBtn = $('[data-t="do"]', b), goBtn = $('[data-t="go"]', b), exBtn = $('[data-t="exit"]', b);
            if (exBtn) exBtn.addEventListener('click', function () { self.exit(); });
            if (step.do === 'click') {
                var onClick = function () { el.removeEventListener('click', onClick, true); self.clear(); self.advance(); };
                el.addEventListener('click', onClick, true);
                doBtn.addEventListener('click', function () { self.clear(); el.click(); });
            } else if (step.do === 'type' || step.do === 'select') {
                var val = typeof step.value === 'function' ? step.value() : step.value;
                doBtn.addEventListener('click', function () {
                    doBtn.disabled = true;
                    self.fill(el, val).then(function () { self.clear(); self.advance(); });
                });
                goBtn.addEventListener('click', function () {
                    if (!String(el.value || '').trim()) { self.nudge('Type it in first, or tap “Do it for me”.'); return; }
                    self.clear(); self.advance();
                });
            }
            if (goBtn && (!step.do || step.do === 'none')) {
                goBtn.addEventListener('click', function () {
                    if (step.end) { self.jump(0); return; }
                    self.clear(); self.advance();
                });
            }
            var loop = function () { self.layout(); self.raf = requestAnimationFrame(loop); };
            loop();
            setTimeout(function () { (doBtn || goBtn || b).focus(); }, 60);
        },

        layout: function () {
            if (!this.target || !this.ring) return;
            var r = this.target.getBoundingClientRect(), pad = 6;
            var W = window.innerWidth, H = window.innerHeight;
            var t = Math.max(0, r.top - pad), l = Math.max(0, r.left - pad), bt = Math.min(H, r.bottom + pad), rt = Math.min(W, r.right + pad);
            var s = [
                [0, 0, W, t], [0, bt, W, H - bt], [0, t, l, bt - t], [rt, t, W - rt, bt - t]
            ];
            this.blocks.forEach(function (d, i) {
                d.style.left = s[i][0] + 'px'; d.style.top = s[i][1] + 'px';
                d.style.width = Math.max(0, s[i][2]) + 'px'; d.style.height = Math.max(0, s[i][3]) + 'px';
            });
            this.ring.style.left = l + 'px'; this.ring.style.top = t + 'px';
            this.ring.style.width = (rt - l) + 'px'; this.ring.style.height = (bt - t) + 'px';
            var b = this.bubble; if (!b) return;
            var bw = b.offsetWidth, bh = b.offsetHeight;
            var top = bt + 12;
            if (top + bh > H - 8) top = t - bh - 12;
            if (top < 52) top = Math.min(H - bh - 8, Math.max(52, bt + 12));
            if (top + bh > H - 8) top = H - bh - 8;      // tall targets: keep the bubble on screen
            var left = Math.min(Math.max(12, r.left + r.width / 2 - bw / 2), W - bw - 12);
            b.style.top = top + 'px'; b.style.left = left + 'px';
        },

        fill: function (el, val) {
            return new Promise(function (resolve) {
                el.focus();
                if (el.tagName === 'SELECT' || el.type === 'date') {
                    el.value = val;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    return setTimeout(resolve, 450);
                }
                el.value = '';
                var s = String(val), i = 0;
                (function next() {
                    if (i >= s.length) {
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                        return setTimeout(resolve, 300);
                    }
                    el.value += s.charAt(i++);
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    setTimeout(next, 70);
                })();
            });
        },

        nudge: function (msg) {
            if (!this.bubble) return;
            var img = $('img', this.bubble);
            img.classList.remove('km-wiggle'); void img.offsetWidth; img.classList.add('km-wiggle');
            toast(msg || 'Tap the highlighted spot, or “Do it for me”.');
        },

        say: function (title, text) {
            this.clear();
            var b = document.createElement('div');
            b.className = 'km-bubble km-bubble-fixed';
            b.innerHTML = '<div class="km-bubble-top"><img src="' + esc(MASCOT) + '" alt="" class="km-wiggle"><div><div class="km-bubble-title">' + esc(title) + '</div><p>' + esc(text) + '</p></div></div>';
            document.body.appendChild(b);
            this.bubble = b;
        },

        clear: function () {
            cancelAnimationFrame(this.raf);
            this.blocks.forEach(function (d) { d.remove(); });
            this.blocks = [];
            if (this.ring) this.ring.remove();
            if (this.bubble) this.bubble.remove();
            this.ring = this.bubble = this.target = null;
        },

        offScript: function () {
            var self = this;
            this.clear();
            var good = this.st.good && this.st.good.ch === this.st.ch ? this.st.good : { ch: this.st.ch, st: 0 };
            modal({
                title: 'We went off the planned steps',
                html: '<p>How would you like to continue?</p>',
                dismissable: false,
                buttons: [
                    { label: 'Go back to the last good step', onClick: function () { self.goTo(good.ch, good.st, false); } },
                    { label: 'Restart this chapter', cls: 'btn-secondary', onClick: function () { self.jump(self.st.ch); } },
                    { label: 'Start over from the beginning', cls: 'btn-secondary', onClick: function () { self.jump(0); } },
                    { label: 'Exit demo', cls: 'btn-secondary', onClick: function () { self.exit(); } }
                ]
            });
        },

        problem: function (msg) {
            var self = this;
            this.clear();
            modal({ title: 'The demo hit a snag', html: '<p>' + esc(msg || 'Something went wrong.') + '</p>', buttons: [
                { label: 'Restart this chapter', onClick: function () { self.jump(self.st ? self.st.ch : 0); } },
                { label: 'Exit demo', cls: 'btn-secondary', onClick: function () { self.exit(); } }
            ] });
        },

        // Go to a step without touching the data.
        goTo: function (ch, st) {
            this.st = { ch: ch, st: st, good: this.st && this.st.good, nav: true };
            this.save();
            this.render();
        },

        // Start a chapter with freshly reset demo data.
        jump: function (ch) {
            var self = this;
            this.clear();
            this.say('Resetting the demo…', 'Putting all the demo data back so we start clean.');
            post('api/demo.php', { action: 'reset' }).then(function (j) {
                if (!j.ok) { self.problem(j.message); return; }
                self.st = { ch: ch, st: 0, nav: true };
                self.save();
                self.render();
            });
        },

        chapters: function () {
            var self = this;
            this.clear();
            var cur = this.st ? this.st.ch : -1;
            modal({
                title: 'Demo chapters',
                html: '<p>Pick a chapter. The demo data is reset each time so it can be repeated.</p><ol class="km-chapters">' +
                    CHAPTERS.map(function (c, i) { return '<li><button type="button" data-ch="' + i + '"' + (i === cur ? ' class="current"' : '') + '>' + (i + 1) + '. ' + esc(c.title) + '</button></li>'; }).join('') + '</ol>',
                buttons: [{ label: 'Exit demo', cls: 'btn-secondary', onClick: function () { self.exit(); } }],
                onCancel: function () { if (self.st) self.render(); },
                onOpen: function (box) {
                    $$('[data-ch]', box).forEach(function (b) {
                        b.addEventListener('click', function () { closeModal(); self.jump(Number(b.dataset.ch)); });
                    });
                }
            });
        },

        exit: function () {
            this.clear();
            closeModal();
            this.st = null; this.save();
            post('api/demo.php', { action: 'exit' }).then(function () { location.href = url('index.php'); });
        }
    };
    window.addEventListener('beforeunload', function () { Tour.leaving = true; });
    window.addEventListener('pageshow', function (e) { if (e.persisted) location.reload(); });

    function startDemo() {
        askConfirm({
            title: 'Start the demo tour?',
            text: 'This replaces ALL data with the demo data (products, orders, sales, logs) and switches between the demo accounts.',
            warn: 'Download a backup first if this site has real data.',
            yes: 'Yes, start the demo', danger: true
        }).then(function (yes) {
            if (!yes) return;
            post('api/demo.php', { action: 'start' }).then(function (j) {
                if (!j.ok) { toast(j.message || 'Could not start the demo.', 4000); return; }
                setJSON('sessionStorage', 'km_tour', { ch: 0, st: 0, nav: true });
                location.href = url('index.php');
            });
        });
    }

    /* ============================================================
       6. PAGE SET-UP
       ============================================================ */
    function tagPage() {
        // Main button of the page and Save button of the main form, for tips and the tour.
        var main = $('.content .page-header .btn:not(.btn-secondary)');
        if (main && !main.dataset.km) main.dataset.km = 'main-action';
        var form = $('.content form.form-box');
        if (form) {
            var save = $('button[type="submit"]:not(.btn-danger)', form) || $('button[type="submit"]', form);
            if (save && !save.dataset.km) save.dataset.km = 'save';
        }
    }

    function init() {
        applyDisplay();
        tagPage();
        var aa = $('#km-aa-btn'); if (aa) aa.addEventListener('click', openAaPanel);
        var help = $('#km-help-btn'); if (help) help.addEventListener('click', openHelp);
        var sheet = $('#km-more');
        if (sheet) {
            $$('[data-km-more]').forEach(function (more) {
                more.addEventListener('click', function () { sheet.hidden = false; var c = $('[data-km-close]', sheet); if (c) c.focus(); });
            });
            sheet.addEventListener('click', function (e) { if (e.target === sheet || e.target.closest('[data-km-close]')) sheet.hidden = true; });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') sheet.hidden = true; });
        }
        // Show password toggles (login, users).
        $$('[data-km-showpw]').forEach(function (cb) {
            var target = document.getElementById(cb.getAttribute('data-km-showpw'));
            cb.addEventListener('change', function () { if (target) target.type = cb.checked ? 'text' : 'password'; });
        });

        Tour.boot();
        if (Tour.running()) return;
        if (KM.role && KM.guided === null && !(KM.demo && KM.demo.active)) { setTimeout(askGuided, 500); return; }
        setTimeout(function () { Guide.start(); }, 400);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    window.KMUI = { confirm: askConfirm, help: openHelp, tour: Tour, guide: Guide };
})();
