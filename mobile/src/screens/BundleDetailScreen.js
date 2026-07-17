import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, StyleSheet, ScrollView, Image, TouchableOpacity,
  ActivityIndicator, RefreshControl,
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import { COLORS, getImageUrl } from '../config';
import { formatPrice } from '../utils';
import api from '../api/client';

export default function BundleDetailScreen({ route, navigation }) {
  const { bundleId } = route.params;
  const { user, refreshCartCount } = useAuth();
  const [bundle, setBundle] = useState(null);
  const [loading, setLoading] = useState(true);
  const [adding, setAdding] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState(null);

  const fetchBundle = useCallback(async () => {
    try {
      const res = await api.get(`/api/bundles/${bundleId}`);
      if (res.data?.success) {
        setBundle(res.data.data);
        setError(null);
      } else {
        setError(res.data?.message || 'Paket tidak ditemukan');
      }
    } catch (e) {
      setError('Gagal memuat data paket');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [bundleId]);

  useEffect(() => { fetchBundle(); }, [fetchBundle]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchBundle();
  }, [fetchBundle]);

  const handleAddToCart = async () => {
    if (!user) {
      navigation.navigate('Login');
      return;
    }
    setAdding(true);
    try {
      const res = await api.post(`/api/bundles/${bundleId}/add-to-cart`);
      if (res.data?.success) {
        refreshCartCount();
        navigation.navigate('Main', { screen: 'Cart' });
      } else {
        setError(res.data?.message || 'Gagal menambahkan ke keranjang');
      }
    } catch (e) {
      setError('Gagal menambahkan ke keranjang');
    } finally {
      setAdding(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }

  if (error && !bundle) {
    return (
      <View style={styles.center}>
        <Text style={styles.errorText}>{error}</Text>
        <TouchableOpacity onPress={fetchBundle} style={styles.retryBtn}>
          <Text style={styles.retryText}>Coba Lagi</Text>
        </TouchableOpacity>
      </View>
    );
  }

  if (!bundle) return null;

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={{ paddingBottom: 100 }}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[COLORS.primary]} />}
    >
      {error && (
        <View style={styles.errorBanner}>
          <Text style={styles.errorBannerText}>{error}</Text>
        </View>
      )}

      {/* Bundle Image */}
      {bundle.image ? (
        <Image source={{ uri: getImageUrl(bundle.image) }} style={styles.heroImage} resizeMode="cover" />
      ) : (
        <View style={[styles.heroImage, styles.heroPlaceholder]}>
          <Text style={{ fontSize: 60 }}>🎁</Text>
        </View>
      )}

      {/* Bundle Info */}
      <View style={styles.section}>
        <Text style={styles.bundleName}>{bundle.name}</Text>
        {bundle.description && (
          <Text style={styles.bundleDesc}>{bundle.description}</Text>
        )}

        {/* Price Card */}
        <View style={styles.priceCard}>
          <View style={styles.priceRow}>
            <Text style={styles.priceLabel}>Harga Paket</Text>
            <Text style={styles.priceValue}>{bundle.bundle_price_formatted}</Text>
          </View>
          {bundle.total_original_price > bundle.bundle_price && (
            <>
              <View style={styles.priceRow}>
                <Text style={styles.priceLabel}>Harga Normal</Text>
                <Text style={styles.originalPrice}>{bundle.total_original_price_formatted}</Text>
              </View>
              <View style={styles.savingsRow}>
                <Text style={styles.savingsLabel}>Hemat</Text>
                <Text style={styles.savingsValue}>{bundle.savings_formatted}</Text>
              </View>
            </>
          )}
        </View>
      </View>

      {/* Products in Bundle */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Produk dalam Paket ({bundle.products.length})</Text>
        {bundle.products.map((product, index) => (
          <View key={product.id} style={styles.productCard}>
            <Text style={styles.productIndex}>{index + 1}</Text>
            {product.image ? (
              <Image source={{ uri: getImageUrl(product.image) }} style={styles.productImage} resizeMode="contain" />
            ) : (
              <View style={[styles.productImage, styles.productImagePlaceholder]}>
                <Text style={{ fontSize: 18 }}>📦</Text>
              </View>
            )}
            <View style={styles.productInfo}>
              <Text style={styles.productName} numberOfLines={2}>{product.name}</Text>
              {product.brand && <Text style={styles.productBrand}>{product.brand}</Text>}
              <View style={styles.productMeta}>
                <Text style={styles.productPrice}>{product.price_formatted}</Text>
                <Text style={styles.productQty}>x{product.quantity}</Text>
              </View>
            </View>
          </View>
        ))}
      </View>

      {/* Bottom CTA */}
      <View style={styles.bottomBar}>
        <TouchableOpacity
          style={[styles.addButton, adding && styles.addButtonDisabled]}
          onPress={handleAddToCart}
          disabled={adding}
          activeOpacity={0.8}
        >
          {adding ? (
            <ActivityIndicator color="#fff" size="small" />
          ) : (
            <Text style={styles.addButtonText}>🛒 Tambah Paket ke Keranjang</Text>
          )}
        </TouchableOpacity>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: COLORS.background },
  errorText: { fontSize: 14, color: COLORS.error, textAlign: 'center', marginBottom: 12 },
  retryBtn: { backgroundColor: COLORS.primary, paddingHorizontal: 20, paddingVertical: 10, borderRadius: 8 },
  retryText: { color: '#fff', fontWeight: '600' },
  errorBanner: { backgroundColor: '#FEF2F2', padding: 12, borderBottomWidth: 1, borderBottomColor: '#FECACA' },
  errorBannerText: { color: COLORS.error, fontSize: 13, textAlign: 'center' },
  heroImage: { width: '100%', height: 220 },
  heroPlaceholder: { backgroundColor: '#FEF3C7', justifyContent: 'center', alignItems: 'center' },
  section: { padding: 16 },
  bundleName: { fontSize: 20, fontWeight: '800', color: COLORS.text, marginBottom: 4 },
  bundleDesc: { fontSize: 14, color: COLORS.textSecondary, lineHeight: 20, marginBottom: 12 },
  priceCard: { backgroundColor: '#FEF3C7', borderRadius: 12, padding: 16, borderWidth: 1, borderColor: '#FDE68A' },
  priceRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 },
  priceLabel: { fontSize: 13, color: COLORS.textSecondary },
  priceValue: { fontSize: 20, fontWeight: '800', color: COLORS.primary },
  originalPrice: { fontSize: 13, color: COLORS.textLight, textDecorationLine: 'line-through' },
  savingsRow: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 8, paddingTop: 8, borderTopWidth: 1, borderTopColor: '#FDE68A' },
  savingsLabel: { fontSize: 13, fontWeight: '600', color: '#DC2626' },
  savingsValue: { fontSize: 14, fontWeight: '700', color: '#DC2626' },
  sectionTitle: { fontSize: 16, fontWeight: '700', color: COLORS.text, marginBottom: 12 },
  productCard: { flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.white, borderRadius: 12, padding: 12, marginBottom: 8, borderWidth: 1, borderColor: COLORS.border },
  productIndex: { width: 22, height: 22, borderRadius: 11, backgroundColor: COLORS.primary, color: '#fff', fontSize: 11, fontWeight: '700', textAlign: 'center', lineHeight: 22, marginRight: 10 },
  productImage: { width: 56, height: 56, borderRadius: 10, backgroundColor: COLORS.background, marginRight: 12 },
  productImagePlaceholder: { justifyContent: 'center', alignItems: 'center' },
  productInfo: { flex: 1 },
  productName: { fontSize: 14, fontWeight: '600', color: COLORS.text, marginBottom: 2 },
  productBrand: { fontSize: 11, color: COLORS.textLight, marginBottom: 4 },
  productMeta: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  productPrice: { fontSize: 13, fontWeight: '700', color: COLORS.primary },
  productQty: { fontSize: 12, color: COLORS.textSecondary, fontWeight: '600' },
  bottomBar: { position: 'absolute', bottom: 0, left: 0, right: 0, padding: 16, backgroundColor: COLORS.white, borderTopWidth: 1, borderTopColor: COLORS.border },
  addButton: { backgroundColor: COLORS.primary, paddingVertical: 16, borderRadius: 12, alignItems: 'center' },
  addButtonDisabled: { opacity: 0.6 },
  addButtonText: { color: '#fff', fontSize: 16, fontWeight: '700' },
});
