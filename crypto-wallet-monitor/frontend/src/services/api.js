import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
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

export function getWalletBalance(walletId) {
  return api.get(`/wallets/${walletId}/balance`);
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
   SENHA
========================= */

export function forgotPassword(email) {
  return api.post('/forgot-password', { email });
}

export function resetPassword({ token, email, password }) {
  return api.post('/reset-password', { token, email, password });
}

export default api;
