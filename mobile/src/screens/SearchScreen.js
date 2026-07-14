import React, { useState, useCallback, useRef } from 'react';
import {
  View, Text, TextInput, FlatList, TouchableOpacity, Image,
  StyleSheet, ActivityIndicator, Keyboard,
} from 'react-native';
import { useToast } from '../components/Toast';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../api/client';
import { COLORS, getImageUrl } from '../config';

export default function SearchScreen({ navigation }) {
  const insets = useSafeAreaInsets();
  const toast = useToast();
  const [query, setQuery] = useState('');
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(false);
  const [searched, setSearched] = useState(false);
  const inputRef = useRef(null);
  const debounceRef = useRef(null);

  React.useEffect(() => {
    inputRef.current?.focus();
  }, []);

  const doSearch = useCallback(async (q) => {
    const trimmed = q.trim();
    if (trimmed.length < 2) {
      setResults([]);
      setSearched(false);
      return;
    }
    setLoading(true);
    setSearched(true);
    try {
      const response = await api.get('/api/products', { params: { search: trimmed, per_page: 20 } });
      const data = response.data?.data;
      setResults(data?.data || []);
    } catch (e) {
      toast('Gagal mencari produk.', 'error');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  const onChangeText = (text) => {
    setQuery(text);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => doSearch(text), 400);
  };

  const handleSubmit = () => {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    Keyboard.dismiss();
    doSearch(query);
  };

  const formatPrice = (p) => `Rp${Number(p).toLocaleString('id-ID')}`;

  const renderItem = ({ item }) => (
    <TouchableOpacity
      style={styles.card}
      onPress={() => navigation.navigate('ProductDetail', { product: item })}
      activeOpacity={0.7}
    >
      <Image
        source={{ uri: getImageUrl(item.image) || 'https://via.placeholder.com/80' }}
        style={styles.image}
        resizeMode="cover"
      />
      <View style={styles.info}>
        <Text style={styles.name} numberOfLines={2}>{item.name}</Text>
        <Text style={styles.price}>{formatPrice(item.final_price || item.price)}</Text>
        {item.compare_price && item.compare_price > (item.final_price || item.price) && (
          <Text style={styles.compare}>{formatPrice(item.compare_price)}</Text>
        )}
        {item.rating > 0 && <Text style={styles.rating}>★ {Number(item.rating).toFixed(1)}</Text>}
      </View>
    </TouchableOpacity>
  );

  return (
    <View style={styles.container}>
      <View style={styles.searchBar}>
        <Text style={styles.searchIcon}>🔍</Text>
        <TextInput
          ref={inputRef}
          style={styles.searchInput}
          placeholder="Ketik nama produk atau SKU..."
          placeholderTextColor={COLORS.textLight}
          value={query}
          onChangeText={onChangeText}
          onSubmitEditing={handleSubmit}
          returnKeyType="search"
          autoCapitalize="none"
        />
        {query.length > 0 && (
          <TouchableOpacity onPress={() => { setQuery(''); setResults([]); setSearched(false); }}>
            <Text style={styles.clearBtn}>✕</Text>
          </TouchableOpacity>
        )}
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={COLORS.primary} />
        </View>
      ) : results.length > 0 ? (
        <FlatList
          data={results}
          renderItem={renderItem}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={[styles.list, { paddingBottom: insets.bottom + 20 }]}
          keyboardShouldPersistTaps="handled"
        />
      ) : searched ? (
        <View style={styles.center}>
          <Text style={styles.emptyIcon}>🔍</Text>
          <Text style={styles.emptyText}>Tidak ada produk ditemukan untuk "{query}"</Text>
        </View>
      ) : (
        <View style={styles.center}>
          <Text style={styles.emptyIcon}>💡</Text>
          <Text style={styles.emptyText}>Ketik kata kunci untuk mulai mencari</Text>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
  searchBar: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.white,
    marginHorizontal: 12, marginTop: 10, marginBottom: 6, borderRadius: 12,
    paddingHorizontal: 14, paddingVertical: 10, borderWidth: 1, borderColor: COLORS.border,
    elevation: 1,
  },
  searchIcon: { fontSize: 16, marginRight: 10 },
  searchInput: { flex: 1, fontSize: 15, color: COLORS.text, paddingVertical: 0 },
  clearBtn: { fontSize: 16, color: COLORS.textLight, padding: 4, marginLeft: 8 },
  list: { padding: 12 },
  card: {
    flexDirection: 'row', backgroundColor: COLORS.white, borderRadius: 12,
    padding: 12, marginBottom: 8, alignItems: 'center',
    elevation: 1, shadowColor: '#000', shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05, shadowRadius: 3,
  },
  image: { width: 64, height: 64, borderRadius: 8, backgroundColor: COLORS.border },
  info: { flex: 1, marginLeft: 12 },
  name: { fontSize: 14, fontWeight: '600', color: COLORS.text, marginBottom: 4 },
  price: { fontSize: 15, fontWeight: '700', color: COLORS.primary },
  compare: { fontSize: 12, color: COLORS.textLight, textDecorationLine: 'line-through', marginTop: 2 },
  rating: { fontSize: 12, color: '#F59E0B', marginTop: 2 },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
  emptyIcon: { fontSize: 48, marginBottom: 12 },
  emptyText: { fontSize: 14, color: COLORS.textSecondary, textAlign: 'center' },
});
