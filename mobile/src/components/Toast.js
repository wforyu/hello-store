import React, { createContext, useContext, useState, useCallback, useRef } from 'react';
import { Animated, Text, StyleSheet } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const ToastContext = createContext(null);

export function ToastProvider({ children }) {
  const insets = useSafeAreaInsets();
  const [toast, setToast] = useState(null);
  const opacity = useRef(new Animated.Value(0)).current;

  const showToast = useCallback((message, type = 'success') => {
    setToast({ message, type });
    opacity.setValue(0);
    Animated.sequence([
      Animated.timing(opacity, { toValue: 1, duration: 200, useNativeDriver: true }),
      Animated.delay(2000),
      Animated.timing(opacity, { toValue: 0, duration: 200, useNativeDriver: true }),
    ]).start(() => setToast(null));
  }, [opacity]);

  const bg = toast?.type === 'error' ? '#EF4444' : toast?.type === 'info' ? '#3B82F6' : '#10B981';

  return (
    <ToastContext.Provider value={showToast}>
      {children}
      {toast && (
        <Animated.View style={[styles.container, { opacity, backgroundColor: bg, bottom: insets.bottom + 80 }]}>  
          <Text style={styles.text}>{toast.message}</Text>
        </Animated.View>
      )}
    </ToastContext.Provider>
  );
}

export const useToast = () => useContext(ToastContext);

const styles = StyleSheet.create({
  container: {
    position: 'absolute', bottom: 80, left: 20, right: 20,
    backgroundColor: '#10B981', borderRadius: 12,
    paddingHorizontal: 16, paddingVertical: 14,
    elevation: 8, shadowColor: '#000', shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25, shadowRadius: 8, zIndex: 9999,
    alignItems: 'center',
  },
  text: { color: '#fff', fontSize: 14, fontWeight: '600', textAlign: 'center' },
});
