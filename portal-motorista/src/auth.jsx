(() => {
  const { Button, Icon, maskPhone } = window.PortalMotoristaShared;
  const Pages = (window.PortalMotoristaPages = window.PortalMotoristaPages || {});

  Pages.LoginPage = function LoginPage({ onLogin, busy, error }) {
    const [telefone, setTelefone] = React.useState('');
    const [senha, setSenha] = React.useState('');
    const canSubmit = telefone.replace(/\D/g, '').length >= 10 && senha.trim().length > 0;

    const handleSubmit = async (event) => {
      event.preventDefault();
      if (!canSubmit || busy) return;
      await onLogin({ telefone, senha_portal: senha });
    };

    return (
      <div className="pm-login-screen">
        <div className="pm-login-panel">
          <div className="pm-login-brand">
            <div className="pm-brand-badge"><Icon name="truck" /></div>
            <p className="pm-eyebrow">Sulconex</p>
            <h1>Portal do Motorista -Sulconexlog</h1>
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
                autoFocus
                disabled={busy}
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
                disabled={busy}
              />
            </label>

            {error ? <div className="pm-inline-alert">{error}</div> : null}

            <Button type="submit" variant="primary" disabled={busy || !canSubmit} className="pm-btn-block">
              {busy ? 'Entrando...' : 'Entrar no portal'}
            </Button>

            <a href={window.PORTAL_MOTORISTA_CONFIG.cadastroRequestUrl} className="pm-secondary-link">
              Novo motorista ou solicitar cadastro
            </a>

            <a href={window.PORTAL_MOTORISTA_CONFIG.legacyLoginUrl} className="pm-back-link">
              Voltar ao sistema administrativo
            </a>
          </form>
        </div>
      </div>
    );
  };

  Pages.PasswordSetupPage = function PasswordSetupPage({ driver, onSubmit, onLogout, busy, error }) {
    const [novaSenha, setNovaSenha] = React.useState('');
    const [confirmacaoSenha, setConfirmacaoSenha] = React.useState('');
    const hasMismatch = confirmacaoSenha.length > 0 && novaSenha !== confirmacaoSenha;
    const localError = hasMismatch ? 'As senhas precisam ser iguais para continuar.' : '';
    const canSubmit = novaSenha.trim().length >= 6 && confirmacaoSenha.trim().length >= 6 && !hasMismatch;

    const handleSubmit = async (event) => {
      event.preventDefault();
      if (!canSubmit || busy) return;
      await onSubmit({ nova_senha: novaSenha, confirmacao_senha: confirmacaoSenha });
    };

    return (
      <div className="pm-login-screen">
        <div className="pm-login-panel">
          <div className="pm-login-brand">
            <div className="pm-brand-badge"><Icon name="lock" /></div>
            <p className="pm-eyebrow">Primeiro acesso</p>
            <h1>Defina sua senha definitiva</h1>
            <p className="pm-login-copy">Seu cadastro foi aprovado e a senha temporaria ja validou sua entrada. Antes de continuar, crie a senha que voce vai usar no dia a dia.</p>
            <div className="pm-inline-success">
              <strong>{driver?.nome || 'Motorista'}</strong>
              <div>{driver?.telefone || 'Telefone nao informado'}</div>
            </div>
          </div>

          <form className="pm-login-form" onSubmit={handleSubmit}>
            <p className="pm-support-copy">Escolha uma senha com pelo menos 6 caracteres. Depois disso, o acesso ao portal fica liberado normalmente.</p>

            <label className="pm-field">
              <span>Nova senha</span>
              <input
                type="password"
                placeholder="Digite sua nova senha"
                value={novaSenha}
                onChange={(event) => setNovaSenha(event.target.value)}
                autoComplete="new-password"
                autoFocus
                disabled={busy}
              />
            </label>

            <label className="pm-field">
              <span>Confirmar nova senha</span>
              <input
                type="password"
                placeholder="Repita a nova senha"
                value={confirmacaoSenha}
                onChange={(event) => setConfirmacaoSenha(event.target.value)}
                autoComplete="new-password"
                disabled={busy}
              />
            </label>

            {localError ? <div className="pm-inline-alert">{localError}</div> : null}
            {!localError && error ? <div className="pm-inline-alert">{error}</div> : null}

            <Button type="submit" variant="primary" disabled={busy || !canSubmit} className="pm-btn-block">
              {busy ? 'Salvando...' : 'Concluir primeiro acesso'}
            </Button>

            <button type="button" className="pm-back-link pm-back-link-button" onClick={onLogout} disabled={busy}>
              Sair e voltar depois
            </button>
          </form>
        </div>
      </div>
    );
  };
})();

