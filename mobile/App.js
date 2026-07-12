import React, { useState, useEffect } from 'react';
import { StatusBar } from 'expo-status-bar';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { View, Text, Image, StyleSheet, Animated } from 'react-native';
import { AuthProvider } from './src/context/AuthContext';
import { ToastProvider } from './src/components/Toast';
import AppNavigator from './src/navigation/AppNavigator';

function SplashScreen({ onFinish }) {
  const [fadeAnim] = useState(new Animated.Value(0));
  const [scaleAnim] = useState(new Animated.Value(0.8));

  useEffect(() => {
    Animated.parallel([
      Animated.timing(fadeAnim, {
        toValue: 1,
        duration: 800,
        useNativeDriver: true,
      }),
      Animated.spring(scaleAnim, {
        toValue: 1,
        friction: 4,
        useNativeDriver: true,
      }),
    ]).start();

    const timer = setTimeout(() => {
      Animated.timing(fadeAnim, {
        toValue: 0,
        duration: 400,
        useNativeDriver: true,
      }).start(() => onFinish());
    }, 2500);

    return () => clearTimeout(timer);
  }, []);

  return (
    <Animated.View style={[splashStyles.container, { opacity: fadeAnim }]}>
      <Animated.View style={[splashStyles.content, { transform: [{ scale: scaleAnim }] }]}>
        <View style={splashStyles.logoBox}>
          <Text style={splashStyles.logoLetter}>H</Text>
        </View>
        <Text style={splashStyles.appName}>Hello Store</Text>
        <Text style={splashStyles.slogan}>Belanja Mudah, Harga Terjangkau</Text>
      </Animated.View>
    </Animated.View>
  );
}

const splashStyles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#FEF3C7',
    justifyContent: 'center',
    alignItems: 'center',
  },
  content: {
    alignItems: 'center',
  },
  logoBox: {
    width: 140,
    height: 140,
    borderRadius: 32,
    backgroundColor: '#F59E0B',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 24,
    shadowColor: '#D97706',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.3,
    shadowRadius: 16,
    elevation: 12,
  },
  logoLetter: {
    fontSize: 80,
    fontWeight: '900',
    color: '#FFFFFF',
  },
  appName: {
    fontSize: 32,
    fontWeight: '800',
    color: '#111827',
    marginBottom: 8,
  },
  slogan: {
    fontSize: 16,
    fontWeight: '500',
    color: '#6B7280',
    letterSpacing: 0.5,
  },
});

export default function App() {
  const [showSplash, setShowSplash] = useState(true);

  if (showSplash) {
    return (
      <GestureHandlerRootView style={{ flex: 1 }}>
        <SplashScreen onFinish={() => setShowSplash(false)} />
        <StatusBar style="dark" />
      </GestureHandlerRootView>
    );
  }

  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <AuthProvider>
        <ToastProvider>
          <AppNavigator />
        </ToastProvider>
        <StatusBar style="auto" />
      </AuthProvider>
    </GestureHandlerRootView>
  );
}
