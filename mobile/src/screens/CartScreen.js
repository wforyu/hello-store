import React, { useState, useCallback } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, Image,
  StyleSheet, ActivityIndicator, Alert,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { useAuth } from '../context/AuthContext';
import LoginPrompt from '../components/LoginPrompt';
import api from '../api/client';
import { COLORS, getImageUrl } from '../config';

export default function CartScreen({ navigation }) {
  const { user, refreshCartCount } = useAuth();
  const [items, setItems] = useState([]);
  const [subtotal, setSubtotal] = useState(0);
  const [loading, setLoading] = useState(true);

  useFocusEffect(
    useCallback(() => {
      fetchCart();
    }, [])
  );

  const fetchCart = async () => {
    setLoading(true);
    try {
      const response = await api.get('/api/cart');
      if (response.data?.success) {
        setItems(response.data.data.items || []);
        setSubtotal(response.data.data.subtotal || 0);
      }
    } catch (e) {
    } finally {
      setLoading(false);
    }
  };

  const updateQty = async (id, quantity, stock) => {
    const newQty = Math.min(Math.max(1, quantity), stock);
    try {
      await api.post('/api/cart/update', { items: [{ id, quantity: newQty }] });
      fetchCart();
      refreshCartCount();
    } catch (e) {
    }
  };

  const removeItem = async (id) => {
    try {
      await api.delete(`/api/cart/remove/${id}`);
      fetchCart();
      refreshCartCount();
    } catch (e) {
      Alert.alert('Error', 'Gagal menghapus item.');
    }
  };

  const formatPrice = (p) => `Rp${Number(p).toLocaleString('id-ID')}`;

  const renderItem = ({ item }) => (
    <View style={styles.itemCard}>
      <Image
        source={{ uri: getImageUrl(item.image) }}
        style={styles.itemImage}
      />
      <View style={styles.itemInfo}>
        <Text style={styles.itemName} numberOfLines={2}>{item.name}</Text>
        <Text style={styles.itemPrice}>{formatPrice(item.price)}</Text>
        <View style={styles.qtyControl}>
          <TouchableOpacity
            style={styles.qtyBtn}
            onPress={() => updateQty(item.id, item.quantity - 1, item.stock)}
          >
            <Text style={styles.qtyBtnText}>-</Text>
          </TouchableOpacity>
          <Text style={styles.qtyValue}>{item.quantity}</Text>
          <TouchableOpacity
            style={styles.qtyBtn}
            onPress={() => updateQty(item.id, item.quantity + 1, item.stock)}
          >
            <Text style={styles.qtyBtnText}>+</Text>
          </TouchableOpacity>
        </View>
      </View>
      <TouchableOpacity onPress={() => removeItem(item.id)} style={styles.removeBtn}>
        <Text style={styles.removeText}>×</Text>
      </TouchableOpacity>
    </View>
  );

  if (!user) {
    return <LoginPrompt navigation={navigation} message="Silakan login untuk melihat dan mengelola keranjang." />;
  }

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {items.length === 0 ? (
        <View style={styles.center}>
          <Text style={styles.emptyText}>Keranjang belanja kosong.</Text>
          <TouchableOpacity onPress={() => navigation.navigate('Home')}>
            <Text style={styles.shopLink}>Belanja Sekarang</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <>
          <FlatList
            data={items}
            renderItem={renderItem}
            keyExtractor={(item) => String(item.id)}
            contentContainerStyle={styles.list}
          />
          <View style={styles.bottomBar}>
            <View>
              <Text style={styles.totalLabel}>Total</Text>
              <Text style={styles.totalValue}>{formatPrice(subtotal)}</Text>
            </View>
            <TouchableOpacity
              style={styles.checkoutBtn}
              onPress={() => navigation.navigate('Checkout')}
            >
              <Text style={styles.checkoutText}>Checkout</Text>
            </TouchableOpacity>
          </View>
        </>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
  list: { padding: 12 },
  itemCard: {
    flexDirection: 'row', backgroundColor: COLORS.white, borderRadius: 12,
    padding: 12, marginBottom: 10, alignItems: 'center',
    elevation: 1, shadowColor: '#000', shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05, shadowRadius: 3,
  },
  itemImage: { width: 70, height: 70, borderRadius: 8, backgroundColor: COLORS.border },
  itemInfo: { flex: 1, marginLeft: 12 },
  itemName: { fontSize: 14, fontWeight: '600', color: COLORS.text, marginBottom: 4 },
  itemPrice: { fontSize: 15, fontWeight: '700', color: COLORS.primary, marginBottom: 8 },
  qtyControl: { flexDirection: 'row', alignItems: 'center' },
  qtyBtn: {
    width: 28, height: 28, borderRadius: 14, backgroundColor: COLORS.background,
    justifyContent: 'center', alignItems: 'center',
  },
  qtyBtnText: { fontSize: 16, fontWeight: '600', color: COLORS.text },
  qtyValue: { fontSize: 14, fontWeight: '600', marginHorizontal: 12, color: COLORS.text },
  removeBtn: { padding: 8, marginLeft: 8 },
  removeText: { fontSize: 22, color: COLORS.error, fontWeight: '300' },
  bottomBar: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
    padding: 16, backgroundColor: COLORS.white, borderTopWidth: 1, borderTopColor: COLORS.border,
  },
  totalLabel: { fontSize: 13, color: COLORS.textSecondary },
  totalValue: { fontSize: 20, fontWeight: '700', color: COLORS.primary },
  checkoutBtn: {
    backgroundColor: COLORS.primary, borderRadius: 12, paddingHorizontal: 32, paddingVertical: 14,
  },
  checkoutText: { color: '#fff', fontSize: 16, fontWeight: '600' },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
  emptyText: { fontSize: 16, color: COLORS.textSecondary, marginBottom: 12 },
  shopLink: { fontSize: 16, color: COLORS.primary, fontWeight: '600' },
});
