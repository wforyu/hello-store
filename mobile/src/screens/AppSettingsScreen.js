import React, { useState, useEffect } from 'react';
import {
  View, Text, TextInput, TouchableOpacity, StyleSheet,
  ScrollView, ActivityIndicator, KeyboardAvoidingView, Platform,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAlert } from '../context/AlertContext';
import { useToast } from '../components/Toast';
import {
  getApiUrl, setApiUrl, resetApiUrl, checkForUrlUpdate, testApiUrl, FALLBACK_API_URL,
} from '../config';

export default function AppSettingsScreen({ navigation }) {
  const insets = useSafeAreaInsets();
  const { showAlert } = useAlert();
  const toast = useToast();

  const [currentUrl, setCurrentUrl] = useState('');
  const [inputUrl, setInputUrl] = useState('');
  const [loading, setLoading] = useState(true);
  const [testing, setTesting] = useState(false);
  const [saving, setSaving] = useState(false);
  const [checking, setChecking] = useState(false);
  const [status, setStatus] = useState(null);

  useEffect(() => {
    loadCurrentUrl();
  }, []);

  const loadCurrentUrl = async () => {
    setLoading(true);
    try {
      const url = await getApiUrl();
      setCurrentUrl(url);
      setInputUrl(url);
    } catch (e) {
      setCurrentUrl(FALLBACK_API_URL);
      setInputUrl(FALLBACK_API_URL);
    } finally {
      setLoading(false);
    }
  };

  const handleTest = async () => {
    if (!inputUrl.trim()) {
      showAlert({ title: 'Error', message: 'Masukkan URL server terlebih dahulu.', type: 'error' });
      return;
    }
    setTesting(true);
    setStatus(null);
    try {
      const result = await testApiUrl(inputUrl.trim());
      if (result.success) {
        setStatus({ type: 'success', message: `Terhubung ke ${result.data?.store_name || 'server'}` });
      } else {
        setStatus({ type: 'error', message: `Gagal: ${result.error || 'Server tidak merespons'}` });
      }
    } catch (e) {
      setStatus({ type: 'error', message: 'Gagal menghubungi server.' });
    } finally {
      setTesting(false);
    }
  };

  const handleSave = async () => {
    if (!inputUrl.trim()) {
      showAlert({ title: 'Error', message: 'URL tidak boleh kosong.', type: 'error' });
      return;
    }
    setSaving(true);
    try {
      const result = await testApiUrl(inputUrl.trim());
      if (!result.success) {
        showAlert({ title: 'URL Tidak Valid', message: 'Server tidak ditemukan. Pastikan URL benar dan server aktif.', type: 'error' });
        setSaving(false);
        return;
      }
      await setApiUrl(inputUrl.trim());
      setCurrentUrl(inputUrl.trim());
      toast('URL server berhasil disimpan. Aplikasi akan menggunakan URL baru.', 'success');
      setStatus({ type: 'success', message: 'Tersimpan!' });
    } catch (e) {
      showAlert({ title: 'Error', message: 'Gagal menyimpan URL.', type: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const handleAutoDetect = async () => {
    setChecking(true);
    setStatus(null);
    try {
      const result = await checkForUrlUpdate();
      if (result.updated) {
        setCurrentUrl(result.newUrl);
        setInputUrl(result.newUrl);
        toast(`URL diperbarui otomatis ke ${result.newUrl}`, 'success');
        setStatus({ type: 'success', message: 'URL berhasil diperbarui dari server!' });
      } else {
        setStatus({ type: 'info', message: 'Tidak ada pembaruan URL dari server.' });
      }
    } catch (e) {
      setStatus({ type: 'error', message: 'Gagal memeriksa pembaruan.' });
    } finally {
      setChecking(false);
    }
  };

  const handleReset = () => {
    showAlert({
      title: 'Reset URL',
      message: 'Kembali ke URL default (ngrok untuk testing)?',
      type: 'warning',
      buttons: [
        { text: 'Batal', style: 'cancel' },
        {
          text: 'Reset',
          onPress: async () => {
            await resetApiUrl();
            setCurrentUrl(FALLBACK_API_URL);
            setInputUrl(FALLBACK_API_URL);
            toast('URL direset ke default.', 'info');
            setStatus({ type: 'info', message: 'URL direset ke default.' });
          },
        },
      ],
    });
  };

  return (
    <KeyboardAvoidingView
      style={styles.flex}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView
        style={styles.container}
        contentContainerStyle={{ paddingBottom: insets.bottom + 40 }}
      >
        {loading ? (
          <View style={styles.center}>
            <ActivityIndicator size="large" color={COLORS.primary} />
          </View>
        ) : (
          <>
            {/* Current URL Status */}
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Status Server</Text>
              <View style={styles.statusCard}>
                <View style={styles.statusRow}>
                  <View style={[styles.statusDot, { backgroundColor: COLORS.success }]} />
                  <Text style={styles.statusLabel}>URL Aktif</Text>
                </View>
                <Text style={styles.statusUrl} selectable>{currentUrl}</Text>
              </View>
            </View>

            {/* Auto Detect */}
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Deteksi Otomatis</Text>
              <Text style={styles.helperText}>
                Cek apakah admin sudah mengubah URL server di panel admin.
              </Text>
              <TouchableOpacity
                style={[styles.detectBtn, checking && styles.btnDisabled]}
                onPress={handleAutoDetect}
                disabled={checking}
              >
                {checking ? (
                  <ActivityIndicator color="#fff" size="small" />
                ) : (
                  <Text style={styles.detectBtnText}>🔍 Cek Pembaruan URL</Text>
                )}
              </TouchableOpacity>
            </View>

            {/* Manual URL */}
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>URL Server Manual</Text>
              <Text style={styles.helperText}>
                Masukkan URL lengkap (contoh: https://domain-anda.com)
              </Text>
              <TextInput
                style={styles.input}
                value={inputUrl}
                onChangeText={setInputUrl}
                placeholder="https://domain-anda.com"
                placeholderTextColor="#9CA3AF"
                autoCapitalize="none"
                autoCorrect={false}
                keyboardType="url"
                editable={!testing && !saving}
              />

              {status && (
                <View style={[
                  styles.statusBanner,
                  { backgroundColor: status.type === 'success' ? '#ECFDF5' : status.type === 'error' ? '#FEF2F2' : '#EFF6FF' },
                ]}>
                  <Text style={[
                    styles.statusBannerText,
                    { color: status.type === 'success' ? '#059669' : status.type === 'error' ? '#DC2626' : '#2563EB' },
                  ]}>
                    {status.type === 'success' ? '✅ ' : status.type === 'error' ? '❌ ' : 'ℹ️ '}
                    {status.message}
                  </Text>
                </View>
              )}

              <View style={styles.buttonRow}>
                <TouchableOpacity
                  style={[styles.testBtn, testing && styles.btnDisabled]}
                  onPress={handleTest}
                  disabled={testing || saving}
                >
                  {testing ? (
                    <ActivityIndicator color={COLORS.primary} size="small" />
                  ) : (
                    <Text style={styles.testBtnText}>🧪 Test Koneksi</Text>
                  )}
                </TouchableOpacity>

                <TouchableOpacity
                  style={[styles.saveBtn, saving && styles.btnDisabled]}
                  onPress={handleSave}
                  disabled={saving || testing}
                >
                  {saving ? (
                    <ActivityIndicator color="#fff" size="small" />
                  ) : (
                    <Text style={styles.saveBtnText}>💾 Simpan</Text>
                  )}
                </TouchableOpacity>
              </View>
            </View>

            {/* Reset */}
            <View style={styles.section}>
              <TouchableOpacity style={styles.resetBtn} onPress={handleReset}>
                <Text style={styles.resetBtnText}>↩️ Reset ke URL Default</Text>
              </TouchableOpacity>
              <Text style={styles.resetHelper}>
                Default: {FALLBACK_API_URL}
              </Text>
            </View>

            {/* How it works */}
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Cara Kerja</Text>
              <View style={styles.infoItem}>
                <Text style={styles.infoNum}>1</Text>
                <Text style={styles.infoText}>Aplikasi otomatis cek URL dari server saat startup</Text>
              </View>
              <View style={styles.infoItem}>
                <Text style={styles.infoNum}>2</Text>
                <Text style={styles.infoText}>Admin bisa ganti URL di Panel Admin → Pengaturan → Mobile App</Text>
              </View>
              <View style={styles.infoItem}>
                <Text style={styles.infoNum}>3</Text>
                <Text style={styles.infoText}>Aplikasi akan otomatis switch ke URL baru</Text>
              </View>
              <View style={styles.infoItem}>
                <Text style={styles.infoNum}>4</Text>
                <Text style={styles.infoText}>Atau set manual di atas jika auto-detect tidak berhasil</Text>
              </View>
            </View>
          </>
        )}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const COLORS = {
  primary: '#F59E0B',
  text: '#111827',
  textSecondary: '#6B7280',
  textLight: '#9CA3AF',
  border: '#E5E7EB',
  background: '#F9FAFB',
  white: '#FFFFFF',
};

const styles = StyleSheet.create({
  flex: { flex: 1 },
  container: { flex: 1, backgroundColor: COLORS.background },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
  section: {
    backgroundColor: COLORS.white, marginHorizontal: 12, marginTop: 12,
    borderRadius: 12, padding: 16,
  },
  sectionTitle: { fontSize: 16, fontWeight: '700', color: COLORS.text, marginBottom: 8 },
  helperText: { fontSize: 13, color: COLORS.textSecondary, marginBottom: 12, lineHeight: 18 },

  statusCard: {
    backgroundColor: '#F0FDF4', borderRadius: 10, padding: 14,
    borderWidth: 1, borderColor: '#BBF7D0',
  },
  statusRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 6 },
  statusDot: { width: 8, height: 8, borderRadius: 4, marginRight: 8 },
  statusLabel: { fontSize: 13, fontWeight: '600', color: '#059669' },
  statusUrl: { fontSize: 14, color: COLORS.text, fontWeight: '500' },

  detectBtn: {
    backgroundColor: '#3B82F6', borderRadius: 10,
    paddingVertical: 14, alignItems: 'center',
  },
  detectBtnText: { color: '#fff', fontSize: 15, fontWeight: '600' },

  input: {
    borderWidth: 1, borderColor: COLORS.border, borderRadius: 10,
    paddingHorizontal: 14, paddingVertical: 14, fontSize: 15,
    color: COLORS.text, backgroundColor: COLORS.background,
  },

  statusBanner: {
    borderRadius: 8, padding: 12, marginTop: 10,
  },
  statusBannerText: { fontSize: 13, fontWeight: '500', lineHeight: 18 },

  buttonRow: { flexDirection: 'row', gap: 10, marginTop: 12 },
  testBtn: {
    flex: 1, borderWidth: 2, borderColor: COLORS.primary, borderRadius: 10,
    paddingVertical: 14, alignItems: 'center',
  },
  testBtnText: { color: COLORS.primary, fontSize: 14, fontWeight: '600' },
  saveBtn: {
    flex: 1, backgroundColor: COLORS.primary, borderRadius: 10,
    paddingVertical: 14, alignItems: 'center',
  },
  saveBtnText: { color: '#fff', fontSize: 14, fontWeight: '600' },
  btnDisabled: { opacity: 0.5 },

  resetBtn: {
    borderWidth: 1, borderColor: '#FCA5A5', borderRadius: 10,
    paddingVertical: 12, alignItems: 'center', backgroundColor: '#FEF2F2',
  },
  resetBtnText: { color: '#DC2626', fontSize: 14, fontWeight: '600' },
  resetHelper: { fontSize: 12, color: COLORS.textLight, marginTop: 8, textAlign: 'center' },

  infoItem: { flexDirection: 'row', alignItems: 'center', marginBottom: 10 },
  infoNum: {
    width: 24, height: 24, borderRadius: 12, backgroundColor: COLORS.primary,
    color: '#fff', fontSize: 12, fontWeight: '700', textAlign: 'center', lineHeight: 24,
    marginRight: 10, overflow: 'hidden',
  },
  infoText: { flex: 1, fontSize: 13, color: COLORS.textSecondary, lineHeight: 18 },
});
