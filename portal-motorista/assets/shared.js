(() => {
  const config = window.PORTAL_MOTORISTA_CONFIG || {};
  const TOKEN_KEY = 'portal_motorista_token';
  const ROUTES = ['inicio', 'cargas', 'solicitacoes', 'andamento', 'documentos', 'contratos'];

  const ICON_PATHS = {
    home: 'M3 10.5 12 3l9 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-4.5V15H9v6H4.5A1.5 1.5 0 0 1 3 19.5v-9Z',
    search: 'm21 21-4.35-4.35m1.6-5.15a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0Z',
    clipboard: 'M9 4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5h3A1.5 1.5 0 0 1 19.5 6v13.5A1.5 1.5 0 0 1 18 21H6A1.5 1.5 0 0 1 4.5 19.5V6A1.5 1.5 0 0 1 6 4.5Zm-1.5 4.5h9m-9 4.5h9m-9 4.5h6',
    route: 'M7.5 5.25A2.25 2.25 0 1 1 3 5.25a2.25 2.25 0 0 1 4.5 0Zm13.5 13.5A2.25 2.25 0 1 1 16.5 18.75a2.25 2.25 0 0 1 4.5 0ZM5.25 7.5v4.125A2.625 2.625 0 0 0 7.875 14.25h8.25A2.625 2.625 0 0 1 18.75 16.875V18',
    docs: 'M7.5 3.75h6l4.5 4.5v10.5A1.5 1.5 0 0 1 16.5 20.25h-9A1.5 1.5 0 0 1 6 18.75v-13.5A1.5 1.5 0 0 1 7.5 3.75Zm6 0v4.5H18',
    contract: 'M6 4.5h12A1.5 1.5 0 0 1 19.5 6v12A1.5 1.5 0 0 1 18 19.5H6A1.5 1.5 0 0 1 4.5 18V6A1.5 1.5 0 0 1 6 4.5Zm3 4.5h6m-6 3h6m-6 3h3',
    truck: 'M3 7.5A1.5 1.5 0 0 1 4.5 6h9A1.5 1.5 0 0 1 15 7.5V9h2.379a1.5 1.5 0 0 1 1.06.44l2.121 2.12A1.5 1.5 0 0 1 21 12.621V16.5h-1.5a2.25 2.25 0 1 1-4.5 0h-6a2.25 2.25 0 1 1-4.5 0H3Zm3 10.5a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm11.25 0a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM15 12h4.5l-1.5-1.5H15Z',
    logout: 'M15.75 9V5.625A2.625 2.625 0 0 0 13.125 3h-6A2.625 2.625 0 0 0 4.5 5.625v12.75A2.625 2.625 0 0 0 7.125 21h6a2.625 2.625 0 0 0 2.625-2.625V15M12 15l3-3m0 0-3-3m3 3H3',
    bell: 'M14.25 18.75a2.25 2.25 0 0 1-4.5 0m7.28-2.03c.538-.394.87-1.011.87-1.69V11.25a5.25 5.25 0 1 0-10.5 0v3.78c0 .679.332 1.296.87 1.69l.66.48h7.44Z',
    upload: 'M12 16.5V6m0 0 3.75 3.75M12 6 8.25 9.75M4.5 18.75h15',
    pin: 'M12 21s6-4.35 6-10.125A6 6 0 1 0 6 10.875C6 16.65 12 21 12 21Zm0-8.25a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Z',
    alert: 'M12 9v3.75m0 3h.008v.008H12v-.008Zm9-1.5c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9Z',
    check: 'm5 12 4 4L19 6',
    close: 'M6 18 18 6M6 6l12 12',
    arrowRight: 'M4.5 12h15m0 0-6-6m6 6-6 6',
    refresh: 'M16.023 9.348h4.992V4.356m-.944 10.637a8.25 8.25 0 1 1 2.235-9.637',
    chevronRight: 'M9 6 15 12 9 18'
  };

  async function apiRequest(path, options = {}) {
    const {
      method = 'GET',
      body,
      formData,
      token,
      auth = true,
      onUnauthorized,
    } = options;

    const headers = { Accept: 'application/json' };
    const requestOptions = { method, headers, credentials: 'same-origin' };

    if (auth && token) {
      headers.Authorization = `Bearer ${token}`;
    }

    if (formData) {
      requestOptions.body = formData;
    } else if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
      requestOptions.body = JSON.stringify(body);
    }

    const response = await fetch(`${config.apiBase}${path}`, requestOptions);
    const text = await response.text();
    let payload = null;

    try {
      payload = text ? JSON.parse(text) : null;
    } catch (error) {
      payload = null;
    }

    if (!response.ok || !payload?.ok) {
      const error = new Error(payload?.error?.message || `Erro ${response.status}`);
      error.status = response.status;
      error.code = payload?.error?.code;
      if (response.status === 401 && typeof onUnauthorized === 'function') {
        onUnauthorized(error);
      }
      throw error;
    }

    return payload.data;
  }

  const readStoredToken = () => window.localStorage.getItem(TOKEN_KEY) || '';
  const writeStoredToken = (token) => window.localStorage.setItem(TOKEN_KEY, token);
  const clearStoredToken = () => window.localStorage.removeItem(TOKEN_KEY);
  const maskPhone = (value) => {
    const digits = String(value || '').replace(/\D/g, '').slice(0, 11);
    if (digits.length <= 2) return digits;
    if (digits.length <= 7) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
  };
  const formatDate = (value) => value || '-';
  const greeting = (firstName) => {
    const hour = new Date().getHours();
    const salute = hour < 12 ? 'Bom dia' : hour < 18 ? 'Boa tarde' : 'Boa noite';
    return `${salute}, ${firstName || 'Motorista'}`;
  };

  function Icon({ name, className = '' }) {
    return (
      <svg viewBox="0 0 24 24" className={`pm-icon ${className}`.trim()} fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d={ICON_PATHS[name] || ICON_PATHS.home} />
      </svg>
    );
  }

  function Button({ children, variant = 'primary', type = 'button', disabled = false, onClick, className = '' }) {
    return (
      <button type={type} disabled={disabled} className={`pm-btn pm-btn-${variant} ${className}`.trim()} onClick={onClick}>
        {children}
      </button>
    );
  }

  function LoadingState({ label = 'Carregando...' }) {
    return <div className="pm-loading-card">{label}</div>;
  }

  function EmptyState({ title, description, action }) {
    return (
      <div className="pm-empty-state">
        <div className="pm-empty-icon"><Icon name="truck" /></div>
        <h3>{title}</h3>
        <p>{description}</p>
        {action || null}
      </div>
    );
  }

  function Modal({ open, title, children, onClose }) {
    if (!open) return null;
    return (
      <div className="pm-modal-backdrop" onClick={onClose}>
        <div className="pm-modal" onClick={(event) => event.stopPropagation()}>
          <div className="pm-modal-header">
            <div>
              <p className="pm-eyebrow">Portal do Motorista</p>
              <h3>{title}</h3>
            </div>
            <button type="button" className="pm-icon-button" onClick={onClose}>
              <Icon name="close" />
            </button>
          </div>
          <div className="pm-modal-body">{children}</div>
        </div>
      </div>
    );
  }

  window.PortalMotoristaShared = {
    config,
    ROUTES,
    apiRequest,
    readStoredToken,
    writeStoredToken,
    clearStoredToken,
    maskPhone,
    formatDate,
    greeting,
    Icon,
    Button,
    LoadingState,
    EmptyState,
    Modal,
  };
})();