import React, { useState, useCallback } from 'react';
import {
  View, Text, TouchableOpacity, StyleSheet, ScrollView, TextInput, ActivityIndicator, Modal, FlatList,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useFocusEffect } from '@react-navigation/native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '../context/AuthContext';
import { useAlert } from '../context/AlertContext';
import { useToast } from '../components/Toast';
import LoginPrompt from '../components/LoginPrompt';
import api from '../api/client';
import { COLORS } from '../config';
import { formatPrice, STATUS_COLORS } from '../utils';

const AVATARS = [
  { id: 'bear', emoji: '🐻', label: 'Beruang' },
  { id: 'cat', emoji: '🐱', label: 'Kucing' },
  { id: 'dog', emoji: '🐶', label: 'Anjing' },
  { id: 'panda', emoji: '🐼', label: 'Panda' },
  { id: 'rabbit', emoji: '🐰', label: 'Kelinci' },
  { id: 'fox', emoji: '🦊', label: 'Rubah' },
  { id: 'owl', emoji: '🦉', label: 'Burung Hantu' },
  { id: 'penguin', emoji: '🐧', label: 'Pinguin' },
  { id: 'lion', emoji: '🦁', label: 'Singa' },
  { id: 'tiger', emoji: '🐯', label: 'Harimau' },
  { id: 'monkey', emoji: '🐵', label: 'Monyet' },
  { id: 'frog', emoji: '🐸', label: 'Katak' },
  { id: 'unicorn', emoji: '🦄', label: 'Unicorn' },
  { id: 'dragon', emoji: '🐲', label: 'Naga' },
  { id: 'robot', emoji: '🤖', label: 'Robot' },
  { id: 'alien', emoji: '👽', label: 'Alien' },
];

const AVATAR_KEY = 'user_avatar';

const TIER_COLORS = {
  diamond: '#8B5CF6',
  platinum: '#10B981',
  gold: '#F59E0B',
  silver: '#9CA3AF',
  bronze: '#D97706',
};

const TIER_LABELS = {
  diamond: '💎 Diamond',
  platinum: '🏆 Platinum',
  gold: '🥇 Gold',
  silver: '🥈 Silver',
  bronze: '🥉 Bronze',
};

