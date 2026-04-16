(() => {
  const Shared = window.PortalMotoristaShared;
  const Pages = window.PortalMotoristaPages;
  const { Button, Icon, LoadingState, apiRequest, clearStoredToken, getErrorMessage, readStoredToken, ROUTES, writeStoredToken } = Shared;

  const NAV_ITEMS = [
    { key: 'inicio', label: 'Inicio', icon: 'home' },
    { key: 'cargas', label: 'Cargas', icon: 'search' },
    { key: 'solicitacoes', label: 'Solicitacoes', icon: 'clipboard' },
    { key: 'andamento', label: 'Andamento', icon: 'route' },
    { key: 'documentos', label: 'Documentos', icon: 'docs' },
    { key: 'contratos', label: 'Contratos', icon: 'contract' },
  ];

  const PAGE_COMPONENTS = {
    inicio: Pages.DashboardPage,
    cargas: Pages.CargasPage,
    solicitacoes: Pages.SolicitacoesPage,
    andamento: Pages.AndamentoPage,
    documentos: Pages.DocumentosPage,
    contratos: Pages.ContratosPage,
  };

  function useHashRoute() {
    const normalize = React.useCallback((hashValue) => {
      const cleaned = String(hashValue || '').replace(/^#\/?/, '');
      return ROUTES.includes(cleaned) ? cleaned : 'inicio';
    }, []);

    const [route, setRoute] = React.useState(() => normalize(window.location.hash));

    React.useEffect(() => {
      const initialRoute = normalize(window.location.hash);
      if (window.location.hash !== `#/${initialRoute}`) {
        window.location.hash = `#/${initialRoute}`;
      }

      const handler = () => {
        setRoute(normalize(window.location.hash));
      };

      window.addEventListener('hashchange', handler);
      return () => window.removeEventListener('hashchange', handler);
    }, [normalize]);

    const navigate = React.useCallback((nextRoute) => {
      const normalizedRoute = normalize(nextRoute);
      window.location.hash = `#/${normalizedRoute}`;
    }, [normalize]);

    return [route, navigate];
  }

  function Toast({ toast, onClose }) {
    React.useEffect(() => {
      if (!toast) return undefined;
      const timer = window.setTimeout(onClose, 3200);
      return () => window.clearTimeout(timer);
    }, [toast?.id, onClose]);

    if (!toast) return null;
    return <div className={`pm-toast pm-toast-${toast.type}`} role="status" aria-live="polite">{toast.message}</div>;
  }

  function Shell({ driver, route, navigate, request, onLogout, showToast }) {
    const pageProps = { request, driver, navigate, showToast };
    const CurrentPage = PAGE_COMPONENTS[route] || PAGE_COMPONENTS.inicio;

    return (
      <div className="pm-shell">
        <aside className="pm-sidebar">
          <div>
            <p className="pm-eyebrow">Sulconex</p>
            <h1>Portal do Motorista</h1>

          </div>
          <nav className="pm-nav-list">
            {NAV_ITEMS.map((item) => (
              <button key={item.key} type="button" className={`pm-nav-item ${route === item.key ? 'is-active' : ''}`} aria-pressed={route === item.key} onClick={() => navigate(item.key)}>
                <Icon name={item.icon} />
                <span>{item.label}</span>
              </button>
            ))}
          </nav>
          <button type="button" className="pm-nav-item pm-nav-item-logout" onClick={onLogout}>
            <Icon name="logout" />
            <span>Sair</span>
          </button>
        </aside>

        <div className="pm-content">
          <header className="pm-topbar">
            <div>
              <p className="pm-eyebrow">Motorista autenticado</p>
              <h2>{driver.nome || 'Motorista'}</h2>
            </div>
            <div className="pm-topbar-actions">
              <div className="pm-driver-pill">{driver.telefone || 'Sem telefone'}</div>
              <Button variant="ghost" onClick={onLogout}>Sair</Button>
            </div>
          </header>

          <main className="pm-main">
            <CurrentPage {...pageProps} />
          </main>

          <nav className="pm-mobile-nav">
            {NAV_ITEMS.map((item) => (
              <button key={item.key} type="button" className={`pm-mobile-item ${route === item.key ? 'is-active' : ''}`} aria-pressed={route === item.key} onClick={() => navigate(item.key)}>
                <Icon name={item.icon} />
                <span>{item.label}</span>
              </button>
            ))}
          </nav>
        </div>
      </div>
    );
  }

  function App() {
    const [route, navigate] = useHashRoute();
    const [token, setToken] = React.useState(() => readStoredToken());
    const [driver, setDriver] = React.useState(null);
    const [booting, setBooting] = React.useState(() => Boolean(readStoredToken()));
    const [busy, setBusy] = React.useState(false);
    const [loginError, setLoginError] = React.useState('');
    const [toast, setToast] = React.useState(null);

    const dismissToast = React.useCallback(() => {
      setToast(null);
    }, []);

    const showToast = React.useCallback((message, type = 'success') => {
      setToast({ id: Date.now() + Math.random(), message, type });
    }, []);

    const resetSession = React.useCallback((message = '') => {
      clearStoredToken();
      setToken('');
      setDriver(null);
      setLoginError(message);
    }, []);

    const handleUnauthorized = React.useCallback(() => {
      resetSession('Sua sessao expirou. Entre novamente.');
    }, [resetSession]);

    const request = React.useCallback((path, options = {}) => {
      return apiRequest(path, { ...options, token, onUnauthorized: handleUnauthorized });
    }, [handleUnauthorized, token]);

    React.useEffect(() => {
      const storedToken = readStoredToken();
      if (!storedToken) {
        setBooting(false);
        return undefined;
      }

      let active = true;

      apiRequest('/auth/me', { token: storedToken, onUnauthorized: handleUnauthorized })
        .then((data) => {
          if (!active) return;
          setToken(storedToken);
          setDriver(data.driver);
          setLoginError('');
        })
        .catch((error) => {
          if (!active || error?.status === 401) return;
          setLoginError('Nao foi possivel reconectar sua sessao agora. Tente novamente.');
          setDriver(null);
        })
        .finally(() => {
          if (active) {
            setBooting(false);
          }
        });

      return () => {
        active = false;
      };
    }, [handleUnauthorized]);

    const onLogin = React.useCallback(async (credentials) => {
      setBusy(true);
      setLoginError('');
      try {
        const data = await apiRequest('/auth/login', { method: 'POST', body: credentials, auth: false });
        writeStoredToken(data.token);
        setToken(data.token);
        setDriver(data.driver);
        navigate('inicio');
        if (!data.driver?.must_change_password) {
          showToast('Bem-vindo ao novo portal.');
        }
      } catch (error) {
        setLoginError(getErrorMessage(error));
      } finally {
        setBusy(false);
      }
    }, [navigate, showToast]);

    const onChangePassword = React.useCallback(async (payload) => {
      setBusy(true);
      setLoginError('');
      try {
        const data = await request('/auth/change-password', { method: 'POST', body: payload });
        setDriver(data.driver);
        navigate('inicio');
        showToast('Senha atualizada. Acesso liberado com sucesso.');
      } catch (error) {
        setLoginError(getErrorMessage(error));
      } finally {
        setBusy(false);
      }
    }, [navigate, request, showToast]);

    const onLogout = React.useCallback(async () => {
      try {
        if (token) {
          await request('/auth/logout', { method: 'POST' });
        }
      } catch (error) {
      }
      resetSession('');
      navigate('inicio');
    }, [navigate, request, resetSession, token]);

    if (booting) return <LoadingState label="Reconectando ao portal..." />;
    if (!token || !driver) {
      return (
        <>
          <Pages.LoginPage onLogin={onLogin} busy={busy} error={loginError} />
          <Toast toast={toast} onClose={dismissToast} />
        </>
      );
    }

    if (driver.must_change_password) {
      return (
        <>
          <Pages.PasswordSetupPage driver={driver} onSubmit={onChangePassword} onLogout={onLogout} busy={busy} error={loginError} />
          <Toast toast={toast} onClose={dismissToast} />
        </>
      );
    }

    return (
      <>
        <Shell driver={driver} route={route} navigate={navigate} request={request} onLogout={onLogout} showToast={showToast} />
        <Toast toast={toast} onClose={dismissToast} />
      </>
    );
  }

  ReactDOM.createRoot(document.getElementById('root')).render(<App />);
})();

