import React, { useState, useEffect } from 'react';
import {
  View, Text, Image, TouchableOpacity, Modal, StyleSheet,
  TouchableWithoutFeedback, Dimensions,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { COLORS, getImageUrl } from '../config';

const { width: SCREEN_WIDTH } = Dimensions.get('window');

export default function PromoPopup({ popup }) {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    if (!popup?.id) return;
    checkDismissed();
  }, [popup?.id]);

  const checkDismissed = async () => {
    try {
      const dismissed = await AsyncStorage.getItem(`popup_dismissed_${popup.id}`);
      if (!dismissed) {
        setVisible(true);
      }
    } catch {
      setVisible(true);
    }
  };

  const dismiss = async () => {
    setVisible(false);
    try {
      await AsyncStorage.setItem(`popup_dismissed_${popup.id}`, '1');
    } catch {}
  };

  if (!popup) return null;

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={dismiss}>
      <TouchableWithoutFeedback onPress={dismiss}>
        <View style={styles.overlay}>
          <TouchableWithoutFeedback>
            <View style={styles.card}>
              {popup.image && (
                <Image
                  source={{ uri: getImageUrl(popup.image) }}
                  style={styles.image}
                  resizeMode="contain"
                />
              )}
              <View style={styles.content}>
                {popup.title && <Text style={styles.title}>{popup.title}</Text>}
                {popup.description && <Text style={styles.desc}>{popup.description}</Text>}
                <View style={styles.actions}>
                  {popup.link && popup.link_label && (
                    <TouchableOpacity style={styles.linkBtn} onPress={dismiss}>
                      <Text style={styles.linkBtnText}>{popup.link_label}</Text>
                    </TouchableOpacity>
                  )}
                  <TouchableOpacity onPress={dismiss}>
                    <Text style={styles.closeText}>Tutup</Text>
                  </TouchableOpacity>
                </View>
              </View>
            </View>
          </TouchableWithoutFeedback>
        </View>
      </TouchableWithoutFeedback>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.3)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  card: {
    width: Math.min(SCREEN_WIDTH - 40, 400),
    backgroundColor: 'transparent',
    borderRadius: 16,
    overflow: 'hidden',
    elevation: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.2,
    shadowRadius: 12,
  },
  image: {
    width: '100%',
    height: 220,
    borderRadius: 16,
  },
  content: {
    padding: 16,
    paddingTop: 8,
    alignItems: 'center',
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: COLORS.text,
    marginBottom: 8,
  },
  desc: {
    fontSize: 13,
    color: COLORS.textSecondary,
    lineHeight: 20,
    marginBottom: 16,
  },
  actions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
  },
  linkBtn: {
    backgroundColor: COLORS.primary,
    borderRadius: 10,
    paddingHorizontal: 20,
    paddingVertical: 10,
  },
  linkBtnText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  closeText: {
    fontSize: 13,
    color: COLORS.textSecondary,
    fontWeight: '500',
  },
});
