import axios from 'axios';
import * as SecureStore from 'expo-secure-store';
import { API_URL } from '../config';

const TOKEN_KEY = 'auth_token';

const api = axios.create({
  baseURL: API_URL,
  timeout: 15000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'ngrok-skip-browser-warning': 'true',
  },
});

api.interceptors.request.use(async (config) => {
  try {
    const token = await SecureStore.getItemAsync(TOKEN_KEY);
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
  } catch (e) {
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
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

export default api;
