(() => {
  const { Button, Icon, maskPhone } = window.PortalMotoristaShared;
  const Pages = (window.PortalMotoristaPages = window.PortalMotoristaPages || {});

  Pages.LoginPage = function LoginPage({ onLogin, busy, error }) {
    const [telefone, setTelefone] = React.useState('');
    const [senha, setSenha] = React.useState('');

    const handleSubmit = async (event) => {
      event.preventDefault();
      await onLogin({ telefone, senha_portal: senha });
    };

    return (
      <div className="pm-login-screen">
        <div className="pm-login-panel">
          <div className="pm-login-brand">
            <div className="pm-brand-badge"><Icon name="truck" /></div>
            <p className="pm-eyebrow">Sulconex</p>
            <h1>Portal do Motorista em React</h1>
            <p className="pm-login-copy">Acesse cargas, documentos, andamento e contratos em uma experiencia unica, responsiva e conectada pela nova API.</p>
          </div>

          <form className="pm-login-form" onSubmit={handleSubmit}>
            <label className="pm-field">
              <span>Telefone</span>
              <input
                type="tel"
                placeholder="(00) 00000-0000"
                value={telefone}
                onChange={(event) => setTelefone(maskPhone(event.target.value))}
                autoComplete="username"
              />
            </label>

            <label className="pm-field">
              <span>Senha</span>
              <input
                type="password"
                placeholder="Sua senha do portal"
                value={senha}
                onChange={(event) => setSenha(event.target.value)}
                autoComplete="current-password"
              />
            </label>

            {error ? <div className="pm-inline-alert">{error}</div> : null}

            <Button type="submit" variant="primary" disabled={busy} className="pm-btn-block">
              {busy ? 'Entrando...' : 'Entrar no portal'}
            </Button>

            <a href={window.PORTAL_MOTORISTA_CONFIG.legacyLoginUrl} className="pm-back-link">
              Voltar ao sistema administrativo
            </a>
          </form>
        </div>
      </div>
    );
  };
})();