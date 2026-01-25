import api from './api';

export function getWallets() {
  return api.get('/wallets');
}

export function getWalletBalance(walletId) {
  return api.get(`/wallets/${walletId}/balance`);
}