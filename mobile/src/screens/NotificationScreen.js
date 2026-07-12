import React, { useState, useCallback } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, StyleSheet, ActivityIndicator, RefreshControl,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { useAuth } from '../context/AuthContext';
import LoginPrompt from '../components/LoginPrompt';
import api from '../api/client';
import { COLORS } from '../config';

const TYPE_COLORS = {
  order: '#3B82F6',
  promo: '#EC4899',
  voucher: '#8B5CF6',
  review: '#10B981',
  stock: '#F97316',
};

export default function NotificationScreen({ navigation }) {
  const { user } = useAuth();
  const [notifications, setNotifications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  useFocusEffect(
    useCallback(() => {
      fetchData(1);
    }, [])
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
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const onRefresh = () => {
    setRefreshing(true);
    fetchData(1);
  };

  const markAsRead = async (id) => {
    try {
      await api.post(`/api/notifications/${id}/read`);
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, is_read: true } : n))
      );
    } catch (e) {
    }
  };

  const renderNotification = ({ item }) => {
    const color = TYPE_COLORS[item.type] || COLORS.textSecondary;
    return (
      <TouchableOpacity
        style={[styles.card, !item.is_read && styles.unread]}
        onPress={() => markAsRead(item.id)}
        activeOpacity={0.7}
      >
        <View style={[styles.icon, { backgroundColor: color + '20' }]}>
          <View style={[styles.iconDot, { backgroundColor: color }]} />
        </View>
        <View style={styles.content}>
          <Text style={[styles.title, !item.is_read && styles.titleUnread]}>
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
    <View style={styles.container}>
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
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[COLORS.primary]} />
          }
          onEndReached={() => page < lastPage && fetchData(page + 1)}
          onEndReachedThreshold={0.5}
          ListEmptyComponent={
            <View style={styles.center}>
              <Text style={styles.emptyText}>Tidak ada notifikasi.</Text>
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
  icon: { width: 40, height: 40, borderRadius: 20, justifyContent: 'center', alignItems: 'center', marginRight: 12 },
  iconDot: { width: 12, height: 12, borderRadius: 6 },
  content: { flex: 1 },
  title: { fontSize: 14, color: COLORS.textSecondary, marginBottom: 2 },
  titleUnread: { fontWeight: '600', color: COLORS.text },
  body: { fontSize: 13, color: COLORS.textSecondary, marginBottom: 4 },
  time: { fontSize: 11, color: COLORS.textLight },
  unreadDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: COLORS.primary, marginLeft: 8 },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
  emptyText: { fontSize: 16, color: COLORS.textSecondary },
});
