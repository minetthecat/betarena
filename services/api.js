/*
	🌐 API
*/

// Universal function for API calls
// sendApiRequest(endpoint, data = {}, method = 'POST')
// - POST/PUT/DELETE send data as JSON in the request body
// - FormData (file upload) is sent as multipart/form-data
// - GET sends data as a query string
// - throws an Error with a message on failure (HTTP error or success === false)
async function sendApiRequest(endpoint, data = {}, method = 'POST') {
    const isFormData = data instanceof FormData;
    const options = {
        method: method,
        credentials: 'same-origin', // sends and stores session cookies (PHPSESSID)
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    };

    // The browser sets Content-Type (with boundary) for FormData itself
    if (!isFormData) options.headers['Content-Type'] = 'application/json';

    let url = endpoint;
    if (method === 'GET') {
        const query = new URLSearchParams(data || {}).toString();
        if (query) url += '?' + query;
    } else if (data !== undefined) {
        options.body = isFormData ? data : JSON.stringify(data);
    }

    let response;
    try {
        response = await fetch(url, options);
    } catch (error) {
        console.error('Error fetching data.', error);
        throw new Error('Server is unavailable.');
    }

    const raw = await response.text();
    let result;
    try {
        result = JSON.parse(raw.replace(/^\uFEFF/, ''));
    } catch (error) {
        console.error('API returned non-JSON response (HTTP ' + response.status + ').');
        const e = new Error('Neplatná odpověď serveru (HTTP ' + response.status + ').');
        e.status = response.status;
        throw e;
    }
    if (!response.ok || result.success === false) {
        const e = new Error(result.message || 'Požadavek selhal.');
        e.status = response.status;
        e.errors = result.errors || {};
        throw e;
    }

    return result;
}

/*
	📦 AUTH
*/

/**
Example of the "data" variable structure:
register({email: "testUser@email.com", password: "12345", password_confirm: "12345"})
**/

// Register user
async function register(data) {
    return sendApiRequest('/api/auth/register', data, 'POST');
}

// Log in user
async function login(data) {
    return sendApiRequest('/api/auth/login', data, 'POST');
}

// Log out user
async function logout() {
    return sendApiRequest('/api/auth/logout', {}, 'POST');
}

// Request password reset e-mail
async function requestPasswordReset(data) {
    return sendApiRequest('/api/auth/forgot-password', data, 'POST');
}

// Set new password by token
async function resetPassword(data) {
    return sendApiRequest('/api/auth/reset-password', data, 'POST');
}

// Verify e-mail by token
async function verifyEmail(data) {
    return sendApiRequest('/api/auth/verify', data, 'GET');
}

/*
	👤 ACCOUNT
*/

// Account overview
async function getAccount() {
    return sendApiRequest('/api/account/account', {}, 'GET');
}

// Get personal data
async function getProfile() {
    return sendApiRequest('/api/account/profile', {}, 'GET');
}

// Update personal data
async function updateProfile(data) {
    return sendApiRequest('/api/account/profile', data, 'PUT');
}

// KYC status and documents
async function getKyc() {
    return sendApiRequest('/api/account/kyc', {}, 'GET');
}

/**
Example of the "data" variable structure (file upload uses FormData):
const data = new FormData();
data.append('document_type', 'passport');
data.append('file', fileInput.files[0]);
uploadKycDocument(data)
**/

// Upload KYC document
async function uploadKycDocument(data) {
    return sendApiRequest('/api/account/kyc', data, 'POST');
}

// Get account settings
async function getSettings() {
    return sendApiRequest('/api/account/settings', {}, 'GET');
}

// Update account settings
async function updateSettings(data) {
    return sendApiRequest('/api/account/settings', data, 'PUT');
}

// Change password
async function changePassword(data) {
    return sendApiRequest('/api/account/password', data, 'PUT');
}

// Tournaments of the user (data: {page, limit})
async function getAccountTournaments(data = {}) {
    return sendApiRequest('/api/account/tournaments', data, 'GET');
}

// Bonuses of the user (data: {status, page, limit})
async function getAccountBonuses(data = {}) {
    return sendApiRequest('/api/account/bonuses', data, 'GET');
}

/*
	💰 WALLET
*/

/**
Example of the "data" variable structure:
createWallet({currency: "CZK"})
depositToWallet({currency: "CZK", amount: 100})
**/

// List wallets of the logged-in user
async function getWallets() {
    return sendApiRequest('/api/wallet/wallets', {}, 'GET');
}

// Wallet detail (data: {wallet_id})
async function getWallet(data) {
    return sendApiRequest('/api/wallet/wallet', data, 'GET');
}

// Create wallet (data: {currency})
async function createWallet(data) {
    return sendApiRequest('/api/wallet/wallet', data, 'POST');
}

