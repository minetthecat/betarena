(function () {
    'use strict';

    const TYPES = {
        deposit: 'Vklad',
        withdraw: 'Výběr',
        bet: 'Sázka',
        win: 'Výhra',
        bonus: 'Bonus',
        refund: 'Vrácení'
    };

    function dataOf(response) {
        return response && response.data !== undefined ? response.data : response;
    }

    function listOf(response, key) {
        const data = dataOf(response);
        if (Array.isArray(data)) return data;
        if (data && Array.isArray(data[key])) return data[key];
        return [];
    }

    function errorText(error) {
        return error && error.message ? error.message : 'Požadavek se nepodařilo dokončit.';
    }

    function setStatus(element, message) {
        if (element) element.textContent = message || '';
    }

    function setBusy(form, busy) {
        if (!form) return;
        form.querySelectorAll('button[type="submit"]').forEach(function (button) {
            button.disabled = busy;
        });
    }

    function isValidAmount(value) {
        return /^(?:0|[1-9][0-9]{0,15})(?:\.[0-9]{1,2})?$/.test(String(value || '').trim()) &&
            /[1-9]/.test(String(value || ''));
    }

    function toCents(value) {
        const parts = String(value || '0').split('.');
        return Number(parts[0] || 0) * 100 + Number((parts[1] || '').padEnd(2, '0').slice(0, 2));
    }

    function formatMoney(value, currency) {
        const amount = Number(value || 0);
        const code = String(currency || 'CZK').toUpperCase();

        if (!Number.isFinite(amount)) return '—';
        if (code === 'BSC') {
            return new Intl.NumberFormat('cs-CZ', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount) + ' BONUS';
        }

        try {
            return new Intl.NumberFormat('cs-CZ', {
                style: 'currency',
                currency: code
            }).format(amount);
        } catch (error) {
            return new Intl.NumberFormat('cs-CZ', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount) + ' ' + code;
        }
    }

    async function loadWallets() {
        return listOf(await getWallets(), 'wallets');
    }

    async function loadMethods(capability) {
        const methods = listOf(await getPaymentMethods({ page: 1, limit: 100 }), 'payment_methods');
        return methods.filter(function (method) {
            if (method.is_active === false || Number(method.is_active) === 0) return false;
            if (capability && !Boolean(method[capability])) return false;
            return true;
        });
    }

    function getCurrency(wallet) {
        return String(wallet.currency || '').toUpperCase();
    }

    function balanceOf(wallets, currency) {
        const wallet = wallets.find(function (item) {
            return getCurrency(item) === String(currency).toUpperCase();
        });
        return wallet ? wallet.balance : '0';
    }

    function updateBalanceHint(form, wallets) {
        const hint = form.querySelector('[data-wallet-balance-hint]');
        if (!hint) return;
        const currency = form.elements.currency ? form.elements.currency.value : 'CZK';
        hint.textContent = 'Dostupný zůstatek: ' + formatMoney(balanceOf(wallets, currency), currency) + '.';
    }

    function methodType(method) {
        const value = String(method.code || method.type || '').toLowerCase();
        if (value.includes('card')) return 'card';
        if (value.includes('bsc') || value.includes('crypto')) return 'bsc';
        return 'bank';
    }

    function mask(value, left, right) {
        const text = String(value || '');
        if (!text) return '—';
        if (text.length <= left + right) return '•'.repeat(text.length);
        return text.slice(0, left) + '•'.repeat(text.length - left - right) + text.slice(-right);
    }

    function field(labelText, name, type, value) {
        const label = document.createElement('label');
        label.className = 'wallet-deposit-field';
        label.appendChild(document.createTextNode(labelText));

        const input = document.createElement('input');
        input.name = name;
        input.type = type || 'text';
        if (value !== undefined) input.value = value;
        label.appendChild(input);
        return label;
    }

    function createMoneyForm(method, action, wallets) {
        const form = document.createElement('form');
        form.className = 'wallet-deposit-form';
        form.dataset.walletAction = action;
        form.dataset.methodId = method.method_id || '';

        const amount = field('Částka', 'amount', 'number');
        amount.querySelector('input').min = '0.01';
        amount.querySelector('input').step = '0.01';
        amount.querySelector('input').required = true;

        const currencyLabel = document.createElement('label');
        currencyLabel.className = 'wallet-deposit-field';
        currencyLabel.appendChild(document.createTextNode('Měna'));

        const select = document.createElement('select');
        select.name = 'currency';

        (action === 'withdraw' ? wallets.map(getCurrency) : ['CZK', 'EUR', 'BSC'])
            .filter(function (currency, index, values) {
                return currency && values.indexOf(currency) === index;
            })
            .forEach(function (currency) {
                const option = document.createElement('option');
                option.value = currency;
                option.textContent = currency;
                select.appendChild(option);
            });

        if (!select.options.length) {
            const option = document.createElement('option');
            option.value = 'CZK';
            option.textContent = 'CZK';
            select.appendChild(option);
        }

        currencyLabel.appendChild(select);

        const hint = document.createElement('p');
        hint.className = 'wallet-balance-hint';
        hint.dataset.walletBalanceHint = '';

        const submit = document.createElement('button');
        submit.className = 'wallet-deposit-submit';
        submit.type = 'submit';
        submit.textContent = action === 'deposit' ? 'Vložit' : 'Vybrat';

        const status = document.createElement('p');
        status.className = 'wallet-deposit-message';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');

        form.append(amount, currencyLabel);
        if (action === 'withdraw') form.appendChild(hint);
        form.append(submit, status);

        select.addEventListener('change', function () {
            updateBalanceHint(form, wallets);
        });
        updateBalanceHint(form, wallets);

        return form;
    }

    function methodLabel(method) {
        const type = methodType(method);
        if (type === 'card') return 'Platební karta';
        if (type === 'bsc') return 'Krypto převod · BSC';
        return 'Bankovní převod';
    }

    function buildMethodItem(method, details) {
        const box = document.createElement('details');
        box.className = 'wallet-method-collapse';

        const summary = document.createElement('summary');
        summary.className = 'wallet-method-summary';

        const icon = document.createElement('span');
        icon.className = 'wallet-method-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = methodType(method).toUpperCase();

        const text = document.createElement('span');
        text.className = 'wallet-method-summary-text';

        const title = document.createElement('span');
        title.className = 'wallet-method-summary-title';
        title.textContent = method.name || methodLabel(method);

        const description = document.createElement('span');
        description.className = 'wallet-method-summary-description';
        description.textContent = methodLabel(method);

        text.append(title, description);
        summary.append(icon, text);
        box.append(summary, details);
        return box;
    }

    function methodPayload(form) {
        const values = new FormData(form);
        const type = form.dataset.method || form.dataset.addMethod;
        const payload = {
            code: type,
            name: String(values.get('name') || '').trim(),
            can_deposit: true,
            can_withdraw: true,
            is_active: true
        };

        [
            'recipient_account_number',
            'recipient_bank_code',
            'recipient_iban',
            'recipient_bic',
            'recipient_bank_name',
            'recipient_bank_address',
            'recipient_crypto_address',
            'card_holder_name',
            'card_number',
            'card_expiry'
        ].forEach(function (key) {
            const value = values.get(key);
            if (typeof value === 'string' && value.trim()) payload[key] = value.trim();
        });

        // CVV намеренно nikdy neposíláme ani neukládáme.
        return payload;
    }

    function bankProblem(form) {
        const account = form.elements.recipient_account_number;
        const bankCode = form.elements.recipient_bank_code;
        const iban = form.elements.recipient_iban;

        const hasAccount = account && account.value.trim() && bankCode && bankCode.value.trim();
        const hasIban = iban && iban.value.trim();

        return hasAccount || hasIban
            ? ''
            : 'Vyplňte IBAN nebo číslo účtu společně s kódem banky.';
    }

    function renderMoneyForms(container, methods, action, wallets, emptyText) {
        container.replaceChildren();

        if (!methods.length) {
            const p = document.createElement('p');
            p.className = 'wallet-empty';
            p.textContent = emptyText;
            container.appendChild(p);
            return;
        }

        methods.forEach(function (method) {
            const details = document.createElement('div');
            details.className = 'wallet-saved-methods';

            const form = createMoneyForm(method, action, wallets);
            details.appendChild(form);
            container.appendChild(buildMethodItem(method, details));
        });
    }

    function transactionDate(value) {
        if (!value) return '—';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return String(value);

        return new Intl.DateTimeFormat('cs-CZ', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    }

    function renderHistory(transactions) {
        const body = document.getElementById('wallet-history-body');
        if (!body) return;

        body.replaceChildren();

        if (!transactions.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 4;
            cell.textContent = 'Zatím nemáte žádné transakce.';
            row.appendChild(cell);
            body.appendChild(row);
            return;
        }

        transactions.forEach(function (transaction) {
            const row = document.createElement('tr');
            const date = document.createElement('td');
            date.textContent = transactionDate(transaction.created_at);
            const type = document.createElement('td');
            type.textContent = TYPES[String(transaction.type || '').toLowerCase()] || transaction.type || 'Transakce';
            const method = document.createElement('td');
            method.textContent = transaction.payment_method_name || transaction.method_name || '—';
            const amountCell = document.createElement('td');
            amountCell.className = 'wallet-amount';

            const amount = Number(transaction.amount || 0);
            const outgoing = ['withdraw', 'bet'].includes(String(transaction.type || '').toLowerCase());
            const signed = outgoing ? -Math.abs(amount) : Math.abs(amount);
            amountCell.textContent = (signed > 0 ? '+' : '') +
                formatMoney(signed, transaction.currency || 'CZK');

            row.append(date, type, method, amountCell);
            body.appendChild(row);
        });
    }

    async function loadOverview() {
        const status = document.getElementById('wallet-balances-status');

        try {
            const results = await Promise.all([
                loadWallets(),
                getTransactions({ page: 1, limit: 20 })
            ]);

            const wallets = results[0];
            const czk = document.getElementById('wallet-balance-czk');
            const bonus = document.getElementById('wallet-balance-bonus');

            if (czk) czk.textContent = formatMoney(balanceOf(wallets, 'CZK'), 'CZK');
            if (bonus) bonus.textContent = formatMoney(balanceOf(wallets, 'BSC'), 'BSC');

            const transactions = listOf(results[1], 'transactions');
            renderHistory(transactions);

            if (status) {
                status.textContent = '';
                status.hidden = true;
            }
        } catch (error) {
            if (status) {
                status.textContent = errorText(error);
                status.hidden = false;
            }

            const body = document.getElementById('wallet-history-body');
            if (body) {
                body.replaceChildren();
                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = 4;
                cell.textContent = 'Historii transakcí se nepodařilo načíst.';
                row.appendChild(cell);
                body.appendChild(row);
            }
        }
    }

    function methodDetails(method) {
        const box = document.createElement('div');
        box.className = 'wallet-deposit-form';

        const type = methodType(method);
        const info = document.createElement('p');
        info.className = 'wallet-deposit-message';

        if (type === 'card') {
            info.textContent = 'Karta •••• ' + (method.card_last4 || '—') +
                (method.card_holder_name ? ' · ' + method.card_holder_name : '');
        } else if (type === 'bsc') {
            info.textContent = 'Adresa: ' + mask(method.recipient_crypto_address, 6, 4);
        } else {
            info.textContent = method.recipient_iban
                ? 'IBAN: ' + mask(method.recipient_iban, 2, 4)
                : 'Číslo účtu: ' + mask(method.recipient_account_number, 0, 4);
        }

        const rename = document.createElement('form');
        rename.className = 'wallet-deposit-form';
        rename.dataset.renameMethod = method.method_id;
        const name = field('Název metody', 'name', 'text', method.name || '');
        name.querySelector('input').maxLength = 100;
        name.querySelector('input').required = true;

        const renameButton = document.createElement('button');
        renameButton.type = 'submit';
        renameButton.className = 'wallet-deposit-submit';
        renameButton.textContent = 'Uložit název';
        rename.append(name, renameButton);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'wallet-btn-danger wallet-btn-small';
        remove.dataset.removeMethod = method.method_id;
        remove.dataset.methodName = method.name || '';
        remove.textContent = 'Odebrat metodu';

        box.append(info, rename, remove);
        return box;
    }

    async function renderMethodsPage() {
        const list = document.getElementById('saved-methods');
        if (!list) return;

        try {
            const methods = await loadMethods();
            list.replaceChildren();

            if (!methods.length) {
                const empty = document.createElement('p');
                empty.className = 'wallet-empty';
                empty.textContent = 'Zatím nemáte žádnou platební metodu. Přidejte ji níže.';
                list.appendChild(empty);
                return;
            }

            methods.forEach(function (method) {
                list.appendChild(buildMethodItem(method, methodDetails(method)));
            });
        } catch (error) {
            list.textContent = errorText(error);
        }
    }

    async function submitMoneyForm(form) {
        const status = form.querySelector('[role="status"]');
        const action = form.dataset.walletAction;
        const amount = form.elements.amount.value.trim();
        const currency = form.elements.currency.value;

        if (!isValidAmount(amount)) {
            setStatus(status, 'Zadejte platnou částku větší než nula (nejvýše 2 desetinná místa).');
            return;
        }

        setBusy(form, true);
        setStatus(status, 'Zpracovávám…');

        try {
            if (action === 'withdraw') {
                const wallets = await loadWallets();
                if (toCents(amount) > toCents(balanceOf(wallets, currency))) {
                    throw new Error('Nedostatek prostředků. Dostupný zůstatek je ' +
                        formatMoney(balanceOf(wallets, currency), currency) + '.');
                }

                await withdrawFromWallet({ currency: currency, amount: amount });
                setStatus(status, 'Výběr byl zpracován.');
            } else {
                const wallets = await loadWallets();
                if (!wallets.some(function (wallet) { return getCurrency(wallet) === currency; })) {
                    await createWallet({ currency: currency });
                }

                await depositToWallet({ currency: currency, amount: amount });
                setStatus(status, 'Vklad byl připsán.');
            }

            form.reset();
            if (document.getElementById('wallet-balances')) await loadOverview();
        } catch (error) {
            setStatus(status, errorText(error));
        } finally {
            setBusy(form, false);
        }
    }

    async function submitNewMethodForm(form) {
        const status = form.querySelector('[role="status"]');
        const type = form.dataset.method || form.dataset.addMethod;

        if (type === 'bank') {
            const problem = bankProblem(form);
            if (problem) {
                setStatus(status, problem);
                return;
            }
        }

        setBusy(form, true);
        setStatus(status, 'Ukládám…');

        try {
            await createPaymentMethod(methodPayload(form));
            form.reset();
            setStatus(status, 'Platební metoda byla uložena.');

            const saved = document.getElementById('saved-methods');
            if (saved) await renderMethodsPage();
        } catch (error) {
            setStatus(status, errorText(error));
        } finally {
            setBusy(form, false);
        }
    }

    async function renderDepositPage() {
        const box = document.getElementById('saved-methods');
        if (!box) return;

        try {
            const results = await Promise.all([loadMethods('can_deposit'), loadWallets()]);
            renderMoneyForms(
                box,
                results[0],
                'deposit',
                results[1],
                'Zatím nemáte žádnou uloženou platební metodu. Přidejte ji níže.'
            );
        } catch (error) {
            box.textContent = errorText(error);
        }
    }

    async function renderWithdrawPage() {
        const box = document.getElementById('saved-methods');
        if (!box) return;

        try {
            const results = await Promise.all([loadMethods('can_withdraw'), loadWallets()]);
            renderMoneyForms(
                box,
                results[0],
                'withdraw',
                results[1],
                'Nemáte žádnou platební metodu pro výběr. Přidejte ji v sekci Platební metody.'
            );
        } catch (error) {
            box.textContent = errorText(error);
        }
    }

    async function onSubmit(event) {
        const form = event.target;

        if (form.matches('[data-wallet-action]')) {
            event.preventDefault();
            await submitMoneyForm(form);
            return;
        }

        if (form.matches('[data-new-method="true"]')) {
            event.preventDefault();

            const save = form.elements.save_method;
            if (save && !save.checked) {
                const status = form.querySelector('[role="status"]');
                const currency = form.elements.currency.value;
                const amount = form.elements.amount.value.trim();

                if (!isValidAmount(amount)) {
                    setStatus(status, 'Zadejte platnou částku větší než nula (nejvýše 2 desetinná místa).');
                    return;
                }

                setBusy(form, true);
                setStatus(status, 'Zpracovávám…');
                try {
                    const wallets = await loadWallets();
                    if (!wallets.some(function (wallet) { return getCurrency(wallet) === currency; })) {
                        await createWallet({ currency: currency });
                    }
                    await depositToWallet({ currency: currency, amount: amount });
                    setStatus(status, 'Vklad byl připsán.');
                    form.reset();
                } catch (error) {
                    setStatus(status, errorText(error));
                } finally {
                    setBusy(form, false);
                }
                return;
            }

            // Uloží metodu a následně provede vklad přes existující wallet API.
            const status = form.querySelector('[role="status"]');
            const currency = form.elements.currency.value;
            const amount = form.elements.amount.value.trim();

            if (!isValidAmount(amount)) {
                setStatus(status, 'Zadejte platnou částku větší než nula (nejvýše 2 desetinná místa).');
                return;
            }

            setBusy(form, true);
            setStatus(status, 'Zpracovávám…');

            try {
                await createPaymentMethod(methodPayload(form));
                const wallets = await loadWallets();
                if (!wallets.some(function (wallet) { return getCurrency(wallet) === currency; })) {
                    await createWallet({ currency: currency });
                }
                await depositToWallet({ currency: currency, amount: amount });
                setStatus(status, 'Platební metoda byla uložena a vklad byl připsán.');
                form.reset();
                await renderDepositPage();
            } catch (error) {
                setStatus(status, errorText(error));
            } finally {
                setBusy(form, false);
            }
            return;
        }

        if (form.matches('[data-add-method]')) {
            event.preventDefault();
            await submitNewMethodForm(form);
            return;
        }

        if (form.matches('[data-rename-method]')) {
            event.preventDefault();
            const status = document.getElementById('methods-status');

            try {
                await updatePaymentMethod({
                    method_id: form.dataset.renameMethod,
                    name: form.elements.name.value.trim()
                });
                setStatus(status, 'Název platební metody byl změněn.');
                await renderMethodsPage();
            } catch (error) {
                setStatus(status, errorText(error));
            }
        }
    }

    async function onClick(event) {
        const button = event.target.closest('[data-remove-method]');
        if (!button) return;

        const id = button.dataset.removeMethod;
        const name = button.dataset.methodName || 'platební metodu';
        if (!window.confirm('Opravdu chcete odebrat platební metodu „' + name + '“?')) return;

        try {
            await deletePaymentMethod({ method_id: id });
            setStatus(document.getElementById('methods-status'), 'Platební metoda byla odebrána.');
            await renderMethodsPage();
        } catch (error) {
            if (error.status === 409 && window.confirm(
                'Metodu nelze smazat, protože je použitá v transakcích. Chcete ji deaktivovat?'
            )) {
                try {
                    await updatePaymentMethod({ method_id: id, is_active: false });
                    setStatus(document.getElementById('methods-status'), 'Platební metoda byla deaktivována.');
                    await renderMethodsPage();
                } catch (deactivateError) {
                    setStatus(document.getElementById('methods-status'), errorText(deactivateError));
                }
            } else {
                setStatus(document.getElementById('methods-status'), errorText(error));
            }
        }
    }

    function init() {
        document.addEventListener('submit', onSubmit);
        document.addEventListener('click', onClick);

        if (document.getElementById('wallet-balances')) loadOverview();
        if (document.getElementById('saved-methods')) {
            if (document.querySelector('[data-new-method="true"]')) renderDepositPage();
            else if (document.querySelector('[data-add-method]')) renderMethodsPage();
            else renderWithdrawPage();
        }

        // Po úspěšném vkladu nebo výběru obnoví přehled zůstatků a historii.
        document.addEventListener('wallet:refresh', loadOverview);
    }

    window.WalletUI = {
        loadWallets: loadWallets,
        loadMethods: loadMethods,
        renderMoneyForms: renderMoneyForms,
        methodPayload: methodPayload,
        bankProblem: bankProblem,
        setStatus: setStatus,
        setBusy: setBusy,
        isValidAmount: isValidAmount,
        toCents: toCents,
        formatMoney: formatMoney,
        balanceOf: balanceOf,
        updateBalanceHint: updateBalanceHint,
        methodType: methodType,
        mask: mask,
        buildMethodItem: buildMethodItem,
        errorText: errorText
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();