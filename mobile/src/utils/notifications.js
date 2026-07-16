import * as Notifications from 'expo-notifications';
import * as Device from 'expo-device';
import { Platform } from 'react-native';
import api from '../api/client';

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: true,
  }),
});

export async function registerForPushNotificationsAsync() {
  if (!Device.isDevice) {
    return null;
  }

  const { status: existingStatus } = await Notifications.getPermissionsAsync();
  let finalStatus = existingStatus;

  if (existingStatus !== 'granted') {
    const { status } = await Notifications.requestPermissionsAsync();
    finalStatus = status;
  }

  if (finalStatus !== 'granted') {
    return null;
  }

  try {
    const tokenData = await Notifications.getExpoPushTokenAsync();
    const pushToken = tokenData.data;

    if (Platform.OS === 'android') {
      await Notifications.setNotificationChannelAsync('default', {
        name: 'Hello Store',
        importance: Notifications.AndroidImportance.MAX,
        vibrationPattern: [0, 250, 250, 250],
        lightColor: '#F59E0B',
      });
    }

    return pushToken;
  } catch (e) {
    return null;
  }
}

export async function sendTokenToServer(pushToken) {
  try {
    await api.post('/api/devices/register', {
      token: pushToken,
      platform: Platform.OS,
      device_name: Device.deviceName || Platform.OS,
    });
  } catch (e) {
    // silent
  }
}

export async function unregisterDevice(pushToken) {
  try {
    await api.post('/api/devices/unregister', {
      token: pushToken,
    });
  } catch (e) {
    // silent
  }
}
