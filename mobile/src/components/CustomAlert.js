import React, { useEffect, useRef } from 'react';
import {
  View, Text, Modal, TouchableOpacity, StyleSheet, Animated,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const ICONS = {
  success: '✓',
  error: '✕',
  warning: '!',
  info: 'i',
};

const ICON_COLORS = {
  success: '#10B981',
  error: '#EF4444',
  warning: '#F59E0B',
  info: '#3B82F6',
};

const ICON_BG = {
  success: '#ECFDF5',
  error: '#FEF2F2',
  warning: '#FEF3C7',
  info: '#EFF6FF',
};

export default function CustomAlert({ visible, title, message, type = 'info', buttons = [], onClose }) {
  const scaleAnim = useRef(new Animated.Value(0.85)).current;
  const opacityAnim = useRef(new Animated.Value(0)).current;
  const insets = useSafeAreaInsets();

  useEffect(() => {
    if (visible) {
      scaleAnim.setValue(0.85);
      opacityAnim.setValue(0);
      Animated.parallel([
        Animated.spring(scaleAnim, { toValue: 1, friction: 7, tension: 80, useNativeDriver: true }),
        Animated.timing(opacityAnim, { toValue: 1, duration: 200, useNativeDriver: true }),
      ]).start();
    }
  }, [visible]);

  const handleClose = () => {
    Animated.parallel([
      Animated.timing(scaleAnim, { toValue: 0.85, duration: 150, useNativeDriver: true }),
      Animated.timing(opacityAnim, { toValue: 0, duration: 150, useNativeDriver: true }),
    ]).start(() => {
      if (onClose) onClose();
    });
  };

  const handlePress = (btn) => {
    handleClose();
    if (btn.onPress) {
      setTimeout(btn.onPress, 180);
    }
  };

  const icon = ICONS[type] || ICONS.info;
  const iconColor = ICON_COLORS[type] || ICON_COLORS.info;
  const iconBg = ICON_BG[type] || ICON_BG.info;

  return (
    <Modal visible={visible} transparent animationType="none" statusBarTranslucent>
      <Animated.View style={[styles.overlay, { opacity: opacityAnim, paddingBottom: insets.bottom + 20 }]}>
        <Animated.View style={[styles.card, { transform: [{ scale: scaleAnim }] }]}>
          <View style={[styles.iconCircle, { backgroundColor: iconBg }]}>
            <View style={[styles.iconBadge, { backgroundColor: iconColor }]}>
              <Text style={styles.iconText}>{icon}</Text>
            </View>
          </View>

          {title ? <Text style={styles.title}>{title}</Text> : null}
          {message ? <Text style={styles.message}>{message}</Text> : null}

          <View style={[styles.buttonRow, buttons.length > 2 && { flexDirection: 'column' }]}>
            {buttons.map((btn, idx) => {
              const isCancel = btn.style === 'cancel';
              const isDestructive = btn.style === 'destructive';
              return (
                <TouchableOpacity
                  key={idx}
                  style={[
                    styles.button,
                    isCancel && styles.buttonCancel,
                    isDestructive && styles.buttonDestructive,
                    !isCancel && !isDestructive && styles.buttonPrimary,
                    buttons.length > 2 && { marginBottom: 8 },
                  ]}
                  onPress={() => handlePress(btn)}
                  activeOpacity={0.7}
                >
                  <Text style={[
                    styles.buttonText,
                    isCancel && styles.buttonTextCancel,
                    isDestructive && styles.buttonTextDestructive,
                    !isCancel && !isDestructive && styles.buttonTextPrimary,
                  ]}>
                    {btn.text}
                  </Text>
                </TouchableOpacity>
              );
            })}
          </View>
        </Animated.View>
      </Animated.View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.45)',
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 32,
  },
  card: {
    backgroundColor: '#FFFFFF',
    borderRadius: 20,
    paddingVertical: 28,
    paddingHorizontal: 24,
    width: '100%',
    alignItems: 'center',
    elevation: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.18,
    shadowRadius: 24,
  },
  iconCircle: {
    width: 64,
    height: 64,
    borderRadius: 32,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  iconBadge: {
    width: 44,
    height: 44,
    borderRadius: 22,
    justifyContent: 'center',
    alignItems: 'center',
  },
  iconText: {
    color: '#FFFFFF',
    fontSize: 22,
    fontWeight: '800',
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111827',
    textAlign: 'center',
    marginBottom: 8,
  },
  message: {
    fontSize: 14,
    color: '#6B7280',
    textAlign: 'center',
    lineHeight: 20,
    marginBottom: 24,
    paddingHorizontal: 4,
  },
  buttonRow: {
    flexDirection: 'row',
    width: '100%',
    gap: 10,
  },
  button: {
    flex: 1,
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonPrimary: {
    backgroundColor: '#F59E0B',
  },
  buttonCancel: {
    backgroundColor: '#F3F4F6',
  },
  buttonDestructive: {
    backgroundColor: '#FEE2E2',
  },
  buttonText: {
    fontSize: 15,
    fontWeight: '600',
  },
  buttonTextPrimary: {
    color: '#FFFFFF',
  },
  buttonTextCancel: {
    color: '#6B7280',
  },
  buttonTextDestructive: {
    color: '#EF4444',
  },
});
