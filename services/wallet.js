(function () {
    'use strict';

    const TRANSACTION_TYPES = {
        deposit: 'Vklad',
        withdraw: 'Výběr',
        bet: 'Sázka',
        win: 'Výhra',
        bonus: 'Bonus',
        refund: 'Vrácení'
    };

    function unwrap(response) {
        return response && response.data !== undefined
            ? response.data
            : response;
    }

    function getList(response, keys) {
        const data = unwrap(response);

        if (Array.isArray(data)) return data;
        if (!data || typeof data !== 'object') return [];

        for (const key of keys) {
            if (Array.isArray(data[key])) return data[key];
        }

        return [];
    }

    function errorText(error) {
        return error && error.message
            ? error.message
            : 'Požadavek se nepodařilo dokončit.';
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

    function isTrue(value) {
        return value === true || value === 1 || value === '1' || value === 'true';
    }

    function isFalse(value) {
        return value === false || value === 0 || value === '0' || value === 'false';
    }

    function isValidAmount(value) {
        const text = String(value || '').trim();

        return /^(?:0|[1-9][0-9]{0,15})(?:\.[0-9]{1,2})?$/.test(text) &&
            /[1-9]/.test(text);
    }

    function toCents(value) {
        const parts = String(value || '0').split('.');
        const whole = Number(parts[0] || 0);
        const fraction = Number((parts[1] || '').padEnd(2, '0').slice(0, 2));

        return whole * 100 + fraction;
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
        return getList(await getWallets(), ['wallets', 'items', 'results']);
    }

    async function loadMethods(capability) {
        const response = await getPaymentMethods({ page: 1, limit: 100 });
        const methods = getList(response, [
            'payment_methods',
            'methods',
            'items',
            'results'
        ]);

        return methods.filter(function (method) {
            if (method.is_active !== undefined && isFalse(method.is_active)) {
                return false;
            }

            // Pokud API pole oprávnění nevrátí, metodu nevyřazujeme.
            if (capability &&
                method[capability] !== undefined &&
                isFalse(method[capability])) {
                return false;
            }

            return true;
        });
    }

    function getCurrency(wallet) {
        return String(wallet.currency || '').toUpperCase();
    }

    function balanceOf(wallets, currency) {
        const wanted = String(currency || 'CZK').toUpperCase();
        const wallet = wallets.find(function (item) {
            return getCurrency(item) === wanted;
        });

        return wallet ? wallet.balance : '0';
    }

    function updateBalanceHint(form, wallets) {
        const hint = form.querySelector('[data-wallet-balance-hint]');
        if (!hint) return;

        const currency = form.elements.currency
            ? form.elements.currency.value
            : 'CZK';

        hint.textContent = 'Dostupný zůstatek: ' +
            formatMoney(balanceOf(wallets, currency), currency) + '.';
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

        return text.slice(0, left) +
            '•'.repeat(text.length - left - right) +
            text.slice(-right);
    }

    function createField(labelText, name, type, value) {
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

    function methodLabel(method) {
        const type = methodType(method);

        if (type === 'card') return 'Platební karta';
        if (type === 'bsc') return 'Krypto převod · BSC';
        return 'Bankovní převod';
    }

    function buildMethodItem(method, details) {
        const item = document.createElement('details');
        item.className = 'wallet-method-collapse';

        const summary = document.createElement('summary');
        summary.className = 'wallet-method-summary';

        const icon = document.createElement('span');
        icon.className = 'wallet-method-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = methodType(method).toUpperCase();

        const summaryText = document.createElement('span');
        summaryText.className = 'wallet-method-summary-text';

        const title = document.createElement('span');
        title.className = 'wallet-method-summary-title';
        title.textContent = method.name || methodLabel(method);

        const description = document.createElement('span');
        description.className = 'wallet-method-summary-description';
        description.textContent = methodLabel(method);

        summaryText.append(title, description);
        summary.append(icon, summaryText);
        item.append(summary, details);

        return item;
    }

    function createMoneyForm(method, action, wallets) {
        const form = document.createElement('form');
        form.className = 'wallet-deposit-form';
        form.dataset.walletAction = action;
        form.dataset.methodId = method.method_id || '';

        const amount = createField('Částka', 'amount', 'number');
        const amountInput = amount.querySelector('input');
        amountInput.min = '0.01';
        amountInput.step = '0.01';
        amountInput.placeholder = '0,00';
        amountInput.required = true;

        const currencyLabel = document.createElement('label');
        currencyLabel.className = 'wallet-deposit-field';
        currencyLabel.appendChild(document.createTextNode('Měna'));

        const currencySelect = document.createElement('select');
        currencySelect.name = 'currency';

        let currencies;

        if (action === 'withdraw') {
            currencies = wallets.map(getCurrency).filter(Boolean);
        } else {
            currencies = ['CZK', 'EUR', 'BSC'];
        }

        currencies = currencies.filter(function (currency, index, list) {
            return list.indexOf(currency) === index;
        });

        if (!currencies.length) currencies = ['CZK'];

        currencies.forEach(function (currency) {
            const option = document.createElement('option');
            option.value = currency;
            option.textContent = currency;
            currencySelect.appendChild(option);
        });

        currencyLabel.appendChild(currencySelect);

        const submit = document.createElement('button');
        submit.className = 'wallet-deposit-submit';
        submit.type = 'submit';
        submit.textContent = action === 'deposit' ? 'Vložit' : 'Vybrat';

        const status = document.createElement('p');
        status.className = 'wallet-deposit-message';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');

        form.append(amount, currencyLabel);

        if (action === 'withdraw') {
            const hint = document.createElement('p');
            hint.className = 'wallet-balance-hint';
            hint.dataset.walletBalanceHint = '';
            form.appendChild(hint);
            updateBalanceHint(form, wallets);
        }

        form.append(submit, status);

        currencySelect.addEventListener('change', function () {
            updateBalanceHint(form, wallets);
        });

        return form;
    }

    function renderMoneyForms(container, methods, action, wallets, emptyText) {
        container.replaceChildren();

        if (!methods.length) {
            const empty = document.createElement('p');
            empty.className = 'wallet-empty';
            empty.textContent = emptyText;
            container.appendChild(empty);
            return;
        }

        methods.forEach(function (method) {
            const details = document.createElement('div');
            details.className = 'wallet-saved-methods';

            details.appendChild(createMoneyForm(method, action, wallets));
            container.appendChild(buildMethodItem(method, details));
        });
    }

    function methodPayload(form) {
        const values = new FormData(form);
        const method = form.dataset.method || form.dataset.addMethod;

        const payload = {
            code: method,
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

            if (typeof value === 'string' && value.trim()) {
                payload[key] = value.trim();
            }
        });

        // CVV se záměrně neodesílá ani neukládá.
        return payload;
    }

    function bankProblem(form) {
        const account = form.elements.recipient_account_number;
        const bankCode = form.elements.recipient_bank_code;
        const iban = form.elements.recipient_iban;

        const hasAccountAndCode =
            account && account.value.trim() &&
            bankCode && bankCode.value.trim();

        const hasIban = iban && iban.value.trim();

        return hasAccountAndCode || hasIban
            ? ''
            : 'Vyplňte IBAN nebo číslo účtu společně s kódem banky.';
    }

    async function renderDepositPage() {
        const container = document.getElementById('saved-methods');
        if (!container) return;

        // Platební metody zobrazíme hned po jejich načtení.
        // Nečekáme na loadWallets(), protože chybný zůstatek nesmí blokovat metody.
        try {
            const methods = await loadMethods('can_deposit');
            renderMoneyForms(
                container,
                methods,
                'deposit',
                [],
                'Zatím nemáte žádnou uloženou platební metodu. Přidejte ji níže.'
            );
        } catch (error) {
            container.textContent = errorText(error);
            return;
        }

        // Zůstatky načteme samostatně a aktualizujeme pouze formuláře, pokud jsou dostupné.
        try {
            const wallets = await loadWallets();
            container.querySelectorAll('[data-wallet-action="deposit"]').forEach(function (form) {
                updateBalanceHint(form, wallets);
            });
        } catch (error) {
            console.warn('Zůstatky peněženky se nepodařilo načíst:', error);
        }
    }

    async function renderWithdrawPage() {
        const container = document.getElementById('saved-methods');
        if (!container) return;

        // Nezávislé načtení metod a peněženek; selhání jednoho neskrývá metody.
        let methods;

        try {
            methods = await loadMethods('can_withdraw');
        } catch (error) {
            container.textContent = errorText(error);
            return;
        }

        let wallets = [];

        try {
            wallets = await loadWallets();
        } catch (error) {
            console.warn('Zůstatky peněženky se nepodařilo načíst:', error);
        }

        renderMoneyForms(
            container,
            methods,
            'withdraw',
            wallets,
            'Nemáte žádnou platební metodu pro výběr. Přidejte ji v sekci Platební metody.'
        );
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

            const dateCell = document.createElement('td');
            dateCell.textContent = transactionDate(
                transaction.created_at || transaction.created
            );

            const typeCell = document.createElement('td');
            const type = String(transaction.type || '').toLowerCase();
            typeCell.textContent = TRANSACTION_TYPES[type] ||
                transaction.type ||
                'Transakce';

            const methodCell = document.createElement('td');
            methodCell.textContent =
                transaction.payment_method_name ||
                transaction.method_name ||
                '—';

            const amountCell = document.createElement('td');
            amountCell.className = 'wallet-amount';

            const amount = Number(transaction.amount || 0);
            const outgoing = type === 'withdraw' ||
                type === 'bet' ||
                transaction.direction === 'out';
            const signedAmount = outgoing ? -Math.abs(amount) : Math.abs(amount);

            amountCell.textContent =
                (signedAmount > 0 ? '+' : '') +
                formatMoney(signedAmount, transaction.currency || 'CZK');

            row.append(dateCell, typeCell, methodCell, amountCell);
            body.appendChild(row);
        });
    }

    async function loadOverview() {
        const status = document.getElementById('wallet-balances-status');

        try {
            const wallets = await loadWallets();

            const czkElement = document.getElementById('wallet-balance-czk');
            const bonusElement = document.getElementById('wallet-balance-bonus');

            if (czkElement) {
                czkElement.textContent = formatMoney(balanceOf(wallets, 'CZK'), 'CZK');
            }

            if (bonusElement) {
                bonusElement.textContent = formatMoney(balanceOf(wallets, 'BSC'), 'BSC');
            }

            if (status) {
                status.textContent = '';
                status.hidden = true;
            }
        } catch (error) {
            const czkElement = document.getElementById('wallet-balance-czk');
            const bonusElement = document.getElementById('wallet-balance-bonus');

            if (czkElement) czkElement.textContent = '—';
            if (bonusElement) bonusElement.textContent = '—';

            if (status) {
                status.textContent = 'Zůstatky se nepodařilo načíst: ' + errorText(error);
                status.hidden = false;
            }
        }

        // Historie je také nezávislá na načtení zůstatků.
        const historyBody = document.getElementById('wallet-history-body');

        if (historyBody) {
            try {
                const response = await getTransactions({ page: 1, limit: 20 });
                renderHistory(getList(response, ['transactions', 'items', 'results']));
            } catch (error) {
                historyBody.replaceChildren();

                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = 4;
                cell.textContent = 'Historii transakcí se nepodařilo načíst.';
                row.appendChild(cell);
                historyBody.appendChild(row);
            }
        }
    }

    function methodDetails(method) {
        const box = document.createElement('div');
        box.className = 'wallet-deposit-form';

        const type = methodType(method);
        const details = document.createElement('p');
        details.className = 'wallet-deposit-message';

        if (type === 'card') {
            details.textContent = 'Karta •••• ' +
                (method.card_last4 || '—') +
                (method.card_holder_name ? ' · ' + method.card_holder_name : '');
        } else if (type === 'bsc') {
            details.textContent = 'Adresa: ' +
                mask(method.recipient_crypto_address, 6, 4);
        } else if (method.recipient_iban) {
            details.textContent = 'IBAN: ' + mask(method.recipient_iban, 2, 4);
        } else {
            details.textContent = 'Číslo účtu: ' +
                mask(method.recipient_account_number, 0, 4);
        }

        const renameForm = document.createElement('form');
        renameForm.className = 'wallet-deposit-form';
        renameForm.dataset.renameMethod = method.method_id;

        const name = createField(
            'Název metody',
            'name',
            'text',
            method.name || ''
        );
        name.querySelector('input').maxLength = 100;
        name.querySelector('input').required = true;

        const renameButton = document.createElement('button');
        renameButton.type = 'submit';
        renameButton.className = 'wallet-deposit-submit';
        renameButton.textContent = 'Uložit název';

        renameForm.append(name, renameButton);

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'wallet-btn-danger wallet-btn-small';
        removeButton.dataset.removeMethod = method.method_id;
        removeButton.dataset.methodName = method.name || '';
        removeButton.textContent = 'Odebrat metodu';

        box.append(details, renameForm, removeButton);
        return box;
    }

    async function renderMethodsPage() {
        const container = document.getElementById('saved-methods');
        if (!container) return;

        try {
            const methods = await loadMethods();
            container.replaceChildren();

            if (!methods.length) {
                const empty = document.createElement('p');
                empty.className = 'wallet-empty';
                empty.textContent =
                    'Zatím nemáte žádnou platební metodu. Přidejte ji níže.';
                container.appendChild(empty);
                return;
            }

            methods.forEach(function (method) {
                container.appendChild(
                    buildMethodItem(method, methodDetails(method))
                );
            });
        } catch (error) {
            container.textContent = errorText(error);
        }
    }

    async function submitMoneyForm(form) {
        const status = form.querySelector('[role="status"]');
        const action = form.dataset.walletAction;
        const amount = form.elements.amount.value.trim();
        const currency = form.elements.currency.value;

        if (!isValidAmount(amount)) {
            setStatus(
                status,
                'Zadejte platnou částku větší než nula (nejvýše 2 desetinná místa).'
            );
            return;
        }

        setBusy(form, true);
        setStatus(status, 'Zpracovávám…');

        try {
            if (action === 'withdraw') {
                const wallets = await loadWallets();
                const balance = balanceOf(wallets, currency);

                if (toCents(amount) > toCents(balance)) {
                    throw new Error(
                        'Nedostatek prostředků. Dostupný zůstatek je ' +
                        formatMoney(balance, currency) + '.'
                    );
                }

                await withdrawFromWallet({
                    currency: currency,
                    amount: amount
                });

                setStatus(status, 'Výběr byl zpracován.');
            } else {
                const wallets = await loadWallets();
                const walletExists = wallets.some(function (wallet) {
                    return getCurrency(wallet) === currency.toUpperCase();
                });

                if (!walletExists) {
                    await createWallet({ currency: currency });
                }

                await depositToWallet({
                    currency: currency,
                    amount: amount
                });

                setStatus(status, 'Vklad byl připsán.');
            }

            form.reset();

            if (document.getElementById('wallet-balances')) {
                await loadOverview();
            }
        } catch (error) {
            setStatus(status, errorText(error));
        } finally {
            setBusy(form, false);
        }
    }

    async function submitDepositWithNewMethod(form) {
        const status = form.querySelector('[role="status"]');
        const amount = form.elements.amount.value.trim();
        const currency = form.elements.currency.value;
        const saveMethod = form.elements.save_method;

        if (!isValidAmount(amount)) {
            setStatus(
                status,
                'Zadejte platnou částku větší než nula (nejvýše 2 desetinná místa).'
            );
            return;
        }

        if (form.dataset.method === 'bank') {
            const problem = bankProblem(form);
            if (problem) {
                setStatus(status, problem);
                return;
            }
        }

        setBusy(form, true);
        setStatus(status, 'Zpracovávám…');

        let methodSaved = false;

        try {
            if (!saveMethod || saveMethod.checked) {
                await createPaymentMethod(methodPayload(form));
                methodSaved = true;
            }

            let wallets = await loadWallets();
            const walletExists = wallets.some(function (wallet) {
                return getCurrency(wallet) === currency.toUpperCase();
            });

            if (!walletExists) {
                await createWallet({ currency: currency });
                wallets = await loadWallets();
            }

            const response = await depositToWallet({
                currency: currency,
                amount: amount
            });

            const transaction = unwrap(response) || {};
            const balanceText = transaction.balance_after !== undefined
                ? ' Nový zůstatek: ' +
                    formatMoney(transaction.balance_after, transaction.currency || currency) + '.'
                : '';

            form.reset();
            setStatus(
                status,
                'Vklad ' + formatMoney(
                    transaction.amount !== undefined ? transaction.amount : amount,
                    transaction.currency || currency
                ) +
                ' byl zpracován.' +
                balanceText +
                (methodSaved ? ' Platební metoda byla uložena.' : '')
            );

            if (methodSaved) {
                renderDepositPage();
            }
        } catch (error) {
            setStatus(
                status,
                errorText(error) +
                (methodSaved
                    ? ' Platební metoda byla uložena, ale vklad se neprovedl.'
                    : '')
            );
        } finally {
            setBusy(form, false);
        }
    }

    async function submitAddMethodForm(form) {
        const status = form.querySelector('[role="status"]');
        const type = form.dataset.addMethod;

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
            setStatus(status, 'Platební metoda byla přidána.');
            await renderMethodsPage();
        } catch (error) {
            setStatus(status, errorText(error));
        } finally {
            setBusy(form, false);
        }
    }

    async function handleSubmit(event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) return;

        if (form.matches('[data-wallet-action]')) {
            event.preventDefault();
            await submitMoneyForm(form);
            return;
        }

        if (form.matches('[data-new-method="true"]')) {
            event.preventDefault();
            await submitDepositWithNewMethod(form);
            return;
        }

        if (form.matches('[data-add-method]')) {
            event.preventDefault();
            await submitAddMethodForm(form);
            return;
        }

        if (form.matches('[data-rename-method]')) {
            event.preventDefault();

            const status = document.getElementById('methods-status');
            const name = form.elements.name.value.trim();

            if (!name) {
                setStatus(status, 'Zadejte název platební metody.');
                return;
            }

            setBusy(form, true);

            try {
                await updatePaymentMethod({
                    method_id: form.dataset.renameMethod,
                    name: name
                });

                setStatus(status, 'Název platební metody byl změněn.');
                await renderMethodsPage();
            } catch (error) {
                setStatus(status, errorText(error));
            } finally {
                setBusy(form, false);
            }
        }
    }

    async function handleRemoveMethod(event) {
        const button = event.target.closest('[data-remove-method]');
        if (!button) return;

        const methodId = button.dataset.removeMethod;
        const methodName = button.dataset.methodName || 'platební metodu';

        if (!window.confirm(
            'Opravdu chcete odebrat platební metodu „' + methodName + '“?'
        )) {
            return;
        }

        button.disabled = true;

        try {
            await deletePaymentMethod({ method_id: methodId });
            setStatus(
                document.getElementById('methods-status'),
                'Platební metoda byla odebrána.'
            );
            await renderMethodsPage();
        } catch (error) {
            if (error.status === 409 && window.confirm(
                'Metodu nelze smazat, protože je použitá v transakcích. ' +
                'Chcete ji deaktivovat?'
            )) {
                try {
                    await updatePaymentMethod({
                        method_id: methodId,
                        is_active: false
                    });

                    setStatus(
                        document.getElementById('methods-status'),
                        'Platební metoda byla deaktivována.'
                    );
                    await renderMethodsPage();
                } catch (deactivateError) {
                    setStatus(
                        document.getElementById('methods-status'),
                        errorText(deactivateError)
                    );
                }
            } else {
                setStatus(
                    document.getElementById('methods-status'),
                    errorText(error)
                );
            }
        } finally {
            button.disabled = false;
        }
    }

    function init() {
        document.addEventListener('submit', handleSubmit);
        document.addEventListener('click', handleRemoveMethod);

        if (document.getElementById('wallet-balances')) {
            loadOverview();
        }

        if (document.getElementById('saved-methods')) {
            if (document.querySelector('[data-new-method="true"]')) {
                renderDepositPage