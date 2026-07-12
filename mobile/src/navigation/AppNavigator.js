import React from 'react';
import { ActivityIndicator, View, Text, StyleSheet, Platform } from 'react-native';
import { SafeAreaProvider, useSafeAreaInsets } from 'react-native-safe-area-context';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { useAuth } from '../context/AuthContext';
import { COLORS } from '../config';

import LoginScreen from '../screens/LoginScreen';
import RegisterScreen from '../screens/RegisterScreen';
import HomeScreen from '../screens/HomeScreen';
import ProductDetailScreen from '../screens/ProductDetailScreen';
import CartScreen from '../screens/CartScreen';
import CheckoutScreen from '../screens/CheckoutScreen';
import OrderListScreen from '../screens/OrderListScreen';
import OrderDetailScreen from '../screens/OrderDetailScreen';
import NotificationScreen from '../screens/NotificationScreen';
import ProfileScreen from '../screens/ProfileScreen';
import AddressScreen from '../screens/AddressScreen';
import WishlistScreen from '../screens/WishlistScreen';
import SearchScreen from '../screens/SearchScreen';

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

function TabIcon({ emoji, color, badge }) {
  return (
    <View>
      <Text style={{ color, fontSize: 20 }}>{emoji}</Text>
      {badge > 0 && (
        <View style={styles.badge}>
          <Text style={styles.badgeText}>{badge > 99 ? '99+' : badge}</Text>
        </View>
      )}
    </View>
  );
}

function MainTabs() {
  const { cartCount } = useAuth();
  const insets = useSafeAreaInsets();

  return (
    <Tab.Navigator
      screenOptions={{
        tabBarActiveTintColor: COLORS.primary,
        tabBarInactiveTintColor: COLORS.textLight,
        tabBarStyle: {
          backgroundColor: COLORS.white,
          borderTopColor: COLORS.border,
          paddingBottom: Math.max(insets.bottom, 4),
          height: 56 + Math.max(insets.bottom, 0),
        },
        tabBarLabelStyle: { fontSize: 11, fontWeight: '500' },
        headerStyle: { backgroundColor: COLORS.white },
        headerTintColor: COLORS.text,
        headerTitleStyle: { fontWeight: '600' },
      }}
    >
      <Tab.Screen
        name="Home"
        component={HomeScreen}
        options={{
          title: 'Hello Store',
          tabBarIcon: ({ color }) => <TabIcon emoji="🏠" color={color} />,
        }}
      />
      <Tab.Screen
        name="Cart"
        component={CartScreen}
        options={{
          title: 'Keranjang',
          tabBarIcon: ({ color }) => <TabIcon emoji="🛒" color={color} badge={cartCount} />,
        }}
      />
      <Tab.Screen
        name="Orders"
        component={OrderListScreen}
        options={{
          title: 'Pesanan',
          tabBarIcon: ({ color }) => <TabIcon emoji="📦" color={color} />,
        }}
      />
      <Tab.Screen
        name="Notifications"
        component={NotificationScreen}
        options={{
          title: 'Notifikasi',
          tabBarIcon: ({ color }) => <TabIcon emoji="🔔" color={color} />,
        }}
      />
      <Tab.Screen
        name="Profile"
        component={ProfileScreen}
        options={{
          title: 'Akun',
          tabBarIcon: ({ color }) => <TabIcon emoji="👤" color={color} />,
        }}
      />
    </Tab.Navigator>
  );
}

export default function AppNavigator() {
  const { loading } = useAuth();

  if (loading) {
    return (
      <View style={styles.loading}>
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }

  return (
    <SafeAreaProvider>
      <NavigationContainer>
        <Stack.Navigator>
          <Stack.Screen name="Main" component={MainTabs} options={{ headerShown: false }} />
        <Stack.Screen
          name="ProductDetail"
          component={ProductDetailScreen}
          options={{ title: 'Detail Produk', headerTintColor: COLORS.text }}
        />
        <Stack.Screen
          name="Checkout"
          component={CheckoutScreen}
          options={{ title: 'Checkout', headerTintColor: COLORS.text }}
        />
        <Stack.Screen
          name="OrderDetail"
          component={OrderDetailScreen}
          options={{ title: 'Detail Pesanan', headerTintColor: COLORS.text }}
        />
        <Stack.Screen
          name="Address"
          component={AddressScreen}
          options={{ title: 'Alamat Saya', headerTintColor: COLORS.text }}
        />
        <Stack.Screen
          name="Wishlist"
          component={WishlistScreen}
          options={{ title: 'Wishlist', headerTintColor: COLORS.text }}
        />
        <Stack.Screen
          name="Search"
          component={SearchScreen}
          options={{ title: 'Cari Produk', headerTintColor: COLORS.text }}
        />
        <Stack.Screen
          name="Login"
          component={LoginScreen}
          options={{ headerShown: false }}
        />
        <Stack.Screen
          name="Register"
          component={RegisterScreen}
          options={{
            headerShown: true,
            title: 'Daftar',
            headerTintColor: COLORS.text,
          }}
        />
        </Stack.Navigator>
      </NavigationContainer>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  loading: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: COLORS.background,
  },
  badge: {
    position: 'absolute',
    top: -4,
    right: -10,
    backgroundColor: '#EF4444',
    borderRadius: 10,
    minWidth: 18,
    height: 18,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 4,
  },
  badgeText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: '700',
  },
});
