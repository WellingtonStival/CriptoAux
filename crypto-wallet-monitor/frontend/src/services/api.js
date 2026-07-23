import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  headers: {
    Accept: 'application/json',
  },
});

api.interceptors.request.use(config => {
  const token = localStorage.getItem('token');

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});
// ⬇️ Response → trata erro 401
api.interceptors.response.use(
  response => response,
  error => {
    if (
      error.response &&
      error.response.status === 401
    ) {
      // remove token inválido
      localStorage.removeItem("token");

      // evita loop infinito
      if (window.location.pathname !== "/login") {
        window.location.href = "/login";
      }
    }

    return Promise.reject(error);
  }
);

/* =========================
   WALLET
========================= */

export function getWallets() {
  return api.get('/wallets');
}

export function createWallet(address, network, name) {
  return api.post('/wallets', { address, network, name: name || undefined });
}

export function renameWallet(walletId, name) {
  return api.patch(`/wallets/${walletId}`, { name: name || null });
}

export function getWalletBalance(walletId, force = false) {
  return api.get(`/wallets/${walletId}/balance`, { params: { force: force || undefined } });
}

export function getPrices() {
  return api.get('/prices');
}

export function getWalletHistory(walletId, period) {
  return api.get(`/wallets/${walletId}/history`, { params: { period } });
}

export function deleteWallet(walletId) {
  return api.delete(`/wallets/${walletId}`);
}

export function getWalletTransactions(walletId) {
  return api.get(`/wallets/${walletId}/transactions`);
}

export function getPortfolioHistory(period) {
  return api.get('/portfolio/history', { params: { period } });
}

export function getNews(network) {
  return api.get('/news', { params: { network: network || undefined } });
}

/* =========================
   TOKENS / ATIVOS
========================= */

export function getAssets() {
  return api.get('/assets');
}

export function getWalletTokens(walletId) {
  return api.get(`/wallets/${walletId}/tokens`);
}

export function syncWalletTokens(walletId) {
  return api.post(`/wallets/${walletId}/tokens/sync`);
}

export function deleteWalletToken(walletId, tokenId) {
  return api.delete(`/wallets/${walletId}/tokens/${tokenId}`);
}

/* =========================
   MERCADO
========================= */

export function getMarketOverview() {
  return api.get('/market/overview');
}

export function getFearGreedHistory(period) {
  return api.get('/market/fear-greed/history', { params: { period } });
}

/* =========================
   ALERTAS / TELEGRAM
========================= */

export function getTelegramStatus() {
  return api.get('/telegram/status');
}

export function generateTelegramLinkCode() {
  return api.post('/telegram/link-code');
}

export function unlinkTelegram() {
  return api.post('/telegram/unlink');
}

export function getAlerts() {
  return api.get('/alerts');
}

export function createAlert(data) {
  return api.post('/alerts', data);
}

export function updateAlert(id, data) {
  return api.patch(`/alerts/${id}`, data);
}

export function deleteAlert(id) {
  return api.delete(`/alerts/${id}`);
}

/* =========================
   CONTA
========================= */

export function getAccount() {
  return api.get('/account');
}

export function updateAccountName(name) {
  return api.patch('/account', { name });
}

export function updateAccountPassword(data) {
  return api.post('/account/password', data);
}

export function deleteAccount(password) {
  return api.delete('/account', { data: { password } });
}

/* =========================
   SEGURANÇA
========================= */

export function getSecurityApprovals() {
  return api.get('/security/approvals');
}

/* =========================
   SENHA
========================= */

export function forgotPassword(email) {
  return api.post('/forgot-password', { email });
}

export function resetPassword({ token, email, password }) {
  return api.post('/reset-password', { token, email, password });
}

/* =========================
   VERIFICAÇÃO DE EMAIL
========================= */

export function verifyEmail({ token, email }) {
  return api.post('/email/verify', { token, email });
}

export function resendVerification(email) {
  return api.post('/email/resend', { email });
}

export default api;
