import React, {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  PermissionsAndroid,
  Platform,
  SafeAreaView,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
  Animated,
  Dimensions,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {Camera, CameraType} from 'react-native-camera-kit';

const {width: SCREEN_WIDTH} = Dimensions.get('window');

type MobileUser = {
  idUser: number;
  nom: string;
  prenom: string;
  email: string;
  telephone: string;
  role: string;
  status: string;
  profile_image_url?: string;
};

type NotificationItem = {
  idNotification?: number;
  title?: string;
  message?: string;
  created_at?: string;
};

type SupportMessageItem = {
  idSupportMessage?: number;
  sender_role?: string;
  sender_name?: string;
  message_text?: string;
};

type TabKey = 'profile' | 'notifications' | 'support' | 'scanner';

const STORAGE_TOKEN_KEY = 'nexora.mobile.token';
const STORAGE_BASE_URL_KEY = 'nexora.mobile.base_url';
const STORAGE_DEVICE_ID_KEY = 'nexora.mobile.device_id';
const DEFAULT_BASE_URL = 'http://10.196.198.191:8001';
const LEGACY_LOCAL_BASE_URLS = new Set(['http://10.0.2.2:8001', 'http://127.0.0.1:8001']);

// ── Nexora Design Tokens ──────────────────────────────────────
const NX = {
  bg:        '#040d1a',
  bgCard:    'rgba(255,255,255,0.04)',
  bgCardAlt: 'rgba(10,37,64,0.5)',
  teal:      '#00B4A0',
  tealLight: '#5ff5e1',
  tealDim:   'rgba(0,180,160,0.15)',
  tealBorder:'rgba(0,180,160,0.25)',
  white:     '#ffffff',
  textPrimary:   '#eaf4ff',
  textSecondary: 'rgba(180,210,255,0.6)',
  textMuted:     'rgba(180,210,255,0.4)',
  border:    'rgba(255,255,255,0.08)',
  borderLight:'rgba(255,255,255,0.12)',
  danger:    '#ff607a',
  gold:      '#ffd447',
  blue:      '#7eb8ff',
  success:   '#00dc78',
};

function makeDeviceId(): string {
  return `nexora-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

function normalizeBaseUrl(value: string): string {
  return String(value || '').trim().replace(/\/+$/, '');
}

function parseQrPayload(raw: string): {type: string; token: string} | null {
  const value = String(raw || '').trim();
  if (value === '') return null;

  try {
    const parsed = JSON.parse(value) as {type?: string; token?: string};
    const type  = String(parsed.type  || '').trim();
    const token = String(parsed.token || '').trim();
    if (type !== '' && token !== '') return {type, token};
  } catch (_) {}

  const tokenMatch = value.match(/token=([A-Za-z0-9_-]+)/);
  if (!tokenMatch) return null;

  const lowered = value.toLowerCase();
  const type = lowered.includes('trust') ? 'trust_device' : 'web_login';
  return {type, token: tokenMatch[1]};
}

// ── Small reusable components ─────────────────────────────────

function NxBadge({label, color = NX.teal}: {label: string; color?: string}) {
  return (
    <View style={[badgeStyles.wrap, {borderColor: color + '44', backgroundColor: color + '18'}]}>
      <View style={[badgeStyles.dot, {backgroundColor: color}]} />
      <Text style={[badgeStyles.text, {color}]}>{label}</Text>
    </View>
  );
}

const badgeStyles = StyleSheet.create({
  wrap: {
    flexDirection: 'row', alignItems: 'center', gap: 5,
    paddingHorizontal: 10, paddingVertical: 4,
    borderRadius: 999, borderWidth: 1,
    alignSelf: 'flex-start',
  },
  dot: {width: 6, height: 6, borderRadius: 3},
  text: {fontSize: 11, fontWeight: '700', letterSpacing: 0.5},
});

function NxSectionTag({label}: {label: string}) {
  return (
    <View style={tagStyles.wrap}>
      <Text style={tagStyles.text}>{label.toUpperCase()}</Text>
    </View>
  );
}

const tagStyles = StyleSheet.create({
  wrap: {
    alignSelf: 'flex-start',
    backgroundColor: 'rgba(0,180,160,0.1)',
    borderWidth: 1, borderColor: 'rgba(0,180,160,0.25)',
    borderRadius: 999, paddingHorizontal: 14, paddingVertical: 5,
    marginBottom: 8,
  },
  text: {
    fontSize: 10, fontWeight: '700',
    letterSpacing: 2, color: NX.teal,
  },
});

function NxCard({children, style, accent = false}: {
  children: React.ReactNode;
  style?: object;
  accent?: boolean;
}) {
  return (
    <View style={[
      cardStyles.card,
      accent && cardStyles.cardAccent,
      style,
    ]}>
      {children}
    </View>
  );
}

const cardStyles = StyleSheet.create({
  card: {
    backgroundColor: NX.bgCard,
    borderWidth: 1, borderColor: NX.border,
    borderRadius: 20, padding: 18,
    marginBottom: 12,
  },
  cardAccent: {
    backgroundColor: NX.bgCardAlt,
    borderColor: NX.tealBorder,
  },
});

// ── Main App ──────────────────────────────────────────────────

export default function App(): React.JSX.Element {
  const [baseUrl, setBaseUrl]           = useState(DEFAULT_BASE_URL);
  const [deviceId, setDeviceId]         = useState('');
  const [token, setToken]               = useState('');
  const [user, setUser]                 = useState<MobileUser | null>(null);
  const [trustedDevice, setTrustedDevice] = useState(false);
  const [initializing, setInitializing] = useState(true);
  const [busy, setBusy]                 = useState(false);
  const [tab, setTab]                   = useState<TabKey>('profile');

  const [loginEmail, setLoginEmail]       = useState('');
  const [loginPassword, setLoginPassword] = useState('');
  const [showPassword, setShowPassword]   = useState(false);

  const [editNom, setEditNom]           = useState('');
  const [editPrenom, setEditPrenom]     = useState('');
  const [editTelephone, setEditTelephone] = useState('');

  const [notifications, setNotifications]   = useState<NotificationItem[]>([]);
  const [supportMessages, setSupportMessages] = useState<SupportMessageItem[]>([]);
  const [supportText, setSupportText]       = useState('');
  const [supportBusy, setSupportBusy]       = useState(false);
  const lastSupportMessageId = useRef(0);

  const [scannerPermission, setScannerPermission] = useState<boolean | null>(null);
  const [scannerLocked, setScannerLocked]         = useState(false);

  // Animated underline for tabs
  const tabAnim = useRef(new Animated.Value(0)).current;
  const TAB_KEYS: TabKey[] = ['profile', 'notifications', 'support', 'scanner'];
  const TAB_LABELS = ['Profile', 'Alerts', 'Support', 'Scan'];
  const TAB_ICONS  = ['◈', '◉', '◎', '⬡'];

  const hasApiBaseUrl = useMemo(() => normalizeBaseUrl(baseUrl) !== '', [baseUrl]);

  const apiRequest = useCallback(
    async (
      path: string,
      options: {method?: string; auth?: boolean; body?: unknown} = {},
    ): Promise<Record<string, unknown>> => {
      const method = options.method || 'GET';
      const headers: Record<string, string> = {Accept: 'application/json'};
      if (options.auth !== false && token !== '') {
        headers.Authorization = `Bearer ${token}`;
      }
      if (options.body !== undefined) {
        headers['Content-Type'] = 'application/json';
      }

      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), 15000);
      let response: Response;
      try {
        response = await fetch(`${normalizeBaseUrl(baseUrl)}${path}`, {
          method,
          headers,
          body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
          signal: controller.signal,
        });
      } catch (error) {
        if ((error as Error)?.name === 'AbortError') {
          throw new Error('Request timeout. Check Symfony server and Wi-Fi connectivity.');
        }
        throw error;
      } finally {
        clearTimeout(timeout);
      }

      let data: Record<string, unknown> = {};
      try { data = (await response.json()) as Record<string, unknown>; }
      catch (_) { data = {}; }

      if (!response.ok || data.ok === false) {
        throw new Error(String(data.message || `Request failed (${response.status})`));
      }
      return data;
    },
    [baseUrl, token],
  );

  const loadMe = useCallback(async () => {
    const data = await apiRequest('/api/mobile/me');
    const currentUser = (data.user || null) as MobileUser | null;
    setUser(currentUser);
    setTrustedDevice(Boolean(data.trusted_device));
    setEditNom(String(currentUser?.nom || ''));
    setEditPrenom(String(currentUser?.prenom || ''));
    setEditTelephone(String(currentUser?.telephone || ''));
  }, [apiRequest]);

  const loadNotifications = useCallback(async () => {
    const data = await apiRequest('/api/mobile/notifications?limit=40');
    const items = Array.isArray(data.items) ? (data.items as NotificationItem[]) : [];
    setNotifications(items);
  }, [apiRequest]);

  const loadSupport = useCallback(
    async (incremental = true) => {
      const query = incremental ? `?after_id=${lastSupportMessageId.current}` : '';
      const data = await apiRequest(`/api/mobile/support/messages${query}`);
      const incoming = Array.isArray(data.messages)
        ? (data.messages as SupportMessageItem[])
        : [];

      if (!incremental) {
        setSupportMessages(incoming);
        lastSupportMessageId.current = incoming.reduce(
          (acc, item) => Math.max(acc, Number(item.idSupportMessage || 0)), 0,
        );
        return;
      }

      if (incoming.length > 0) {
        setSupportMessages(prev => {
          const known = new Set(prev.map(i => Number(i.idSupportMessage || 0)));
          const merged = [...prev];
          incoming.forEach(item => {
            const id = Number(item.idSupportMessage || 0);
            if (!known.has(id)) { merged.push(item); known.add(id); }
          });
          return merged;
        });
        lastSupportMessageId.current = incoming.reduce(
          (acc, item) => Math.max(acc, Number(item.idSupportMessage || 0)),
          lastSupportMessageId.current,
        );
      }
    },
    [apiRequest],
  );

  const restoreSession = useCallback(async () => {
    setInitializing(true);
    try {
      const savedBaseUrl  = await AsyncStorage.getItem(STORAGE_BASE_URL_KEY);
      const savedToken    = await AsyncStorage.getItem(STORAGE_TOKEN_KEY);
      let savedDeviceId   = await AsyncStorage.getItem(STORAGE_DEVICE_ID_KEY);
      if (!savedDeviceId) {
        savedDeviceId = makeDeviceId();
        await AsyncStorage.setItem(STORAGE_DEVICE_ID_KEY, savedDeviceId);
      }
      setDeviceId(savedDeviceId);
      if (savedBaseUrl) {
        const normalizedSavedBaseUrl = normalizeBaseUrl(savedBaseUrl);
        if (LEGACY_LOCAL_BASE_URLS.has(normalizedSavedBaseUrl)) {
          await AsyncStorage.setItem(STORAGE_BASE_URL_KEY, DEFAULT_BASE_URL);
          setBaseUrl(DEFAULT_BASE_URL);
        } else {
          setBaseUrl(normalizedSavedBaseUrl);
        }
      }
      if (savedToken)   setToken(savedToken);
    } finally {
      setInitializing(false);
    }
  }, []);

  useEffect(() => { restoreSession(); }, [restoreSession]);

  useEffect(() => {
    if (token === '') {
      setUser(null); setTrustedDevice(false);
      setNotifications([]); setSupportMessages([]);
      lastSupportMessageId.current = 0;
      return;
    }
    (async () => {
      try {
        await loadMe();
        await Promise.all([loadNotifications(), loadSupport(false)]);
      } catch (error) {
        Alert.alert('Session error', String((error as Error).message || error));
        await AsyncStorage.removeItem(STORAGE_TOKEN_KEY);
        setToken('');
      }
    })();
  }, [loadMe, loadNotifications, loadSupport, token]);

  useEffect(() => {
    if (token === '') return;
    const timer = setInterval(() => {
      loadSupport(true).catch(() => {});
    }, 2200);
    return () => clearInterval(timer);
  }, [loadSupport, token]);

  const switchTab = useCallback((key: TabKey) => {
    const idx = TAB_KEYS.indexOf(key);
    Animated.spring(tabAnim, {
      toValue: idx,
      useNativeDriver: true,
      tension: 80,
      friction: 10,
    }).start();
    setTab(key);
  }, [tabAnim, TAB_KEYS]);

  const ensureCameraPermission = useCallback(async (): Promise<boolean> => {
    if (Platform.OS !== 'android') { setScannerPermission(true); return true; }
    const granted = await PermissionsAndroid.check(PermissionsAndroid.PERMISSIONS.CAMERA);
    if (granted) { setScannerPermission(true); return true; }
    const result = await PermissionsAndroid.request(
      PermissionsAndroid.PERMISSIONS.CAMERA,
      {
        title: 'Camera permission',
        message: 'Camera access is required to scan QR codes.',
        buttonPositive: 'Allow',
        buttonNegative: 'Deny',
      },
    );
    const allowed = result === PermissionsAndroid.RESULTS.GRANTED;
    setScannerPermission(allowed);
    return allowed;
  }, []);

  const handleLogin = useCallback(async () => {
    if (!hasApiBaseUrl) { Alert.alert('Base URL', 'Please enter a valid Symfony base URL.'); return; }
    if (loginEmail.trim() === '' || loginPassword.trim() === '') {
      Alert.alert('Login', 'Email and password are required.'); return;
    }
    setBusy(true);
    try {
      const data = await apiRequest('/api/mobile/login', {
        method: 'POST', auth: false,
        body: {
          email: loginEmail.trim(), password: loginPassword,
          device_id: deviceId,
          device_name: Platform.OS === 'ios' ? 'iPhone' : 'Android',
          platform: Platform.OS, app_version: '1.0.0',
        },
      });
      const issuedToken = String(data.token || '');
      if (issuedToken === '') throw new Error('Token missing in login response.');
      setToken(issuedToken);
      setTrustedDevice(Boolean(data.trusted_device));
      setLoginPassword('');
      switchTab('profile');
      AsyncStorage.setItem(STORAGE_TOKEN_KEY, issuedToken).catch(() => {});
      AsyncStorage.setItem(STORAGE_BASE_URL_KEY, normalizeBaseUrl(baseUrl)).catch(() => {});
    } catch (error) {
      Alert.alert('Login failed', String((error as Error).message || error));
    } finally { setBusy(false); }
  }, [apiRequest, baseUrl, deviceId, hasApiBaseUrl, loginEmail, loginPassword, switchTab]);

  const handleLogout = useCallback(async () => {
    try { await apiRequest('/api/mobile/logout', {method: 'POST'}); } catch (_) {}
    await AsyncStorage.removeItem(STORAGE_TOKEN_KEY);
    setToken(''); setUser(null); switchTab('profile');
  }, [apiRequest, switchTab]);

  const handleSaveProfile = useCallback(async () => {
    setBusy(true);
    try {
      const data = await apiRequest('/api/mobile/profile', {
        method: 'POST',
        body: {nom: editNom, prenom: editPrenom, telephone: editTelephone},
      });
      setUser((data.user || null) as MobileUser | null);
      Alert.alert('✓ Profile updated', 'Your changes have been saved.');
    } catch (error) {
      Alert.alert('Profile update failed', String((error as Error).message || error));
    } finally { setBusy(false); }
  }, [apiRequest, editNom, editPrenom, editTelephone]);

  const handleSupportSend = useCallback(async () => {
    if (supportText.trim() === '') return;
    setSupportBusy(true);
    try {
      const data = await apiRequest('/api/mobile/support/messages', {
        method: 'POST', body: {message: supportText.trim()},
      });
      const message = data.message as SupportMessageItem | undefined;
      if (message) {
        setSupportMessages(prev => [...prev, message]);
        lastSupportMessageId.current = Math.max(
          lastSupportMessageId.current, Number(message.idSupportMessage || 0),
        );
      }
      setSupportText('');
    } catch (error) {
      Alert.alert('Support', String((error as Error).message || error));
    } finally { setSupportBusy(false); }
  }, [apiRequest, supportText]);

  const handleQrCode = useCallback(
    async (rawCode: string) => {
      if (scannerLocked) return;
      setScannerLocked(true);
      try {
        const parsed = parseQrPayload(rawCode);
        if (!parsed) throw new Error('Unsupported QR format.');

        if (parsed.type === 'trust_device') {
          const response = await apiRequest('/api/mobile/trust/confirm', {
            method: 'POST',
            body: {
              qr_token: parsed.token, device_id: deviceId,
              device_name: Platform.OS === 'ios' ? 'iPhone' : 'Android',
              platform: Platform.OS, app_version: '1.0.0',
            },
          });
          setTrustedDevice(Boolean(response.trusted_device));
          Alert.alert('Device trust', String(response.message || 'Device trusted successfully.'));
        } else if (parsed.type === 'web_login') {
          const response = await apiRequest('/api/mobile/qr-login/approve', {
            method: 'POST', body: {qr_token: parsed.token},
          });
          Alert.alert('QR Web Login', String(response.message || 'Website login approved.'));
        } else {
          throw new Error('Unknown QR type.');
        }
        await loadMe();
      } catch (error) {
        Alert.alert('QR scan', String((error as Error).message || error));
      } finally {
        setTimeout(() => setScannerLocked(false), 1300);
      }
    },
    [apiRequest, deviceId, loadMe, scannerLocked],
  );

  // ── Splash / Loading ─────────────────────────────────────────
  if (initializing) {
    return (
      <SafeAreaView style={styles.splash}>
        <StatusBar barStyle="light-content" backgroundColor={NX.bg} />
        <View style={styles.splashInner}>
          <View style={styles.splashLogoRing}>
            <Text style={styles.splashLogoText}>NX</Text>
          </View>
          <Text style={styles.splashTitle}>NEXORA</Text>
          <Text style={styles.splashSub}>PLATEFORME BANCAIRE</Text>
          <ActivityIndicator
            size="small"
            color={NX.teal}
            style={{marginTop: 32}}
          />
          <Text style={styles.splashLoading}>Loading secure session...</Text>
        </View>
      </SafeAreaView>
    );
  }

  // ── Auth Screen ───────────────────────────────────────────────
  if (token === '') {
    return (
      <SafeAreaView style={styles.authRoot}>
        <StatusBar barStyle="light-content" backgroundColor={NX.bg} />
        <ScrollView
          contentContainerStyle={styles.authScroll}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}>

          {/* Brand */}
          <View style={styles.authBrand}>
            <View style={styles.authLogoRing}>
              <Text style={styles.authLogoText}>NX</Text>
            </View>
            <View>
              <Text style={styles.authEyebrow}>PLATEFORME BANCAIRE</Text>
              <Text style={styles.authTitle}>NEXORA</Text>
            </View>
            <View style={styles.liveBadge}>
              <View style={styles.liveDot} />
              <Text style={styles.liveText}>LIVE</Text>
            </View>
          </View>

          {/* Hero text */}
          <Text style={styles.authHeadline}>
            Gérez votre argent avec{' '}
            <Text style={styles.authHeadlineAccent}>confiance.</Text>
          </Text>
          <Text style={styles.authSub}>
            Connectez-vous à votre espace bancaire sécurisé.
          </Text>

          {/* Card form */}
          <View style={styles.authCard}>
            <NxSectionTag label="Connexion" />
            <Text style={styles.authCardTitle}>Accéder à mon compte</Text>

            <Text style={styles.fieldLabel}>URL Serveur Symfony</Text>
            <View style={styles.inputWrap}>
              <TextInput
                style={styles.input}
                value={baseUrl}
                onChangeText={setBaseUrl}
                autoCapitalize="none"
                placeholder={DEFAULT_BASE_URL}
                placeholderTextColor={NX.textMuted}
                keyboardAppearance="dark"
              />
            </View>

            <Text style={styles.fieldLabel}>Adresse e-mail</Text>
            <View style={styles.inputWrap}>
              <TextInput
                style={styles.input}
                value={loginEmail}
                onChangeText={setLoginEmail}
                autoCapitalize="none"
                keyboardType="email-address"
                placeholder="vous@exemple.com"
                placeholderTextColor={NX.textMuted}
                keyboardAppearance="dark"
              />
            </View>

            <Text style={styles.fieldLabel}>Mot de passe</Text>
            <View style={styles.inputWrap}>
              <TextInput
                style={[styles.input, {flex: 1, borderWidth: 0, padding: 0}]}
                value={loginPassword}
                onChangeText={setLoginPassword}
                secureTextEntry={!showPassword}
                placeholder="••••••••"
                placeholderTextColor={NX.textMuted}
                keyboardAppearance="dark"
              />
              <TouchableOpacity
                onPress={() => setShowPassword(p => !p)}
                style={styles.eyeBtn}>
                <Text style={styles.eyeText}>{showPassword ? '🙈' : '👁'}</Text>
              </TouchableOpacity>
            </View>

            <TouchableOpacity
              style={[styles.primaryBtn, busy && styles.primaryBtnDisabled]}
              onPress={handleLogin}
              disabled={busy}
              activeOpacity={0.85}>
              {busy
                ? <ActivityIndicator size="small" color="#03201a" />
                : <Text style={styles.primaryBtnText}>Connexion →</Text>
              }
            </TouchableOpacity>
          </View>

          {/* Device ID */}
          <View style={styles.deviceIdRow}>
            <Text style={styles.deviceIdLabel}>Device ID</Text>
            <Text style={styles.deviceIdValue} numberOfLines={1}>
              {deviceId || 'initializing...'}
            </Text>
          </View>

          {/* Trust badges */}
          <View style={styles.trustRow}>
            <NxBadge label="🔒 SSL" color={NX.teal} />
            <NxBadge label="🛡 AES-256" color={NX.blue} />
            <NxBadge label="✅ RGPD" color={NX.success} />
          </View>
        </ScrollView>
      </SafeAreaView>
    );
  }

  // ── Main App ──────────────────────────────────────────────────
  const tabW = SCREEN_WIDTH / TAB_KEYS.length;

  return (
    <SafeAreaView style={styles.appRoot}>
      <StatusBar barStyle="light-content" backgroundColor={NX.bg} />

      {/* ── Header ── */}
      <View style={styles.header}>
        <View style={styles.headerLeft}>
          <View style={styles.headerLogoRing}>
            <Text style={styles.headerLogoText}>NX</Text>
          </View>
          <View>
            <Text style={styles.headerTitle}>NEXORA</Text>
            <Text style={styles.headerSub}>
              {user ? `${user.prenom || ''} ${user.nom || ''}`.trim() : 'Chargement...'}
            </Text>
          </View>
        </View>
        <View style={styles.headerRight}>
          {trustedDevice && (
            <View style={styles.trustedBadge}>
              <Text style={styles.trustedText}>✓ Trusted</Text>
            </View>
          )}
          <TouchableOpacity
            style={styles.logoutBtn}
            onPress={handleLogout}
            activeOpacity={0.8}>
            <Text style={styles.logoutText}>Déconnexion</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* ── Tab Bar ── */}
      <View style={styles.tabBar}>
        {/* Animated indicator */}
        <Animated.View
          style={[
            styles.tabIndicator,
            {
              width: tabW - 16,
              transform: [{
                translateX: tabAnim.interpolate({
                  inputRange: [0, 1, 2, 3],
                  outputRange: [8, tabW + 8, tabW * 2 + 8, tabW * 3 + 8],
                }),
              }],
            },
          ]}
        />
        {TAB_KEYS.map((key, idx) => (
          <TouchableOpacity
            key={key}
            style={styles.tabItem}
            onPress={async () => {
              switchTab(key);
              if (key === 'notifications') {
                try { await loadNotifications(); } catch (_) {}
              }
              if (key === 'scanner') { await ensureCameraPermission(); }
            }}
            activeOpacity={0.7}>
            <Text style={[styles.tabIcon, tab === key && styles.tabIconActive]}>
              {TAB_ICONS[idx]}
            </Text>
            <Text style={[styles.tabLabel, tab === key && styles.tabLabelActive]}>
              {TAB_LABELS[idx]}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      {/* ══════════════ PROFILE TAB ══════════════ */}
      {tab === 'profile' && (
        <ScrollView
          style={styles.flex1}
          contentContainerStyle={styles.tabContent}
          showsVerticalScrollIndicator={false}>

          <NxSectionTag label="Profil" />
          <Text style={styles.pageTitle}>Mon espace</Text>

          {/* User info card */}
          <NxCard accent style={{marginTop: 4}}>
            <View style={styles.profileAvatarRow}>
              <View style={styles.profileAvatar}>
                <Text style={styles.profileAvatarText}>
                  {(user?.prenom?.[0] || '') + (user?.nom?.[0] || '')}
                </Text>
              </View>
              <View style={styles.profileAvatarInfo}>
                <Text style={styles.profileName}>
                  {`${user?.prenom || ''} ${user?.nom || ''}`.trim()}
                </Text>
                <Text style={styles.profileEmail}>{user?.email || '-'}</Text>
                <View style={styles.profileStatusRow}>
                  <NxBadge
                    label={user?.status || 'ACTIVE'}
                    color={user?.status === 'BLOCKED' ? NX.danger : NX.success}
                  />
                  {trustedDevice && (
                    <NxBadge label="✓ Appareil de confiance" color={NX.teal} />
                  )}
                </View>
              </View>
            </View>
          </NxCard>

          {/* Info row */}
          <View style={styles.infoGrid}>
            <View style={styles.infoCell}>
              <Text style={styles.infoCellLabel}>Statut</Text>
              <Text style={styles.infoCellValue}>{user?.status || '-'}</Text>
            </View>
            <View style={styles.infoCell}>
              <Text style={styles.infoCellLabel}>Rôle</Text>
              <Text style={styles.infoCellValue}>
                {(user?.role || 'USER').replace('ROLE_', '')}
              </Text>
            </View>
            <View style={styles.infoCell}>
              <Text style={styles.infoCellLabel}>Appareil</Text>
              <Text style={styles.infoCellValue}>
                {trustedDevice ? 'Approuvé' : 'Non approuvé'}
              </Text>
            </View>
          </View>

          {/* Edit form */}
          <NxCard style={{marginTop: 4}}>
            <Text style={styles.cardSectionTitle}>Modifier le profil</Text>

            <Text style={styles.fieldLabel}>Prénom</Text>
            <View style={styles.inputWrap}>
              <TextInput
                style={styles.input}
                value={editPrenom}
                onChangeText={setEditPrenom}
                placeholderTextColor={NX.textMuted}
                keyboardAppearance="dark"
              />
            </View>

            <Text style={styles.fieldLabel}>Nom</Text>
            <View style={styles.inputWrap}>
              <TextInput
                style={styles.input}
                value={editNom}
                onChangeText={setEditNom}
                placeholderTextColor={NX.textMuted}
                keyboardAppearance="dark"
              />
            </View>

            <Text style={styles.fieldLabel}>Téléphone</Text>
            <View style={styles.inputWrap}>
              <TextInput
                style={styles.input}
                value={editTelephone}
                onChangeText={setEditTelephone}
                keyboardType="phone-pad"
                placeholderTextColor={NX.textMuted}
                keyboardAppearance="dark"
              />
            </View>

            <TouchableOpacity
              style={[styles.primaryBtn, busy && styles.primaryBtnDisabled]}
              onPress={handleSaveProfile}
              disabled={busy}
              activeOpacity={0.85}>
              {busy
                ? <ActivityIndicator size="small" color="#03201a" />
                : <Text style={styles.primaryBtnText}>Enregistrer →</Text>
              }
            </TouchableOpacity>
          </NxCard>
        </ScrollView>
      )}

      {/* ══════════════ NOTIFICATIONS TAB ══════════════ */}
      {tab === 'notifications' && (
        <View style={styles.flex1}>
          <View style={styles.tabContentHeader}>
            <NxSectionTag label="Notifications" />
            <Text style={styles.pageTitle}>Alertes bancaires</Text>
          </View>
          <FlatList
            data={notifications}
            contentContainerStyle={styles.listContent}
            keyExtractor={(item, idx) =>
              String(item.idNotification || `notif-${idx}`)
            }
            showsVerticalScrollIndicator={false}
            renderItem={({item, index}) => (
              <View style={[styles.notifCard, index === 0 && styles.notifCardNew]}>
                <View style={styles.notifDotWrap}>
                  <View style={[
                    styles.notifDot,
                    index === 0 && {backgroundColor: NX.success, shadowColor: NX.success},
                  ]} />
                </View>
                <View style={styles.notifBody}>
                  <Text style={styles.notifTitle}>
                    {item.title || 'Notification'}
                  </Text>
                  <Text style={styles.notifMessage}>{item.message || ''}</Text>
                </View>
                <Text style={styles.notifTime}>{item.created_at || ''}</Text>
              </View>
            )}
            ListEmptyComponent={
              <View style={styles.emptyState}>
                <Text style={styles.emptyIcon}>◎</Text>
                <Text style={styles.emptyText}>Aucune notification</Text>
              </View>
            }
          />
        </View>
      )}

      {/* ══════════════ SUPPORT TAB ══════════════ */}
      {tab === 'support' && (
        <View style={styles.flex1}>
          <View style={styles.tabContentHeader}>
            <NxSectionTag label="Support" />
            <Text style={styles.pageTitle}>Assistance client</Text>
          </View>

          <FlatList
            style={styles.flex1}
            contentContainerStyle={styles.chatContent}
            data={supportMessages}
            keyExtractor={(item, idx) =>
              String(item.idSupportMessage || `msg-${idx}`)
            }
            showsVerticalScrollIndicator={false}
            renderItem={({item}) => {
              const mine =
                String(item.sender_role || '').toUpperCase() === 'ROLE_USER';
              return (
                <View style={[styles.bubble, mine ? styles.bubbleMine : styles.bubbleOther]}>
                  <Text style={styles.bubbleSender}>
                    {item.sender_name || (mine ? 'Vous' : 'Support')}
                  </Text>
                  <Text style={[styles.bubbleText, mine && styles.bubbleTextMine]}>
                    {item.message_text || ''}
                  </Text>
                </View>
              );
            }}
            ListEmptyComponent={
              <View style={styles.emptyState}>
                <Text style={styles.emptyIcon}>◎</Text>
                <Text style={styles.emptyText}>Aucun message pour l'instant</Text>
              </View>
            }
          />

          <View style={styles.chatInputRow}>
            <TextInput
              style={styles.chatInput}
              value={supportText}
              onChangeText={setSupportText}
              placeholder="Écrivez votre message..."
              placeholderTextColor={NX.textMuted}
              keyboardAppearance="dark"
              multiline
            />
            <TouchableOpacity
              style={[styles.sendBtn, supportBusy && {opacity: 0.6}]}
              onPress={handleSupportSend}
              disabled={supportBusy}
              activeOpacity={0.8}>
              {supportBusy
                ? <ActivityIndicator size="small" color="#03201a" />
                : <Text style={styles.sendBtnText}>→</Text>
              }
            </TouchableOpacity>
          </View>
        </View>
      )}

      {/* ══════════════ SCANNER TAB ══════════════ */}
      {tab === 'scanner' && (
        <ScrollView
          style={styles.flex1}
          contentContainerStyle={styles.tabContent}
          showsVerticalScrollIndicator={false}>

          <NxSectionTag label="Scanner" />
          <Text style={styles.pageTitle}>Scanner QR</Text>
          <Text style={styles.pageSubtitle}>
            Scannez un QR de confiance depuis votre profil,
            ou un QR de connexion web depuis la page de login.
          </Text>

          {scannerPermission !== true && (
            <NxCard>
              <Text style={styles.cardSectionTitle}>Autorisation caméra</Text>
              <Text style={styles.fieldLabel}>
                L'accès à la caméra est nécessaire pour scanner les QR codes.
              </Text>
              <TouchableOpacity
                style={[styles.primaryBtn, {marginTop: 12}]}
                onPress={ensureCameraPermission}
                activeOpacity={0.85}>
                <Text style={styles.primaryBtnText}>Activer la caméra</Text>
              </TouchableOpacity>
            </NxCard>
          )}

          {scannerPermission === false && (
            <NxCard>
              <Text style={[styles.cardSectionTitle, {color: NX.danger}]}>
                Permission refusée
              </Text>
              <Text style={styles.fieldLabel}>
                Activez la permission caméra dans les paramètres système.
              </Text>
            </NxCard>
          )}

          {scannerPermission === true && (
            <View style={styles.scannerWrap}>
              <View style={styles.scannerBox}>
                <Camera
                  style={StyleSheet.absoluteFill}
                  cameraType={CameraType.Back}
                  scanBarcode
                  onReadCode={(event: {nativeEvent?: {codeStringValue?: string}}) => {
                    const code = String(
                      event?.nativeEvent?.codeStringValue || '',
                    ).trim();
                    if (code !== '') handleQrCode(code);
                  }}
                />
                {/* Corner markers */}
                <View style={[styles.corner, styles.cornerTL]} />
                <View style={[styles.corner, styles.cornerTR]} />
                <View style={[styles.corner, styles.cornerBL]} />
                <View style={[styles.corner, styles.cornerBR]} />

                <View style={styles.scannerOverlay}>
                  <View style={styles.scannerStatusDot}>
                    <View style={[
                      styles.scannerStatusInner,
                      {backgroundColor: scannerLocked ? NX.gold : NX.teal},
                    ]} />
                  </View>
                  <Text style={styles.scannerText}>
                    {scannerLocked ? 'Traitement en cours...' : 'Alignez le QR dans le cadre'}
                  </Text>
                </View>
              </View>

              {/* Instructions */}
              <View style={styles.scanInstructRow}>
                <NxCard style={{flex: 1, marginBottom: 0, marginRight: 6}}>
                  <Text style={styles.scanInstructIcon}>🔐</Text>
                  <Text style={styles.scanInstructTitle}>Confiance</Text>
                  <Text style={styles.scanInstructText}>
                    Approuver cet appareil
                  </Text>
                </NxCard>
                <NxCard style={{flex: 1, marginBottom: 0, marginLeft: 6}}>
                  <Text style={styles.scanInstructIcon}>🌐</Text>
                  <Text style={styles.scanInstructTitle}>Connexion Web</Text>
                  <Text style={styles.scanInstructText}>
                    Se connecter via navigateur
                  </Text>
                </NxCard>
              </View>
            </View>
          )}
        </ScrollView>
      )}
    </SafeAreaView>
  );
}

// ── Styles ────────────────────────────────────────────────────
const styles = StyleSheet.create({
  flex1: {flex: 1},

  // ── Splash
  splash: {flex: 1, backgroundColor: NX.bg},
  splashInner: {
    flex: 1, alignItems: 'center', justifyContent: 'center',
  },
  splashLogoRing: {
    width: 80, height: 80, borderRadius: 22,
    backgroundColor: 'rgba(0,180,160,0.12)',
    borderWidth: 1, borderColor: NX.tealBorder,
    alignItems: 'center', justifyContent: 'center',
    marginBottom: 16,
  },
  splashLogoText: {
    fontSize: 28, fontWeight: '900',
    color: NX.teal, letterSpacing: 2,
  },
  splashTitle: {
    fontFamily: Platform.OS === 'ios' ? 'System' : 'sans-serif-condensed',
    fontSize: 32, fontWeight: '900',
    color: NX.white, letterSpacing: 8,
  },
  splashSub: {
    fontSize: 11, fontWeight: '600',
    letterSpacing: 3, color: NX.textSecondary,
    marginTop: 6,
  },
  splashLoading: {
    marginTop: 10, fontSize: 12,
    color: NX.textMuted, letterSpacing: 0.5,
  },

  // ── Auth
  authRoot: {flex: 1, backgroundColor: NX.bg},
  authScroll: {
    padding: 24, paddingTop: 20,
    gap: 0,
  },
  authBrand: {
    flexDirection: 'row', alignItems: 'center',
    gap: 12, marginBottom: 32,
  },
  authLogoRing: {
    width: 44, height: 44, borderRadius: 13,
    backgroundColor: 'rgba(0,180,160,0.12)',
    borderWidth: 1, borderColor: NX.tealBorder,
    alignItems: 'center', justifyContent: 'center',
  },
  authLogoText: {
    fontSize: 16, fontWeight: '900',
    color: NX.teal, letterSpacing: 1,
  },
  authEyebrow: {
    fontSize: 9, fontWeight: '700',
    letterSpacing: 2, color: NX.teal,
  },
  authTitle: {
    fontSize: 20, fontWeight: '900',
    color: NX.white, letterSpacing: 4,
  },
  liveBadge: {
    flexDirection: 'row', alignItems: 'center', gap: 5,
    backgroundColor: 'rgba(0,229,204,0.1)',
    borderWidth: 1, borderColor: 'rgba(0,229,204,0.25)',
    borderRadius: 999, paddingHorizontal: 10, paddingVertical: 4,
  },
  liveDot: {
    width: 6, height: 6, borderRadius: 3,
    backgroundColor: '#00e5cc',
  },
  liveText: {
    fontSize: 10, fontWeight: '700',
    color: '#00e5cc', letterSpacing: 1,
  },
  authHeadline: {
    fontSize: 32, fontWeight: '900',
    color: NX.white, lineHeight: 40,
    letterSpacing: -0.5, marginBottom: 10,
  },
  authHeadlineAccent: {
    color: NX.teal,
  },
  authSub: {
    fontSize: 15, color: NX.textSecondary,
    lineHeight: 22, marginBottom: 28,
  },
  authCard: {
    backgroundColor: NX.bgCard,
    borderWidth: 1, borderColor: NX.border,
    borderRadius: 24, padding: 22, marginBottom: 16,
  },
  authCardTitle: {
    fontSize: 18, fontWeight: '800',
    color: NX.white, marginBottom: 20,
  },
  deviceIdRow: {
    flexDirection: 'row', alignItems: 'center',
    gap: 8, marginBottom: 16,
    backgroundColor: 'rgba(255,255,255,0.03)',
    borderRadius: 12, padding: 12,
    borderWidth: 1, borderColor: NX.border,
  },
  deviceIdLabel: {
    fontSize: 11, color: NX.textMuted,
    fontWeight: '600', letterSpacing: 0.5,
  },
  deviceIdValue: {
    flex: 1, fontSize: 10,
    color: NX.textSecondary, fontFamily: Platform.OS === 'ios' ? 'Menlo' : 'monospace',
  },
  trustRow: {
    flexDirection: 'row', gap: 8,
    flexWrap: 'wrap', marginBottom: 32,
  },

  // ── App shell
  appRoot: {flex: 1, backgroundColor: NX.bg},

  // ── Header
  header: {
    flexDirection: 'row', alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16, paddingTop: 10, paddingBottom: 12,
    backgroundColor: 'rgba(4,13,26,0.95)',
    borderBottomWidth: 1, borderColor: NX.border,
  },
  headerLeft: {flexDirection: 'row', alignItems: 'center', gap: 12},
  headerLogoRing: {
    width: 38, height: 38, borderRadius: 11,
    backgroundColor: 'rgba(0,180,160,0.12)',
    borderWidth: 1, borderColor: NX.tealBorder,
    alignItems: 'center', justifyContent: 'center',
  },
  headerLogoText: {
    fontSize: 13, fontWeight: '900',
    color: NX.teal, letterSpacing: 1,
  },
  headerTitle: {
    fontSize: 16, fontWeight: '900',
    color: NX.white, letterSpacing: 3,
  },
  headerSub: {
    fontSize: 11, color: NX.textSecondary,
    marginTop: 1,
  },
  headerRight: {flexDirection: 'row', alignItems: 'center', gap: 8},
  trustedBadge: {
    backgroundColor: 'rgba(0,180,160,0.12)',
    borderWidth: 1, borderColor: NX.tealBorder,
    borderRadius: 8, paddingHorizontal: 8, paddingVertical: 4,
  },
  trustedText: {
    fontSize: 10, fontWeight: '700', color: NX.teal,
  },
  logoutBtn: {
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1, borderColor: NX.borderLight,
    borderRadius: 10, paddingHorizontal: 12, paddingVertical: 7,
  },
  logoutText: {
    fontSize: 12, fontWeight: '700',
    color: NX.textSecondary,
  },

  // ── Tab bar
  tabBar: {
    flexDirection: 'row',
    backgroundColor: 'rgba(4,13,26,0.95)',
    borderBottomWidth: 1, borderColor: NX.border,
    position: 'relative', paddingVertical: 4,
  },
  tabIndicator: {
    position: 'absolute', bottom: 4, height: 42,
    backgroundColor: 'rgba(0,180,160,0.12)',
    borderRadius: 12,
    borderWidth: 1, borderColor: NX.tealBorder,
    zIndex: 0,
  },
  tabItem: {
    flex: 1, alignItems: 'center', justifyContent: 'center',
    paddingVertical: 8, zIndex: 1,
  },
  tabIcon: {
    fontSize: 16, color: NX.textMuted, marginBottom: 2,
  },
  tabIconActive: {color: NX.teal},
  tabLabel: {
    fontSize: 9, fontWeight: '700',
    letterSpacing: 0.8, color: NX.textMuted,
    textTransform: 'uppercase',
  },
  tabLabelActive: {color: NX.teal},

  // ── Shared content
  tabContent: {padding: 16, gap: 0, paddingBottom: 32},
  tabContentHeader: {padding: 16, paddingBottom: 4},
  listContent: {padding: 16, paddingBottom: 32},
  pageTitle: {
    fontSize: 26, fontWeight: '900',
    color: NX.white, letterSpacing: -0.5,
    marginBottom: 16,
  },
  pageSubtitle: {
    fontSize: 14, color: NX.textSecondary,
    lineHeight: 21, marginBottom: 16,
  },
  cardSectionTitle: {
    fontSize: 16, fontWeight: '800',
    color: NX.white, marginBottom: 14,
  },

  // ── Form
  fieldLabel: {
    fontSize: 11, fontWeight: '700',
    color: NX.textSecondary, letterSpacing: 0.5,
    marginBottom: 6, marginTop: 12,
  },
  inputWrap: {
    flexDirection: 'row', alignItems: 'center',
    backgroundColor: 'rgba(255,255,255,0.05)',
    borderWidth: 1, borderColor: NX.border,
    borderRadius: 14, paddingHorizontal: 14, paddingVertical: 12,
  },
  input: {
    flex: 1, fontSize: 14,
    color: NX.textPrimary,
  },
  eyeBtn: {paddingLeft: 8},
  eyeText: {fontSize: 16},
  primaryBtn: {
    marginTop: 16, backgroundColor: NX.teal,
    borderRadius: 14, paddingVertical: 14,
    alignItems: 'center',
    shadowColor: NX.teal,
    shadowOffset: {width: 0, height: 8},
    shadowOpacity: 0.4, shadowRadius: 20,
    elevation: 8,
  },
  primaryBtnDisabled: {opacity: 0.6},
  primaryBtnText: {
    color: '#03201a', fontSize: 15,
    fontWeight: '800', letterSpacing: 0.5,
  },

  // ── Profile
  profileAvatarRow: {
    flexDirection: 'row', alignItems: 'center', gap: 14,
  },
  profileAvatar: {
    width: 60, height: 60, borderRadius: 18,
    backgroundColor: 'rgba(0,180,160,0.2)',
    borderWidth: 2, borderColor: NX.tealBorder,
    alignItems: 'center', justifyContent: 'center',
  },
  profileAvatarText: {
    fontSize: 22, fontWeight: '900', color: NX.teal,
  },
  profileAvatarInfo: {flex: 1, gap: 4},
  profileName: {
    fontSize: 18, fontWeight: '800', color: NX.white,
  },
  profileEmail: {
    fontSize: 12, color: NX.textSecondary,
  },
  profileStatusRow: {
    flexDirection: 'row', gap: 6, marginTop: 4, flexWrap: 'wrap',
  },
  infoGrid: {
    flexDirection: 'row', gap: 10, marginBottom: 12,
  },
  infoCell: {
    flex: 1,
    backgroundColor: NX.bgCard,
    borderWidth: 1, borderColor: NX.border,
    borderRadius: 14, padding: 12,
  },
  infoCellLabel: {
    fontSize: 10, fontWeight: '700',
    color: NX.textMuted, letterSpacing: 0.5,
    marginBottom: 4, textTransform: 'uppercase',
  },
  infoCellValue: {
    fontSize: 13, fontWeight: '700', color: NX.white,
  },

  // ── Notifications
  notifCard: {
    flexDirection: 'row', alignItems: 'flex-start', gap: 12,
    backgroundColor: NX.bgCard,
    borderWidth: 1, borderColor: NX.border,
    borderRadius: 18, padding: 14, marginBottom: 10,
  },
  notifCardNew: {
    borderColor: 'rgba(0,220,120,0.25)',
    backgroundColor: 'rgba(0,220,120,0.04)',
  },
  notifDotWrap: {paddingTop: 3},
  notifDot: {
    width: 8, height: 8, borderRadius: 4,
    backgroundColor: NX.blue,
    shadowColor: NX.blue, shadowOffset: {width: 0, height: 0},
    shadowOpacity: 0.7, shadowRadius: 4, elevation: 3,
  },
  notifBody: {flex: 1},
  notifTitle: {
    fontSize: 14, fontWeight: '700', color: NX.white, marginBottom: 3,
  },
  notifMessage: {
    fontSize: 13, color: NX.textSecondary, lineHeight: 19,
  },
  notifTime: {
    fontSize: 10, color: NX.textMuted, paddingTop: 2,
  },
  emptyState: {
    alignItems: 'center', paddingTop: 60, gap: 10,
  },
  emptyIcon: {fontSize: 40, color: NX.textMuted},
  emptyText: {fontSize: 14, color: NX.textMuted},

  // ── Support / Chat
  chatContent: {
    padding: 16, paddingBottom: 12, gap: 0,
  },
  bubble: {
    maxWidth: '82%', marginBottom: 10,
    borderRadius: 18, padding: 12,
  },
  bubbleMine: {
    alignSelf: 'flex-end',
    backgroundColor: NX.teal,
    borderBottomRightRadius: 4,
  },
  bubbleOther: {
    alignSelf: 'flex-start',
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1, borderColor: NX.border,
    borderBottomLeftRadius: 4,
  },
  bubbleSender: {
    fontSize: 10, fontWeight: '700',
    color: 'rgba(255,255,255,0.6)',
    marginBottom: 4, letterSpacing: 0.3,
  },
  bubbleText: {fontSize: 14, color: NX.textSecondary, lineHeight: 20},
  bubbleTextMine: {color: '#03201a'},
  chatInputRow: {
    flexDirection: 'row', alignItems: 'flex-end',
    padding: 12, gap: 10,
    backgroundColor: 'rgba(4,13,26,0.95)',
    borderTopWidth: 1, borderColor: NX.border,
  },
  chatInput: {
    flex: 1, minHeight: 44, maxHeight: 120,
    backgroundColor: 'rgba(255,255,255,0.05)',
    borderWidth: 1, borderColor: NX.border,
    borderRadius: 14, paddingHorizontal: 14, paddingVertical: 10,
    color: NX.textPrimary, fontSize: 14,
  },
  sendBtn: {
    width: 44, height: 44, borderRadius: 13,
    backgroundColor: NX.teal,
    alignItems: 'center', justifyContent: 'center',
    shadowColor: NX.teal,
    shadowOffset: {width: 0, height: 6},
    shadowOpacity: 0.4, shadowRadius: 12,
    elevation: 6,
  },
  sendBtnText: {
    fontSize: 20, fontWeight: '900', color: '#03201a',
  },

  // ── Scanner
  scannerWrap: {gap: 14},
  scannerBox: {
    height: 340, borderRadius: 20, overflow: 'hidden',
    borderWidth: 1, borderColor: NX.tealBorder,
    position: 'relative',
    shadowColor: NX.teal,
    shadowOffset: {width: 0, height: 8},
    shadowOpacity: 0.3, shadowRadius: 24,
    elevation: 10,
  },
  scannerOverlay: {
    position: 'absolute', left: 0, right: 0, bottom: 0,
    backgroundColor: 'rgba(4,13,26,0.75)',
    padding: 14, alignItems: 'center',
    flexDirection: 'row', gap: 10, justifyContent: 'center',
  },
  scannerStatusDot: {
    width: 16, height: 16, borderRadius: 8,
    borderWidth: 2, borderColor: 'rgba(255,255,255,0.2)',
    alignItems: 'center', justifyContent: 'center',
  },
  scannerStatusInner: {
    width: 8, height: 8, borderRadius: 4,
  },
  scannerText: {
    fontSize: 13, fontWeight: '700', color: NX.white,
  },
  // QR Corner markers
  corner: {
    position: 'absolute', width: 24, height: 24,
    borderColor: NX.teal, borderWidth: 3,
  },
  cornerTL: {top: 20, left: 20, borderRightWidth: 0, borderBottomWidth: 0, borderRadius: 4},
  cornerTR: {top: 20, right: 20, borderLeftWidth: 0, borderBottomWidth: 0, borderRadius: 4},
  cornerBL: {bottom: 60, left: 20, borderRightWidth: 0, borderTopWidth: 0, borderRadius: 4},
  cornerBR: {bottom: 60, right: 20, borderLeftWidth: 0, borderTopWidth: 0, borderRadius: 4},
  scanInstructRow: {flexDirection: 'row'},
  scanInstructIcon: {fontSize: 22, marginBottom: 6},
  scanInstructTitle: {
    fontSize: 13, fontWeight: '800', color: NX.white, marginBottom: 4,
  },
  scanInstructText: {
    fontSize: 12, color: NX.textSecondary, lineHeight: 17,
  },
});
