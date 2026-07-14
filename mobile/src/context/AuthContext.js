import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api, { setToken, clearToken } from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [cartCount, setCartCount] = useState(0);

  useEffect(() => {
    checkAuth();
  }, []);

  const checkAuth = async () => {
    try {
      const response = await api.get('/api/profile');
      if (response.data?.success) {
        setUser(response.data.data);
        fetchCartCount();
      }
    } catch (e) {
      setUser(null);
      await clearToken();
    } finally {
      setLoading(false);
    }
  };

  const fetchCartCount = useCallback(async () => {
    try {
      const response = await api.get('/api/cart/count');
      if (response.data?.success) {
        setCartCount(response.data.data.count || 0);
      }
    } catch (e) {
      // silent
    }
  }, []);

  const refreshCartCount = useCallback(() => {
    if (user) fetchCartCount();
  }, [user, fetchCartCount]);

  const login = async (email, password) => {
    const response = await api.post('/api/login', { email, password });
    if (response.data?.success) {
      await setToken(response.data.data.token);
      setUser(response.data.data.user);
      fetchCartCount();
    }
    return response.data;
  };

  const register = async (name, email, password, passwordConfirmation) => {
    const response = await api.post('/api/register', {
      name,
      email,
      password,
      password_confirmation: passwordConfirmation,
    });
    if (response.data?.success) {
      await setToken(response.data.data.token);
      setUser(response.data.data.user);
      fetchCartCount();
    }
    return response.data;
  };

  const logout = async () => {
    try {
      await api.post('/api/logout');
    } catch (e) {
    }
    await clearToken();
    setUser(null);
    setCartCount(0);
  };

  const updateUser = (data) => {
    setUser((prev) => (prev ? { ...prev, ...data } : prev));
  };

  return (
    <AuthContext.Provider value={{ user, loading, login, register, logout, cartCount, refreshCartCount, updateUser }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
