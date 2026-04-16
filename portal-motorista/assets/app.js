(() => {
  const Shared = window.PortalMotoristaShared;
  const Pages = window.PortalMotoristaPages;
  const { Button, Icon, LoadingState, apiRequest, readStoredToken, writeStoredToken, clearStoredToken, ROUTES } = Shared;

  function useHashRoute() {
    const normalize = (hashValue) => {
      const cleaned = String(hashValue || '').replace(/^#\/?/, '');
      return ROUTES.includes(cleaned) ? cleaned : 'inicio';
    };

    const [route, setRoute] = React.useState(() => normalize(window.location.hash));

    React.useEffect(() => {
      if (!window.location.hash) {
        window.location.hash = '#/inicio';
      }
      const handler = () => setRoute(normalize(window.location.hash));
      window.addEventListener('hashchange', handler);
      return () => window.removeEventListener('hashchange', handler);
    }, []);

    return [route, (nextRoute) => { window.location.hash = `#/${nextRoute}`; }];
  }

  function Toast({ toast, onClose }) {
    React.useEffect(() => {
      if (!toast) return undefined;
      const timer = window.setTimeout(onClose, 3200);
      return () => window.clearTimeout(timer);
    }, [toast, onClose]);

    if (!toast) return null;
    return <div className={`pm-toast pm-toast-${toast.type}`}>{toast.message}</div>;
  }

  function Shell({ driver, route, navigate, request, onLogout, showToast }) {
    const navItems = [
      { key: 'inicio', label: 'Inicio', icon: 'home' },
      { key: 'cargas', label: 'Cargas', icon: 'search' },
      { key: 'solicitacoes', label: 'Solicitacoes', icon: 'clipboard' },
      { key: 'andamento', label: 'Andamento', icon: 'route' },
      { key: 'documentos', label: 'Documentos', icon: 'docs' },
      { key: 'contratos', label: 'Contratos', icon: 'contract' },
    ];

    const pageProps = { request, driver, navigate, showToast };
    const CurrentPage = {
      inicio: Pages.DashboardPage,
      cargas: Pages.CargasPage,
      solicitacoes: Pages.SolicitacoesPage,
      andamento: Pages.AndamentoPage,
      documentos: Pages.DocumentosPage,
      contratos: Pages.ContratosPage,
    }[route] || Pages.DashboardPage;

    return (
      <div className="pm-shell">
        <aside className="pm-sidebar">
          <div>
            <p className="pm-eyebrow">Sulconex</p>
            <h1>Portal do Motorista</h1>
            <p className="pm-sidebar-copy">React na interface, PHP e Adianti nas regras e na base.</p>
          </div>
          <nav className="pm-nav-list">
            {navItems.map((item) => (
              <button key={item.key} type="button" className={`pm-nav-item ${route === item.key ? 'is-active' : ''}`} onClick={() => navigate(item.key)}>
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
              <h2>{driver.nome}</h2>
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
            {navItems.map((item) => (
              <button key={item.key} type="button" className={`pm-mobile-item ${route === item.key ? 'is-active' : ''}`} onClick={() => navigate(item.key)}>
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
    const [booting, setBooting] = React.useState(Boolean(readStoredToken()));
    const [busy, setBusy] = React.useState(false);
    const [loginError, setLoginError] = React.useState('');
    const [toast, setToast] = React.useState(null);

    const showToast = (message, type = 'success') => setToast({ message, type });

    const handleUnauthorized = React.useMemo(() => () => {
      clearStoredToken();
      setToken('');
      setDriver(null);
      setLoginError('Sua sessao expirou. Entre novamente.');
    }, []);

    const request = React.useMemo(() => (path, options = {}) => apiRequest(path, { ...options, token, onUnauthorized: handleUnauthorized }), [token, handleUnauthorized]);

    React.useEffect(() => {
      const storedToken = readStoredToken();
      if (!storedToken) {
        setBooting(false);
        return;
      }

      apiRequest('/auth/me', { token: storedToken, onUnauthorized: handleUnauthorized })
        .then((data) => {
          setToken(storedToken);
          setDriver(data.driver);
        })
        .catch(() => {
          clearStoredToken();
          setToken('');
          setDriver(null);
        })
        .finally(() => setBooting(false));
    }, [handleUnauthorized]);

    const onLogin = async (credentials) => {
      setBusy(true);
      setLoginError('');
      try {
        const data = await apiRequest('/auth/login', { method: 'POST', body: credentials, auth: false });
        writeStoredToken(data.token);
        setToken(data.token);
        setDriver(data.driver);
        navigate('inicio');
        showToast('Bem-vindo ao novo portal.');
      } catch (error) {
        setLoginError(error.message);
      } finally {
        setBusy(false);
      }
    };

    const onLogout = async () => {
      try {
        if (token) {
          await request('/auth/logout', { method: 'POST' });
        }
      } catch (error) {
      }
      clearStoredToken();
      setToken('');
      setDriver(null);
      setLoginError('');
      navigate('inicio');
    };

    if (booting) return <LoadingState label="Reconectando ao portal..." />;
    if (!token || !driver) return <><Pages.LoginPage onLogin={onLogin} busy={busy} error={loginError} /><Toast toast={toast} onClose={() => setToast(null)} /></>;

    return (
      <>
        <Shell driver={driver} route={route} navigate={navigate} request={request} onLogout={onLogout} showToast={showToast} />
        <Toast toast={toast} onClose={() => setToast(null)} />
      </>
    );
  }

  ReactDOM.createRoot(document.getElementById('root')).render(<App />);
})();