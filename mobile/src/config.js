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

export const resetApiUrl = async () => {
  _cachedUrl = null;
  await SecureStore.deleteItemAsync(API_URL_KEY);
};

export const checkForUrlUpdate = async () => {
  try {
    const currentUrl = await getApiUrl();
    const res = await fetch(`${currentUrl}/api/config`, {
      headers: { Accept: 'application/json' },
      signal: AbortSignal.timeout(8000),
    });
    const json = await res.json();
    const serverUrl = json?.data?.api_url;
    if (serverUrl && serverUrl !== currentUrl) {
      await setApiUrl(serverUrl);
      return { updated: true, oldUrl: currentUrl, newUrl: serverUrl };
    }
    return { updated: false, currentUrl };
  } catch (e) {
    if (_cachedUrl && _cachedUrl !== FALLBACK_API_URL) {
      try {
        const res = await fetch(`${FALLBACK_API_URL}/api/config`, {
          headers: { Accept: 'application/json' },
          signal: AbortSignal.timeout(8000),
        });
        const json = await res.json();
        const serverUrl = json?.data?.api_url;
        if (serverUrl && serverUrl !== _cachedUrl) {
          await setApiUrl(serverUrl);
          return { updated: true, oldUrl: _cachedUrl, newUrl: serverUrl };
        }
      } catch (e2) {}
    }
    return { updated: false, error: e.message };
  }
};

export const testApiUrl = async (url) => {
  try {
    const res = await fetch(`${url}/api/config`, {
      headers: { Accept: 'application/json' },
      signal: AbortSignal.timeout(8000),
    });
    const json = await res.json();
    return { success: json?.success === true, data: json?.data };
  } catch (e) {
    return { success: false, error: e.message };
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
