import React, { useState, useCallback } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, TextInput, StyleSheet,
  ActivityIndicator, ScrollView, Switch,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '../context/AuthContext';
import { useAlert } from '../context/AlertContext';
import LoginPrompt from '../components/LoginPrompt';
import api from '../api/client';
import { COLORS } from '../config';

const EMPTY_FORM = {
  label: '', recipient: '', phone: '', street: '', city: '',
  province: '', postal_code: '', notes: '', is_default: false,
};

export default function AddressScreen({ navigation, route }) {
  const onSelect = route?.params?.onSelect;
  const insets = useSafeAreaInsets();
  const { user } = useAuth();
  const { showAlert } = useAlert();

  const [addresses, setAddresses] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ ...EMPTY_FORM });

  useFocusEffect(
    useCallback(() => {
      if (user) fetchAddresses();
    }, [user])
  );

  if (!user) {
    return <LoginPrompt navigation={navigation} message="Silakan login untuk mengelola alamat." />;
  }

  const fetchAddresses = async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    try {
      const res = await api.get('/api/addresses');
      const data = res.data?.data;
      if (Array.isArray(data)) {
        setAddresses(data);
      }
    } catch (e) {
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const setField = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));

  const openCreate = () => {
    setEditingId(null);
    setForm({ ...EMPTY_FORM });
    setShowForm(true);
  };

  const openEdit = (addr) => {
    setEditingId(addr.id);
    setForm({
      label: addr.label || '',
      recipient: addr.recipient || '',
      phone: addr.phone || '',
      street: addr.street || '',
      city: addr.city || '',
      province: addr.province || '',
      postal_code: addr.postal_code || '',
      notes: addr.notes || '',
      is_default: !!addr.is_default,
    });
    setShowForm(true);
  };

  const closeForm = () => {
    setShowForm(false);
    setEditingId(null);
    setForm({ ...EMPTY_FORM });
  };

  const handleSave = async () => {
    if (!form.label.trim() || !form.recipient.trim() || !form.phone.trim() || !form.street.trim() || !form.city.trim()) {
      showAlert({ title: 'Error', message: 'Label, nama penerima, telepon, alamat, dan kota wajib diisi.', type: 'error' });
      return;
    }
    setSaving(true);
    try {
      const payload = {
        label: form.label.trim(),
        recipient: form.recipient.trim(),
        phone: form.phone.trim(),
        street: form.street.trim(),
        city: form.city.trim(),
        province: form.province.trim(),
        postal_code: form.postal_code.trim(),
        notes: form.notes.trim(),
        is_default: form.is_default,
      };
      if (editingId) {
        await api.put(`/api/addresses/${editingId}`, payload);
      } else {
        await api.post('/api/addresses', payload);
      }
      closeForm();
      fetchAddresses();
    } catch (e) {
      const msg = e.response?.data?.message || 'Gagal menyimpan alamat.';
      showAlert({ title: 'Error', message: msg, type: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = (addr) => {
    showAlert({
      title: 'Hapus Alamat',
      message: `Hapus alamat "${addr.label}"?`,
      type: 'warning',
      buttons: [
        { text: 'Batal', style: 'cancel' },
        {
          text: 'Hapus',
          style: 'destructive',
          onPress: async () => {
            try {
              await api.delete(`/api/addresses/${addr.id}`);
              fetchAddresses();
            } catch (e) {
              const msg = e.response?.data?.message || 'Gagal menghapus alamat.';
              showAlert({ title: 'Error', message: msg, type: 'error' });
            }
          },
        },
      ],
    });
  };

  const handleCardPress = (addr) => {
    if (onSelect) {
      navigation.navigate('Checkout', { selectedAddress: addr });
    }
  };

  const renderAddress = ({ item }) => (
    <TouchableOpacity
      style={[styles.card, onSelect && styles.cardSelectable]}
      onPress={() => handleCardPress(item)}
      activeOpacity={onSelect ? 0.7 : 1}
    >
      <View style={styles.cardHeader}>
        <View style={styles.cardHeaderLeft}>
          <Text style={styles.cardLabel}>{item.label}</Text>
          {item.is_default ? (
            <View style={styles.defaultBadge}>
              <Text style={styles.defaultBadgeText}>Utama</Text>
            </View>
          ) : null}
        </View>
        <View style={styles.cardActions}>
          <TouchableOpacity onPress={() => openEdit(item)} style={styles.iconBtn}>
            <Text style={styles.editIcon}>✎</Text>
          </TouchableOpacity>
          <TouchableOpacity onPress={() => handleDelete(item)} style={styles.iconBtn}>
            <Text style={styles.deleteIcon}>✕</Text>
          </TouchableOpacity>
        </View>
      </View>
      <Text style={styles.cardRecipient}>{item.recipient} · {item.phone}</Text>
      <Text style={styles.cardAddress} numberOfLines={2}>
        {item.street}{item.city ? `, ${item.city}` : ''}{item.province ? `, ${item.province}` : ''}{item.postal_code ? ` ${item.postal_code}` : ''}
      </Text>
    </TouchableOpacity>
  );

  return (
    <View style={styles.container}>
      {showForm ? (
        <ScrollView style={styles.formContainer} contentContainerStyle={[styles.formContent, { paddingBottom: insets.bottom + 40 }]}>
          <Text style={styles.formTitle}>{editingId ? 'Edit Alamat' : 'Tambah Alamat'}</Text>

          <Text style={styles.fieldLabel}>Label *</Text>
          <TextInput
            style={styles.input}
            placeholder="Contoh: Rumah, Kantor"
            placeholderTextColor={COLORS.textLight}
            value={form.label}
            onChangeText={(v) => setField('label', v)}
          />

          <Text style={styles.fieldLabel}>Nama Penerima *</Text>
          <TextInput
            style={styles.input}
            placeholder="Nama lengkap"
            placeholderTextColor={COLORS.textLight}
            value={form.recipient}
            onChangeText={(v) => setField('recipient', v)}
          />

          <Text style={styles.fieldLabel}>Telepon *</Text>
          <TextInput
            style={styles.input}
            placeholder="08xxxxxxxxxx"
            placeholderTextColor={COLORS.textLight}
            keyboardType="phone-pad"
            value={form.phone}
            onChangeText={(v) => setField('phone', v)}
          />

          <Text style={styles.fieldLabel}>Alamat Lengkap *</Text>
          <TextInput
            style={[styles.input, styles.textArea]}
            placeholder="Jalan, nomor, RT/RW"
            placeholderTextColor={COLORS.textLight}
            value={form.street}
            onChangeText={(v) => setField('street', v)}
            multiline
            numberOfLines={3}
          />

          <View style={styles.row}>
            <View style={styles.halfField}>
              <Text style={styles.fieldLabel}>Kota</Text>
              <TextInput
                style={styles.input}
                placeholder="Kota"
                placeholderTextColor={COLORS.textLight}
                value={form.city}
                onChangeText={(v) => setField('city', v)}
              />
            </View>
            <View style={styles.halfField}>
              <Text style={styles.fieldLabel}>Provinsi</Text>
              <TextInput
                style={styles.input}
                placeholder="Provinsi"
                placeholderTextColor={COLORS.textLight}
                value={form.province}
                onChangeText={(v) => setField('province', v)}
              />
            </View>
          </View>

          <Text style={styles.fieldLabel}>Kode Pos</Text>
          <TextInput
            style={styles.input}
            placeholder="Kode pos"
            placeholderTextColor={COLORS.textLight}
            keyboardType="numeric"
            value={form.postal_code}
            onChangeText={(v) => setField('postal_code', v)}
          />

          <Text style={styles.fieldLabel}>Catatan</Text>
          <TextInput
            style={styles.input}
            placeholder="Catatan untuk kurir (opsional)"
            placeholderTextColor={COLORS.textLight}
            value={form.notes}
            onChangeText={(v) => setField('notes', v)}
          />

          <View style={styles.switchRow}>
            <Text style={styles.switchLabel}>Jadikan alamat utama</Text>
            <Switch
              value={form.is_default}
              onValueChange={(v) => setField('is_default', v)}
              trackColor={{ false: COLORS.border, true: COLORS.primary + '60' }}
              thumbColor={form.is_default ? COLORS.primary : '#f4f3f4'}
            />
          </View>

          <View style={styles.formButtons}>
            <TouchableOpacity
              style={[styles.saveBtn, saving && { opacity: 0.6 }]}
              onPress={handleSave}
              disabled={saving}
            >
              {saving ? (
                <ActivityIndicator color="#fff" />
              ) : (
                <Text style={styles.saveBtnText}>Simpan</Text>
              )}
            </TouchableOpacity>
            <TouchableOpacity style={styles.cancelBtn} onPress={closeForm}>
              <Text style={styles.cancelBtnText}>Batal</Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      ) : (
        <>
          <TouchableOpacity style={styles.addBtn} onPress={openCreate}>
            <Text style={styles.addBtnText}>+ Tambah Alamat</Text>
          </TouchableOpacity>

          {loading ? (
            <View style={styles.center}>
              <ActivityIndicator size="large" color={COLORS.primary} />
            </View>
          ) : (
            <FlatList
              data={addresses}
              renderItem={renderAddress}
              keyExtractor={(item) => String(item.id)}
              contentContainerStyle={[styles.list, { paddingBottom: insets.bottom + 40 }]}
              refreshing={refreshing}
              onRefresh={() => fetchAddresses(true)}
              ListEmptyComponent={
                <View style={styles.center}>
                  <Text style={styles.emptyText}>Belum ada alamat tersimpan.</Text>
                </View>
              }
            />
          )}
        </>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
  list: { padding: 12, paddingBottom: 40 },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
  emptyText: { fontSize: 16, color: COLORS.textSecondary },
  addBtn: {
    backgroundColor: COLORS.white, borderRadius: 12, paddingVertical: 14,
    alignItems: 'center', marginHorizontal: 12, marginTop: 12,
    borderWidth: 1, borderColor: COLORS.primary, borderStyle: 'dashed',
  },
  addBtnText: { fontSize: 15, fontWeight: '600', color: COLORS.primary },
  card: {
    backgroundColor: COLORS.white, borderRadius: 12, padding: 16,
    marginBottom: 10, elevation: 1, shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 }, shadowOpacity: 0.05, shadowRadius: 3,
  },
  cardSelectable: { borderWidth: 1, borderColor: COLORS.primary + '30' },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 },
  cardHeaderLeft: { flexDirection: 'row', alignItems: 'center', flex: 1 },
  cardLabel: { fontSize: 16, fontWeight: '700', color: COLORS.text, marginRight: 8 },
  defaultBadge: {
    backgroundColor: COLORS.primary + '20', paddingHorizontal: 8,
    paddingVertical: 2, borderRadius: 10,
  },
  defaultBadgeText: { fontSize: 11, fontWeight: '600', color: COLORS.primary },
  cardActions: { flexDirection: 'row', alignItems: 'center' },
  iconBtn: { paddingHorizontal: 8, paddingVertical: 4 },
  editIcon: { fontSize: 16, color: COLORS.info },
  deleteIcon: { fontSize: 16, color: COLORS.error },
  cardRecipient: { fontSize: 14, fontWeight: '500', color: COLORS.text, marginBottom: 4 },
  cardAddress: { fontSize: 13, color: COLORS.textSecondary, lineHeight: 18 },

  formContainer: { flex: 1, backgroundColor: COLORS.background },
  formContent: { padding: 16, paddingBottom: 40 },
  formTitle: { fontSize: 18, fontWeight: '700', color: COLORS.text, marginBottom: 16 },
  fieldLabel: { fontSize: 13, fontWeight: '600', color: COLORS.text, marginBottom: 4, marginTop: 10 },
  input: {
    borderWidth: 1, borderColor: COLORS.border, borderRadius: 10,
    paddingHorizontal: 14, paddingVertical: 12, fontSize: 15,
    color: COLORS.text, backgroundColor: COLORS.white,
  },
  textArea: { minHeight: 80, textAlignVertical: 'top' },
  row: { flexDirection: 'row', gap: 10 },
  halfField: { flex: 1 },
  switchRow: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
    marginTop: 14, paddingVertical: 8,
  },
  switchLabel: { fontSize: 15, fontWeight: '500', color: COLORS.text },
  formButtons: { flexDirection: 'row', marginTop: 20 },
  saveBtn: {
    flex: 1, backgroundColor: COLORS.primary, borderRadius: 10,
    paddingVertical: 14, alignItems: 'center', marginRight: 8,
  },
  saveBtnText: { color: '#fff', fontSize: 15, fontWeight: '600' },
  cancelBtn: {
    flex: 1, borderWidth: 1, borderColor: COLORS.border, borderRadius: 10,
    paddingVertical: 14, alignItems: 'center',
  },
  cancelBtnText: { color: COLORS.textSecondary, fontSize: 15, fontWeight: '600' },
});
