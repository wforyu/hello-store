import React, { useState, useEffect } from 'react';
import {
  View, Text, TouchableOpacity, ScrollView, Image,
  StyleSheet, TextInput, ActivityIndicator,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '../context/AuthContext';
import { useAlert } from '../context/AlertContext';
import LoginPrompt from '../components/LoginPrompt';
import api from '../api/client';
import { COLORS, getImageUrl } from '../config';
import { formatPrice, STATUS_COLORS } from '../utils';

const TIER_DISCOUNT = { diamond: 0.20, platinum: 0.15, gold: 0.10, silver: 0.05, bronze: 0 };
const TIER_LABELS = { diamond: '💎 Diamond', platinum: '🏆 Platinum', gold: '🥇 Gold', silver: '🥈 Silver', bronze: '🥉 Bronze' };

export default function CheckoutScreen({ navigation }) {
  const insets = useSafeAreaInsets();
  const { user, refreshCartCount } = useAuth();
  const { showAlert } = useAlert();

  const [cart, setCart] = useState(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [notes, setNotes] = useState('');
  const [usePoints, setUsePoints] = useState(false);

  const [addresses, setAddresses] = useState([]);
  const [selectedAddress, setSelectedAddress] = useState(null);

  const [couponCode, setCouponCode] = useState('');
  const [couponLoading, setCouponLoading] = useState(false);
  const [couponDiscount, setCouponDiscount] = useState(null);
  const [couponName, setCouponName] = useState('');

  const [ppnEnabled, setPpnEnabled] = useState(false);
  const [ppnRate, setPpnRate] = useState(11);

  const [shippingRates, setShippingRates] = useState([]);
  const [shippingLoading, setShippingLoading] = useState(false);
  const [selectedShipping, setSelectedShipping] = useState(null);

  useEffect(() => {
    if (user) {
      Promise.all([fetchCart(), fetchAddresses(), fetchPpnSettings()]);
    }
  }, [user]);

  useEffect(() => {
    if (!user) return;
    const unsubscribe = navigation.addListener('focus', () => {
      const params = navigation.getState()?.routes?.find(
        (r) => r.name === 'Checkout'
      )?.params;
      if (params?.selectedAddress) {
        setSelectedAddress(params.selectedAddress);
        navigation.setParams({ selectedAddress: null });
      }
    });
    return unsubscribe;
  }, [navigation, user]);

  useEffect(() => {
    if (selectedAddress) {
      fetchShippingRates();
    } else {
      setShippingRates([]);
      setSelectedShipping(null);
    }
  }, [selectedAddress?.id]);

  if (!user) {
    return <LoginPrompt navigation={navigation} message="Silakan login untuk melanjutkan checkout." />;
  }

  const fetchCart = async () => {
    try {
      const response = await api.get('/api/cart');
      if (response.data?.success) {
        setCart(response.data.data);
      }
    } catch (e) {
      // silent
    } finally {
      setLoading(false);
    }
  };

  const fetchAddresses = async () => {
    try {
      const response = await api.get('/api/addresses');
      if (response.data?.success) {
        const list = response.data.data;
        setAddresses(list);
        if (!selectedAddress) {
          const defaultAddr = list.find((a) => a.is_default);
          if (defaultAddr) setSelectedAddress(defaultAddr);
        }
      }
    } catch (e) {
      // silent
    }
  };

  const fetchPpnSettings = async () => {
    try {
      const response = await api.get('/api/settings/ppn');
      if (response.data?.success) {
        setPpnEnabled(response.data.data.ppn_enabled);
        setPpnRate(response.data.data.ppn_percentage);
      }
    } catch (e) {
      // silent
    }
  };

  const fetchShippingRates = async () => {
    setShippingLoading(true);
    setSelectedShipping(null);
    try {
      const response = await api.post('/api/shipping/rates', {
        address_id: selectedAddress.id,
      });
      if (response.data?.success) {
        setShippingRates(response.data.data.rates || []);
      }
    } catch (e) {
      setShippingRates([]);
    } finally {
      setShippingLoading(false);
    }
  };

  const subtotal = cart ? cart.subtotal : 0;
  const discountAmount = couponDiscount || 0;
  const dpp = Math.max(0, subtotal - discountAmount);
  const ppnAmount = ppnEnabled ? Math.round(dpp * ppnRate / 100) : 0;
  const shippingCost = selectedShipping ? selectedShipping.cost : 0;
  const memberDiscountRate = TIER_DISCOUNT[user?.segment] || 0;
  const memberDiscount = memberDiscountRate > 0 ? Math.round(dpp * memberDiscountRate) : 0;
  const pointsToRedeem = usePoints ? Math.min(user.points || 0, Math.floor((dpp + shippingCost + ppnAmount - memberDiscount) * 0.5)) : 0;
  const grandTotal = dpp + shippingCost + ppnAmount - memberDiscount - pointsToRedeem;

  const applyCoupon = async () => {
    if (!couponCode.trim()) return;
    setCouponLoading(true);
    try {
      const response = await api.post('/api/coupons/validate', {
        code: couponCode.trim(),
        subtotal,
      });
      if (response.data?.success) {
        const data = response.data.data;
        const discount = data.discount_amount || data.discount || 0;
        setCouponDiscount(discount);
        setCouponName(data.code || couponCode.trim());
        showAlert({ title: 'Kupon Diterapkan', message: `Diskon ${formatPrice(discount)} berhasil diterapkan!`, type: 'success' });
      }
    } catch (e) {
      setCouponDiscount(null);
      setCouponName('');
      const msg = e.response?.data?.message || 'Kupon tidak valid.';
      showAlert({ title: 'Kupon Gagal', message: msg, type: 'error' });
    } finally {
      setCouponLoading(false);
    }
  };

  const removeCoupon = () => {
    setCouponCode('');
    setCouponDiscount(null);
    setCouponName('');
  };

  const placeOrder = async () => {
    if (!selectedAddress) {
      showAlert({ title: 'Alamat', message: 'Silakan pilih alamat pengiriman terlebih dahulu.', type: 'warning' });
      return;
    }
    if (!selectedShipping) {
      showAlert({ title: 'Kurir', message: 'Silakan pilih jasa pengiriman terlebih dahulu.', type: 'warning' });
      return;
    }

    setSubmitting(true);
    try {
      const items = cart.items.map((item) => ({
        product_id: item.product_id,
        quantity: item.quantity,
        ...(item.variant_id ? { variant_id: item.variant_id } : {}),
      }));

      const payload = {
        items,
        address_id: selectedAddress.id,
        payment_method: 'manual_transfer',
        notes,
        use_points: usePoints ? 1 : 0,
        shipping_courier: `${selectedShipping.code} - ${selectedShipping.service}`,
        shipping_cost: selectedShipping.cost,
        ...(couponDiscount ? { coupon_code: couponCode.trim() } : {}),
      };

      const response = await api.post('/api/orders', payload);
      if (response.data?.success) {
        setCart(null);
        refreshCartCount();
        const orderId = response.data.data.id;
        const orderNumber = response.data.data.order_number;
        showAlert({
          title: 'Pesanan Dibuat!',
          message: `Pesanan #${orderNumber} berhasil dibuat. Silakan lanjutkan pembayaran.`,
          type: 'success',
          buttons: [{ text: 'Bayar Sekarang', onPress: () => navigation.navigate('OrderDetail', { orderId }) }],
        });
      }
    } catch (e) {
      const msg = e.response?.data?.message || 'Gagal membuat pesanan.';
      showAlert({ title: 'Error', message: msg, type: 'error' });
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }

  if (!cart || !cart.items?.length) {
    return (
      <View style={styles.center}>
        <Text style={styles.emptyIcon}>🛒</Text>
        <Text style={styles.emptyText}>Keranjang kosong.</Text>
        <TouchableOpacity style={styles.shopBtn} onPress={() => navigation.navigate('Home')}>
          <Text style={styles.shopBtnText}>Mulai Belanja</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <ScrollView style={styles.container}>
      {/* Address Section */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>📍 Alamat Pengiriman</Text>
        {selectedAddress ? (
          <View style={styles.addressCard}>
            <View style={styles.addressHeader}>
              <Text style={styles.addressLabel}>{selectedAddress.label || 'Alamat'}</Text>
              {selectedAddress.is_default && (
                <View style={styles.defaultBadge}>
                  <Text style={styles.defaultBadgeText}>Utama</Text>
                </View>
              )}
            </View>
            <Text style={styles.addressName}>{selectedAddress.recipient || user.name}</Text>
            <Text style={styles.addressDetail}>
              {selectedAddress.street}{selectedAddress.city ? `, ${selectedAddress.city}` : ''}
              {selectedAddress.province ? `, ${selectedAddress.province}` : ''}
              {selectedAddress.postal_code ? ` ${selectedAddress.postal_code}` : ''}
            </Text>
            {selectedAddress.phone ? (
              <Text style={styles.addressPhone}>📱 {selectedAddress.phone}</Text>
            ) : null}
            <TouchableOpacity onPress={() => setSelectedAddress(null)} style={styles.addressChange}>
              <Text style={styles.addressChangeText}>Ganti Alamat</Text>
            </TouchableOpacity>
          </View>
        ) : (
          <TouchableOpacity
            style={styles.addressBtn}
            onPress={() => navigation.navigate('Address', { onSelect: true })}
          >
            <Text style={styles.addressBtnText}>+ Pilih Alamat</Text>
          </TouchableOpacity>
        )}
      </View>

      {/* Shipping Section */}
      {selectedAddress && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>🚚 Jasa Pengiriman</Text>
          {shippingLoading ? (
            <View style={styles.shippingLoading}>
              <ActivityIndicator size="small" color={COLORS.primary} />
              <Text style={styles.shippingLoadingText}>Memuat ongkos kirim...</Text>
            </View>
          ) : shippingRates.length > 0 ? (
            shippingRates.map((courier) => (
              <View key={courier.code} style={styles.courierBlock}>
                <Text style={styles.courierName}>{courier.name}</Text>
                {courier.services.map((service) => {
                  const isSelected = selectedShipping?.code === courier.code && selectedShipping?.service === service.service;
                  return (
                    <TouchableOpacity
                      key={service.service}
                      style={[styles.serviceItem, isSelected && styles.serviceItemActive]}
                      onPress={() => setSelectedShipping({
                        code: courier.code,
                        name: courier.name,
                        service: service.service,
                        description: service.description,
                        cost: service.cost,
                        etd: service.etd,
                      })}
                    >
                      <View style={styles.serviceInfo}>
                        <View style={styles.serviceRadio}>
                          <View style={[styles.radio, isSelected && styles.radioActive]} />
                        </View>
                        <View style={styles.serviceDetails}>
                          <Text style={styles.serviceName}>{service.service}</Text>
                          <Text style={styles.serviceDesc}>{service.description}</Text>
                          <Text style={styles.serviceEtd}>Estimasi {service.etd} hari</Text>
                        </View>
                      </View>
                      <Text style={[styles.serviceCost, isSelected && styles.serviceCostActive]}>
                        {service.cost_formatted}
                      </Text>
                    </TouchableOpacity>
                  );
                })}
              </View>
            ))
          ) : selectedAddress ? (
            <Text style={styles.shippingEmpty}>Tidak ada opsi pengiriman untuk alamat ini.</Text>
          ) : null}
        </View>
      )}

      {/* Order Items */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>🛒 Ringkasan Belanja ({cart.items.length} item)</Text>
        {cart.items.map((item, idx) => (
          <View key={idx} style={styles.itemRow}>
            <View style={styles.itemImageWrap}>
              {item.image ? (
                <Image
                  source={{ uri: getImageUrl(item.image) }}
                  style={styles.itemImage}
                  resizeMode="cover"
                />
              ) : (
                <View style={styles.itemImagePlaceholder}>
                  <Text style={styles.itemImageText}>{item.name?.charAt(0) || '?'}</Text>
                </View>
              )}
            </View>
            <View style={styles.itemInfo}>
              <Text style={styles.itemName} numberOfLines={2}>{item.name}</Text>
              <Text style={styles.itemQty}>x{item.quantity}</Text>
            </View>
            <Text style={styles.itemPrice}>{formatPrice(item.subtotal || item.price * item.quantity)}</Text>
          </View>
        ))}
      </View>

      {/* Coupon Section */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>🏷️ Kode Kupon</Text>
        {couponDiscount ? (
          <View style={styles.couponApplied}>
            <View style={styles.couponInfo}>
              <Text style={styles.couponLabel}>✅ {couponName}</Text>
              <Text style={styles.couponDiscount}>-{formatPrice(couponDiscount)}</Text>
            </View>
            <TouchableOpacity onPress={removeCoupon}>
              <Text style={styles.couponRemove}>✕</Text>
            </TouchableOpacity>
          </View>
        ) : (
          <View style={styles.couponRow}>
            <TextInput
              style={styles.couponInput}
              placeholder="Masukkan kode kupon"
              placeholderTextColor={COLORS.textLight}
              value={couponCode}
              onChangeText={setCouponCode}
              autoCapitalize="uppercase"
            />
            <TouchableOpacity
              style={[styles.couponBtn, couponLoading && styles.couponBtnDisabled]}
              onPress={applyCoupon}
              disabled={couponLoading}
            >
              {couponLoading ? (
                <ActivityIndicator size="small" color="#fff" />
              ) : (
                <Text style={styles.couponBtnText}>Terapkan</Text>
              )}
            </TouchableOpacity>
          </View>
        )}
      </View>

      {/* Points */}
      {user.points > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>⭐ Gunakan Poin</Text>
          <View style={styles.pointsRow}>
            <View style={styles.pointsInfo}>
              <Text style={styles.pointsAvailable}>Poin tersedia: {user.points}</Text>
              <Text style={styles.pointsNote}>Maksimal 50% total ({formatPrice(Math.floor((dpp + shippingCost + ppnAmount) * 0.5))})</Text>
            </View>
            <TouchableOpacity
              style={[styles.pointsToggle, usePoints && styles.pointsToggleActive]}
              onPress={() => setUsePoints(!usePoints)}
            >
              <Text style={[styles.pointsToggleText, usePoints && styles.pointsToggleTextActive]}>
                {usePoints ? '✓' : ''}
              </Text>
            </TouchableOpacity>
          </View>
          {usePoints && pointsToRedeem > 0 && (
            <Text style={styles.pointsRedeemed}>Menggunakan {pointsToRedeem} poin (-{formatPrice(pointsToRedeem)})</Text>
          )}
        </View>
      )}

      {/* Notes */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>📝 Catatan</Text>
        <TextInput
          style={styles.notesInput}
          placeholder="Catatan untuk penjual (opsional)"
          placeholderTextColor={COLORS.textLight}
          value={notes}
          onChangeText={setNotes}
          multiline
          numberOfLines={3}
        />
      </View>

      {/* Payment Method */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>💳 Metode Pembayaran</Text>
        <View style={styles.paymentBox}>
          <Text style={styles.paymentIcon}>🏦</Text>
          <View style={styles.paymentInfo}>
            <Text style={styles.paymentText}>Transfer Manual (BCA / Mandiri / BNI / BRI)</Text>
            <Text style={styles.paymentNote}>Upload bukti transfer setelah pesanan dibuat</Text>
          </View>
        </View>
      </View>

      {/* Order Summary */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>💰 Ringkasan Pembayaran</Text>
        <View style={styles.summaryRow}>
          <Text style={styles.summaryLabel}>Subtotal</Text>
          <Text style={styles.summaryValue}>{formatPrice(subtotal)}</Text>
        </View>
        {couponDiscount > 0 && (
          <View style={styles.summaryRow}>
            <Text style={styles.summaryLabel}>Diskon Kupon</Text>
            <Text style={[styles.summaryValue, { color: COLORS.success }]}>-{formatPrice(couponDiscount)}</Text>
          </View>
        )}
        {memberDiscount > 0 && (
          <View style={styles.summaryRow}>
            <Text style={styles.summaryLabel}>Diskon Member ({TIER_LABELS[user?.segment]})</Text>
            <Text style={[styles.summaryValue, { color: COLORS.success }]}>-{formatPrice(memberDiscount)}</Text>
          </View>
        )}
        <View style={styles.summaryRow}>
          <Text style={styles.summaryLabel}>Ongkos Kirim{selectedShipping ? ` (${selectedShipping.code} ${selectedShipping.service})` : ''}</Text>
          <Text style={styles.summaryValue}>{selectedShipping ? formatPrice(shippingCost) : '-'}</Text>
        </View>
        {usePoints && pointsToRedeem > 0 && (
          <View style={styles.summaryRow}>
            <Text style={styles.summaryLabel}>Poin Digunakan</Text>
            <Text style={[styles.summaryValue, { color: COLORS.success }]}>-{formatPrice(pointsToRedeem)}</Text>
          </View>
        )}
        {ppnEnabled && ppnAmount > 0 && (
          <View style={styles.summaryRow}>
            <Text style={styles.summaryLabel}>PPN {ppnRate}%</Text>
            <Text style={styles.summaryValue}>{formatPrice(ppnAmount)}</Text>
          </View>
        )}
        <View style={styles.summaryDivider} />
        <View style={styles.summaryRow}>
          <Text style={styles.grandTotalLabel}>Total Bayar</Text>
          <Text style={styles.grandTotalValue}>{formatPrice(grandTotal)}</Text>
        </View>
      </View>

      {/* Submit Button */}
      <TouchableOpacity
        style={[styles.orderBtn, (submitting || !selectedAddress || !selectedShipping) && styles.orderBtnDisabled]}
        onPress={placeOrder}
        disabled={submitting || !selectedAddress || !selectedShipping}
      >
        {submitting ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.orderBtnText}>Buat Pesanan</Text>
        )}
      </TouchableOpacity>

      <View style={{ height: insets.bottom + 20 }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
  section: {
    backgroundColor: COLORS.white, marginHorizontal: 12, marginTop: 12,
    borderRadius: 12, padding: 16,
  },
  sectionTitle: { fontSize: 15, fontWeight: '700', color: COLORS.text, marginBottom: 12 },

  // Address
  addressCard: {
    backgroundColor: COLORS.background, borderRadius: 10, padding: 12,
    borderLeftWidth: 3, borderLeftColor: COLORS.primary,
  },
  addressHeader: { flexDirection: 'row', alignItems: 'center', marginBottom: 6 },
  addressLabel: { fontSize: 12, fontWeight: '600', color: COLORS.textSecondary, textTransform: 'uppercase' },
  defaultBadge: {
    backgroundColor: COLORS.primary + '20', borderRadius: 4, paddingHorizontal: 6, paddingVertical: 1, marginLeft: 8,
  },
  defaultBadgeText: { fontSize: 10, fontWeight: '600', color: COLORS.primary },
  addressName: { fontSize: 14, fontWeight: '600', color: COLORS.text },
  addressDetail: { fontSize: 13, color: COLORS.textSecondary, marginTop: 2, lineHeight: 18 },
  addressPhone: { fontSize: 12, color: COLORS.textSecondary, marginTop: 4 },
  addressChange: { marginTop: 8 },
  addressChangeText: { fontSize: 13, color: COLORS.primary, fontWeight: '500' },
  addressBtn: {
    borderWidth: 1, borderColor: COLORS.primary, borderStyle: 'dashed',
    borderRadius: 10, padding: 16, alignItems: 'center',
  },
  addressBtnText: { fontSize: 14, color: COLORS.primary, fontWeight: '600' },

  // Shipping
  shippingLoading: { flexDirection: 'row', alignItems: 'center', paddingVertical: 8 },
  shippingLoadingText: { marginLeft: 8, fontSize: 13, color: COLORS.textSecondary },
  shippingEmpty: { fontSize: 13, color: COLORS.textLight, fontStyle: 'italic' },
  courierBlock: { marginBottom: 12 },
  courierName: { fontSize: 14, fontWeight: '700', color: COLORS.text, marginBottom: 8 },
  serviceItem: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between',
    paddingVertical: 10, paddingHorizontal: 12, marginBottom: 6,
    borderRadius: 10, borderWidth: 1, borderColor: COLORS.border,
    backgroundColor: COLORS.background,
  },
  serviceItemActive: { borderColor: COLORS.primary, backgroundColor: '#FEF3C7' },
  serviceInfo: { flexDirection: 'row', alignItems: 'center', flex: 1 },
  serviceRadio: { marginRight: 10 },
  radio: {
    width: 18, height: 18, borderRadius: 9, borderWidth: 2, borderColor: COLORS.border,
  },
  radioActive: { borderColor: COLORS.primary, backgroundColor: COLORS.primary },
  serviceDetails: { flex: 1 },
  serviceName: { fontSize: 14, fontWeight: '600', color: COLORS.text },
  serviceDesc: { fontSize: 12, color: COLORS.textSecondary, marginTop: 1 },
  serviceEtd: { fontSize: 11, color: COLORS.textLight, marginTop: 2 },
  serviceCost: { fontSize: 14, fontWeight: '600', color: COLORS.text, marginLeft: 8 },
  serviceCostActive: { color: COLORS.primary },

  // Items
  itemRow: {
    flexDirection: 'row', alignItems: 'center', marginBottom: 10,
    paddingBottom: 10, borderBottomWidth: 0.5, borderBottomColor: COLORS.border,
  },
  itemImageWrap: { width: 50, height: 50, borderRadius: 8, overflow: 'hidden', marginRight: 10 },
  itemImage: { width: 50, height: 50, backgroundColor: COLORS.border },
  itemImagePlaceholder: {
    width: 50, height: 50, borderRadius: 8, backgroundColor: COLORS.border,
    justifyContent: 'center', alignItems: 'center',
  },
  itemImageText: { fontSize: 18, fontWeight: '700', color: COLORS.textSecondary },
  itemInfo: { flex: 1 },
  itemName: { fontSize: 13, fontWeight: '500', color: COLORS.text, lineHeight: 17 },
  itemQty: { fontSize: 12, color: COLORS.textSecondary, marginTop: 2 },
  itemPrice: { fontSize: 14, fontWeight: '600', color: COLORS.text, marginLeft: 8 },

  // Coupon
  couponRow: { flexDirection: 'row', alignItems: 'center' },
  couponInput: {
    flex: 1, borderWidth: 1, borderColor: COLORS.border, borderRadius: 10,
    paddingHorizontal: 12, paddingVertical: 10, fontSize: 14, color: COLORS.text,
    marginRight: 10, textTransform: 'uppercase',
  },
  couponBtn: {
    backgroundColor: COLORS.primary, borderRadius: 10, paddingHorizontal: 16, paddingVertical: 10,
  },
  couponBtnDisabled: { opacity: 0.6 },
  couponBtnText: { color: '#fff', fontSize: 14, fontWeight: '600' },
  couponApplied: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
    backgroundColor: '#ECFDF5', borderRadius: 10, padding: 12,
  },
  couponInfo: { flex: 1 },
  couponLabel: { fontSize: 14, fontWeight: '500', color: COLORS.text },
  couponDiscount: { fontSize: 14, fontWeight: '700', color: COLORS.success, marginTop: 2 },
  couponRemove: { fontSize: 18, color: COLORS.error, fontWeight: '600', marginLeft: 12, padding: 4 },

  // Points
  pointsRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  pointsInfo: { flex: 1 },
  pointsAvailable: { fontSize: 14, fontWeight: '500', color: COLORS.text },
  pointsNote: { fontSize: 12, color: COLORS.textSecondary, marginTop: 2 },
  pointsToggle: {
    width: 28, height: 28, borderRadius: 14, borderWidth: 2, borderColor: COLORS.border,
    justifyContent: 'center', alignItems: 'center',
  },
  pointsToggleActive: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  pointsToggleText: { fontSize: 14, color: COLORS.border },
  pointsToggleTextActive: { color: '#fff', fontWeight: '700' },
  pointsRedeemed: { fontSize: 13, color: COLORS.success, fontWeight: '500', marginTop: 8 },

  // Notes
  notesInput: {
    borderWidth: 1, borderColor: COLORS.border, borderRadius: 10, padding: 12,
    fontSize: 14, color: COLORS.text, minHeight: 80, textAlignVertical: 'top',
  },

  // Payment
  paymentBox: {
    flexDirection: 'row', alignItems: 'center', padding: 12,
    backgroundColor: '#FEF3C7', borderRadius: 10,
  },
  paymentIcon: { fontSize: 24, marginRight: 10 },
  paymentInfo: { flex: 1 },
  paymentText: { fontSize: 14, color: COLORS.text, fontWeight: '500' },
  paymentNote: { fontSize: 12, color: COLORS.textSecondary, marginTop: 2 },

  // Summary
  summaryRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 },
  summaryLabel: { fontSize: 14, color: COLORS.textSecondary },
  summaryValue: { fontSize: 14, color: COLORS.text, fontWeight: '500' },
  summaryDivider: { borderTopWidth: 1, borderTopColor: COLORS.border, marginVertical: 8 },
  grandTotalLabel: { fontSize: 16, fontWeight: '700', color: COLORS.text },
  grandTotalValue: { fontSize: 18, fontWeight: '700', color: COLORS.primary },

  // Submit
  orderBtn: {
    backgroundColor: COLORS.primary, borderRadius: 12, paddingVertical: 16,
    alignItems: 'center', marginHorizontal: 12, marginTop: 20, elevation: 2,
  },
  orderBtnDisabled: { opacity: 0.5 },
  orderBtnText: { color: '#fff', fontSize: 16, fontWeight: '600' },

  center: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
  emptyIcon: { fontSize: 48, marginBottom: 12 },
  emptyText: { fontSize: 16, color: COLORS.textSecondary, marginBottom: 16 },
  shopBtn: {
    backgroundColor: COLORS.primary, borderRadius: 10, paddingHorizontal: 24, paddingVertical: 12,
  },
  shopBtnText: { color: '#fff', fontSize: 14, fontWeight: '600' },
});
