import React, { useState, useEffect, useRef } from 'react';
import { StatusBar } from 'expo-status-bar';
import * as SplashScreen from 'expo-splash-screen';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { View, Text, StyleSheet, Animated } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AuthProvider } from './src/context/AuthContext';
import { ToastProvider } from './src/components/Toast';
import { AlertProvider } from './src/context/AlertContext';
import AppNavigator from './src/navigation/AppNavigator';
import { checkForUrlUpdate } from './src/config';
import { registerForPushNotificationsAsync, sendTokenToServer } from './src/utils/notifications';

SplashScreen.preventAutoHideAsync();

function CustomSplash({ onFinish }) {
  const fadeAnim = useRef(new Animated.Value(1)).current;
  const scaleAnim = useRef(new Animated.Value(0.8)).current;

  useEffect(() => {
    Animated.parallel([
      Animated.timing(fadeAnim, {
        toValue: 1,
        duration: 600,
        useNativeDriver: true,
      }),
      Animated.spring(scaleAnim, {
        toValue: 1,
        friction: 5,
        useNativeDriver: true,
      }),
    ]).start();

    const timer = setTimeout(() => {
      Animated.timing(fadeAnim, {
        toValue: 0,
        duration: 350,
        useNativeDriver: true,
      }).start(() => {
        SplashScreen.hideAsync();
        onFinish();
      });
    }, 2000);

    return () => clearTimeout(timer);
  }, []);

  return (
    <View style={splashStyles.container}>
      <Animated.View style={[splashStyles.content, { opacity: fadeAnim, transform: [{ scale: scaleAnim }] }]}>
        <View style={splashStyles.logoBox}>
          <Text style={splashStyles.logoLetter}>HS</Text>
        </View>
        <Text style={splashStyles.appName}>Hello Store</Text>
        <Text style={splashStyles.slogan}>Belanja Mudah, Harga Terjangkau</Text>
      </Animated.View>
    </View>
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
    width: 120,
    height: 120,
    borderRadius: 28,
    backgroundColor: '#F59E0B',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 24,
    elevation: 12,
  },
  logoLetter: {
    fontSize: 44,
    fontWeight: '900',
    color: '#FFFFFF',
  },
  appName: {
    fontSize: 30,
    fontWeight: '800',
    color: '#111827',
    marginBottom: 8,
  },
  slogan: {
    fontSize: 15,
    fontWeight: '500',
    color: '#6B7280',
  },
});

export default function App() {
  const [showSplash, setShowSplash] = useState(true);

  useEffect(() => {
    checkForUrlUpdate();
  }, []);

  const handleSplashFinish = async () => {
    setShowSplash(false);
    try {
      const token = await registerForPushNotificationsAsync();
      if (token) {
        await sendTokenToServer(token);
      }
    } catch (e) {}
  };

  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <SafeAreaProvider>
        {showSplash ? (
          <CustomSplash onFinish={handleSplashFinish} />
        ) : (
          <AuthProvider>
            <ToastProvider>
              <AlertProvider>
                <AppNavigator />
              </AlertProvider>
            </ToastProvider>
          </AuthProvider>
        )}
        <StatusBar style={showSplash ? 'dark' : 'auto'} />
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}
