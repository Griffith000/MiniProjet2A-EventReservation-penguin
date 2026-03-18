const API_BASE = '/api/auth';

function bufferToBase64Url(buffer) {
    const bytes = new Uint8Array(buffer);
    let str = '';
    for (const byte of bytes) str += String.fromCharCode(byte);
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

function base64UrlToBuffer(base64url) {
    const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return bytes.buffer;
}

function getToken() {
    return localStorage.getItem('jwt_token');
}

function setTokens(token, refreshToken) {
    localStorage.setItem('jwt_token', token);
    localStorage.setItem('refresh_token', refreshToken);
}

function clearTokens() {
    localStorage.removeItem('jwt_token');
    localStorage.removeItem('refresh_token');
}

async function refreshToken() {
    const refreshToken = localStorage.getItem('refresh_token');
    if (!refreshToken) throw new Error('No refresh token available.');

    const res = await fetch('/api/token/refresh', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: refreshToken }),
    });

    if (!res.ok) {
        clearTokens();
        throw new Error('Session expired. Please log in again.');
    }

    const data = await res.json();
    setTokens(data.token, data.refresh_token);
    return data.token;
}

async function authFetch(url, options = {}) {
    let token = getToken();

    const makeRequest = (t) => fetch(url, {
        ...options,
        headers: {
            ...(options.headers ?? {}),
            Authorization: `Bearer ${t}`,
            'Content-Type': 'application/json',
        },
    });

    let res = await makeRequest(token);

    if (res.status === 401) {
        token = await refreshToken();
        res = await makeRequest(token);
    }

    return res;
}

async function loginWithPassword(email, password) {
    const res = await fetch(`${API_BASE}/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
    });

    if (!res.ok) throw new Error('Invalid credentials.');

    const data = await res.json();
    setTokens(data.token, data.refresh_token);
    return data;
}

async function registerPasskey(email, displayName) {
    // 1. Obtenir les options du serveur
    const optionsRes = await fetch(`${API_BASE}/register/options`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, displayName }),
    });

    if (!optionsRes.ok) {
        const err = await optionsRes.json();
        throw new Error(err.error || 'Échec options');
    }

    const options = await optionsRes.json();

    // 2. Créer la credential via l'API navigateur
    const credential = await navigator.credentials.create({
        publicKey: {
            ...options,
            challenge: base64UrlToBuffer(options.challenge),
            user: {
                ...options.user,
                id: base64UrlToBuffer(options.user.id),
            },
            excludeCredentials: options.excludeCredentials?.map(c => ({
                ...c,
                id: base64UrlToBuffer(c.id),
            })),
        },
    });

    // 3. Envoyer la réponse au serveur
    const verifyRes = await fetch(`${API_BASE}/register/verify`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            email,
            credential: {
                id: credential.id,
                rawId: bufferToBase64Url(credential.rawId),
                response: {
                    clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
                    attestationObject: bufferToBase64Url(credential.response.attestationObject),
                },
                type: credential.type,
                clientExtensionResults: credential.getClientExtensionResults(),
            },
        }),
    });

    const result = await verifyRes.json();
    if (!verifyRes.ok) throw new Error(result.error || 'Échec vérification');

    // 4. Stocker les tokens pour les requêtes futures
    if (result.token) {
        localStorage.setItem('jwt_token', result.token);
        localStorage.setItem('refresh_token', result.refresh_token);
    }

    return result;
}

async function loginWithPasskey() {
    // 1. Obtenir les options de connexion
    const optionsRes = await fetch(`${API_BASE}/login/options`, {
        method: 'POST',
    });

    if (!optionsRes.ok) {
        const err = await optionsRes.json();
        throw new Error(err.error || 'Échec options login');
    }

    const options = await optionsRes.json();

    // 2. Demander l'authentification à l'utilisateur
    const assertion = await navigator.credentials.get({
        publicKey: {
            ...options,
            challenge: base64UrlToBuffer(options.challenge),
            allowCredentials: options.allowCredentials?.map(c => ({
                ...c,
                id: base64UrlToBuffer(c.id),
            })),
        },
    });

    // 3. Vérifier avec le serveur
    const verifyRes = await fetch(`${API_BASE}/login/verify`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            credential: {
                id: assertion.id,
                rawId: bufferToBase64Url(assertion.rawId),
                response: {
                    clientDataJSON: bufferToBase64Url(assertion.response.clientDataJSON),
                    authenticatorData: bufferToBase64Url(assertion.response.authenticatorData),
                    signature: bufferToBase64Url(assertion.response.signature),
                    userHandle: assertion.response.userHandle
                        ? bufferToBase64Url(assertion.response.userHandle)
                        : null,
                },
                type: assertion.type,
                clientExtensionResults: assertion.getClientExtensionResults(),
            },
        }),
    });

    const result = await verifyRes.json();
    if (!verifyRes.ok) throw new Error(result.error || 'Échec authentification');

    if (result.token) {
        localStorage.setItem('jwt_token', result.token);
        localStorage.setItem('refresh_token', result.refresh_token);
    }

    return result;
}

export { loginWithPassword, loginWithPasskey, registerPasskey, authFetch, refreshToken, clearTokens, getToken };
