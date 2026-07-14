import React, { createContext, useContext, useState, useCallback } from 'react';
import CustomAlert from '../components/CustomAlert';

const AlertContext = createContext(null);

export function AlertProvider({ children }) {
  const [config, setConfig] = useState({
    visible: false,
    title: '',
    message: '',
    type: 'info',
    buttons: [],
  });

  const showAlert = useCallback(({ title, message, type = 'info', buttons }) => {
    const defaultButtons = buttons || [{ text: 'OK' }];
    setConfig({ visible: true, title, message, type, buttons: defaultButtons });
  }, []);

  const showConfirm = useCallback(({ title, message, type = 'warning', confirmText = 'Ya', cancelText = 'Batal', onConfirm, onCancel }) => {
    setConfig({
      visible: true,
      title,
      message,
      type,
      buttons: [
        { text: cancelText, style: 'cancel', onPress: onCancel },
        { text: confirmText, style: 'destructive', onPress: onConfirm },
      ],
    });
  }, []);

  const close = useCallback(() => {
    setConfig((prev) => ({ ...prev, visible: false }));
  }, []);

  return (
    <AlertContext.Provider value={{ showAlert, showConfirm }}>
      {children}
      <CustomAlert
        visible={config.visible}
        title={config.title}
        message={config.message}
        type={config.type}
        buttons={config.buttons}
        onClose={close}
      />
    </AlertContext.Provider>
  );
}

export const useAlert = () => useContext(AlertContext);
