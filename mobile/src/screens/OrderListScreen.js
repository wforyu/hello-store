import React, { useState, useCallback } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, StyleSheet, ActivityIndicator, RefreshControl,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '../context/AuthContext';
import { useAlert } from '../context/AlertContext';
import LoginPrompt from '../components/LoginPrompt';
import api from '../api/client';
import { COLORS } from '../config';

const STATUS_COLORS = {
  pending: '#F59E0B',
  processing: '#3B82F6',
  shipped: '#8B5CF6',
  delivered: '#10B981',
  cancelled: '#EF4444',
  refunded: '#6B7280',
};

const STATUS_LABELS = {
  pending: 'Menunggu', processing: 'Diproses', shipped: 'Dikirim',
  delivered: 'Diterima', cancelled: 'Dibatalkan', refunded: 'Dikembalikan',
};

export default function OrderListScreen({ navigation }) {
  const insets = useSafeAreaInsets();
  const { user } = useAuth();
  const { showAlert } = useAlert();
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  useFocusEffect(
    useCallback(() => {
      fetchOrders(1);
    }, [])
  );

  const fetchOrders = async (pageNum = 1) => {
    try {
      const response = await api.get('/api/orders', { params: { page: pageNum, per_page: 10 } });
      const data = response.data?.data;
      if (data) {
        const items = data.data || [];
        setOrders(pageNum === 1 ? items : (prev) => [...prev, ...items]);
        setPage(data.current_page || 1);
        setLastPage(data.last_page || 1);
      }
    } catch (e) {
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const onRefresh = () => {
    setRefreshing(true);
    fetchOrders(1);
  };

  const loadMore = () => {
    if (page < lastPage) fetchOrders(page + 1);
  };

  const formatPrice = (p) => `Rp${Number(p).toLocaleString('id-ID')}`;

  const reorder = (item) => {
    showAlert({
      title: 'Beli Lagi',
      message: `Tambahkan item dari pesanan ${item.order_number} ke keranjang?`,
      type: 'warning',
      buttons: [
        { text: 'Batal', style: 'cancel' },
        {
          text: 'Ya, Beli Lagi',
          onPress: async () => {
            try {
              const res = await api.post(`/api/orders/${item.id}/reorder`);
              const data = res.data;
              if (data?.success) {
                const added = data.added ?? 0;
                const skipped = data.skipped ?? 0;
                let msg = `${added} item ditambahkan ke keranjang.`;
                if (skipped > 0) msg += `\n${skipped} item dilewati (stok habis/tidak aktif).`;
                showAlert({ title: 'Berhasil', message: msg, type: 'success', buttons: [{ text: 'OK', onPress: () => navigation.navigate('Cart') }] });
              }
            } catch (e) {
              const msg = e.response?.data?.message || 'Gagal memproses beli lagi.';
              showAlert({ title: 'Error', message: msg, type: 'error' });
            }
          },
        },
      ],
    });
  };

  const renderOrder = ({ item }) => {
    const statusColor = STATUS_COLORS[item.status] || COLORS.textSecondary;
    const statusLabel = STATUS_LABELS[item.status] || item.status;
    return (
      <TouchableOpacity
        style={styles.orderCard}
        onPress={() => navigation.navigate('OrderDetail', { order: item })}
      >
        <View style={styles.orderHeader}>
          <Text style={styles.orderNumber}>{item.order_number}</Text>
          <View style={[styles.badge, { backgroundColor: statusColor + '20' }]}>
            <Text style={[styles.badgeText, { color: statusColor }]}>{statusLabel}</Text>
          </View>
        </View>
        <Text style={styles.orderDate}>{new Date(item.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</Text>
        <View style={styles.orderFooter}>
          <Text style={styles.orderItems}>
            {item.items?.length || 0} item{item.items?.length !== 1 ? 's' : ''}
          </Text>
          <Text style={styles.orderTotal}>{formatPrice(item.total)}</Text>
        </View>
        {item.status === 'delivered' && (
          <TouchableOpacity
            style={styles.reorderBtn}
            onPress={() => reorder(item)}
          >
            <Text style={styles.reorderBtnText}>Beli Lagi</Text>
          </TouchableOpacity>
        )}
      </TouchableOpacity>
    );
  };

  if (!user) {
    return <LoginPrompt navigation={navigation} message="Silakan login untuk melihat pesanan." />;
  }

  return (
    <View style={[styles.container, { paddingTop: insets.top }]}>
      {loading && orders.length === 0 ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={COLORS.primary} />
        </View>
      ) : (
        <FlatList
          data={orders}
          renderItem={renderOrder}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={styles.list}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[COLORS.primary]} />
          }
          onEndReached={loadMore}
          onEndReachedThreshold={0.5}
          ListFooterComponent={
            page < lastPage ? <ActivityIndicator style={{ padding: 16 }} /> : null
          }
          ListEmptyComponent={
            <View style={styles.center}>
              <Text style={styles.emptyText}>Belum ada pesanan.</Text>
            </View>
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
  list: { padding: 12 },
  orderCard: {
    backgroundColor: COLORS.white, borderRadius: 12, padding: 16,
    marginBottom: 10, elevation: 1, shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 }, shadowOpacity: 0.05, shadowRadius: 3,
  },
  orderHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 },
  orderNumber: { fontSize: 14, fontWeight: '700', color: COLORS.text },
  badge: { paddingHorizontal: 10, paddingVertical: 3, borderRadius: 20 },
  badgeText: { fontSize: 11, fontWeight: '600' },
  orderDate: { fontSize: 12, color: COLORS.textSecondary, marginBottom: 8 },
  orderFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  orderItems: { fontSize: 13, color: COLORS.textSecondary },
  orderTotal: { fontSize: 16, fontWeight: '700', color: COLORS.primary },
  reorderBtn: {
    marginTop: 10, paddingVertical: 8, paddingHorizontal: 16,
    backgroundColor: COLORS.primary + '15', borderRadius: 8,
    alignSelf: 'flex-start',
  },
  reorderBtnText: { fontSize: 13, fontWeight: '600', color: COLORS.primary },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
  emptyText: { fontSize: 16, color: COLORS.textSecondary },
});
