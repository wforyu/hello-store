import React, { useState, useCallback } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, StyleSheet, ActivityIndicator, RefreshControl,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '../context/AuthContext';
import LoginPrompt from '../components/LoginPrompt';
import api from '../api/client';
import { COLORS } from '../config';

const TYPE_CONFIG = {
  order: { color: '#3B82F6', icon: '📦' },
  promo: { color: '#EC4899', icon: '🎉' },
  voucher: { color: '#8B5CF6', icon: '🎫' },
  review: { color: '#10B981', icon: '⭐' },
  stock: { color: '#F97316', icon: '🔔' },
  payment: { color: '#6366F1', icon: '💳' },
  shipping: { color: '#14B8A6', icon: '🚚' },
  refund: { color: '#EF4444', icon: '↩️' },
};

export default function NotificationScreen({ navigation }) {
  const insets = useSafeAreaInsets();
  const { user } = useAuth();
  const [notifications, setNotifications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  useFocusEffect(
    useCallback(() => {
      if (user) {
        setLoading(true);
        setPage(1);
        fetchData(1);
      } else {
        setLoading(false);
      }
    }, [user])
  );

  const fetchData = async (pageNum = 1) => {
    try {
      const response = await api.get('/api/notifications', { params: { page: pageNum, per_page: 20 } });
      const data = response.data?.data || [];
      const meta = response.data?.meta || {};
      setNotifications(pageNum === 1 ? data : (prev) => [...prev, ...data]);
      setPage(meta.current_page || 1);
      setLastPage(meta.last_page || 1);
    } catch (e) {
      // silent
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const onRefresh = () => {
    setRefreshing(true);
    setPage(1);
    fetchData(1);
  };

  const markAsRead = async (id) => {
    try {
      await api.post(`/api/notifications/${id}/read`);
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, is_read: true } : n))
      );
    } catch (e) {
      // silent
    }
  };

  const markAllRead = async () => {
    try {
      await api.post('/api/notifications/read-all');
      setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })));
    } catch (e) {
      // silent
    }
  };

  const hasUnread = notifications.some((n) => !n.is_read);

  const renderNotification = ({ item }) => {
    const config = TYPE_CONFIG[item.type] || { color: COLORS.textSecondary, icon: '📌' };
    return (
      <TouchableOpacity
        style={[styles.card, !item.is_read && styles.unread]}
        onPress={() => markAsRead(item.id)}
        activeOpacity={0.7}
      >
        <View style={[styles.icon, { backgroundColor: config.color + '15' }]}>
          <Text style={styles.iconEmoji}>{config.icon}</Text>
        </View>
        <View style={styles.content}>
          <Text style={[styles.title, !item.is_read && styles.titleUnread]} numberOfLines={1}>
            {item.title}
          </Text>
          {item.body && (
            <Text style={styles.body} numberOfLines={2}>{item.body}</Text>
          )}
          <Text style={styles.time}>
            {new Date(item.created_at).toLocaleDateString('id-ID', {
              day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
            })}
          </Text>
        </View>
        {!item.is_read && <View style={styles.unreadDot} />}
      </TouchableOpacity>
    );
  };

  if (!user) {
    return <LoginPrompt navigation={navigation} message="Silakan login untuk melihat notifikasi." />;
  }

  return (
    <View style={[styles.container, { paddingTop: insets.top }]}>
      {loading && notifications.length === 0 ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={COLORS.primary} />
        </View>
      ) : (
        <FlatList
          data={notifications}
          renderItem={renderNotification}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={styles.list}
          ListHeaderComponent={
            <View style={styles.header}>
              <Text style={styles.headerTitle}>Notifikasi</Text>
              {hasUnread && (
                <TouchableOpacity onPress={markAllRead}>
                  <Text style={styles.markAllText}>Tandai Semua</Text>
                </TouchableOpacity>
              )}
            </View>
          }
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[COLORS.primary]} />
          }
          onEndReached={() => page < lastPage && fetchData(page + 1)}
          onEndReachedThreshold={0.5}
          ListEmptyComponent={
            <View style={styles.center}>
              <Text style={styles.emptyIcon}>🔔</Text>
              <Text style={styles.emptyTitle}>Belum Ada Notifikasi</Text>
              <Text style={styles.emptyText}>Notifikasi akan muncul di sini</Text>
            </View>
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
  list: { padding: 12 },
  card: {
    flexDirection: 'row', backgroundColor: COLORS.white, borderRadius: 12,
    padding: 14, marginBottom: 8, alignItems: 'center',
    elevation: 1, shadowColor: '#000', shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05, shadowRadius: 3,
  },
  unread: { borderLeftWidth: 3, borderLeftColor: COLORS.primary },
  icon: {
    width: 42, height: 42, borderRadius: 21, justifyContent: 'center', alignItems: 'center', marginRight: 12,
  },
  iconEmoji: { fontSize: 20 },
  content: { flex: 1 },
  title: { fontSize: 14, color: COLORS.textSecondary, marginBottom: 2 },
  titleUnread: { fontWeight: '600', color: COLORS.text },
  body: { fontSize: 13, color: COLORS.textSecondary, marginBottom: 4, lineHeight: 17 },
  time: { fontSize: 11, color: COLORS.textLight },
  unreadDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: COLORS.primary, marginLeft: 8 },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
  emptyIcon: { fontSize: 48, marginBottom: 12 },
  emptyTitle: { fontSize: 18, fontWeight: '700', color: COLORS.text, marginBottom: 6 },
  emptyText: { fontSize: 14, color: COLORS.textSecondary },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12, paddingHorizontal: 2 },
  headerTitle: { fontSize: 20, fontWeight: '700', color: COLORS.text },
  markAllText: { fontSize: 14, fontWeight: '600', color: COLORS.primary },
});
