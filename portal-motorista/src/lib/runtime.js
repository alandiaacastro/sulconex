export const ROUTES = ['inicio', 'cargas', 'solicitacoes', 'andamento', 'documentos', 'contratos'];

export function getPortalConfig() {
  const runtimeConfig = typeof window !== 'undefined' ? window.PORTAL_MOTORISTA_CONFIG || {} : {};

  return {
    apiBase: runtimeConfig.apiBase || import.meta.env.VITE_PORTAL_API_BASE || '/api/portal-motorista',
    legacyLoginUrl: runtimeConfig.legacyLoginUrl || import.meta.env.VITE_LEGACY_LOGIN_URL || '/index.php?class=LoginForm',
    portalUrl: runtimeConfig.portalUrl || import.meta.env.VITE_PORTAL_URL || '/portal-motorista/',
    cadastroRequestUrl: runtimeConfig.cadastroRequestUrl || import.meta.env.VITE_PORTAL_CADASTRO_URL || '/index.php?class=PortalMotoristaSolicitarCadastro',
  };
}

export function maskPhone(value) {
  const digits = String(value || '').replace(/\D/g, '').slice(0, 11);
  if (digits.length <= 2) return digits;
  if (digits.length <= 7) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

export function formatDate(value) {
  return value || '-';
}

export function greeting(firstName) {
  const hour = new Date().getHours();
  const salute = hour < 12 ? 'Bom dia' : hour < 18 ? 'Boa tarde' : 'Boa noite';
  return `${salute}, ${firstName || 'Motorista'}`;
}

export function buildQueryString(params) {
  const searchParams = new URLSearchParams();

  Object.entries(params || {}).forEach(([key, value]) => {
    if (value === undefined || value === null) return;
    if (typeof value === 'string' && value.trim() === '') return;
    searchParams.set(key, String(value));
  });

  return searchParams.toString();
}

export function getErrorMessage(error, fallback = 'Nao foi possivel concluir a operacao.') {
  if (typeof error === 'string' && error.trim()) return error;
  if (error instanceof Error && error.message.trim()) return error.message;
  if (typeof error?.message === 'string' && error.message.trim()) return error.message;
  return fallback;
}
