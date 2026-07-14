export const API_URL = 'https://yard-steed-ferris.ngrok-free.dev';

export const getImageUrl = (url) => {
  if (!url) return 'https://via.placeholder.com/200';
  if (url.startsWith('http')) return url;
  return `${API_URL}${url.startsWith('/') ? '' : '/'}${url}`;
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
