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
    localStorage.setItem('jwt_refresh_token', refreshToken);
}

function clearTokens() {
    localStorage.removeItem('jwt_token');
    localStorage.removeItem('jwt_refresh_token');
}

async function refreshToken() {
    const refreshToken = localStorage.getItem('jwt_refresh_token');
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

async function loginWithPassword(username, password) {
    const res = await fetch(`${API_BASE}/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password }),
    });

    if (!res.ok) throw new Error('Invalid credentials.');

    const data = await res.json();
    setTokens(data.token, data.refresh_token);
    return data;
}

async function registerPasskey(username, keyName = 'My Passkey') {
    const optRes = await fetch(`${API_BASE}/register/options`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username }),
    });

    if (!optRes.ok) throw new Error('Could not get registration options.');

    const options = await optRes.json();

    options.challenge = base64UrlToBuffer(options.challenge);
    options.user.id = base64UrlToBuffer(options.user.id);

    const credential = await navigator.credentials.create({ publicKey: options });

    const verifyRes = await fetch(`${API_BASE}/register/verify`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            username,
            name: keyName,
            credential: {
                id: credential.id,
                rawId: bufferToBase64Url(credential.rawId),
                type: credential.type,
                response: {
                    clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
                    attestationObject: bufferToBase64Url(credential.response.attestationObject),
                },
            },
        }),
    });

    if (!verifyRes.ok) throw new Error('Passkey registration failed.');

    return verifyRes.json();
}

async function loginWithPasskey() {
    const optRes = await fetch(`${API_BASE}/login/options`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
    });

    if (!optRes.ok) throw new Error('Could not get login options.');

    const options = await optRes.json();
    options.challenge = base64UrlToBuffer(options.challenge);

    const assertion = await navigator.credentials.get({ publicKey: options });

    const verifyRes = await fetch(`${API_BASE}/login/verify`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            assertion: {
                id: assertion.id,
                rawId: bufferToBase64Url(assertion.rawId),
                type: assertion.type,
                response: {
                    clientDataJSON: bufferToBase64Url(assertion.response.clientDataJSON),
                    authenticatorData: bufferToBase64Url(assertion.response.authenticatorData),
                    signature: bufferToBase64Url(assertion.response.signature),
                    userHandle: assertion.response.userHandle
                        ? bufferToBase64Url(assertion.response.userHandle)
                        : null,
                },
            },
        }),
    });

    if (!verifyRes.ok) throw new Error('Passkey login failed.');

    const data = await verifyRes.json();
    setTokens(data.token, data.refresh_token);
    return data;
}

export { loginWithPassword, loginWithPasskey, registerPasskey, authFetch, refreshToken, clearTokens, getToken };
