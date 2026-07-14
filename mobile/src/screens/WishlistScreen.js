import React, { useState, useCallback } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, Image, StyleSheet,
  ActivityIndicator, RefreshControl,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '../context/AuthContext';
import { useAlert } from '../context/AlertContext';
import LoginPrompt from '../components/LoginPrompt';
import api from '../api/client';
import { COLORS, getImageUrl } from '../config';
import { formatPrice } from '../utils';

export default function WishlistScreen({ navigation }) {
  const insets = useSafeAreaInsets();
  const { user } = useAuth();
  const { showAlert } = useAlert();
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [removing, setRemoving] = useState(null);

  useFocusEffect(
    useCallback(() => {
      if (user) fetchWishlist(1);
    }, [user])
  );

  if (!user) {
    return <LoginPrompt navigation={navigation} message="Silakan login untuk melihat wishlist." />;
  }

  const fetchWishlist = async (pageNum = 1) => {
    try {
      const res = await api.get('/api/wishlist', { params: { page: pageNum, per_page: 10 } });
      const data = res.data?.data;
      if (data) {
        const list = data.data || [];
        setItems(pageNum === 1 ? list : (prev) => [...prev, ...list]);
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
    fetchWishlist(1);
  };

  const loadMore = () => {
    if (page < lastPage) fetchWishlist(page + 1);
  };

  const removeItem = (item) => {
    const product = item.product || item;
    showAlert({
      title: 'Hapus Wishlist',
      message: `Hapus "${product.name}" dari wishlist?`,
      type: 'warning',
      buttons: [
        { text: 'Batal', style: 'cancel' },
        {
          text: 'Hapus',
          style: 'destructive',
          onPress: async () => {
            setRemoving(product.id);
            try {
              await api.post(`/api/wishlist/toggle/${product.id}`);
              setItems((prev) => prev.filter((w) => {
                const p = w.product || w;
                return p.id !== product.id;
              }));
            } catch (e) {
              const msg = e.response?.data?.message || 'Gagal menghapus.';
              showAlert({ title: 'Error', message: msg, type: 'error' });
            } finally {
              setRemoving(null);
            }
          },
        },
      ],
    });
  };

  const renderItem = ({ item }) => {
    const product = item.product || item;
    const image = product.image || product.main_image || product.images?.[0] || null;

    return (
      <TouchableOpacity
        style={styles.card}
        onPress={() => navigation.navigate('ProductDetail', { product })}
        activeOpacity={0.7}
      >
        <Image source={{ uri: getImageUrl(image) }} style={styles.image} />
        <View style={styles.info}>
          <Text style={styles.name} numberOfLines={2}>{product.name}</Text>
          <Text style={styles.price}>{formatPrice(product.price)}</Text>
          {product.compare_price && Number(product.compare_price) > Number(product.price) && (
            <Text style={styles.comparePrice}>{formatPrice(product.compare_price)}</Text>
          )}
        </View>
        <TouchableOpacity
          style={[styles.removeBtn, removing === product.id && { opacity: 0.4 }]}
          onPress={() => removeItem(item)}
          disabled={removing === product.id}
        >
          <Text style={styles.removeBtnText}>✕</Text>
        </TouchableOpacity>
      </TouchableOpacity>
    );
  };

  return (
    <View style={styles.container}>
      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={COLORS.primary} />
        </View>
      ) : (
        <FlatList
          data={items}
          renderItem={renderItem}
          keyExtractor={(item) => {
            const p = item.product || item;
            return String(p.id);
          }}
          contentContainerStyle={[styles.list, { paddingBottom: insets.bottom + 40 }]}
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
              <Text style={styles.emptyIcon}>♡</Text>
              <Text style={styles.emptyText}>Belum ada wishlist</Text>
            </View>
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
  list: { padding: 12, paddingBottom: 40 },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
  emptyIcon: { fontSize: 48, color: COLORS.textLight, marginBottom: 12 },
  emptyText: { fontSize: 16, color: COLORS.textSecondary },
  card: {
    flexDirection: 'row', alignItems: 'center',
    backgroundColor: COLORS.white, borderRadius: 12, padding: 12,
    marginBottom: 10, elevation: 1, shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 }, shadowOpacity: 0.05, shadowRadius: 3,
  },
  image: {
    width: 72, height: 72, borderRadius: 10, backgroundColor: COLORS.border,
  },
  info: { flex: 1, marginLeft: 12 },
  name: { fontSize: 14, fontWeight: '600', color: COLORS.text, marginBottom: 4 },
  price: { fontSize: 15, fontWeight: '700', color: COLORS.primary },
  comparePrice: {
    fontSize: 12, color: COLORS.textLight,
    textDecorationLine: 'line-through', marginTop: 2,
  },
  removeBtn: {
    width: 32, height: 32, borderRadius: 16,
    backgroundColor: COLORS.error + '15', justifyContent: 'center', alignItems: 'center',
    marginLeft: 8,
  },
  removeBtnText: { fontSize: 14, color: COLORS.error, fontWeight: '600' },
});
