import axios from 'axios';
import * as SecureStore from 'expo-secure-store';
import { getApiUrl, API_URL_KEY, FALLBACK_API_URL } from '../config';

const TOKEN_KEY = 'auth_token';

let cachedBaseUrl = null;

const getBaseUrl = async () => {
  if (cachedBaseUrl) return cachedBaseUrl;
  cachedBaseUrl = await getApiUrl();
  return cachedBaseUrl;
};

const api = axios.create({
  timeout: 15000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'ngrok-skip-browser-warning': 'true',
  },
});

api.interceptors.request.use(async (config) => {
  const baseURL = await getBaseUrl();
  config.baseURL = baseURL;

  try {
    const token = await SecureStore.getItemAsync(TOKEN_KEY);
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
  } catch (e) {}
  return config;
});

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      SecureStore.deleteItemAsync(TOKEN_KEY);
    }

    if (!error.response) {
      error.friendlyMessage = 'Tidak bisa terhubung ke server.\nPastikan server menyala dan HP terhubung ke WiFi yang sama.';
    }

    return Promise.reject(error);
  },
);

export const setToken = async (token) => {
  if (token) {
    await SecureStore.setItemAsync(TOKEN_KEY, token);
  } else {
    await SecureStore.deleteItemAsync(TOKEN_KEY);
  }
};

export const getToken = () => SecureStore.getItemAsync(TOKEN_KEY);

export const clearToken = () => SecureStore.deleteItemAsync(TOKEN_KEY);

export const refreshBaseUrl = () => {
  cachedBaseUrl = null;
};

export default api;
