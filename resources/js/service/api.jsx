import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
});

export const login = async (credentials, type = 'user') => {
  const endpoint = type === 'client' ? '/login-client' : '/login';
  return api.post(endpoint, credentials);
};

export const getClients = async () => api.get('/clients');
export const getLoans = async () => api.get('/loans');
export const getPayments = async () => api.get('/payments');

export default api;
