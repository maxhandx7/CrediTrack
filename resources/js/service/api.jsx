import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  withCredentials: true, 
});


api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});


export const login = async (credentials, type = 'user') => {
  const endpoint = type === 'client' ? '/auth/client/login' : '/auth/user/login';
  return api.post(endpoint, credentials);
};

export const register = async (userData, type = 'user') => {
  const endpoint = type === 'client' ? '/auth/client/register' : '/auth/user/register';
  return api.post(endpoint, userData);
};


export const getClients = async () => api.get('/clients');
export const getLoans = async () => api.get('/loans');
export const getPayments = async () => api.get('/payments');
export const getSchedules = async () => api.get('/schedules');





api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      console.warn('⚠️ Token expirado o sesión inválida');
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      window.location.href = '/'; 
    }
    return Promise.reject(error);
  }
);

export default api;