// Update wallet (data: {wallet_id, currency})
async function updateWallet(data) {
    return sendApiRequest('/api/wallet/wallet', data, 'PUT');
}

// Delete wallet (data: {wallet_id})
async function deleteWallet(data) {
    return sendApiRequest('/api/wallet/wallet', data, 'DELETE');
}

// Deposit by currency (data: {currency, amount})
async function depositToWallet(data) {
    return sendApiRequest('/api/wallet/deposit', data, 'POST');
}

// Withdraw by currency (data: {currency, amount})
async function withdrawFromWallet(data) {
    return sendApiRequest('/api/wallet/withdraw', data, 'POST');
}

/*
	🧾 TRANSACTION
*/

// Transaction history with filter (data: {type, currency, wallet_id, from, to, page, limit})
async function getTransactions(data = {}) {
    return sendApiRequest('/api/transaction/transactions', data, 'GET');
}

// Transaction detail (data: {transaction_id})
async function getTransaction(data) {
    return sendApiRequest('/api/transaction/transaction', data, 'GET');
}

/*
	🎰 CASINO
*/

// List games (data: {filter, provider, page, limit})
async function getGames(data = {}) {
    return sendApiRequest('/api/casino/games', data, 'GET');
}

// Game detail (data: {game_id})
async function getGame(data) {
    return sendApiRequest('/api/casino/game', data, 'GET');
}

// List game providers
async function getProviders() {
    return sendApiRequest('/api/casino/providers', {}, 'GET');
}

// List favorite games (data: {page, limit})
async function getFavoriteGames(data = {}) {
    return sendApiRequest('/api/casino/favorites', data, 'GET');
}

// Add game to favorites (data: {game_id})
async function addFavoriteGame(data) {
    return sendApiRequest('/api/casino/favorite', data, 'POST');
}

// Remove game from favorites (data: {game_id})
async function removeFavoriteGame(data) {
    return sendApiRequest('/api/casino/favorite', data, 'DELETE');
}

/*
	🏆 TOURNAMENT
*/

// List tournaments by status (data: {status, page, limit})
async function getTournaments(data = {}) {
    return sendApiRequest('/api/tournament/tournaments', data, 'GET');
}

// Tournament detail (data: {tournament_id})
async function getTournament(data) {
    return sendApiRequest('/api/tournament/tournament', data, 'GET');
}

// Tournament leaderboard (data: {tournament_id, limit})
async function getTournamentLeaderboard(data) {
    return sendApiRequest('/api/tournament/leaderboard', data, 'GET');
}

// Join tournament (data: {tournament_id})
async function joinTournament(data) {
    return sendApiRequest('/api/tournament/join', data, 'POST');
}

/*
	🎁 BONUS
*/

// List available bonuses (data: {page, limit})
async function getBonuses(data = {}) {
    return sendApiRequest('/api/bonus/bonuses', data, 'GET');
}

// Bonus detail and conditions (data: {bonus_id})
async function getBonus(data) {
    return sendApiRequest('/api/bonus/bonus', data, 'GET');
}

// Activate bonus (data: {bonus_id})
async function claimBonus(data) {
    return sendApiRequest('/api/bonus/claim', data, 'POST');
}

/*
	🛠️ ADMIN
*/

// Dashboard data
async function getAdminDashboard() {
    return sendApiRequest('/api/admin/dashboard', {}, 'GET');
}

/*
	💳 PAYMENT METHOD
*/

/**
Example of the "data" variable structure:
createPaymentMethod({code: "my_card", name: "My card", card_number: "4242 4242 4242 4242", card_expiry: "12/30", card_holder_name: "JAN NOVAK", can_deposit: true})
The user is taken from the session. The full card number is never returned (only card_last4) and the CVV is not accepted/stored.
**/

// List payment methods of the logged-in user (data: {page, limit})
async function getPaymentMethods(data = {}) {
    return sendApiRequest('/api/payment-method/payment-methods', data, 'GET');
}

// Payment method detail (data: {method_id})
async function getPaymentMethod(data) {
    return sendApiRequest('/api/payment-method/payment-method', data, 'GET');
}

// Create payment method (data: {code, name, card_number, card_expiry, card_holder_name, recipient_*, can_deposit, can_withdraw, is_active})
async function createPaymentMethod(data) {
    return sendApiRequest('/api/payment-method/payment-method', data, 'POST');
}

// Update payment method, partial (data: {method_id, ...fields to change})
async function updatePaymentMethod(data) {
    return sendApiRequest('/api/payment-method/payment-method', data, 'PUT');
}

// Delete payment method (data: {method_id})
async function deletePaymentMethod(data) {
    return sendApiRequest('/api/payment-method/payment-method', data, 'DELETE');
}
