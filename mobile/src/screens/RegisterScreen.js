import React, { useState } from 'react';
import {
  View, Text, TextInput, TouchableOpacity, StyleSheet,
  KeyboardAvoidingView, Platform, ActivityIndicator, ScrollView,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '../context/AuthContext';
import { useAlert } from '../context/AlertContext';
import { COLORS } from '../config';

export default function RegisterScreen({ navigation }) {
  const insets = useSafeAreaInsets();
  const { register } = useAuth();
  const { showAlert } = useAlert();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [loading, setLoading] = useState(false);

  const handleRegister = async () => {
    if (!name || !email || !password) {
      showAlert({ title: 'Error', message: 'Semua field harus diisi.', type: 'error' });
      return;
    }
    if (password !== passwordConfirmation) {
      showAlert({ title: 'Error', message: 'Konfirmasi password tidak cocok.', type: 'error' });
      return;
    }
    if (password.length < 8) {
      showAlert({ title: 'Error', message: 'Password minimal 8 karakter.', type: 'error' });
      return;
    }
    setLoading(true);
    try {
      await register(name, email, password, passwordConfirmation);
      navigation.replace('Main');
    } catch (e) {
      const data = e.response?.data;
      const msg = data?.message || 'Registrasi gagal.';
      const errs = data?.errors;
      if (errs) {
        const first = Object.values(errs)[0]?.[0] || msg;
        showAlert({ title: 'Registrasi Gagal', message: first, type: 'error' });
      } else {
        showAlert({ title: 'Registrasi Gagal', message: msg, type: 'error' });
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView contentContainerStyle={[styles.inner, { paddingTop: insets.top + 20, paddingBottom: insets.bottom + 20 }]}>
        <View style={styles.header}>
          <Text style={styles.logo}>HS</Text>
          <Text style={styles.title}>Daftar Akun</Text>
          <Text style={styles.subtitle}>Buat akun baru Hello Store</Text>
        </View>

        <TextInput
          style={styles.input}
          placeholder="Nama Lengkap"
          placeholderTextColor={COLORS.textLight}
          value={name}
          onChangeText={setName}
          editable={!loading}
        />
        <TextInput
          style={styles.input}
          placeholder="Email"
          placeholderTextColor={COLORS.textLight}
          value={email}
          onChangeText={setEmail}
          keyboardType="email-address"
          autoCapitalize="none"
          editable={!loading}
        />
        <TextInput
          style={styles.input}
          placeholder="Password"
          placeholderTextColor={COLORS.textLight}
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          editable={!loading}
        />
        <TextInput
          style={styles.input}
          placeholder="Konfirmasi Password"
          placeholderTextColor={COLORS.textLight}
          value={passwordConfirmation}
          onChangeText={setPasswordConfirmation}
          secureTextEntry
          editable={!loading}
        />

        <TouchableOpacity
          style={[styles.button, loading && styles.buttonDisabled]}
          onPress={handleRegister}
          disabled={loading}
        >
          {loading ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.buttonText}>Daftar</Text>
          )}
        </TouchableOpacity>

        <TouchableOpacity onPress={() => navigation.goBack()}>
          <Text style={styles.link}>Sudah punya akun? Masuk</Text>
        </TouchableOpacity>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
  inner: { flexGrow: 1, justifyContent: 'center', paddingHorizontal: 24, paddingVertical: 40 },
  header: { alignItems: 'center', marginBottom: 32 },
  logo: {
    fontSize: 48, fontWeight: '800', color: COLORS.primary,
    width: 80, height: 80, borderRadius: 16,
    backgroundColor: '#FEF3C7', textAlign: 'center', lineHeight: 80,
    overflow: 'hidden', marginBottom: 12,
  },
  title: { fontSize: 24, fontWeight: '700', color: COLORS.text },
  subtitle: { fontSize: 14, color: COLORS.textSecondary, marginTop: 4 },
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
  link: { color: COLORS.primary, textAlign: 'center', marginTop: 20, fontSize: 14 },
});
