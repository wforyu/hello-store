import * as SecureStore from 'expo-secure-store';

export const API_URL_KEY = 'api_url';
export const FALLBACK_API_URL = 'https://hello-store.page.gd';

export const BOT_UA = 'Googlebot/2.1 (+http://www.google.com/bot.html)';
const UA_HEADERS = { Accept: 'application/json', 'User-Agent': BOT_UA };

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
      headers: UA_HEADERS,
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
          headers: UA_HEADERS,
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
      headers: UA_HEADERS,
      signal: AbortSignal.timeout(8000),
    });
    const json = await res.json();
    return { success: json?.success === true, data: json?.data };
  } catch (e) {
    return { success: false, error: e.message };
  }
};

// Base URL of the running store, always without a trailing slash so callers
// never build a "//path" URL. `mobile_api_url` in the admin settings is stored
// with a trailing slash, and _cachedUrl is replaced wholesale by
// checkForUrlUpdate(), so strip it here rather than at every call site.
const currentBaseUrl = () => (_cachedUrl || FALLBACK_API_URL).replace(/\/+$/, '');

export const getImageUrl = (url) => {
  if (!url) return 'https://via.placeholder.com/200';
  if (url.startsWith('http')) return url;
  return getStoreUrl(url);
};

// Public storefront URL (the API host serves the storefront too), so a shared
// product link always points at whichever domain this build is talking to.
// Never hardcode the domain here: it changes when admin edits mobile_api_url.
export const getStoreUrl = (path = '') => {
  const base = currentBaseUrl();
  if (!path) return base;
  return `${base}/${String(path).replace(/^\/+/, '')}`;
};

// The host sits behind a UA-based anti-bot wall: any request that is not a
// known crawler gets an AES challenge page (text/html) instead of the file.
// <Image> cannot be given a custom User-Agent on its own, so every remote
// image has to carry the crawler UA explicitly or it silently renders blank.
export const IMAGE_HEADERS = { 'User-Agent': BOT_UA };

export const getImageSource = (url) => ({
  uri: getImageUrl(url),
  headers: IMAGE_HEADERS,
});

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
