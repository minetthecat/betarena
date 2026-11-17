<?php
/* BetArena API tester — PHP 7.0 compatible. No direct database access. */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BetArena · Test API</title>
    <style>
        :root { color-scheme: dark; --bg:#101014; --panel:#1b1b22; --line:#34343e; --text:#f3f3f7; --muted:#b7b7c5; --gold:#e5bc63; --green:#71e0a3; --red:#ff8b98; --yellow:#eacb76; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font:15px/1.55 system-ui,sans-serif; }
        main { max-width:1260px; margin:auto; padding:34px 22px 70px; }
        h1 { margin:0; font-size:clamp(26px,4vw,38px); letter-spacing:-1px; }
        h2 { margin:0 0 16px; font-size:18px; }
        p { margin:8px 0 16px; }
        .muted { color:var(--muted); }
        .eyebrow { color:var(--gold); font-size:12px; letter-spacing:2px; font-weight:700; }
        .panel { padding:22px; background:var(--panel); border:1px solid var(--line); border-radius:14px; margin-top:22px; }
        .notice { color:#f6ddb0; border-left:3px solid var(--gold); padding-left:14px; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        label { display:block; color:var(--muted); font-size:13px; }
        input { width:100%; margin-top:6px; padding:11px 12px; border:1px solid #50505e; border-radius:7px; background:#111116; color:var(--text); font:inherit; }
        input:disabled { opacity:.7; }
        input:focus-visible,button:focus-visible,summary:focus-visible { outline:2px solid var(--gold); outline-offset:3px; }
        button { border:1px solid #50505e; border-radius:8px; padding:11px 17px; background:#2b2b36; color:var(--text); font:inherit; cursor:pointer; }
        button.primary { background:var(--gold); color:#17130a; font-weight:750; border-color:var(--gold); }
        button:disabled { opacity:.45; cursor:default; }
        .toolbar { display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin-top:18px; }
        details.settings { margin-top:18px; }
        summary { cursor:pointer; }
        details.settings .grid { margin-top:14px; }
        .stats { display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-bottom:16px; }
        .stat { padding:12px; border:1px solid var(--line); border-radius:8px; }
        .stat strong { font-size:25px; display:block; }
        .stat span { color:var(--muted); font-size:12px; }
        .green { color:var(--green); } .red { color:var(--red); } .yellow { color:var(--yellow); }
        progress { display:block; width:100%; height:12px; accent-color:var(--gold); margin:14px 0; }
        .test { padding:15px 0; border-top:1px solid var(--line); }
        .testhead { display:flex; align-items:flex-start; gap:12px; }
        .number { color:var(--muted); width:28px; flex-shrink:0; }
        .testname { flex:1; min-width:0; }
        .title { font-weight:650; }
        .route { font:12px/1.5 ui-monospace,monospace; color:var(--muted); overflow-wrap:anywhere; margin:3px 0; }
        .tag { font-size:11px; border:1px solid var(--line); padding:2px 7px; border-radius:5px; margin-right:5px; color:var(--muted); }
        .state { padding:3px 9px; background:#292932; border-radius:6px; font-size:12px; white-space:nowrap; }
        .pass .state { color:var(--green); background:#143b2a; }
        .fail .state { color:var(--red); background:#4c2028; }
        .skip .state { color:var(--yellow); background:#41371d; }
        .running .state { color:#111; background:var(--gold); }
        .message { margin:8px 0 0 40px; font-size:13px; overflow-wrap:anywhere; }
        .test details { margin:9px 0 0 40px; font-size:12px; color:var(--muted); }
        pre { max-height:330px; overflow:auto; white-space:pre-wrap; overflow-wrap:anywhere; background:#101014; border:1px solid var(--line); border-radius:8px; padding:14px; }
        #fatal { display:none; border:1px solid #9b4b56; color:var(--red); padding:14px; margin-top:16px; border-radius:8px; }
        #live { min-height:24px; overflow-wrap:anywhere; }
        @media(max-width:650px) { main{padding:22px 14px} .panel{padding:16px} .grid{grid-template-columns:1fr} .stats{grid-template-columns:repeat(3,1fr)} .testhead{gap:7px} .state{font-size:11px} .message,.test details{margin-left:35px} }
    </style>
</head>
<body>
<main>
    <div class="eyebrow">BETARENA / DIAGNOSTIKA</div>
    <h1>Test funkčnosti API</h1>
    <p class="muted">39 API operací · postupná fronta · průběžné výsledky · JSON protokol</p>
    <section class="panel" aria-labelledby="setup-title">
        <h2 id="setup-title">Spuštění testů</h2>
        <p class="notice">Spuštění odhlásí aktuální relaci a vytvoří nový testovací účet. Na něm upraví profil a nastavení, provede demo vklad a výběr, nahraje malý testovací PNG a podle nabídky aktivuje bonus a vstoupí do turnaje. Účet, transakce, doklad a účast v databázi zůstanou. Při opakovaném spuštění dostane tester automaticky novou identitu.</p>
        <p class="muted">Testy používej na demo instalaci. Zelená znamená splněné očekávání testu; u testu odmítnutí tedy správně vrácenou chybu. Prázdný katalog znamená přeskočený test, nikoli úspěch.</p>
        <div class="grid">
            <label>E-mail nového testovacího účtu<input id="test-email" type="email" autocomplete="off" spellcheck="false"></label>
            <label>Heslo testovacího účtu<input id="test-password" type="text" autocomplete="off" spellcheck="false"></label>
        </div>
        <details class="settings">
            <summary>Volitelné ověření administrátorského přístupu</summary>
            <p class="muted">Bez údajů se ověří odmítnutí běžného uživatele (403). S údaji navíc proběhne přihlášení administrátora a načtení dashboardu. Heslo se nezapisuje do protokolu.</p>
            <div class="grid">
                <label>Admin e-mail<input id="admin-email" type="email" autocomplete="off"></label>
                <label>Admin heslo<input id="admin-password" type="password" autocomplete="off"></label>
            </div>
        </details>
        <div class="toolbar">
            <button class="primary" id="start" type="button">Spustit všechny testy</button>
            <button id="stop" type="button" disabled>Zastavit po aktuálním testu</button>
            <button id="new-account" type="button">Nový testovací účet</button>
            <button id="export" type="button" disabled>Stáhnout protokol</button>
        </div>
        <div id="fatal" role="alert"></div>
    </section>
    <section class="panel" aria-labelledby="results-title">
        <h2 id="results-title">Výsledky testů</h2>
        <div class="stats">
            <div class="stat"><strong id="count-total">0</strong><span>Testů ve frontě</span></div>
            <div class="stat"><strong id="count-pass" class="green">0</strong><span>Splněno</span></div>
            <div class="stat"><strong id="count-fail" class="red">0</strong><span>Chyba</span></div>
            <div class="stat"><strong id="count-skip" class="yellow">0</strong><span>Přeskočeno</span></div>
            <div class="stat"><strong id="count-done">0</strong><span>Dokončeno</span></div>
        </div>
        <progress id="progress" value="0" max="1" aria-label="Průběh testů"></progress>
        <div id="live" role="status" aria-live="polite">Připraveno ke spuštění.</div>
        <p class="muted">Ověření e-mailu a reset hesla zde ověřují odmítnutí neplatného tokenu. Úspěšný tok vyžaduje token z e-mailu či serverového logu a tato fronta jej nepotvrzuje. Při vyžadovaném ověření e-mailu může login nového účtu vrátit 403.</p>
        <div id="tests"></div>
    </section>
    <noscript><p class="red">Pro spuštění testů povol JavaScript.</p></noscript>
</main>
<script src="/services/api.js"></script>
<script>
'use strict';
(function () {
    const $ = id => document.getElementById(id);
    const fields = ['test-email','test-password','admin-email','admin-password'];
    const states = {pending:'Čeká',running:'Probíhá',pass:'Splněno',fail:'Chyba',skip:'Přeskočeno'};
    const required = ['sendApiRequest','register','login','logout','requestPasswordReset','resetPassword','verifyEmail','getAccount','getProfile','updateProfile','getKyc','uploadKycDocument','getSettings','updateSettings','changePassword','getAccountTournaments','getAccountBonuses','getWallets','getWallet','createWallet','updateWallet','deleteWallet','depositToWallet','withdrawFromWallet','getTransactions','getTransaction','getGames','getGame','getProviders','getFavoriteGames','addFavoriteGame','removeFavoriteGame','getTournaments','getTournament','getTournamentLeaderboard','joinTournament','getBonuses','getBonus','claimBonus','getAdminDashboard'];
    let active = false, stopRequested = false, queue = [], context = {}, current = null, started = null;
    class Skip extends Error {}
    function ensure(value, message) { if (!value) throw new Error(message); }
    function need(value, message) { if (!value) throw new Skip(message); return value; }
    function privateAccount() { need(context.loggedIn && context.created, 'Testovací účet není přihlášen.'); }
    function redact(value, key) {
        if (/password|token|secret|authorization/i.test(key || '')) return '[skryto]';
        if (value instanceof FormData) return Array.from(value.entries()).map(([k,v]) => [k,v instanceof Blob ? {name:v.name,type:v.type,size:v.size} : redact(v,k)]);
        if (Array.isArray(value)) return value.map(v => redact(v));
        if (value && typeof value === 'object') {
            const o = {}; Object.keys(value).forEach(k => { o[k] = redact(value[k], k); }); return o;
        }
        return value;
    }
    function newIdentity() {
        const bytes = new Uint32Array(3); crypto.getRandomValues(bytes);
        const unique = Date.now().toString(36) + '-' + Array.from(bytes).map(v => v.toString(36)).join('');
        $('test-email').value = 'api-test-' + unique + '@example.test';
        $('test-password').value = 'Api!' + unique + 'Aa9';
    }
    function resultData(result) {
        ensure(result && result.success === true && Object.prototype.hasOwnProperty.call(result,'data'), 'Odpověď nemá očekávaný formát {success:true, message, data}.');
        return result.data;
    }
    function listData(result) { const d = resultData(result); ensure(Array.isArray(d), 'data musí být pole záznamů.'); return d; }
    function recordData(result, id) { const d = resultData(result); ensure(d && typeof d === 'object' && !Array.isArray(d), 'data musí být objekt.'); if (id) ensure(d[id] !== undefined, 'V odpovědi chybí ' + id + '.'); return d; }
    async function rejectRequest(fn, data, status) {
        try { await fn(data); } catch (e) { ensure(Number(e.status) === status, 'Očekáváno HTTP ' + status + ', přijato ' + (e.status || 'bez HTTP statusu') + ': ' + e.message); return 'Správně odmítnuto: HTTP ' + status + '.'; }
        throw new Error('Požadavek byl úspěšný, očekáváno HTTP ' + status + '.');
    }
    function add(title, method, endpoint, run, kind = 'Funkční') {
        queue.push({title,method,endpoint,run,kind,state:'pending',message:'',calls:[],duration:0});
    }
    function buildQueue() {
        queue = [];
        add('Příprava: odhlášení původní relace','POST','/api/auth/logout', async () => {
            try { resultData(await logout()); } catch (e) { if (Number(e.status) !== 401) throw e; }
            return 'Relace je odhlášena.';
        },'Příprava');
        add('Registrace nového účtu','POST','/api/auth/register', async () => {
            const d = recordData(await register({email:context.email,password:context.password,password_confirm:context.password}),'user_id');
            ensure(d.email === context.email, 'Registrace vrátila jiný e-mail.'); context.created = true; context.uid = d.user_id;
        });
        add('Žádost o reset hesla','POST','/api/auth/forgot-password', async () => { need(context.created,'Registrace neproběhla.'); resultData(await requestPasswordReset({email:context.email})); });
        add('Ověření e-mailu: odmítnutí neplatného tokenu','GET','/api/auth/verify', () => rejectRequest(verifyEmail,{token:context.invalidToken},400),'Odmítnutí');
        add('Reset hesla: odmítnutí neplatného tokenu','POST','/api/auth/reset-password', () => rejectRequest(resetPassword,{token:context.invalidToken,password:context.password,password_confirm:context.password},400),'Odmítnutí');
        add('Přihlášení testovacího účtu','POST','/api/auth/login', async () => {
            need(context.created,'Nový testovací účet nebyl vytvořen; do existujícího účtu se tester nepřihlásí.');
            const d = recordData(await login({email:context.email,password:context.password}),'user_id');
            ensure(String(d.user_id) === String(context.uid), 'Přihlášen jiný uživatel.'); context.loggedIn = true;
        });
        add('Přehled účtu','GET','/api/account/account', async () => { privateAccount(); const d = recordData(await getAccount(),'user_id'); ensure(String(d.user_id) === String(context.uid),'Nesouhlasí vlastník účtu.'); ensure(!('password_hash' in d),'API vrací hash hesla.'); });
        add('Načtení profilu','GET','/api/account/profile', async () => { privateAccount(); recordData(await getProfile(),'user_id'); });
        add('Úprava a kontrola profilu','PUT','/api/account/profile', async () => {
            privateAccount(); resultData(await updateProfile({first_name:'API test',last_name:'Automatická fronta',birth_date:'2000-01-01',phone:'+420000000000',address:'Testovací adresa'}));
            ensure(recordData(await getProfile()).first_name === 'API test','Změna profilu se neuložila.');
        });
        add('Načtení nastavení','GET','/api/account/settings', async () => { privateAccount(); recordData(await getSettings()); });
        add('Úprava a kontrola nastavení','PUT','/api/account/settings', async () => { privateAccount(); resultData(await updateSettings({language:'cs',notifications:false})); const d = recordData(await getSettings()); ensure(d.language === 'cs' && Number(d.notifications) === 0,'Změna nastavení se neuložila.'); });
        add('Změna hesla a ověření přihlášením','PUT','/api/account/password', async () => {
            privateAccount(); const next = context.password + 'X';
            resultData(await changePassword({current_password:context.password,password:next,password_confirm:next}));
            context.password = next; $('test-password').value = next;
            resultData(await logout()); context.loggedIn = false;
            recordData(await login({email:context.email,password:next}),'user_id'); context.loggedIn = true;
        });
        add('Načtení KYC','GET','/api/account/kyc', async () => { privateAccount(); const d = recordData(await getKyc()); ensure(Array.isArray(d.documents),'Chybí seznam documents.'); });
        add('Nahrání testovacího KYC PNG','POST','/api/account/kyc', async () => {
            privateAccount(); const raw = atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=');
            const bytes = Uint8Array.from(raw,c => c.charCodeAt(0)); const form = new FormData(); form.append('document_type','api_test'); form.append('file',new Blob([bytes],{type:'image/png'}),'api-test.png');
            const d = recordData(await uploadKycDocument(form),'kyc_id');
            const check = recordData(await getKyc()); ensure(check.documents.some(v => String(v.kyc_id) === String(d.kyc_id)),'Nahraný dokument není v seznamu.');
        });
        add('Vytvoření CZK peněženky','POST','/api/wallet/wallet', async () => { privateAccount(); context.wallet = recordData(await createWallet({currency:'CZK'}),'wallet_id'); });
        add('Seznam peněženek','GET','/api/wallet/wallets', async () => { privateAccount(); const data = listData(await getWallets()); if (context.wallet) ensure(data.some(v => String(v.wallet_id) === String(context.wallet.wallet_id)),'Vytvořená peněženka chybí.'); });
        add('Detail peněženky','GET','/api/wallet/wallet', async () => { privateAccount(); const w = need(context.wallet,'CZK peněženka není dostupná.'); recordData(await getWallet({wallet_id:w.wallet_id}),'wallet_id'); });
        add('Změna měny prázdné peněženky EUR → BSC','PUT','/api/wallet/wallet', async () => {
            privateAccount(); context.temporaryWallet = recordData(await createWallet({currency:'EUR'}),'wallet_id');
            const id = context.temporaryWallet.wallet_id; resultData(await updateWallet({wallet_id:id,currency:'BSC'}));
            ensure(recordData(await getWallet({wallet_id:id})).currency === 'BSC','Měna se nezměnila.');
        });
        add('Smazání prázdné peněženky','DELETE','/api/wallet/wallet', async () => {
            privateAccount(); const w = need(context.temporaryWallet,'Dočasná peněženka není dostupná.'); resultData(await deleteWallet({wallet_id:w.wallet_id}));
            ensure(!listData(await getWallets()).some(v => String(v.wallet_id) === String(w.wallet_id)),'Peněženka zůstala v seznamu.'); context.temporaryWallet = null;
        });
        add('Demo vklad 100,10 CZK','POST','/api/wallet/deposit', async () => {
            privateAccount(); const w = need(context.wallet,'CZK peněženka není dostupná.');
            const before = Number(recordData(await getWallet({wallet_id:w.wallet_id})).balance);
            context.transaction = recordData(await depositToWallet({currency:'CZK',amount:'100.10'}),'transaction_id');
            const after = Number(recordData(await getWallet({wallet_id:w.wallet_id})).balance);
            ensure(Math.abs(after - before - 100.10) < 0.001,'Vklad nezměnil zůstatek o 100,10 CZK.'); context.deposited = true;
        });
        add('Demo výběr 10,05 CZK','POST','/api/wallet/withdraw', async () => {
            privateAccount(); need(context.deposited,'Úspěšný vklad není potvrzen.'); const id = context.wallet.wallet_id;
            const before = Number(recordData(await getWallet({wallet_id:id})).balance);
            recordData(await withdrawFromWallet({currency:'CZK',amount:'10.05'}),'transaction_id');
            ensure(Math.abs(Number(recordData(await getWallet({wallet_id:id})).balance) - before + 10.05) < 0.001,'Výběr nezměnil zůstatek o 10,05 CZK.');
        });
        add('Odmítnutí záporného vkladu','POST','/api/wallet/deposit', async () => { privateAccount(); return rejectRequest(depositToWallet,{currency:'CZK',amount:-1},400); },'Odmítnutí');
        add('Odmítnutí výběru nad zůstatek','POST','/api/wallet/withdraw', async () => {
            privateAccount(); const w = need(context.wallet,'CZK peněženka není dostupná.'); const before = recordData(await getWallet({wallet_id:w.wallet_id})).balance;
            const message = await rejectRequest(withdrawFromWallet,{currency:'CZK',amount:'999999999999.99'},409);
            ensure(String(recordData(await getWallet({wallet_id:w.wallet_id})).balance) === String(before),'Neúspěšný výběr změnil zůstatek.'); return message;
        },'Odmítnutí');
        add('Historie transakcí','GET','/api/transaction/transactions', async () => { privateAccount(); const data = listData(await getTransactions({currency:'CZK',page:1,limit:20})); if (context.transaction) ensure(data.some(v => String(v.transaction_id) === String(context.transaction.transaction_id)),'Vklad není v historii.'); });
        add('Detail transakce','GET','/api/transaction/transaction', async () => { privateAccount(); const t = need(context.transaction,'Vklad nevytvořil transakci.'); recordData(await getTransaction({transaction_id:t.transaction_id}),'transaction_id'); });
        add('Seznam poskytovatelů','GET','/api/casino/providers', async () => { context.providers = listData(await getProviders()); return 'Počet poskytovatelů: ' + context.providers.length + '.'; });
        add('Seznam her','GET','/api/casino/games', async () => { const data = listData(await getGames({filter:'all',page:1,limit:20})); context.game = data[0]; return 'Počet her v první stránce: ' + data.length + '.'; });
        add('Detail hry','GET','/api/casino/game', async () => { const game = need(context.game,'Katalog her je prázdný nebo se nenačetl.'); recordData(await getGame({game_id:game.game_id}),'game_id'); });
        add('Přidání hry do oblíbených','POST','/api/casino/favorite', async () => { privateAccount(); const game = need(context.game,'Katalog her je prázdný.'); resultData(await addFavoriteGame({game_id:game.game_id})); context.favorited = true; });
        add('Seznam oblíbených her','GET','/api/casino/favorites', async () => { privateAccount(); const data = listData(await getFavoriteGames()); if (context.favorited) ensure(data.some(v => String(v.game_id) === String(context.game.game_id)),'Přidaná hra není v oblíbených.'); });
        add('Odebrání hry z oblíbených','DELETE','/api/casino/favorite', async () => { privateAccount(); need(context.favorited,'Hra nebyla přidána do oblíbených.'); resultData(await removeFavoriteGame({game_id:context.game.game_id})); ensure(!listData(await getFavoriteGames()).some(v => String(v.game_id) === String(context.game.game_id)),'Hra zůstala v oblíbených.'); });
        add('Seznam bonusů','GET','/api/bonus/bonuses', async () => { const data = listData(await getBonuses()); context.bonus = data[0]; return 'Počet dostupných bonusů: ' + data.length + '.'; });
        add('Detail bonusu','GET','/api/bonus/bonus', async () => { const b = need(context.bonus,'Žádný dostupný bonus.'); recordData(await getBonus({bonus_id:b.bonus_id}),'bonus_id'); });
        add('Aktivace bonusu','POST','/api/bonus/claim', async () => {
            privateAccount(); const b = need(context.bonus,'Žádný dostupný bonus.');
            let wallet, before;
            if (b.currency && Number(b.amount) > 0) {
                wallet = listData(await getWallets()).find(w => w.currency === b.currency);
                if (!wallet) wallet = recordData(await createWallet({currency:b.currency}),'wallet_id');
                before = Number(recordData(await getWallet({wallet_id:wallet.wallet_id})).balance);
            }
            recordData(await claimBonus({bonus_id:b.bonus_id}),'user_bonus_id'); context.claimed = true;
            if (wallet) ensure(Math.abs(Number(recordData(await getWallet({wallet_id:wallet.wallet_id})).balance) - before - Number(b.amount)) < 0.001,'Bonus se nepřipsal do peněženky.');
        });
        add('Bonusy účtu','GET','/api/account/bonuses', async () => { privateAccount(); const data = listData(await getAccountBonuses()); if (context.claimed) ensure(data.some(v => String(v.bonus_id) === String(context.bonus.bonus_id)),'Aktivovaný bonus není u účtu.'); });
        add('Seznam turnajů','GET','/api/tournament/tournaments', async () => {
            const data = listData(await getTournaments({status:'active',limit:100}));
            const upcoming = listData(await getTournaments({status:'upcoming',limit:100}));
            context.tournament = data[0] || upcoming[0]; return 'Aktivní: ' + data.length + ', nadcházející: ' + upcoming.length + '.';
        });
        add('Detail turnaje','GET','/api/tournament/tournament', async () => { const t = need(context.tournament,'Žádný aktivní ani nadcházející turnaj.'); recordData(await getTournament({tournament_id:t.tournament_id}),'tournament_id'); });
        add('Přihlášení do turnaje','POST','/api/tournament/join', async () => { privateAccount(); const t = need(context.tournament,'Žádný dostupný turnaj.'); recordData(await joinTournament({tournament_id:t.tournament_id}),'tournament_id'); context.joined = true; });
        add('Žebříček turnaje','GET','/api/tournament/leaderboard', async () => { const t = need(context.tournament,'Žádný dostupný turnaj.'); listData(await getTournamentLeaderboard({tournament_id:t.tournament_id,limit:100})); });
        add('Turnaje účtu','GET','/api/account/tournaments', async () => { privateAccount(); const data = listData(await getAccountTournaments()); if (context.joined) ensure(data.some(v => String(v.tournament_id) === String(context.tournament.tournament_id)),'Turnaj chybí u účtu.'); });
        add('Administrace: odmítnutí běžného uživatele','GET','/api/admin/dashboard', async () => { privateAccount(); return rejectRequest(getAdminDashboard,{},403); },'Oprávnění');
        add('Administrace: přihlášení a načtení dashboardu','GET','/api/admin/dashboard', async () => {
            need(context.adminEmail && context.adminPassword,'Údaje administrátora nebyly zadány; úspěšný admin přístup není ověřen.');
            if (context.loggedIn) { resultData(await logout()); context.loggedIn = false; }
            recordData(await login({email:context.adminEmail,password:context.adminPassword}),'user_id'); context.adminLoggedIn = true;
            const d = recordData(await getAdminDashboard());
            ['users_total','users_last_7d','deposits_total','withdrawals_total','tournaments_active'].forEach(k => ensure(k in d,'Dashboard nevrací ' + k + '.'));
        });
        add('Závěrečné odhlášení','POST','/api/auth/logout', async () => {
            need(context.loggedIn || context.adminLoggedIn,'Žádná potvrzená přihlášená relace.');
            resultData(await logout()); context.loggedIn = false; context.adminLoggedIn = false;
            return rejectRequest(getAccount,{},401);
        });
    }
    function renderQueue() {
        const root = $('tests'); root.replaceChildren();
        queue.forEach((test,index) => {
            const row = document.createElement('article'); row.className = 'test';
            const head = document.createElement('div'); head.className = 'testhead';
            const number = document.createElement('span'); number.className = 'number'; number.textContent = String(index+1).padStart(2,'0');
            const name = document.createElement('div'); name.className = 'testname';
            const title = document.createElement('div'); title.className = 'title'; title.textContent = test.title;
            const route = document.createElement('div'); route.className = 'route'; route.textContent = test.method + ' ' + test.endpoint;
            const tag = document.createElement('span'); tag.className = 'tag'; tag.textContent = test.kind;
            name.append(title,route,tag);
            const state = document.createElement('span'); state.className = 'state'; state.textContent = states.pending;
            head.append(number,name,state);
            const message = document.createElement('div'); message.className = 'message';
            const detail = document.createElement('details'); const summary = document.createElement('summary'); summary.textContent = 'Požadavky a odpovědi';
            const pre = document.createElement('pre'); detail.append(summary,pre); row.append(head,message,detail); root.append(row);
            test.ui = {row,state,message,pre};
        });
        updateCounts();
    }
    function updateRow(t) {
        t.ui.row.className = 'test ' + t.state; t.ui.state.textContent = states[t.state];
        t.ui.message.textContent = t.message + (t.duration ? ' (' + t.duration + ' ms)' : '');
        t.ui.pre.textContent = JSON.stringify(t.calls,null,2) || '[]';
    }
    function updateCounts() {
        const count = s => queue.filter(t => t.state === s).length;
        const done = count('pass') + count('fail') + count('skip');
        $('count-total').textContent = queue.length; $('count-pass').textContent = count('pass'); $('count-fail').textContent = count('fail'); $('count-skip').textContent = count('skip'); $('count-done').textContent = done;
        $('progress').max = queue.length || 1; $('progress').value = done;
    }
    function lock(value) {
        active = value; fields.forEach(id => { $(id).disabled = value; });
        $('start').disabled = value; $('new-account').disabled = value; $('stop').disabled = !value; $('export').disabled = value || !queue.some(t => t.state !== 'pending');
    }
    function installLogging() {
        if (window.__betArenaTestLogging) return;
        window.__betArenaTestLogging = true;
        const original = window.sendApiRequest;
        window.sendApiRequest = async function (endpoint,data = {},method = 'POST') {
            const step = current; const time = performance.now(); const call = {method,endpoint,request:redact(data)};
            if (step) step.calls.push(call);
            try {
                const response = await original(endpoint,data,method); call.http = '2xx'; call.response = redact(response); return response;
            } catch (e) { call.http = e.status || null; call.error = e.message; throw e; }
            finally { call.duration_ms = Math.round(performance.now() - time); if (step) updateRow(step); }
        };
    }
    async function start() {
        if (active) return;
        $('fatal').style.display = 'none';
        const missing = required.filter(k => typeof window[k] !== 'function');
        if (missing.length) { $('fatal').textContent = 'Nelze načíst úplný /services/api.js. Chybí: ' + missing.join(', ') + '. Nahraj původní api.js do /services/.'; $('fatal').style.display = 'block'; return; }
        if (started && context.created && $('test-email').value.trim() === context.email) newIdentity();
        const email = $('test-email').value.trim(), password = $('test-password').value;
        const adminEmail = $('admin-email').value.trim(), adminPassword = $('admin-password').value;
        if (!$('test-email').checkValidity() || !email || password.length < 8 || password.length > 70 || Boolean(adminEmail) !== Boolean(adminPassword) || (adminEmail && !$('admin-email').checkValidity())) {
            $('fatal').textContent = 'Zadej platný nový e-mail a heslo o délce 8–70 znaků. Údaje administrátora vyplň oba, nebo žádný.'; $('fatal').style.display = 'block'; return;
        }
        context = {email,password,adminEmail,adminPassword,invalidToken:'invalid-api-test-' + Date.now()};
        started = new Date().toISOString(); stopRequested = false; buildQueue(); renderQueue(); installLogging(); lock(true);
        try {
            for (let i=0;i<queue.length;i++) {
                if (stopRequested) {
                    queue.slice(i).forEach(t => { t.state = 'skip'; t.message = 'Zastaveno uživatelem; požadavek nebyl odeslán.'; updateRow(t); }); break;
                }
                const test = queue[i]; current = test; test.state = 'running'; test.message = ''; updateRow(test);
                $('live').textContent = 'Probíhá ' + (i+1) + '/' + queue.length + ': ' + test.title;
                // Let the browser paint the active row before starting the request.
                await new Promise(resolve => setTimeout(resolve,30));
                const time = performance.now();
                try { const message = await test.run(); test.state = 'pass'; test.message = typeof message === 'string' ? message : 'Očekávaný výsledek potvrzen.'; }
                catch (e) { test.state = e instanceof Skip ? 'skip' : 'fail'; test.message = e.message || String(e); }
                test.duration = Math.round(performance.now()-time); updateRow(test); updateCounts(); current = null;
            }
        } finally {
            current = null; updateCounts(); lock(false);
            const fail = queue.filter(t => t.state === 'fail').length, skip = queue.filter(t => t.state === 'skip').length;
            $('live').textContent = (stopRequested ? 'Fronta zastavena.' : 'Fronta dokončena.') + ' Chyb: ' + fail + ', přeskočeno: ' + skip + '. Podrobnosti jsou u jednotlivých testů.' + ((context.loggedIn || context.adminLoggedIn) ? ' Relace zůstala přihlášená; pro její ukončení se odhlas.' : '');
            // Do not retain admin credentials after completion.
            context.adminPassword = ''; $('admin-password').value = '';
        }
    }
    $('start').addEventListener('click',start);
    $('stop').addEventListener('click',() => { stopRequested = true; $('stop').disabled = true; $('live').textContent = 'Čekám na dokončení aktuálního testu. Další test se už nespustí.'; });
    $('new-account').addEventListener('click',newIdentity);
    $('export').addEventListener('click',() => {
        const report = {started,finished:new Date().toISOString(),account:context.email,note:'Zelený negativní test potvrzuje odmítnutí, nikoli úspěšný tok. Hesla a tokeny jsou skryty.',tests:queue.map(t => ({title:t.title,method:t.method,endpoint:t.endpoint,kind:t.kind,state:t.state,message:t.message,duration_ms:t.duration,calls:t.calls}))};
        const blob = new Blob([JSON.stringify(report,null,2)],{type:'application/json'}), url = URL.createObjectURL(blob), link = document.createElement('a');
        link.href = url; link.download = 'betarena-api-test-' + new Date().toISOString().replace(/[:.]/g,'-') + '.json'; link.click(); setTimeout(() => URL.revokeObjectURL(url),1000);
    });
    window.addEventListener('beforeunload',e => { if (active) { e.preventDefault(); e.returnValue = ''; } });
    newIdentity(); buildQueue(); renderQueue();
})();
</script>
</body>
</html>
