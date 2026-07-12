import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
  View, Text, FlatList, TextInput, TouchableOpacity, Image,
  StyleSheet, ActivityIndicator, RefreshControl, ScrollView, Dimensions,
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../components/Toast';
import api from '../api/client';
import { COLORS, getImageUrl, API_URL } from '../config';

const { width: SCREEN_WIDTH } = Dimensions.get('window');
const BANNER_WIDTH = SCREEN_WIDTH - 24;
const FLASH_ICONS = ['⚡', '🔥', '💥', '🎯', '🏷️', '💰', '🎁', '🛒'];

const CATEGORY_ICONS = {
  'Elektronik': '📱', 'Fashion': '👕', 'Makanan': '🍔', 'Minuman': '☕',
  'Kecantikan': '💄', 'Kesehatan': '💊', 'Rumah Tangga': '🏠', 'Olahraga': '⚽',
  'Mainan': '🎮', 'Buku': '📚', 'Otomotif': '🚗', 'Aksesoris': '⌚',
};

export default function HomeScreen({ navigation }) {
  const { user, refreshCartCount } = useAuth();
  const toast = useToast();
  const [homeData, setHomeData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState(null);

  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loadingMore, setLoadingMore] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState('');
  const [bannerIndex, setBannerIndex] = useState(0);
  const bannerRef = useRef(null);
  const [flashTimeLeft, setFlashTimeLeft] = useState('');

  const fetchHomeData = useCallback(async () => {
    try {
      const response = await api.get('/api/home');
      if (response.data?.success) {
        setHomeData(response.data.data);
      }
    } catch (e) {
      // silent
    }
  }, []);

  const fetchCategories = useCallback(async () => {
    try {
      const response = await api.get('/api/categories');
      if (Array.isArray(response.data?.data)) {
        setCategories(response.data.data);
      }
    } catch (e) {
      // silent
    }
  }, []);

  const fetchProducts = useCallback(async (pageNum = 1, catSlug = '', isRefresh = false) => {
    try {
      const params = { per_page: 12, page: pageNum };
      if (catSlug) params.category = catSlug;
      const response = await api.get('/api/products', { params });
      const data = response.data?.data;
      if (data) {
        const items = data.data || [];
        if (pageNum === 1) {
          setProducts(items);
        } else {
          setProducts((prev) => [...prev, ...items]);
        }
        setPage(data.current_page || 1);
        setLastPage(data.last_page || 1);
      }
    } catch (e) {
      setError('Tidak bisa terhubung ke server.');
    } finally {
      setLoading(false);
      setRefreshing(false);
      setLoadingMore(false);
    }
  }, []);

  useEffect(() => {
    setLoading(true);
    fetchHomeData();
    fetchCategories();
    fetchProducts(1);
  }, []);

  useEffect(() => {
    if (!homeData?.flash_sale?.end_time) return;
    const interval = setInterval(() => {
      const end = new Date(homeData.flash_sale.end_time).getTime();
      const now = Date.now();
      const diff = end - now;
      if (diff <= 0) {
        setFlashTimeLeft('Selesai');
        clearInterval(interval);
        return;
      }
      const h = Math.floor(diff / 3600000);
      const m = Math.floor((diff % 3600000) / 60000);
      const s = Math.floor((diff % 60000) / 1000);
      setFlashTimeLeft(`${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`);
    }, 1000);
    return () => clearInterval(interval);
  }, [homeData?.flash_sale?.end_time]);

  // Auto-scroll banners
  useEffect(() => {
    const banners = homeData?.banners;
    if (!banners || banners.length <= 1) return;
    const interval = setInterval(() => {
      setBannerIndex((prev) => {
        const next = (prev + 1) % banners.length;
        bannerRef.current?.scrollToOffset({ offset: next * (BANNER_WIDTH + 12), animated: true });
        return next;
      });
    }, 4000);
    return () => clearInterval(interval);
  }, [homeData?.banners?.length]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchHomeData();
    fetchProducts(1, selectedCategory);
  };

  const loadMore = () => {
    if (page < lastPage && !loadingMore) {
      setLoadingMore(true);
      fetchProducts(page + 1, selectedCategory);
    }
  };

  const handleCategoryPress = (slug) => {
    setSelectedCategory(slug);
    setPage(1);
    setLoading(true);
    fetchProducts(1, slug);
  };

  const addToCart = async (product) => {
    if (!user) {
      toast('Silakan login untuk berbelanja', 'info');
      navigation.navigate('Login');
      return;
    }
    try {
      const response = await api.post(`/api/cart/add/${product.id}`, { quantity: 1 });
      if (response.data?.success) {
        toast(`${product.name.substring(0, 20)}... ditambahkan 🛒`, 'success');
        refreshCartCount();
      }
    } catch (e) {
      toast(e.response?.data?.message || 'Gagal', 'error');
    }
  };

  const formatPrice = (p) => `Rp${Number(p).toLocaleString('id-ID')}`;

  const renderProductCard = ({ item }) => (
    <TouchableOpacity
      style={styles.productCard}
      onPress={() => navigation.navigate('ProductDetail', { product: item })}
      activeOpacity={0.7}
    >
      <View style={styles.productImageWrap}>
        <Image
          source={{ uri: getImageUrl(item.image) || 'https://via.placeholder.com/200' }}
          style={styles.productImage}
          resizeMode="cover"
        />
        {item.flash_sale && (
          <View style={styles.flashBadge}>
            <Text style={styles.flashBadgeText}>-{item.flash_sale.discount_percent || Math.round((1 - (item.final_price || item.price) / item.price) * 100)}%</Text>
          </View>
        )}
        {item.featured && !item.flash_sale && (
          <View style={styles.featuredBadge}>
            <Text style={styles.featuredBadgeText}>⭐ Unggulan</Text>
          </View>
        )}
        <TouchableOpacity style={styles.quickCartBtn} onPress={() => addToCart(item)} activeOpacity={0.7}>
          <Text style={styles.quickCartBtnText}>🛒</Text>
        </TouchableOpacity>
      </View>
      <View style={styles.productInfo}>
        <Text style={styles.productName} numberOfLines={2}>{item.name}</Text>
        <Text style={styles.productPrice}>{formatPrice(item.final_price || item.price)}</Text>
        {item.compare_price && item.compare_price > (item.final_price || item.price) && (
          <Text style={styles.productCompare}>{formatPrice(item.compare_price)}</Text>
        )}
        <View style={styles.productMeta}>
          {item.rating > 0 && (
            <Text style={styles.productRating}>★ {Number(item.rating).toFixed(1)}</Text>
          )}
          {item.review_count > 0 && (
            <Text style={styles.productSold}>| {item.review_count} ulasan</Text>
          )}
        </View>
      </View>
    </TouchableOpacity>
  );

  const flashSaleProducts = homeData?.flash_sale?.products || [];
  const featuredProducts = homeData?.featured_products || [];
  const latestProducts = homeData?.latest_products || [];
  const bundles = homeData?.bundles || [];
  const banners = homeData?.banners || [];

  return (
    <View style={styles.container}>
      {/* Search Bar */}
      <TouchableOpacity
        style={styles.searchBar}
        onPress={() => navigation.navigate('Search')}
        activeOpacity={0.7}
      >
        <Text style={styles.searchIcon}>🔍</Text>
        <Text style={styles.searchPlaceholder}>Cari produk di Hello Store...</Text>
      </TouchableOpacity>

      <FlatList
        data={products}
        renderItem={renderProductCard}
        keyExtractor={(item) => String(item.id)}
        numColumns={2}
        columnWrapperStyle={styles.row}
        contentContainerStyle={styles.productList}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[COLORS.primary]} />
        }
        onEndReached={loadMore}
        onEndReachedThreshold={0.5}
        ListHeaderComponent={
          <View>
            {/* Banner Carousel */}
            {banners.length > 0 && (
              <View style={styles.bannerSection}>
                <FlatList
                  ref={bannerRef}
                  data={banners}
                  horizontal
                  pagingEnabled
                  showsHorizontalScrollIndicator={false}
                  keyExtractor={(item) => String(item.id)}
                  onMomentumScrollEnd={(e) => {
                    const idx = Math.round(e.nativeEvent.contentOffset.x / (BANNER_WIDTH + 12));
                    setBannerIndex(idx);
                  }}
                  renderItem={({ item }) => (
                    <TouchableOpacity activeOpacity={0.9} style={styles.bannerWrap}>
                      {item.image ? (
                        <Image source={{ uri: getImageUrl(item.image) }} style={styles.bannerImage} resizeMode="cover" />
                      ) : (
                        <View style={[styles.bannerImage, styles.bannerPlaceholder]}>
                          <Text style={styles.bannerPlaceholderTitle}>{item.title}</Text>
                          {item.description && <Text style={styles.bannerPlaceholderDesc}>{item.description}</Text>}
                        </View>
                      )}
                    </TouchableOpacity>
                  )}
                />
                {banners.length > 1 && (
                  <View style={styles.bannerDots}>
                    {banners.map((_, i) => (
                      <View key={i} style={[styles.bannerDot, i === bannerIndex && styles.bannerDotActive]} />
                    ))}
                  </View>
                )}
              </View>
            )}

            {/* Category Grid */}
            {categories.length > 0 && (
              <View style={styles.section}>
                <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.categoryScroll}>
                  <TouchableOpacity
                    style={[styles.categoryItem, !selectedCategory && styles.categoryItemActive]}
                    onPress={() => handleCategoryPress('')}
                    activeOpacity={0.7}
                  >
                    <View style={[styles.categoryIconWrap, !selectedCategory && styles.categoryIconWrapActive]}>
                      <Text style={styles.categoryEmoji}>🏪</Text>
                    </View>
                    <Text style={[styles.categoryLabel, !selectedCategory && styles.categoryLabelActive]} numberOfLines={1}>Semua</Text>
                  </TouchableOpacity>
                  {categories.map((cat) => (
                    <TouchableOpacity
                      key={cat.id}
                      style={[styles.categoryItem, selectedCategory === cat.slug && styles.categoryItemActive]}
                      onPress={() => handleCategoryPress(cat.slug)}
                      activeOpacity={0.7}
                    >
                      <View style={[styles.categoryIconWrap, selectedCategory === cat.slug && styles.categoryIconWrapActive]}>
                        <Text style={styles.categoryEmoji}>{CATEGORY_ICONS[cat.name] || '📦'}</Text>
                      </View>
                      <Text style={[styles.categoryLabel, selectedCategory === cat.slug && styles.categoryLabelActive]} numberOfLines={1}>{cat.name}</Text>
                    </TouchableOpacity>
                  ))}
                </ScrollView>
              </View>
            )}

            {/* Flash Sale */}
            {flashSaleProducts.length > 0 && (
              <View style={styles.section}>
                <View style={styles.sectionHeader}>
                  <View style={styles.sectionTitleRow}>
                    <Text style={styles.sectionEmoji}>⚡</Text>
                    <Text style={styles.sectionTitle}>Flash Sale</Text>
                  </View>
                  {flashTimeLeft && flashTimeLeft !== 'Selesai' && (
                    <View style={styles.countdownWrap}>
                      <Text style={styles.countdownLabel}>⏰</Text>
                      <Text style={styles.countdownText}>{flashTimeLeft}</Text>
                    </View>
                  )}
                </View>
                <FlatList
                  data={flashSaleProducts}
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  keyExtractor={(item) => String(item.id)}
                  contentContainerStyle={styles.flashList}
                  renderItem={({ item }) => (
                    <TouchableOpacity
                      style={styles.flashCard}
                      onPress={() => navigation.navigate('ProductDetail', { product: item })}
                      activeOpacity={0.7}
                    >
                      <Image
                        source={{ uri: getImageUrl(item.image) || 'https://via.placeholder.com/120' }}
                        style={styles.flashImage}
                        resizeMode="cover"
                      />
                      <Text style={styles.flashPrice}>{item.flash_price_formatted}</Text>
                      <Text style={styles.flashOriginal}>{item.price_formatted}</Text>
                      <View style={styles.flashDiscountWrap}>
                        <Text style={styles.flashDiscount}>-{item.discount_percent}%</Text>
                      </View>
                    </TouchableOpacity>
                  )}
                />
              </View>
            )}

            {/* Bundles */}
            {bundles.length > 0 && (
              <View style={styles.section}>
                <View style={styles.sectionHeader}>
                  <View style={styles.sectionTitleRow}>
                    <Text style={styles.sectionEmoji}>🎁</Text>
                    <Text style={styles.sectionTitle}>Paket Hemat</Text>
                  </View>
                </View>
                {bundles.map((bundle) => (
                  <TouchableOpacity key={bundle.id} style={styles.bundleCard} activeOpacity={0.7}>
                    <View style={styles.bundleInfo}>
                      <Text style={styles.bundleName} numberOfLines={1}>{bundle.name}</Text>
                      <Text style={styles.bundleDesc} numberOfLines={1}>{bundle.product_count} produk</Text>
                      <View style={styles.bundlePriceRow}>
                        <Text style={styles.bundlePrice}>{bundle.bundle_price_formatted}</Text>
                        <Text style={styles.bundleOriginal}>{bundle.total_original_price_formatted}</Text>
                      </View>
                      <View style={styles.bundleSavings}>
                        <Text style={styles.bundleSavingsText}>Hemat {bundle.savings_formatted}</Text>
                      </View>
                    </View>
                  </TouchableOpacity>
                ))}
              </View>
            )}

            {/* Featured Section Header */}
            {featuredProducts.length > 0 && (
              <View style={styles.section}>
                <View style={styles.sectionHeader}>
                  <View style={styles.sectionTitleRow}>
                    <Text style={styles.sectionEmoji}>⭐</Text>
                    <Text style={styles.sectionTitle}>Produk Unggulan</Text>
                  </View>
                </View>
              </View>
            )}

            {/* Featured Products Horizontal */}
            {featuredProducts.length > 0 && (
              <FlatList
                data={featuredProducts}
                horizontal
                showsHorizontalScrollIndicator={false}
                keyExtractor={(item) => 'feat-' + String(item.id)}
                contentContainerStyle={styles.featuredList}
                renderItem={({ item }) => (
                  <TouchableOpacity
                    style={styles.featuredCard}
                    onPress={() => navigation.navigate('ProductDetail', { product: item })}
                    activeOpacity={0.7}
                  >
                    <Image
                      source={{ uri: getImageUrl(item.image) || 'https://via.placeholder.com/120' }}
                      style={styles.featuredImage}
                      resizeMode="cover"
                    />
                    <Text style={styles.featuredName} numberOfLines={2}>{item.name}</Text>
                    <Text style={styles.featuredPrice}>{formatPrice(item.price)}</Text>
                    {item.rating > 0 && <Text style={styles.featuredRating}>★ {Number(item.rating).toFixed(1)}</Text>}
                  </TouchableOpacity>
                )}
              />
            )}

            {/* All Products Section Header */}
            <View style={[styles.section, { marginTop: 8 }]}>
              <View style={styles.sectionHeader}>
                <View style={styles.sectionTitleRow}>
                  <Text style={styles.sectionEmoji}>🛒</Text>
                  <Text style={styles.sectionTitle}>Semua Produk</Text>
                </View>
              </View>
            </View>
          </View>
        }
        ListFooterComponent={
          loadingMore ? <ActivityIndicator style={{ padding: 16 }} color={COLORS.primary} /> : null
        }
        ListEmptyComponent={
          loading ? (
            <View style={styles.center}>
              <ActivityIndicator size="large" color={COLORS.primary} />
            </View>
          ) : (
            <View style={styles.center}>
              <Text style={styles.emptyIcon}>📦</Text>
              <Text style={styles.emptyText}>{error || 'Tidak ada produk ditemukan.'}</Text>
            </View>
          )
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.background },
  searchBar: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.white,
    marginHorizontal: 12, marginTop: 8, marginBottom: 4, borderRadius: 12,
    paddingHorizontal: 14, paddingVertical: 12, borderWidth: 1, borderColor: COLORS.border,
    elevation: 1,
  },
  searchIcon: { fontSize: 16, marginRight: 10 },
  searchPlaceholder: { fontSize: 14, color: COLORS.textLight },

  /* Banner */
  bannerSection: { marginTop: 8, marginBottom: 4 },
  bannerWrap: { width: BANNER_WIDTH, marginHorizontal: 6 },
  bannerImage: { width: '100%', height: 160, borderRadius: 14, backgroundColor: COLORS.border },
  bannerPlaceholder: {
    justifyContent: 'center', alignItems: 'center', padding: 20,
    backgroundColor: COLORS.primary + '15',
  },
  bannerPlaceholderTitle: { fontSize: 18, fontWeight: '700', color: COLORS.text, marginBottom: 4 },
  bannerPlaceholderDesc: { fontSize: 13, color: COLORS.textSecondary },
  bannerDots: { flexDirection: 'row', justifyContent: 'center', marginTop: 8 },
  bannerDot: { width: 6, height: 6, borderRadius: 3, backgroundColor: COLORS.border, marginHorizontal: 3 },
  bannerDotActive: { backgroundColor: COLORS.primary, width: 16 },

  /* Categories */
  section: { marginTop: 12 },
  sectionHeader: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
    paddingHorizontal: 12, marginBottom: 10,
  },
  sectionTitleRow: { flexDirection: 'row', alignItems: 'center' },
  sectionEmoji: { fontSize: 18, marginRight: 6 },
  sectionTitle: { fontSize: 17, fontWeight: '700', color: COLORS.text },

  categoryScroll: { paddingHorizontal: 12 },
  categoryItem: { alignItems: 'center', marginRight: 14, width: 60 },
  categoryItemActive: {},
  categoryIconWrap: {
    width: 52, height: 52, borderRadius: 26, backgroundColor: COLORS.white,
    justifyContent: 'center', alignItems: 'center', marginBottom: 6,
    borderWidth: 1.5, borderColor: COLORS.border,
    elevation: 1, shadowColor: '#000', shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.08, shadowRadius: 3,
  },
  categoryIconWrapActive: { borderColor: COLORS.primary, backgroundColor: '#FEF3C7' },
  categoryEmoji: { fontSize: 24 },
  categoryLabel: { fontSize: 10, color: COLORS.textSecondary, textAlign: 'center' },
  categoryLabelActive: { color: COLORS.primary, fontWeight: '600' },

  /* Flash Sale */
  countdownWrap: { flexDirection: 'row', alignItems: 'center' },
  countdownLabel: { fontSize: 14, marginRight: 4 },
  countdownText: {
    backgroundColor: '#DC2626', color: '#fff', fontSize: 12, fontWeight: '700',
    paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6,
    letterSpacing: 1,
  },
  flashList: { paddingHorizontal: 12 },
  flashCard: {
    width: 130, marginRight: 10, backgroundColor: COLORS.white, borderRadius: 12,
    overflow: 'hidden', elevation: 1, shadowColor: '#000', shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.08, shadowRadius: 3,
  },
  flashImage: { width: 130, height: 130, backgroundColor: COLORS.border },
  flashPrice: { fontSize: 14, fontWeight: '700', color: '#DC2626', paddingHorizontal: 8, paddingTop: 6 },
  flashOriginal: {
    fontSize: 11, color: COLORS.textLight, textDecorationLine: 'line-through',
    paddingHorizontal: 8,
  },
  flashDiscountWrap: {
    position: 'absolute', top: 6, left: 6, backgroundColor: '#DC2626',
    borderRadius: 6, paddingHorizontal: 6, paddingVertical: 2,
  },
  flashDiscountText: { color: '#fff', fontSize: 10, fontWeight: '700' },
  flashDiscount: { color: '#fff', fontSize: 10, fontWeight: '700' },

  /* Bundles */
  bundleCard: {
    flexDirection: 'row', backgroundColor: COLORS.white, borderRadius: 12,
    marginHorizontal: 12, marginBottom: 8, padding: 14, alignItems: 'center',
    elevation: 1, borderWidth: 1, borderColor: '#FEF3C7',
  },
  bundleInfo: { flex: 1 },
  bundleName: { fontSize: 15, fontWeight: '700', color: COLORS.text, marginBottom: 2 },
  bundleDesc: { fontSize: 12, color: COLORS.textSecondary, marginBottom: 6 },
  bundlePriceRow: { flexDirection: 'row', alignItems: 'center' },
  bundlePrice: { fontSize: 16, fontWeight: '700', color: COLORS.primary, marginRight: 8 },
  bundleOriginal: { fontSize: 12, color: COLORS.textLight, textDecorationLine: 'line-through' },
  bundleSavings: { marginTop: 4 },
  bundleSavingsText: { fontSize: 11, color: '#DC2626', fontWeight: '600' },

  /* Featured Horizontal */
  featuredList: { paddingHorizontal: 12 },
  featuredCard: {
    width: 130, marginRight: 10, backgroundColor: COLORS.white, borderRadius: 12,
    overflow: 'hidden', elevation: 1,
  },
  featuredImage: { width: 130, height: 130, backgroundColor: COLORS.border },
  featuredName: { fontSize: 12, fontWeight: '600', color: COLORS.text, paddingHorizontal: 8, paddingTop: 6 },
  featuredPrice: { fontSize: 14, fontWeight: '700', color: COLORS.primary, paddingHorizontal: 8, marginTop: 2 },
  featuredRating: { fontSize: 11, color: '#F59E0B', paddingHorizontal: 8, paddingBottom: 8, marginTop: 2 },

  /* Product Grid */
  productList: { paddingHorizontal: 6, paddingBottom: 20 },
  row: { justifyContent: 'space-between', paddingHorizontal: 6 },
  productCard: {
    backgroundColor: COLORS.white, borderRadius: 12, marginBottom: 10,
    width: '48%', overflow: 'hidden', elevation: 2, shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 }, shadowOpacity: 0.1, shadowRadius: 4,
  },
  productImageWrap: { position: 'relative' },
  productImage: { width: '100%', height: 160, backgroundColor: COLORS.border },
  quickCartBtn: {
    position: 'absolute', bottom: 6, right: 6, width: 32, height: 32,
    borderRadius: 16, backgroundColor: COLORS.primary, justifyContent: 'center',
    alignItems: 'center', elevation: 3,
  },
  quickCartBtnText: { fontSize: 16 },
  flashBadge: {
    position: 'absolute', top: 6, left: 6, backgroundColor: '#DC2626',
    borderRadius: 6, paddingHorizontal: 6, paddingVertical: 2,
  },
  flashBadgeText: { color: '#fff', fontSize: 10, fontWeight: '700' },
  featuredBadge: {
    position: 'absolute', top: 6, left: 6, backgroundColor: COLORS.primary,
    borderRadius: 6, paddingHorizontal: 6, paddingVertical: 2,
  },
  featuredBadgeText: { color: '#fff', fontSize: 10, fontWeight: '600' },
  productInfo: { padding: 10 },
  productName: { fontSize: 12, fontWeight: '600', color: COLORS.text, marginBottom: 4, lineHeight: 16 },
  productPrice: { fontSize: 14, fontWeight: '700', color: COLORS.primary },
  productCompare: {
    fontSize: 11, color: COLORS.textLight, textDecorationLine: 'line-through', marginTop: 2,
  },
  productMeta: { flexDirection: 'row', alignItems: 'center', marginTop: 4 },
  productRating: { fontSize: 11, color: '#F59E0B', fontWeight: '500' },
  productSold: { fontSize: 11, color: COLORS.textSecondary, marginLeft: 4 },

  center: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
  emptyIcon: { fontSize: 48, marginBottom: 12 },
  emptyText: { fontSize: 14, color: COLORS.textSecondary, textAlign: 'center' },
});