export default function ProfileScreen({ navigation }) {
  const insets = useSafeAreaInsets();
  const { user, logout, updateUser } = useAuth();
  const { showAlert } = useAlert();
  const toast = useToast();
  const [editing, setEditing] = useState(false);
  const [name, setName] = useState(user?.name || '');
  const [email, setEmail] = useState(user?.email || '');
  const [saving, setSaving] = useState(false);
  const [showAvatarModal, setShowAvatarModal] = useState(false);
  const [selectedAvatarId, setSelectedAvatarId] = useState(null);
  const [stats, setStats] = useState({ orders: 0, addresses: 0 });

  useFocusEffect(
    useCallback(() => {
      (async () => {
        const saved = await AsyncStorage.getItem(AVATAR_KEY);
        if (saved) setSelectedAvatarId(saved);
      })();
      fetchStats();
    }, [])
  );

  const fetchStats = async () => {
    try {
      const [ordersRes, addrRes] = await Promise.all([
        api.get('/api/orders', { params: { per_page: 1 } }),
        api.get('/api/addresses', { params: { per_page: 1 } }),
      ]);
      const ordersTotal = ordersRes.data?.data?.meta?.total ?? 0;
      const addrList = addrRes.data?.data;
      const addrTotal = Array.isArray(addrList) ? addrList.length : (addrRes.data?.meta?.total ?? 0);
      setStats({ orders: ordersTotal, addresses: addrTotal });
    } catch (e) {
      // silent
    }
  };

  if (!user) {
    return <LoginPrompt navigation={navigation} message="Silakan login untuk mengakses profil." />;
  }

  const currentAvatar = AVATARS.find((a) => a.id === selectedAvatarId) || AVATARS[0];

  const handleLogout = () => {
    showAlert({
      title: 'Logout',
      message: 'Apakah Anda yakin ingin keluar?',
      type: 'warning',
      buttons: [
        { text: 'Batal', style: 'cancel' },
        { text: 'Keluar', style: 'destructive', onPress: logout },
      ],
    });
  };

  const handleSave = async () => {
    if (!name) {
      toast('Nama harus diisi.', 'error');
      return;
    }
    setSaving(true);
    try {
      await api.put('/api/profile', { name, email });
      toast('Profil diperbarui', 'success');
      updateUser({ name, email });
      setEditing(false);
    } catch (e) {
      const msg = e.response?.data?.message || 'Gagal menyimpan.';
      toast(msg, 'error');
    } finally {
      setSaving(false);
    }
  };

  const selectAvatar = async (avatar) => {
    setSelectedAvatarId(avatar.id);
    await AsyncStorage.setItem(AVATAR_KEY, avatar.id);
    setShowAvatarModal(false);
    toast(`Avatar ${avatar.label} dipilih`, 'success');
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={{ paddingTop: insets.top + 12, paddingBottom: insets.bottom + 24 }}>
      {/* Profile Header */}
      <View style={styles.header}>
        <TouchableOpacity style={styles.avatarWrap} onPress={() => setShowAvatarModal(true)} activeOpacity={0.7}>
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>{currentAvatar.emoji}</Text>
          </View>
          <View style={styles.cameraIcon}>
            <Text style={styles.cameraIconText}>📷</Text>
          </View>
          <Text style={styles.avatarHint}>Tap untuk ganti</Text>
        </TouchableOpacity>

        {editing ? (
          <View style={styles.editFields}>
            <TextInput
              style={styles.input}
              value={name}
              onChangeText={setName}
              placeholder="Nama"
              placeholderTextColor={COLORS.textLight}
            />
            <TextInput
              style={styles.input}
              value={email}
              onChangeText={setEmail}
              placeholder="Email"
              placeholderTextColor={COLORS.textLight}
              keyboardType="email-address"
              autoCapitalize="none"
            />
            <View style={styles.editActions}>
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
              <TouchableOpacity style={styles.cancelBtn} onPress={() => setEditing(false)}>
                <Text style={styles.cancelBtnText}>Batal</Text>
              </TouchableOpacity>
            </View>
          </View>
        ) : (
          <>
            <Text style={styles.name}>{user?.name}</Text>
            <Text style={styles.email}>{user?.email}</Text>
            <Text style={styles.role}>{user?.role === 'customer' ? 'Pelanggan' : user?.role}</Text>
            {user?.segment && (
              <View style={styles.tierBadge}>
                <Text style={[styles.tierBadgeText, { color: TIER_COLORS[user.segment] || COLORS.primary }]}>
                  {TIER_LABELS[user.segment] || user.segment}
                </Text>
                {user.total_spent > 0 && (
                  <Text style={styles.tierSpent}>Total belanja: {formatPrice(user.total_spent)}</Text>
                )}
              </View>
            )}
            {user?.points > 0 && (
              <View style={styles.pointsBadge}>
                <Text style={styles.pointsBadgeText}>⭐ {user.points} poin</Text>
              </View>
            )}
          </>
        )}
      </View>

      {/* Stats Cards */}
      {!editing && (
        <View style={styles.statsRow}>
          <View style={styles.statCard}>
            <Text style={styles.statIcon}>⭐</Text>
            <Text style={styles.statValue}>{user?.points || 0}</Text>
            <Text style={styles.statLabel}>Poin</Text>
          </View>
          <View style={styles.statCard}>
            <Text style={styles.statIcon}>🛒</Text>
            <Text style={styles.statValue}>{stats.orders}</Text>
            <Text style={styles.statLabel}>Pesanan</Text>
          </View>
          <View style={styles.statCard}>
            <Text style={styles.statIcon}>📍</Text>
            <Text style={styles.statValue}>{stats.addresses}</Text>
            <Text style={styles.statLabel}>Alamat</Text>
          </View>
        </View>
      )}

      {/* Menu Items */}
      {!editing && (
        <View style={styles.actions}>
          <Text style={styles.menuSectionTitle}>Akun</Text>
          <TouchableOpacity style={styles.actionBtn} onPress={() => setEditing(true)}>
            <View style={[styles.actionIconWrap, { backgroundColor: '#EFF6FF' }]}>
              <Text style={styles.actionIcon}>✏️</Text>
            </View>
            <Text style={styles.actionBtnText}>Edit Profil</Text>
            <Text style={styles.actionArrow}>›</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.actionBtn} onPress={() => navigation.navigate('Orders')}>
            <View style={[styles.actionIconWrap, { backgroundColor: '#FEF3C7' }]}>
              <Text style={styles.actionIcon}>📦</Text>
            </View>
            <Text style={styles.actionBtnText}>Pesanan Saya</Text>
            <Text style={styles.actionArrow}>›</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.actionBtn} onPress={() => navigation.navigate('Wishlist')}>
            <View style={[styles.actionIconWrap, { backgroundColor: '#FEE2E2' }]}>
              <Text style={styles.actionIcon}>❤️</Text>
            </View>
            <Text style={styles.actionBtnText}>Wishlist Saya</Text>
            <Text style={styles.actionArrow}>›</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.actionBtn} onPress={() => navigation.navigate('Address')}>
            <View style={[styles.actionIconWrap, { backgroundColor: '#ECFDF5' }]}>
              <Text style={styles.actionIcon}>📍</Text>
            </View>
            <Text style={styles.actionBtnText}>Alamat Saya</Text>
            <Text style={styles.actionArrow}>›</Text>
          </TouchableOpacity>

          <Text style={[styles.menuSectionTitle, { marginTop: 16 }]}>Lainnya</Text>
          <TouchableOpacity style={styles.actionBtn} onPress={() => navigation.navigate('Notifications')}>
            <View style={[styles.actionIconWrap, { backgroundColor: '#F3E8FF' }]}>
              <Text style={styles.actionIcon}>🔔</Text>
            </View>
            <Text style={styles.actionBtnText}>Notifikasi</Text>
            <Text style={styles.actionArrow}>›</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.actionBtn, styles.logoutBtn]}
            onPress={handleLogout}
          >
            <View style={[styles.actionIconWrap, { backgroundColor: '#FEE2E2' }]}>
              <Text style={styles.actionIcon}>🚪</Text>
            </View>
            <Text style={[styles.actionBtnText, styles.logoutText]}>Keluar</Text>
            <Text style={[styles.actionArrow, { color: COLORS.error }]}>›</Text>
          </TouchableOpacity>
        </View>
      )}

      {/* Avatar Picker Modal */}
      <Modal visible={showAvatarModal} transparent animationType="slide">
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContent, { paddingBottom: insets.bottom + 32 }]}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Pilih Avatar</Text>
              <TouchableOpacity onPress={() => setShowAvatarModal(false)}>
                <Text style={styles.modalClose}>✕</Text>
              </TouchableOpacity>
            </View>
            <Text style={styles.modalSubtitle}>Pilih avatar lucu untuk profil Anda</Text>
            <FlatList
              data={AVATARS}
              numColumns={4}
              keyExtractor={(item) => item.id}
              contentContainerStyle={styles.avatarGrid}
              renderItem={({ item }) => (
                <TouchableOpacity
                  style={[
                    styles.avatarOption,
                    selectedAvatarId === item.id && styles.avatarOptionActive,
                  ]}
                  onPress={() => selectAvatar(item)}
                  activeOpacity={0.7}
                >
                  <Text style={styles.avatarOptionEmoji}>{item.emoji}</Text>
                  <Text style={styles.avatarOptionLabel}>{item.label}</Text>
                </TouchableOpacity>
              )}
            />
          </View>
        </View>
      </Modal>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
  header: {
    backgroundColor: COLORS.white, alignItems: 'center', padding: 28,
    marginHorizontal: 12, marginTop: 12, borderRadius: 16,
    elevation: 1, shadowColor: '#000', shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05, shadowRadius: 3,
  },
  avatarWrap: { alignItems: 'center', marginBottom: 16 },
  avatar: {
    width: 90, height: 90, borderRadius: 45, backgroundColor: '#FEF3C7',
    justifyContent: 'center', alignItems: 'center', marginBottom: 4,
    borderWidth: 3, borderColor: COLORS.primary,
  },
  avatarText: { fontSize: 44 },
  cameraIcon: {
    position: 'absolute', bottom: 8, right: -4,
    width: 28, height: 28, borderRadius: 14, backgroundColor: COLORS.primary,
    justifyContent: 'center', alignItems: 'center',
  },
  cameraIconText: { fontSize: 14 },
  avatarHint: { fontSize: 11, color: COLORS.primary, fontWeight: '500', marginTop: 4 },
  name: { fontSize: 22, fontWeight: '700', color: COLORS.text, marginBottom: 4 },
  email: { fontSize: 14, color: COLORS.textSecondary, marginBottom: 4 },
  role: { fontSize: 13, color: COLORS.textLight },
  tierBadge: { alignItems: 'center', marginTop: 8 },
  tierBadgeText: { fontSize: 15, fontWeight: '700' },
  tierSpent: { fontSize: 11, color: COLORS.textSecondary, marginTop: 2 },
  pointsBadge: {
    marginTop: 6, backgroundColor: '#FEF3C7', borderRadius: 12,
    paddingHorizontal: 12, paddingVertical: 4,
  },
  pointsBadgeText: { fontSize: 13, fontWeight: '600', color: '#D97706' },

  // Stats
  statsRow: {
    flexDirection: 'row', marginHorizontal: 12, marginTop: 12, gap: 8,
  },
  statCard: {
    flex: 1, backgroundColor: COLORS.white, borderRadius: 12, padding: 14,
    alignItems: 'center', elevation: 1,
  },
  statIcon: { fontSize: 20, marginBottom: 4 },
  statValue: { fontSize: 18, fontWeight: '700', color: COLORS.text },
  statLabel: { fontSize: 11, color: COLORS.textSecondary, marginTop: 2 },

  // Menu
  actions: { paddingHorizontal: 12, marginTop: 12, marginBottom: 24 },
  menuSectionTitle: {
    fontSize: 12, fontWeight: '600', color: COLORS.textSecondary,
    textTransform: 'uppercase', marginBottom: 8, marginLeft: 4,
  },
  actionBtn: {
    backgroundColor: COLORS.white, borderRadius: 12, paddingVertical: 14,
    paddingHorizontal: 14, marginBottom: 8,
    flexDirection: 'row', alignItems: 'center',
    elevation: 1, shadowColor: '#000', shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05, shadowRadius: 3,
  },
  actionIconWrap: {
    width: 36, height: 36, borderRadius: 10, justifyContent: 'center', alignItems: 'center', marginRight: 12,
  },
  actionIcon: { fontSize: 18 },
  actionBtnText: { flex: 1, fontSize: 15, fontWeight: '500', color: COLORS.text },
  actionArrow: { fontSize: 20, color: COLORS.textLight },
  logoutBtn: { borderWidth: 1, borderColor: COLORS.error + '30' },
  logoutText: { color: COLORS.error },

  editFields: { width: '100%' },
  input: {
    borderWidth: 1, borderColor: COLORS.border, borderRadius: 10,
    paddingHorizontal: 14, paddingVertical: 12, fontSize: 16,
    color: COLORS.text, marginBottom: 10,
  },
  editActions: { flexDirection: 'row', marginTop: 4 },
  saveBtn: {
    flex: 1, backgroundColor: COLORS.primary, borderRadius: 10,
    paddingVertical: 12, alignItems: 'center', marginRight: 8,
  },
  saveBtnText: { color: '#fff', fontSize: 14, fontWeight: '600' },
  cancelBtn: {
    flex: 1, borderWidth: 1, borderColor: COLORS.border, borderRadius: 10,
    paddingVertical: 12, alignItems: 'center',
  },
  cancelBtnText: { color: COLORS.textSecondary, fontSize: 14, fontWeight: '600' },

  // Modal
  modalOverlay: {
    flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: COLORS.white, borderTopLeftRadius: 20, borderTopRightRadius: 20,
    paddingBottom: 32, maxHeight: '70%',
  },
  modalHeader: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
    padding: 20, paddingBottom: 4,
  },
  modalTitle: { fontSize: 18, fontWeight: '700', color: COLORS.text },
  modalClose: { fontSize: 20, color: COLORS.textSecondary, padding: 4 },
  modalSubtitle: { fontSize: 13, color: COLORS.textSecondary, paddingHorizontal: 20, marginBottom: 8 },
  avatarGrid: { paddingHorizontal: 12, paddingVertical: 8 },
  avatarOption: {
    flex: 1, alignItems: 'center', paddingVertical: 12, margin: 4,
    borderRadius: 12, backgroundColor: COLORS.background, minWidth: '20%',
  },
  avatarOptionActive: { backgroundColor: '#FEF3C7', borderWidth: 2, borderColor: COLORS.primary },
  avatarOptionEmoji: { fontSize: 32, marginBottom: 4 },
  avatarOptionLabel: { fontSize: 10, color: COLORS.textSecondary },
});
