import React, { useState, useEffect } from 'react';
import {
  View, Text, Image, ScrollView, TouchableOpacity,
  StyleSheet, ActivityIndicator, TextInput, Share,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '../context/AuthContext';
import { useAlert } from '../context/AlertContext';
import { useToast } from '../components/Toast';
import api from '../api/client';
import { COLORS, getImageUrl } from '../config';
import { formatPrice } from '../utils';

export default function ProductDetailScreen({ route, navigation }) {
  const insets = useSafeAreaInsets();
  const { user, refreshCartCount } = useAuth();
  const { showAlert } = useAlert();
  const toast = useToast();
  const { product: initialProduct } = route.params;
  const [product, setProduct] = useState(initialProduct);
  const [loading, setLoading] = useState(false);
  const [qty, setQty] = useState(1);
  const [selectedVariant, setSelectedVariant] = useState(null);
  const [selectedImage, setSelectedImage] = useState(0);
  const [isWished, setIsWished] = useState(false);
  const [wishLoading, setWishLoading] = useState(false);
  const [reviewRating, setReviewRating] = useState(0);
  const [reviewComment, setReviewComment] = useState('');
  const [reviewSubmitting, setReviewSubmitting] = useState(false);
  const [descExpanded, setDescExpanded] = useState(false);
  const [descOverflow, setDescOverflow] = useState(false);

  useEffect(() => {
    fetchDetail();
  }, []);

  const fetchDetail = async () => {
    setLoading(true);
    try {
      const response = await api.get(`/api/products/${initialProduct.id}`);
      if (response.data?.success) {
        setProduct(response.data.data);
        if (response.data.data?.is_wished !== undefined) {
          setIsWished(response.data.data.is_wished);
        }
      }
    } catch (e) {
      showAlert({ title: 'Error', message: 'Gagal memuat detail produk.', type: 'error' });
    } finally {
      setLoading(false);
    }
  };

  const getPrice = () => {
    if (selectedVariant) return selectedVariant.price;
    return product.final_price ?? product.price;
  };

  const getStock = () => {
    if (selectedVariant) return selectedVariant.stock;
    return product.stock;
  };

  const toggleWishlist = async () => {
    if (!user) {
      showAlert({
        title: 'Login Diperlukan',
        message: 'Silakan login untuk menambahkan ke wishlist.',
        type: 'warning',
        buttons: [
          { text: 'Batal', style: 'cancel' },
          { text: 'Login', onPress: () => navigation.navigate('Login') },
        ],
      });
      return;
    }
    setWishLoading(true);
    try {
      const response = await api.post(`/api/wishlist/toggle/${product.id}`);
      if (response.data?.success) {
        setIsWished((prev) => !prev);
        showAlert({ title: 'Berhasil', message: response.data.message || (isWished ? 'Dihapus dari wishlist' : 'Ditambahkan ke wishlist'), type: 'success' });
      }
    } catch (e) {
      showAlert({ title: 'Error', message: 'Gagal memperbarui wishlist.', type: 'error' });
    } finally {
      setWishLoading(false);
    }
  };

  const submitReview = async () => {
    if (!user) {
      showAlert({ title: 'Login Diperlukan', message: 'Silakan login untuk memberikan ulasan.', type: 'warning' });
      return;
    }
    if (reviewRating === 0) {
      showAlert({ title: 'Rating Diperlukan', message: 'Pilih rating bintang terlebih dahulu.', type: 'warning' });
      return;
    }
    setReviewSubmitting(true);
    try {
      const response = await api.post(`/api/products/${product.id}/review`, {
        rating: reviewRating,
        comment: reviewComment.trim(),
      });
      if (response.data?.success) {
        showAlert({ title: 'Berhasil', message: 'Ulasan berhasil dikirim.', type: 'success' });
        setReviewRating(0);
        setReviewComment('');
        fetchDetail();
      }
    } catch (e) {
      const msg = e.response?.data?.message || 'Gagal mengirim ulasan.';
      showAlert({ title: 'Error', message: msg, type: 'error' });
    } finally {
      setReviewSubmitting(false);
    }
  };

  const addToCart = async (goToCheckout = false) => {
    if (!user) {
      showAlert({
        title: 'Login Diperlukan',
        message: 'Silakan login terlebih dahulu untuk menambahkan produk ke keranjang.',
        type: 'warning',
        buttons: [
          { text: 'Batal', style: 'cancel' },
          { text: 'Login', onPress: () => navigation.navigate('Login') },
        ],
      });
      return;
    }
    try {
      const payload = { quantity: qty };
      if (selectedVariant) payload.variant_id = selectedVariant.id;

      const response = await api.post(`/api/cart/add/${product.id}`, payload);
      if (response.data?.success) {
        toast(response.data.message || 'Ditambahkan ke keranjang 🛒', 'success');
        refreshCartCount();
        if (goToCheckout) {
          navigation.navigate('Checkout');
        } else {
          navigation.navigate('Cart');
        }
      }
    } catch (e) {
      const msg = e.response?.data?.message || 'Gagal menambahkan ke keranjang.';
      toast(msg, 'error');
    }
  };

  const handleShare = async () => {
    const url = `https://hellostore.test/product/${product.slug || product.id}`;
    try {
      await Share.share({
        message: `Cek produk ini di Hello Store: ${product.name}\n${url}`,
        url,
        title: product.name,
      });
    } catch (e) {
      // silent
    }
  };

  const images = product.images?.length ? product.images : [{ url: product.image }];
  const description = (product.description || '').replace(/<[^>]*>/g, '');
  const sold = product.total_sold || 0;

  return (
    <View style={styles.root}>
      <ScrollView style={styles.container} contentContainerStyle={{ paddingBottom: 90 }}>
        {loading && product.id === initialProduct.id ? (
          <ActivityIndicator size="large" color={COLORS.primary} style={{ marginTop: 40 }} />
        ) : (
          <>
            <Image
              source={{ uri: getImageUrl(images[selectedImage]?.url || product.image) }}
              style={styles.mainImage}
              resizeMode="contain"
            />
            {images.length > 1 && (
              <ScrollView horizontal style={styles.thumbnails}>
                {images.map((img, idx) => (
                  <TouchableOpacity key={idx} onPress={() => setSelectedImage(idx)}>
                    <Image
                      source={{ uri: getImageUrl(img.url) }}
                      style={[
                        styles.thumb,
                        idx === selectedImage && styles.thumbActive,
                      ]}
                    />
                  </TouchableOpacity>
                ))}
              </ScrollView>
            )}

            <View style={styles.info}>
              <View style={styles.nameRow}>
                <Text style={styles.name} numberOfLines={2}>{product.name}</Text>
                <View style={styles.actionRow}>
                  <TouchableOpacity style={styles.shareBtn} onPress={handleShare}>
                    <Text style={styles.shareIcon}>↗</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={styles.wishlistBtn}
                    onPress={toggleWishlist}
                    disabled={wishLoading}
                  >
                    <Text style={styles.wishlistIcon}>{isWished ? '♥' : '♡'}</Text>
                  </TouchableOpacity>
                </View>
              </View>
              {product.category && (
                <Text style={styles.category}>{product.category}</Text>
              )}
              <Text style={styles.price}>{formatPrice(getPrice())}</Text>
              {product.compare_price && product.compare_price > getPrice() && (
                <Text style={styles.compare}>{formatPrice(product.compare_price)}</Text>
              )}

              <View style={styles.soldRow}>
                <View style={styles.stockRow}>
                  <Text style={styles.stockLabel}>Stok: </Text>
                  <Text style={[styles.stockValue, getStock() <= 5 && { color: COLORS.error }]}>
                    {getStock()}
                  </Text>
                </View>
                {sold > 0 && (
                  <Text style={styles.soldText}>{sold} terjual</Text>
                )}
              </View>

              {product.variants?.length > 0 && (
                <View style={styles.variantSection}>
                  <Text style={styles.sectionTitle}>Varian</Text>
                  <View style={styles.variantList}>
                    {product.variants.map((v) => (
                      <TouchableOpacity
                        key={v.id}
                        style={[
                          styles.variantChip,
                          selectedVariant?.id === v.id && styles.variantChipActive,
                        ]}
                        onPress={() => setSelectedVariant(selectedVariant?.id === v.id ? null : v)}
                      >
                        <Text
                          style={[
                            styles.variantText,
                            selectedVariant?.id === v.id && styles.variantTextActive,
                          ]}
                        >
                          {v.name}
                        </Text>
                      </TouchableOpacity>
                    ))}
                  </View>
                </View>
              )}

              {description ? (
                <View style={styles.description}>
                  <Text style={styles.sectionTitle}>Deskripsi</Text>
                  <Text
                    style={styles.descriptionText}
                    numberOfLines={descExpanded ? undefined : 4}
                    onTextLayout={(e) => {
                      if (!descOverflow && e.nativeEvent.lines.length > 4) {
                        setDescOverflow(true);
                      }
                    }}
                  >
                    {description}
                  </Text>
                  {descOverflow && (
                    <TouchableOpacity onPress={() => setDescExpanded(!descExpanded)}>
                      <Text style={styles.expandBtn}>
                        {descExpanded ? 'Tutup' : 'Lihat Selengkapnya'}
                      </Text>
                    </TouchableOpacity>
                  )}
                </View>
              ) : null}

              <View style={styles.reviews}>
                <Text style={styles.sectionTitle}>
                  Ulasan ({product.review_stats?.total || product.reviews?.length || 0})
                </Text>
                {product.reviews?.slice(0, 3).map((review) => (
                  <View key={review.id} style={styles.reviewItem}>
                    <View style={styles.reviewHeader}>
                      <Text style={styles.reviewUser}>{review.user_name}</Text>
                      <Text style={styles.reviewRating}>{'★'.repeat(review.rating)}{'☆'.repeat(5 - review.rating)}</Text>
                    </View>
                    <Text style={styles.reviewComment}>{review.comment}</Text>
                  </View>
                ))}
                {(!product.reviews || product.reviews.length === 0) && (
                  <Text style={styles.emptyReview}>Belum ada ulasan.</Text>
                )}
              </View>

              {user && (
                <View style={styles.reviewForm}>
                  <Text style={styles.sectionTitle}>Tulis Ulasan</Text>
                  <Text style={styles.reviewFormLabel}>Rating</Text>
                  <View style={styles.starPicker}>
                    {[1, 2, 3, 4, 5].map((star) => (
                      <TouchableOpacity key={star} onPress={() => setReviewRating(star)}>
                        <Text style={[styles.star, star <= reviewRating && styles.starActive]}>
                          {star <= reviewRating ? '★' : '☆'}
                        </Text>
                      </TouchableOpacity>
                    ))}
                  </View>
                  <TextInput
                    style={styles.reviewInput}
                    placeholder="Tulis komentar (opsional)..."
                    placeholderTextColor={COLORS.textLight}
                    value={reviewComment}
                    onChangeText={setReviewComment}
                    multiline
                    numberOfLines={3}
                    textAlignVertical="top"
                  />
                  <TouchableOpacity
                    style={[styles.submitBtn, reviewSubmitting && styles.submitBtnDisabled]}
                    onPress={submitReview}
                    disabled={reviewSubmitting}
                  >
                    <Text style={styles.submitBtnText}>
                      {reviewSubmitting ? 'Mengirim...' : 'Kirim Ulasan'}
                    </Text>
                  </TouchableOpacity>
                </View>
              )}
            </View>
          </>
        )}
      </ScrollView>

      {/* Sticky Bottom Bar */}
      <View style={[styles.bottomBar, { paddingBottom: Math.max(insets.bottom, 12) }]}>
        <View style={styles.qtyControl}>
          <TouchableOpacity
            style={styles.qtyBtn}
            onPress={() => setQty(Math.max(1, qty - 1))}
          >
            <Text style={styles.qtyBtnText}>-</Text>
          </TouchableOpacity>
          <Text style={styles.qtyValue}>{qty}</Text>
          <TouchableOpacity
            style={styles.qtyBtn}
            onPress={() => setQty(Math.min(getStock(), qty + 1))}
          >
            <Text style={styles.qtyBtnText}>+</Text>
          </TouchableOpacity>
        </View>
        <TouchableOpacity style={styles.addBtn} onPress={() => addToCart(false)}>
          <Text style={styles.addBtnText}>+ Keranjang</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.buyNowBtn} onPress={() => addToCart(true)}>
          <Text style={styles.buyNowText}>Beli</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.white },
  container: { flex: 1, backgroundColor: COLORS.white },
  mainImage: { width: '100%', height: 350, backgroundColor: '#F9FAFB' },
  thumbnails: { paddingHorizontal: 12, paddingVertical: 8 },
  thumb: { width: 60, height: 60, borderRadius: 8, marginRight: 8, borderWidth: 2, borderColor: 'transparent' },
  thumbActive: { borderColor: COLORS.primary },
  info: { padding: 16 },
  nameRow: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between' },
  name: { fontSize: 20, fontWeight: '700', color: COLORS.text, marginBottom: 4, flex: 1, marginRight: 8 },
  actionRow: { flexDirection: 'row', alignItems: 'center' },
  shareBtn: { padding: 6, marginRight: 4 },
  shareIcon: { fontSize: 20, color: COLORS.textSecondary },
  wishlistBtn: { padding: 4 },
  wishlistIcon: { fontSize: 24, color: COLORS.error },
  category: { fontSize: 13, color: COLORS.textSecondary, marginBottom: 8 },
  price: { fontSize: 22, fontWeight: '700', color: COLORS.primary },
  compare: { fontSize: 14, color: COLORS.textLight, textDecorationLine: 'line-through', marginTop: 2 },
  soldRow: { flexDirection: 'row', alignItems: 'center', marginTop: 8 },
  stockRow: { flexDirection: 'row', alignItems: 'center' },
  stockLabel: { fontSize: 14, color: COLORS.textSecondary },
  stockValue: { fontSize: 14, fontWeight: '600', color: COLORS.success },
  soldText: { fontSize: 13, color: COLORS.textSecondary, marginLeft: 12 },
  variantSection: { marginTop: 16 },
  sectionTitle: { fontSize: 16, fontWeight: '600', color: COLORS.text, marginBottom: 8 },
  variantList: { flexDirection: 'row', flexWrap: 'wrap' },
  variantChip: {
    paddingHorizontal: 16, paddingVertical: 8, borderRadius: 20,
    borderWidth: 1, borderColor: COLORS.border, marginRight: 8, marginBottom: 8,
  },
  variantChipActive: { borderColor: COLORS.primary, backgroundColor: '#FEF3C7' },
  variantText: { fontSize: 13, color: COLORS.text },
  variantTextActive: { color: COLORS.primary },
  description: { marginTop: 16 },
  descriptionText: { fontSize: 14, color: COLORS.textSecondary, lineHeight: 20 },
  expandBtn: { fontSize: 14, color: COLORS.primary, fontWeight: '600', marginTop: 6 },
  reviews: { marginTop: 16 },
  emptyReview: { fontSize: 13, color: COLORS.textLight, fontStyle: 'italic' },
  reviewItem: { marginBottom: 12, paddingBottom: 12, borderBottomWidth: 1, borderBottomColor: COLORS.border },
  reviewHeader: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 4 },
  reviewUser: { fontSize: 14, fontWeight: '600', color: COLORS.text },
  reviewRating: { color: '#F59E0B', fontSize: 14 },
  reviewComment: { fontSize: 13, color: COLORS.textSecondary },
  reviewForm: { marginTop: 20, paddingTop: 16, borderTopWidth: 1, borderTopColor: COLORS.border },
  reviewFormLabel: { fontSize: 13, color: COLORS.textSecondary, marginBottom: 6 },
  starPicker: { flexDirection: 'row', marginBottom: 12 },
  star: { fontSize: 30, color: COLORS.border, marginRight: 6 },
  starActive: { color: '#F59E0B' },
  reviewInput: {
    borderWidth: 1, borderColor: COLORS.border, borderRadius: 10,
    padding: 12, fontSize: 14, color: COLORS.text, minHeight: 80,
    backgroundColor: COLORS.background, marginBottom: 12,
  },
  submitBtn: {
    backgroundColor: COLORS.primary, borderRadius: 10,
    paddingVertical: 12, alignItems: 'center',
  },
  submitBtnDisabled: { opacity: 0.6 },
  submitBtnText: { color: '#fff', fontSize: 15, fontWeight: '600' },
  bottomBar: {
    flexDirection: 'row', alignItems: 'center', padding: 12,
    borderTopWidth: 1, borderTopColor: COLORS.border, backgroundColor: COLORS.white,
    position: 'absolute', bottom: 0, left: 0, right: 0,
    elevation: 8, shadowColor: '#000', shadowOffset: { width: 0, height: -2 },
    shadowOpacity: 0.1, shadowRadius: 4,
  },
  qtyControl: { flexDirection: 'row', alignItems: 'center', marginRight: 10 },
  qtyBtn: {
    width: 34, height: 34, borderRadius: 17, backgroundColor: COLORS.background,
    justifyContent: 'center', alignItems: 'center',
  },
  qtyBtnText: { fontSize: 18, fontWeight: '600', color: COLORS.text },
  qtyValue: { fontSize: 16, fontWeight: '600', marginHorizontal: 12, color: COLORS.text },
  addBtn: {
    flex: 1, backgroundColor: '#FEF3C7', borderRadius: 12,
    paddingVertical: 12, alignItems: 'center', borderWidth: 1, borderColor: COLORS.primary,
  },
  addBtnText: { color: COLORS.primary, fontSize: 14, fontWeight: '600' },
  buyNowBtn: {
    flex: 1, backgroundColor: COLORS.primary, borderRadius: 12,
    paddingVertical: 12, alignItems: 'center', marginLeft: 8,
  },
  buyNowText: { color: '#fff', fontSize: 14, fontWeight: '600' },
});
