export const formatPrice = (p) => `Rp${Number(p || 0).toLocaleString('id-ID')}`;

export const STATUS_COLORS = {
  pending: '#F59E0B', processing: '#3B82F6', shipped: '#8B5CF6',
  delivered: '#10B981', cancelled: '#EF4444', refunded: '#6B7280',
};

export const STATUS_LABELS = {
  pending: 'Menunggu', processing: 'Diproses', shipped: 'Dikirim',
  delivered: 'Diterima', cancelled: 'Dibatalkan', refunded: 'Dikembalikan',
};
