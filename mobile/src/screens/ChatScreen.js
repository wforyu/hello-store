import React, { useState, useCallback } from 'react';
import {
  View, Text, TextInput, TouchableOpacity, StyleSheet, ScrollView,
  KeyboardAvoidingView, Platform, ActivityIndicator,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '../context/AuthContext';
import { useAlert } from '../context/AlertContext';
import LoginPrompt from '../components/LoginPrompt';
import api from '../api/client';
import { COLORS } from '../config';

export default function ChatScreen({ navigation }) {
  const insets = useSafeAreaInsets();
  const { user } = useAuth();
  const { showAlert } = useAlert();
  const [messages, setMessages] = useState([]);
  const [conversationId, setConversationId] = useState(null);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [lastId, setLastId] = useState(0);
  const pollRef = React.useRef(null);
  const scrollRef = React.useRef(null);
  const conversationIdRef = React.useRef(null);
  const lastIdRef = React.useRef(0);

  const startPolling = useCallback(() => {
    if (pollRef.current) return;
    pollRef.current = setInterval(() => {
      const cid = conversationIdRef.current;
      if (!cid) return;
      api.get(`/api/chat/${cid}/poll`, { params: { after_id: lastIdRef.current } })
        .then((res) => {
          if (res.data?.success && Array.isArray(res.data.messages)) {
            const fresh = res.data.messages.filter((m) => m.id > lastIdRef.current);
            if (fresh.length) {
              lastIdRef.current = Math.max(lastIdRef.current, ...fresh.map((m) => m.id));
              setLastId(lastIdRef.current);
              setMessages((prev) => [...prev, ...fresh]);
            }
          }
        })
        .catch(() => {});
    }, 5000);
  }, []);

  const stopPolling = useCallback(() => {
    if (pollRef.current) {
      clearInterval(pollRef.current);
      pollRef.current = null;
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      if (user) {
        fetchHistory();
        startPolling();
      } else {
        setLoading(false);
      }
      return stopPolling;
    }, [user])
  );

  const fetchHistory = async () => {
    setLoading(true);
    try {
      const res = await api.get('/api/chat');
      if (res.data?.success) {
        const cid = res.data.data.conversation_id;
        conversationIdRef.current = cid;
        setConversationId(cid);
        const msgs = res.data.data.messages || [];
        setMessages(msgs);
        if (msgs.length) {
          lastIdRef.current = Math.max(...msgs.map((m) => m.id));
          setLastId(lastIdRef.current);
        }
      }
    } catch (e) {
      showAlert({ title: 'Error', message: 'Gagal memuat chat.', type: 'error' });
    } finally {
      setLoading(false);
    }
  };

  const send = async () => {
    const message = input.trim();
    if (!message || sending) return;
    setSending(true);
    const temp = { id: `tmp-${Date.now()}`, sender_type: 'customer', message, created_at: new Date().toISOString() };
    setMessages((prev) => [...prev, temp]);
    setInput('');
    try {
      const res = await api.post('/api/chat/send', { message });
      if (res.data?.success) {
        setMessages((prev) => prev.filter((m) => m.id !== temp.id).concat(res.data.data));
        if (res.data.data.id > lastIdRef.current) {
          lastIdRef.current = res.data.data.id;
          setLastId(lastIdRef.current);
        }
      }
    } catch (e) {
      const msg = e.response?.data?.message || 'Gagal mengirim pesan.';
      showAlert({ title: 'Error', message: msg, type: 'error' });
      setMessages((prev) => prev.filter((m) => m.id !== temp.id));
    } finally {
      setSending(false);
    }
  };

  if (!user) {
    return <LoginPrompt navigation={navigation} message="Silakan login untuk chat dengan admin." />;
  }

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      keyboardVerticalOffset={100}
    >
      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={COLORS.primary} />
        </View>
      ) : (
        <>
          <ScrollView
            style={styles.messagesWrap}
            contentContainerStyle={styles.messagesContent}
            ref={scrollRef}
            onContentSizeChange={() => scrollRef.current?.scrollToEnd({ animated: true })}
          >
            {messages.length === 0 && (
              <View style={styles.empty}>
                <Text style={{ fontSize: 40, marginBottom: 8 }}>💬</Text>
                <Text style={styles.emptyTitle}>Halo! Ada yang bisa kami bantu?</Text>
                <Text style={styles.emptyText}>Tanyakan tentang produk, pesanan, atau pembayaran.</Text>
              </View>
            )}
            {messages.map((msg) => {
              const isCustomer = msg.sender_type === 'customer';
              return (
                <View key={msg.id} style={[styles.bubbleRow, isCustomer ? styles.rowCustomer : styles.rowAdmin]}>
                  <View style={[styles.bubble, isCustomer ? styles.bubbleCustomer : styles.bubbleAdmin]}>
                    <Text style={isCustomer ? styles.textCustomer : styles.textAdmin}>{msg.message}</Text>
                    <Text style={[styles.time, isCustomer ? styles.timeCustomer : styles.timeAdmin]}>
                      {msg.created_at ? new Date(msg.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : ''}
                    </Text>
                  </View>
                </View>
              );
            })}
          </ScrollView>

          <View style={[styles.inputWrap, { paddingBottom: Math.max(insets.bottom, 10) }]}>
            <TextInput
              style={styles.input}
              placeholder="Ketik pesan..."
              placeholderTextColor={COLORS.textLight}
              value={input}
              onChangeText={setInput}
              multiline
              maxLength={1000}
              editable={!sending}
            />
            <TouchableOpacity
              style={[styles.sendBtn, (!input.trim() || sending) && { opacity: 0.5 }]}
              onPress={send}
              disabled={!input.trim() || sending}
            >
              {sending ? (
                <ActivityIndicator color="#fff" size="small" />
              ) : (
                <Text style={styles.sendText}>Kirim</Text>
              )}
            </TouchableOpacity>
          </View>
        </>
      )}
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  messagesWrap: { flex: 1 },
  messagesContent: { padding: 16 },
  empty: { alignItems: 'center', marginTop: 40, paddingHorizontal: 24 },
  emptyTitle: { fontSize: 16, fontWeight: '600', color: COLORS.text, textAlign: 'center' },
  emptyText: { fontSize: 13, color: COLORS.textSecondary, textAlign: 'center', marginTop: 6, lineHeight: 19 },
  bubbleRow: { flexDirection: 'row', marginBottom: 10 },
  rowCustomer: { justifyContent: 'flex-end' },
  rowAdmin: { justifyContent: 'flex-start' },
  bubble: {
    maxWidth: '78%', borderRadius: 14, paddingHorizontal: 14, paddingVertical: 10,
  },
  bubbleCustomer: { backgroundColor: COLORS.primary, borderBottomRightRadius: 4 },
  bubbleAdmin: { backgroundColor: COLORS.white, borderWidth: 1, borderColor: COLORS.border, borderBottomLeftRadius: 4 },
  textCustomer: { color: '#fff', fontSize: 14 },
  textAdmin: { color: COLORS.text, fontSize: 14 },
  time: { fontSize: 10, marginTop: 4 },
  timeCustomer: { color: 'rgba(255,255,255,0.7)', textAlign: 'right' },
  timeAdmin: { color: COLORS.textLight },
  inputWrap: {
    flexDirection: 'row', alignItems: 'flex-end', padding: 12,
    backgroundColor: COLORS.white, borderTopWidth: 1, borderTopColor: COLORS.border,
    gap: 8,
  },
  input: {
    flex: 1, borderWidth: 1, borderColor: COLORS.border, borderRadius: 12,
    paddingHorizontal: 14, paddingVertical: 10, fontSize: 14, color: COLORS.text,
    maxHeight: 100, backgroundColor: COLORS.background,
  },
  sendBtn: {
    backgroundColor: COLORS.primary, borderRadius: 12, paddingVertical: 11, paddingHorizontal: 18,
    alignItems: 'center', justifyContent: 'center',
  },
  sendText: { color: '#fff', fontSize: 14, fontWeight: '700' },
});