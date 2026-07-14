import React, { useState, useEffect } from 'react';
import {
  View, Text, ScrollView, TouchableOpacity, StyleSheet, ActivityIndicator,
  Modal, TextInput, KeyboardAvoidingView, Platform, Image,
} from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '../context/AuthContext';
import { useAlert } from '../context/AlertContext';
import LoginPrompt from '../components/LoginPrompt';
import api from '../api/client';
import { COLORS, getImageUrl } from '../config';

const STATUS_COLORS = {
  pending: '#F59E0B', processing: '#3B82F6', shipped: '#8B5CF6',
  delivered: '#10B981', cancelled: '#EF4444', refunded: '#6B7280',
};

const STATUS_LABELS = {
  pending: 'Menunggu', processing: 'Diproses', shipped: 'Dikirim',
  delivered: 'Diterima', cancelled: 'Dibatalkan', refunded: 'Dikembalikan',
};

const formatPrice = (p) => `Rp${Number(p).toLocaleString('id-ID')}`;

export default function OrderDetailScreen({ route, navigation }) {
  const insets = useSafeAreaInsets();
  const { user } = useAuth();
  const { showAlert } = useAlert();
  const { order: initialOrder } = route.params;
  const [order, setOrder] = useState(initialOrder);
  const [loading, setLoading] = useState(false);

  const [paymentModal, setPaymentModal] = useState(false);
  const [bankName, setBankName] = useState('');
  const [accountName, setAccountName] = useState('');
  const [accountNumber, setAccountNumber] = useState('');
  const [pickedImage, setPickedImage] = useState(null);
  const [uploading, setUploading] = useState(false);

  const parsePPN = (notes) => {
    if (!notes) return null;
    const match = notes.match(/PPN (\d+)%: Rp ([\d.]+)/);
    if (!match) return null;
    return { rate: match[1], amount: match[2] };
  };

  const parseShipping = (notes) => {
    if (!notes) return null;
    const match = notes.match(/Ongkir: Rp ([\d.]+)/);
    return match ? match[1] : null;
  };

  const refreshOrder = async () => {
    try {
      const res = await api.get(`/api/orders/${order.id}`);
      if (res.data?.data) setOrder(res.data.data);
      else if (res.data?.id) setOrder(res.data);
    } catch (_) {}
  };

  const pickImage = async () => {
    const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (status !== 'granted') {
      showAlert({ title: 'Izin Diperlukan', message: 'Izin akses galeri diperlukan untuk memilih gambar.', type: 'error' });
      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      quality: 0.8,
      allowsEditing: true,
    });
    if (!result.canceled && result.assets?.length > 0) {
      setPickedImage(result.assets[0]);
    }
  };

  const submitPayment = async () => {
    if (!pickedImage) {
      showAlert({ title: 'Error', message: 'Pilih bukti pembayaran terlebih dahulu.', type: 'error' });
      return;
    }
    if (!bankName.trim() || !accountName.trim() || !accountNumber.trim()) {
      showAlert({ title: 'Error', message: 'Lengkapi semua data bank.', type: 'error' });
      return;
    }
    setUploading(true);
    try {
      const formData = new FormData();
      const uri = pickedImage.uri;
      const ext = uri.split('.').pop() || 'jpg';
      formData.append('proof_image', {
        uri,
        name: `bukti_${order.id}.${ext}`,
        type: `image/${ext}`,
      });
      formData.append('bank_name', bankName.trim());
      formData.append('account_name', accountName.trim());
      formData.append('account_number', accountNumber.trim());

      const res = await api.post(`/api/orders/${order.id}/payment`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      if (res.data?.success) {
        showAlert({ title: 'Berhasil', message: 'Bukti pembayaran berhasil diunggah. Pesanan akan diproses setelah diverifikasi admin.', type: 'success', buttons: [{ text: 'OK', onPress: () => refreshOrder() }] });
        setPaymentModal(false);
        setPickedImage(null);
        setBankName('');
        setAccountName('');
        setAccountNumber('');
      }
    } catch (e) {
      const msg = e.response?.data?.message || 'Gagal mengunggah bukti pembayaran.';
      showAlert({ title: 'Error', message: msg, type: 'error' });
    } finally {
      setUploading(false);
    }
  };

  const cancelOrder = () => {
    showAlert({
      title: 'Batalkan Pesanan',
      message: 'Apakah Anda yakin ingin membatalkan pesanan ini?',
      type: 'warning',
      buttons: [
        { text: 'Tidak', style: 'cancel' },
        {
          text: 'Ya, Batalkan',
          style: 'destructive',
          onPress: async () => {
            setLoading(true);
            try {
              const res = await api.post(`/api/orders/${order.id}/cancel`);
              if (res.data?.success) {
                showAlert({ title: 'Berhasil', message: 'Pesanan dibatalkan.', type: 'success', buttons: [{ text: 'OK', onPress: () => refreshOrder() }] });
              }
            } catch (e) {
              const msg = e.response?.data?.message || 'Gagal membatalkan pesanan.';
              showAlert({ title: 'Error', message: msg, type: 'error' });
            } finally {
              setLoading(false);
            }
          },
        },
      ],
    });
  };

  const confirmReceived = () => {
    showAlert({
      title: 'Konfirmasi Penerimaan',
      message: 'Apakah Anda yakin pesanan sudah diterima?',
      type: 'warning',
      buttons: [
        { text: 'Batal', style: 'cancel' },
        {
          text: 'Ya, Terima',
          onPress: async () => {
            setLoading(true);
            try {
              const res = await api.post(`/api/orders/${order.id}/confirm`);
              if (res.data?.success) {
                showAlert({ title: 'Berhasil', message: 'Pesanan berhasil dikonfirmasi.', type: 'success', buttons: [{ text: 'OK', onPress: () => refreshOrder() }] });
              }
            } catch (e) {
              const msg = e.response?.data?.message || 'Gagal mengkonfirmasi.';
              showAlert({ title: 'Error', message: msg, type: 'error' });
            } finally {
              setLoading(false);
            }
          },
        },
      ],
    });
  };

  const reorder = () => {
    showAlert({
      title: 'Beli Lagi',
      message: 'Tambahkan item dari pesanan ini ke keranjang?',
      type: 'warning',
      buttons: [
        { text: 'Batal', style: 'cancel' },
        {
          text: 'Ya, Beli Lagi',
          onPress: async () => {
            setLoading(true);
            try {
              const res = await api.post(`/api/orders/${order.id}/reorder`);
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
            } finally {
              setLoading(false);
            }
          },
        },
      ],
    });
  };

  const statusColor = STATUS_COLORS[order.status] || COLORS.textSecondary;
  const statusLabel = STATUS_LABELS[order.status] || order.status;
  const ppn = parsePPN(order.notes);
  const shippingCost = parseShipping(order.notes);

  if (!user) {
    return <LoginPrompt navigation={navigation} message="Silakan login untuk melihat detail pesanan." />;
  }

  return (
    <ScrollView style={styles.container}>
      <View style={styles.statusBanner}>
        <View style={[styles.badge, { backgroundColor: statusColor + '20' }]}>
          <Text style={[styles.badgeText, { color: statusColor }]}>{statusLabel}</Text>
        </View>
        <Text style={styles.orderNumber}>{order.order_number}</Text>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Detail Pesanan</Text>
        <Text style={styles.detailRow}>
          Tanggal: {new Date(order.created_at).toLocaleDateString('id-ID', {
            day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit',
          })}
        </Text>
        {order.shipped_at && (
          <Text style={styles.detailRow}>
            Dikirim: {new Date(order.shipped_at).toLocaleDateString('id-ID', {
              day: 'numeric', month: 'long', year: 'numeric',
            })}
          </Text>
        )}
        {order.delivered_at && (
          <Text style={styles.detailRow}>
            Diterima: {new Date(order.delivered_at).toLocaleDateString('id-ID', {
              day: 'numeric', month: 'long', year: 'numeric',
            })}
          </Text>
        )}
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Item Pesanan</Text>
        {order.items?.map((item) => (
          <View key={item.id} style={styles.itemRow}>
            <View style={{ flex: 1 }}>
              <Text style={styles.itemName}>{item.product_name}</Text>
              <Text style={styles.itemQty}>x{item.quantity}</Text>
            </View>
            <Text style={styles.itemPrice}>{formatPrice(item.subtotal || item.product_price * item.quantity)}</Text>
          </View>
        ))}
      </View>

      {order.payment && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Pembayaran</Text>
          <Text style={styles.detailRow}>Metode: {order.payment.method}</Text>
          <Text style={styles.detailRow}>
            Status: {order.payment.status || order.payment_status}
          </Text>
          {order.payment.proof_image_url && (
            <View style={{ marginTop: 10 }}>
              <Text style={[styles.detailRow, { fontWeight: '500', marginBottom: 6 }]}>Bukti Pembayaran:</Text>
              <Image
                source={{ uri: getImageUrl(order.payment.proof_image_url) }}
                style={styles.proofImage}
                resizeMode="cover"
              />
            </View>
          )}
          {order.payment.bank_name && (
            <>
              <Text style={styles.detailRow}>Bank: {order.payment.bank_name}</Text>
              <Text style={styles.detailRow}>Atas Nama: {order.payment.account_name}</Text>
              <Text style={styles.detailRow}>No. Rek: {order.payment.account_number}</Text>
            </>
          )}
        </View>
      )}

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Ringkasan</Text>
        {order.subtotal != null && (
          <View style={styles.summaryRow}>
            <Text style={styles.summaryLabel}>Subtotal</Text>
            <Text style={styles.summaryValue}>{formatPrice(order.subtotal)}</Text>
          </View>
        )}
        {shippingCost && (
          <View style={styles.summaryRow}>
            <Text style={styles.summaryLabel}>Ongkir</Text>
            <Text style={styles.summaryValue}>{formatPrice(shippingCost)}</Text>
          </View>
        )}
        {order.discount > 0 && (
          <View style={styles.summaryRow}>
            <Text style={[styles.summaryLabel, { color: COLORS.error }]}>Diskon</Text>
            <Text style={[styles.summaryValue, { color: COLORS.error }]}>-{formatPrice(order.discount)}</Text>
          </View>
        )}
        {ppn && (
          <View style={styles.summaryRow}>
            <Text style={styles.summaryLabel}>PPN {ppn.rate}%</Text>
            <Text style={styles.summaryValue}>{formatPrice(ppn.amount)}</Text>
          </View>
        )}
        <View style={[styles.summaryRow, styles.totalRow]}>
          <Text style={styles.totalLabel}>Total</Text>
          <Text style={styles.totalValue}>{formatPrice(order.total)}</Text>
        </View>
      </View>

      {order.status === 'pending' && (
        <TouchableOpacity
          style={[styles.actionBtn, { backgroundColor: COLORS.info }]}
          onPress={() => setPaymentModal(true)}
          disabled={loading}
        >
          <Text style={styles.actionBtnText}>Upload Bukti Pembayaran</Text>
        </TouchableOpacity>
      )}

      {order.status === 'pending' && (
        <TouchableOpacity
          style={[styles.actionBtn, { backgroundColor: COLORS.error }]}
          onPress={cancelOrder}
          disabled={loading}
        >
          <Text style={styles.actionBtnText}>Batalkan Pesanan</Text>
        </TouchableOpacity>
      )}

      {order.status === 'shipped' && (
        <TouchableOpacity
          style={[styles.actionBtn, { backgroundColor: COLORS.success }]}
          onPress={confirmReceived}
          disabled={loading}
        >
          {loading ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.actionBtnText}>Pesanan Diterima</Text>
          )}
        </TouchableOpacity>
      )}

      {order.status === 'delivered' && (
        <TouchableOpacity
          style={[styles.actionBtn, { backgroundColor: COLORS.primary }]}
          onPress={reorder}
          disabled={loading}
        >
          <Text style={styles.actionBtnText}>Beli Lagi</Text>
        </TouchableOpacity>
      )}

      <View style={{ height: insets.bottom + 20 }} />

      <Modal visible={paymentModal} animationType="slide" transparent>
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
          style={styles.modalOverlay}
        >
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>Upload Bukti Pembayaran</Text>

            <TouchableOpacity style={styles.imagePickerBtn} onPress={pickImage}>
              {pickedImage ? (
                <View style={{ alignItems: 'center' }}>
                  <Image source={{ uri: pickedImage.uri }} style={styles.pickedPreview} resizeMode="cover" />
                  <Text style={[styles.imagePickerText, { marginTop: 8 }]}>Tap untuk ganti gambar</Text>
                </View>
              ) : (
                <View style={{ alignItems: 'center' }}>
                  <Text style={{ fontSize: 32, marginBottom: 8 }}>📷</Text>
                  <Text style={styles.imagePickerText}>Pilih Gambar Bukti Transfer</Text>
                  <Text style={{ fontSize: 12, color: COLORS.textLight, marginTop: 4 }}>JPG, PNG, maks 2MB</Text>
                </View>
              )}
            </TouchableOpacity>

            <TextInput
              style={styles.modalInput}
              placeholder="Nama Bank"
              placeholderTextColor={COLORS.textLight}
              value={bankName}
              onChangeText={setBankName}
            />
            <TextInput
              style={styles.modalInput}
              placeholder="Atas Nama"
              placeholderTextColor={COLORS.textLight}
              value={accountName}
              onChangeText={setAccountName}
            />
            <TextInput
              style={styles.modalInput}
              placeholder="Nomor Rekening"
              placeholderTextColor={COLORS.textLight}
              value={accountNumber}
              onChangeText={setAccountNumber}
              keyboardType="numeric"
            />

            <View style={styles.modalActions}>
              <TouchableOpacity
                style={[styles.modalBtn, { backgroundColor: COLORS.border }]}
                onPress={() => {
                  setPaymentModal(false);
                  setPickedImage(null);
                }}
                disabled={uploading}
              >
                <Text style={[styles.modalBtnText, { color: COLORS.text }]}>Batal</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalBtn, { backgroundColor: COLORS.primary }]}
                onPress={submitPayment}
                disabled={uploading}
              >
                {uploading ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={[styles.modalBtnText, { color: '#fff' }]}>Upload</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
  statusBanner: {
    backgroundColor: COLORS.white, padding: 20, alignItems: 'center',
    borderBottomWidth: 1, borderBottomColor: COLORS.border,
  },
  badge: { paddingHorizontal: 16, paddingVertical: 6, borderRadius: 20, marginBottom: 8 },
  badgeText: { fontSize: 14, fontWeight: '600' },
  orderNumber: { fontSize: 16, fontWeight: '700', color: COLORS.text },
  section: {
    backgroundColor: COLORS.white, marginHorizontal: 12, marginTop: 12,
    borderRadius: 12, padding: 16,
  },
  sectionTitle: { fontSize: 16, fontWeight: '600', color: COLORS.text, marginBottom: 12 },
  detailRow: { fontSize: 14, color: COLORS.textSecondary, marginBottom: 4 },
  itemRow: {
    flexDirection: 'row', justifyContent: 'space-between',
    marginBottom: 10, alignItems: 'center',
  },
  itemName: { fontSize: 14, fontWeight: '500', color: COLORS.text },
  itemQty: { fontSize: 12, color: COLORS.textSecondary, marginTop: 2 },
  itemPrice: { fontSize: 14, fontWeight: '600', color: COLORS.text, marginLeft: 12 },
  summaryRow: {
    flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6,
  },
  summaryLabel: { fontSize: 14, color: COLORS.textSecondary },
  summaryValue: { fontSize: 14, color: COLORS.text },
  totalRow: {
    borderTopWidth: 1, borderTopColor: COLORS.border,
    paddingTop: 10, marginTop: 4, marginBottom: 0,
  },
  totalLabel: { fontSize: 16, fontWeight: '700', color: COLORS.text },
  totalValue: { fontSize: 20, fontWeight: '700', color: COLORS.primary },
  actionBtn: {
    borderRadius: 12, paddingVertical: 16, alignItems: 'center',
    marginHorizontal: 12, marginTop: 12,
  },
  actionBtnText: { color: '#fff', fontSize: 16, fontWeight: '600' },
  modalOverlay: {
    flex: 1, backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: COLORS.white, borderTopLeftRadius: 20, borderTopRightRadius: 20,
    padding: 24, paddingBottom: 40,
  },
  modalTitle: { fontSize: 18, fontWeight: '700', color: COLORS.text, marginBottom: 20, textAlign: 'center' },
  imagePickerBtn: {
    borderWidth: 2, borderColor: COLORS.border, borderStyle: 'dashed',
    borderRadius: 12, padding: 20, alignItems: 'center', marginBottom: 16,
  },
  imagePickerText: { fontSize: 14, color: COLORS.textSecondary },
  pickedPreview: { width: 200, height: 150, borderRadius: 10, backgroundColor: COLORS.border },
  modalInput: {
    borderWidth: 1, borderColor: COLORS.border, borderRadius: 10,
    padding: 14, fontSize: 14, color: COLORS.text, marginBottom: 12,
  },
  modalActions: { flexDirection: 'row', gap: 12, marginTop: 8 },
  modalBtn: { flex: 1, borderRadius: 10, paddingVertical: 14, alignItems: 'center' },
  modalBtnText: { fontSize: 15, fontWeight: '600' },
  proofImage: { width: '100%', height: 200, borderRadius: 10, backgroundColor: COLORS.border },
});
