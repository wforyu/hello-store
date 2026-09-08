import React, { useState } from 'react';
import {
  View, Text, TextInput, TouchableOpacity, StyleSheet, ScrollView,
  KeyboardAvoidingView, Platform, ActivityIndicator, Modal, Image, Share,
} from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { useAlert } from '../context/AlertContext';
import api from '../api/client';
import { COLORS, getImageUrl } from '../config';
import { formatPrice, STATUS_COLORS, STATUS_LABELS } from '../utils';

function parsePPN(notes) {
  if (!notes) return null;
  const match = notes.match(/PPN (\d+)%: Rp ([\d.]+)/);
  if (!match) return null;
  return { rate: match[1], amount: match[2] };
}

function parseShipping(notes) {
  if (!notes) return null;
  const match = notes.match(/Ongkir: Rp ([\d.]+)/);
  return match ? match[1] : null;
}

export default function TrackOrderScreen() {
  const { showAlert } = useAlert();
  const [orderNumber, setOrderNumber] = useState('');
  const [guestEmail, setGuestEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [orderId, setOrderId] = useState(null);
  const [token, setToken] = useState(null);
  const [order, setOrder] = useState(null);

  // payment modal
  const [paymentModal, setPaymentModal] = useState(false);
  const [bankName, setBankName] = useState('');
  const [accountName, setAccountName] = useState('');
  const [accountNumber, setAccountNumber] = useState('');
  const [pickedImage, setPickedImage] = useState(null);
  const [uploading, setUploading] = useState(false);

  const track = async () => {
    if (!orderNumber.trim() || !guestEmail.trim()) {
      showAlert({ title: 'Error', message: 'Nomor pesanan dan email harus diisi.', type: 'error' });
      return;
    }
    setLoading(true);
    try {
      const res = await api.post('/api/guest-orders/track', {
        order_number: orderNumber.trim(),
        guest_email: guestEmail.trim(),
      });
      if (res.data?.success) {
        setOrderId(res.data.data.order_id);
        setToken(res.data.data.token);
        setOrder(res.data.data.order);
      }
    } catch (e) {
      const msg = e.response?.data?.message || 'Gagal melacak pesanan.';
      showAlert({ title: 'Tidak Ditemukan', message: msg, type: 'error' });
    } finally {
      setLoading(false);
    }
  };

  const refreshOrder = async () => {
    try {
      const res = await api.get(`/api/guest-orders/${orderId}`, { params: { token } });
      if (res.data?.data) setOrder(res.data.data);
      else if (res.data?.id) setOrder(res.data);
    } catch (_) {}
  };

  const reset = () => {
    setOrder(null);
    setOrderId(null);
    setToken(null);
    setOrderNumber('');
    setGuestEmail('');
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
        name: `bukti_${orderId}.${ext}`,
        type: `image/${ext}`,
      });
      formData.append('bank_name', bankName.trim());
      formData.append('account_name', accountName.trim());
      formData.append('account_number', accountNumber.trim());

      const res = await api.post(`/api/guest-orders/${orderId}/payment`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        params: { token },
      });
      if (res.data?.success) {
        showAlert({
          title: 'Berhasil',
          message: 'Bukti pembayaran berhasil diunggah. Pesanan akan diproses.',
          type: 'success',
          buttons: [{ text: 'OK', onPress: () => refreshOrder() }],
        });
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
              const res = await api.post(`/api/guest-orders/${orderId}/cancel`, null, { params: { token } });
              if (res.data?.success) {
                showAlert({
                  title: 'Berhasil',
                  message: 'Pesanan dibatalkan.',
                  type: 'success',
                  buttons: [{ text: 'OK', onPress: () => refreshOrder() }],
                });
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

  const shareInvoice = async () => {
    if (!order) return;
    const label = STATUS_LABELS[order.status] || order.status;
    const text = `Invoice pesanan #${order.order_number} Hello Store\nTotal: ${formatPrice(order.total)}\nStatus: ${label}`;
    try {
      await Share.share({ message: text, title: `Invoice #${order.order_number}` });
    } catch (e) {
      // silent
    }
  };

  const confirmReceived = () => {    showAlert({
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
              const res = await api.post(`/api/guest-orders/${orderId}/confirm`, null, { params: { token } });
              if (res.data?.success) {
                showAlert({
                  title: 'Berhasil',
                  message: 'Pesanan berhasil dikonfirmasi.',
                  type: 'success',
                  buttons: [{ text: 'OK', onPress: () => refreshOrder() }],
                });
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

  if (!order) {
    return (
      <KeyboardAvoidingView
        style={styles.container}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      >
        <ScrollView contentContainerStyle={styles.inputWrap} keyboardShouldPersistTaps="handled">
          <View style={styles.header}>
            <Text style={styles.icon}>🔎</Text>
            <Text style={styles.title}>Lacak Pesanan</Text>
            <Text style={styles.subtitle}>
              Masukkan nomor pesanan dan email yang digunakan saat checkout tanpa login.
            </Text>
          </View>

          <TextInput
            style={styles.input}
            placeholder="Nomor Pesanan (contoh: ORD-XXXXXXX)"
            placeholderTextColor={COLORS.textLight}
            value={orderNumber}
            onChangeText={setOrderNumber}
            autoCapitalize="characters"
            editable={!loading}
          />
          <TextInput
            style={styles.input}
            placeholder="Email yang digunakan saat pesan"
            placeholderTextColor={COLORS.textLight}
            value={guestEmail}
            onChangeText={setGuestEmail}
            keyboardType="email-address"
            autoCapitalize="none"
            editable={!loading}
          />

          <TouchableOpacity
            style={[styles.button, loading && styles.buttonDisabled]}
            onPress={track}
            disabled={loading}
          >
            {loading ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.buttonText}>Lacak Pesanan</Text>
            )}
          </TouchableOpacity>
        </ScrollView>
      </KeyboardAvoidingView>
    );
  }

  const statusColor = STATUS_COLORS[order.status] || COLORS.textSecondary;
  const statusLabel = STATUS_LABELS[order.status] || order.status;
  const ppn = parsePPN(order.notes);
  const shippingCost = parseShipping(order.notes);

  return (
    <ScrollView style={styles.container}>
      <TouchableOpacity onPress={reset} style={styles.resetBtn}>
        <Text style={styles.resetText}>← Lacak Pesanan Lainnya</Text>
      </TouchableOpacity>

      <View style={styles.statusBanner}>
        <View style={[styles.badge, { backgroundColor: statusColor + '20' }]}>
          <Text style={[styles.badgeText, { color: statusColor }]}>{statusLabel}</Text>
        </View>
        <Text style={styles.orderNumber}>{order.order_number}</Text>
        <Text style={styles.guestName}>{order.customer_name || 'Pelanggan'}</Text>
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
              {item.bundle_name && (
                <View style={styles.bundleBadge}>
                  <Text style={styles.bundleBadgeText}>PAKET: {item.bundle_name}</Text>
                </View>
              )}
              <Text style={styles.itemQty}>x{item.quantity}</Text>
            </View>
            <Text style={styles.itemPrice}>{formatPrice(item.subtotal || item.product_price * item.quantity)}</Text>
          </View>
        ))}
      </View>

      {order.address && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Alamat Pengiriman</Text>
          <Text style={styles.detailRow}>{order.address.recipient}</Text>
          <Text style={styles.detailRow}>{order.address.phone}</Text>
          <Text style={styles.detailRow}>
            {order.address.street}, {order.address.city}, {order.address.province} {order.address.postal_code}
          </Text>
        </View>
      )}

      {order.payment && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Pembayaran</Text>
          <Text style={styles.detailRow}>Metode: {order.payment.method}</Text>
          <Text style={styles.detailRow}>
            Status: {order.payment.status || order.payment_status}
          </Text>
          {order.payment.proof_image_url && (
            <View style={{ marginTop: 10 }}>
              <Image
                source={{ uri: getImageUrl(order.payment.proof_image_url) }}
                style={styles.proofImage}
                resizeMode="cover"
              />
            </View>
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

      <TouchableOpacity
        style={[styles.actionBtn, { backgroundColor: COLORS.secondary }]}
        onPress={shareInvoice}
        disabled={loading}
      >
        <Text style={styles.actionBtnText}>Bagikan Invoice</Text>
      </TouchableOpacity>

      <View style={{ height: 40 }} />

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
  inputWrap: { flexGrow: 1, justifyContent: 'center', paddingHorizontal: 24, paddingVertical: 24 },
  header: { alignItems: 'center', marginBottom: 32 },
  icon: { fontSize: 40, marginBottom: 12 },
  title: { fontSize: 24, fontWeight: '700', color: COLORS.text },
  subtitle: { fontSize: 14, color: COLORS.textSecondary, textAlign: 'center', marginTop: 8, lineHeight: 20 },
  input: {
    backgroundColor: COLORS.white, borderWidth: 1, borderColor: COLORS.border,
    borderRadius: 12, paddingHorizontal: 16, paddingVertical: 14,
    fontSize: 16, color: COLORS.text, marginBottom: 12,
  },
  button: {
    backgroundColor: COLORS.primary, borderRadius: 12, paddingVertical: 16,
    alignItems: 'center', marginTop: 8,
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
  resetBtn: { paddingHorizontal: 16, paddingTop: 16 },
  resetText: { color: COLORS.primary, fontWeight: '600', fontSize: 14 },
  statusBanner: {
    backgroundColor: COLORS.white, padding: 20, alignItems: 'center',
    borderBottomWidth: 1, borderBottomColor: COLORS.border, marginTop: 8,
  },
  badge: { paddingHorizontal: 16, paddingVertical: 6, borderRadius: 20, marginBottom: 8 },
  badgeText: { fontSize: 14, fontWeight: '600' },
  orderNumber: { fontSize: 16, fontWeight: '700', color: COLORS.text },
  guestName: { fontSize: 13, color: COLORS.textSecondary, marginTop: 4 },
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
  bundleBadge: {
    backgroundColor: '#FEF3C7', borderWidth: 1, borderColor: '#FDE68A', borderRadius: 6,
    paddingHorizontal: 8, paddingVertical: 2, alignSelf: 'flex-start', marginTop: 4, marginBottom: 2,
  },
  bundleBadgeText: { fontSize: 11, fontWeight: '600', color: '#B45309' },
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
  proofImage: { width: '100%', height: 200, borderRadius: 10, backgroundColor: COLORS.border },
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
});