import * as SecureStore from 'expo-secure-store';

export const API_URL_KEY = 'api_url';
export const FALLBACK_API_URL = 'https://change-passivism-implode.ngrok-free.dev';

let _cachedUrl = null;

export const getApiUrl = async () => {
  if (_cachedUrl) return _cachedUrl;
  try {
    const stored = await SecureStore.getItemAsync(API_URL_KEY);
    if (stored) {
      _cachedUrl = stored;
      return stored;
    }
  } catch (e) {}
  _cachedUrl = FALLBACK_API_URL;
  return FALLBACK_API_URL;
};

export const setApiUrl = async (url) => {
  if (url) {
    _cachedUrl = url;
    await SecureStore.setItemAsync(API_URL_KEY, url);
  }
};

export const getImageUrl = (url) => {
  if (!url) return 'https://via.placeholder.com/200';
  if (url.startsWith('http')) return url;
  const base = _cachedUrl || FALLBACK_API_URL;
  return `${base}${url.startsWith('/') ? '' : '/'}${url}`;
};

export const COLORS = {
  primary: '#F59E0B',
  primaryDark: '#D97706',
  secondary: '#1F2937',
  background: '#F9FAFB',
  white: '#FFFFFF',
  text: '#111827',
  textSecondary: '#6B7280',
  textLight: '#9CA3AF',
  border: '#E5E7EB',
  error: '#EF4444',
  success: '#10B981',
  warning: '#F59E0B',
  info: '#3B82F6',
};
