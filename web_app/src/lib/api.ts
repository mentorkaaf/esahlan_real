import { API_BASE, TOKEN_KEY } from './constants';

function getToken(): string | null {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem(TOKEN_KEY);
}

async function request<T>(
  method: string,
  path: string,
  body?: unknown,
  formData?: FormData,
): Promise<T> {
  const token = getToken();
  const headers: Record<string, string> = {
    Accept: 'application/json',
  };
  if (token) headers['Authorization'] = `Bearer ${token}`;
  if (body && !formData) headers['Content-Type'] = 'application/json';

  const res = await fetch(`${API_BASE}${path}`, {
    method,
    headers,
    body: formData ?? (body ? JSON.stringify(body) : undefined),
    credentials: 'include',
  });

  if (res.status === 401) {
    localStorage.removeItem(TOKEN_KEY);
    window.location.href = '/login';
    throw new Error('Unauthenticated');
  }

  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(json?.message ?? `HTTP ${res.status}`);
  return json as T;
}

export const api = {
  get: <T>(path: string) => request<T>('GET', path),
  post: <T>(path: string, body?: unknown) => request<T>('POST', path, body),
  put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body),
  delete: <T>(path: string) => request<T>('DELETE', path),
  postForm: <T>(path: string, form: FormData) => request<T>('POST', path, undefined, form),
};

// ── Media URL helper (matches Flutter _fixUrl) ──────────────────────────────
export function mediaUrl(url: string | null | undefined): string {
  if (!url) return '';
  // Already absolute esahlan.com URL
  if (url.startsWith('https://esahlan.com')) return url;
  // api.esahlan.com → esahlan.com
  if (url.includes('api.esahlan.com')) {
    url = url.replace('api.esahlan.com', 'esahlan.com');
  }
  // storage/ path → proxy
  if (url.includes('/storage/')) {
    const path = url.split('/storage/')[1];
    return `${API_BASE}/media?f=${encodeURIComponent(path)}`;
  }
  if (url.startsWith('/') || url.startsWith('storage/')) {
    const path = url.startsWith('/') ? url.slice(1) : url;
    return `${API_BASE}/media?f=${encodeURIComponent(path)}`;
  }
  return url;
}
