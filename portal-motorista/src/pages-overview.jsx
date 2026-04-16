(() => {
  const { Button, EmptyState, Icon, LoadingState, Modal, RequestErrorState, buildQueryString, greeting, useRemoteData } = window.PortalMotoristaShared;
  const Pages = (window.PortalMotoristaPages = window.PortalMotoristaPages || {});

  function Pagination({ pagination, onPageChange }) {
    if (!pagination || pagination.total_pages <= 1) return null;
    return (
      <div className="pm-pagination">
        <Button variant="ghost" disabled={!pagination.has_previous} onClick={() => onPageChange(pagination.page - 1)}>Anterior</Button>
        <span>Pagina {pagination.page} de {pagination.total_pages}</span>
        <Button variant="ghost" disabled={!pagination.has_next} onClick={() => onPageChange(pagination.page + 1)}>Proxima</Button>
      </div>
    );
  }

  Pages.DashboardPage = function DashboardPage({ request, driver, navigate, showToast }) {
    const { data, error, isInitialLoading, reload } = useRemoteData(React.useCallback(() => request('/dashboard'), [request]));

    if (isInitialLoading) return <LoadingState label="Carregando painel..." />;
    if (error) return <RequestErrorState title="Nao foi possivel carregar o painel" description={error} onRetry={reload} />;

    const actions = [
      { key: 'cargas', label: 'Buscar cargas', icon: 'search' },
      { key: 'andamento', label: 'Viagens em andamento', icon: 'route' },
      { key: 'documentos', label: 'Regularizar documentos', icon: 'docs' },
      { key: 'solicitacoes', label: 'Minhas solicitacoes', icon: 'clipboard' },
    ];

    return (
      <div className="pm-page-stack">
        <section className="pm-hero-card">
          <div>
            <p className="pm-eyebrow">Portal do Motorista</p>
            <h2>{greeting(driver.primeiro_nome)}</h2>
            <p>Seu portal novo esta falando direto com a API dedicada do motorista. O backoffice continua em Adianti, mas a experiencia aqui ficou mais fluida para o dia a dia.</p>
          </div>
          <div className="pm-hero-badge"><Icon name="bell" /></div>
        </section>

        <section className="pm-grid pm-grid-kpis">
          <article className="pm-kpi-card"><span>Cargas</span><strong>{data.kpis.cargas_disponiveis}</strong></article>
          <article className="pm-kpi-card"><span>Em andamento</span><strong>{data.kpis.em_andamento}</strong></article>
          <article className="pm-kpi-card"><span>Solicitacoes</span><strong>{data.kpis.solicitacoes}</strong></article>
          <article className="pm-kpi-card"><span>Documentos</span><strong>{data.kpis.documentos}</strong></article>
        </section>

        {data.alerts?.length ? (
          <section className="pm-warning-card">
            <div className="pm-section-title"><Icon name="alert" /><span>Pendencias de documentos</span></div>
            <ul className="pm-alert-list">
              {data.alerts.map((item) => <li key={item}>{item}</li>)}
            </ul>
            <Button onClick={() => navigate('documentos')}>Regularizar agora</Button>
          </section>
        ) : null}

        <section className="pm-section-card">
          <div className="pm-section-title"><Icon name="arrowRight" /><span>Acesso rapido</span></div>
          <div className="pm-quick-grid">
            {actions.map((action) => (
              <button key={action.key} type="button" className="pm-quick-card" onClick={() => { navigate(action.key); showToast(`${action.label} carregado.`); }}>
                <Icon name={action.icon} />
                <strong>{action.label}</strong>
              </button>
            ))}
          </div>
        </section>
      </div>
    );
  };

  Pages.CargasPage = function CargasPage({ request, showToast }) {
    const [filters, setFilters] = React.useState({ origem: '', destino: '', tipo_veiculo: '', page: 1 });
    const deferredFilters = React.useDeferredValue(filters);
    const [modalCargo, setModalCargo] = React.useState(null);
    const [submitBusy, setSubmitBusy] = React.useState(false);
    const [formState, setFormState] = React.useState({ veiculo_id: '', data_disponibilidade: '', mensagem: '' });

    const queryString = React.useMemo(() => {
      return buildQueryString({
        origem: deferredFilters.origem,
        destino: deferredFilters.destino,
        tipo_veiculo: deferredFilters.tipo_veiculo,
        page: deferredFilters.page,
      });
    }, [deferredFilters.destino, deferredFilters.origem, deferredFilters.page, deferredFilters.tipo_veiculo]);

    const { data, error, isRefreshing, loading, reload } = useRemoteData(React.useCallback(() => {
      return request(queryString ? `/cargas?${queryString}` : '/cargas');
    }, [queryString, request]));

    const updateFilter = React.useCallback((field, value) => {
      React.startTransition(() => {
        setFilters((previous) => ({ ...previous, [field]: value, page: 1 }));
      });
    }, []);

    const changePage = React.useCallback((page) => {
      React.startTransition(() => {
        setFilters((previous) => ({ ...previous, page }));
      });
    }, []);

    const closeSolicitacao = React.useCallback(() => {
      setModalCargo(null);
    }, []);

    const openSolicitacao = React.useCallback((cargo) => {
      const firstVehicle = data?.vehicle_options?.[0]?.id || '';
      setFormState({ veiculo_id: String(firstVehicle), data_disponibilidade: '', mensagem: '' });
      setModalCargo(cargo);
    }, [data]);

    const submitSolicitacao = React.useCallback(async (event) => {
      event.preventDefault();
      if (!modalCargo) return;

      setSubmitBusy(true);
      try {
        await request('/solicitacoes', {
          method: 'POST',
          body: {
            carga_id: modalCargo.id,
            veiculo_id: formState.veiculo_id || null,
            data_disponibilidade: formState.data_disponibilidade || null,
            mensagem: formState.mensagem,
          },
        });
        showToast('Solicitacao enviada com sucesso.');
        closeSolicitacao();
        reload();
      } catch (error) {
        showToast(error.message, 'error');
      } finally {
        setSubmitBusy(false);
      }
    }, [closeSolicitacao, formState, modalCargo, reload, request, showToast]);

    return (
      <div className="pm-page-stack">
        <section className="pm-section-card">
          <div className="pm-section-title"><Icon name="search" /><span>Buscar cargas disponiveis</span></div>
          <div className="pm-form-grid">
            <label className="pm-field"><span>Origem</span><input value={filters.origem} onChange={(event) => updateFilter('origem', event.target.value)} placeholder="Cidade ou origem" /></label>
            <label className="pm-field"><span>Destino</span><input value={filters.destino} onChange={(event) => updateFilter('destino', event.target.value)} placeholder="Destino" /></label>
            <label className="pm-field"><span>Tipo de veiculo</span>
              <select value={filters.tipo_veiculo} onChange={(event) => updateFilter('tipo_veiculo', event.target.value)}>
                <option value="">Todos</option>
                {data?.filters?.tipo_veiculo_options?.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
              </select>
            </label>
          </div>
        </section>

        {loading ? <LoadingState label={isRefreshing ? 'Atualizando cargas...' : 'Carregando cargas...'} /> : null}
        {error ? <RequestErrorState title="Nao foi possivel carregar as cargas" description={error} onRetry={reload} /> : null}

        {data?.items?.length ? (
          <>
            <section className="pm-grid pm-grid-cards">
              {data.items.map((cargo) => (
                <article key={cargo.id} className="pm-cargo-card">
                  <div className="pm-card-head">
                    <div>
                      <p className="pm-eyebrow">{cargo.tipo_carga_label}</p>
                      <h3>{cargo.origem} <span>&rarr;</span> {cargo.destino}</h3>
                    </div>
                    {cargo.is_urgent ? <span className="pm-badge pm-badge-danger">Urgente</span> : null}
                  </div>
                  <div className="pm-info-list">
                    <p><strong>Veiculo:</strong> {cargo.tipo_veiculo_label}</p>
                    <p><strong>Coleta:</strong> {cargo.data_coleta_label}</p>
                    <p><strong>Entrega:</strong> {cargo.data_entrega_prevista_label}</p>
                    <p><strong>Peso:</strong> {cargo.peso_estimado_label}</p>
                    <p><strong>Frete:</strong> {cargo.valor_frete_label}</p>
                  </div>
                  <p className="pm-muted-copy">{cargo.descricao || 'Sem descricao adicional para esta carga.'}</p>
                  <div className="pm-card-actions">
                    {cargo.localizacao_maps ? <a className="pm-text-link" href={cargo.localizacao_maps} target="_blank" rel="noreferrer">Ver mapa</a> : <span />}
                    <Button variant={cargo.has_pending_request ? 'ghost' : 'primary'} disabled={cargo.has_pending_request} onClick={() => openSolicitacao(cargo)}>
                      {cargo.has_pending_request ? 'Solicitacao pendente' : 'Solicitar'}
                    </Button>
                  </div>
                </article>
              ))}
            </section>
            <Pagination pagination={data.pagination} onPageChange={changePage} />
          </>
        ) : null}

        {!loading && data && !data.items.length ? (
          <EmptyState title="Nenhuma carga encontrada" description="Ajuste os filtros ou aguarde novas disponibilidades no portal." />
        ) : null}

        <Modal open={Boolean(modalCargo)} title="Solicitar carga" onClose={closeSolicitacao}>
          {modalCargo ? (
            <form className="pm-page-stack" onSubmit={submitSolicitacao}>
              <div className="pm-detail-card">
                <p className="pm-eyebrow">Carga selecionada</p>
                <strong>{modalCargo.origem} &rarr; {modalCargo.destino}</strong>
                <span>{modalCargo.tipo_veiculo_label}</span>
              </div>
              <label className="pm-field"><span>Veiculo</span>
                <select value={formState.veiculo_id} onChange={(event) => setFormState((previous) => ({ ...previous, veiculo_id: event.target.value }))}>
                  <option value="">Nao informar agora</option>
                  {data?.vehicle_options?.map((vehicle) => <option key={vehicle.id} value={vehicle.id}>{vehicle.label}</option>)}
                </select>
              </label>
              <label className="pm-field"><span>Disponivel a partir de</span><input type="date" value={formState.data_disponibilidade} onChange={(event) => setFormState((previous) => ({ ...previous, data_disponibilidade: event.target.value }))} /></label>
              <label className="pm-field"><span>Mensagem</span><textarea rows="4" value={formState.mensagem} onChange={(event) => setFormState((previous) => ({ ...previous, mensagem: event.target.value }))} placeholder="Descreva disponibilidade, experiencia ou observacoes." /></label>
              <div className="pm-inline-actions">
                <Button variant="ghost" onClick={closeSolicitacao} disabled={submitBusy}>Cancelar</Button>
                <Button type="submit" disabled={submitBusy}>{submitBusy ? 'Enviando...' : 'Enviar solicitacao'}</Button>
              </div>
            </form>
          ) : null}
        </Modal>
      </div>
    );
  };

  Pages.SolicitacoesPage = function SolicitacoesPage({ request, showToast }) {
    const [filters, setFilters] = React.useState({ status: '', page: 1 });
    const [pendingCancelId, setPendingCancelId] = React.useState(null);

    const queryString = React.useMemo(() => {
      return buildQueryString({ status: filters.status, page: filters.page });
    }, [filters.page, filters.status]);

    const { data, error, isRefreshing, loading, reload } = useRemoteData(React.useCallback(() => {
      return request(queryString ? `/solicitacoes?${queryString}` : '/solicitacoes');
    }, [queryString, request]));

    const updateFilters = React.useCallback((nextFilters) => {
      React.startTransition(() => {
        setFilters(nextFilters);
      });
    }, []);

    const changePage = React.useCallback((page) => {
      React.startTransition(() => {
        setFilters((previous) => ({ ...previous, page }));
      });
    }, []);

    const cancelRequest = React.useCallback(async (item) => {
      if (!window.confirm('Deseja realmente cancelar esta solicitacao?')) return;

      setPendingCancelId(item.id);
      try {
        await request(`/solicitacoes/${item.id}/cancelar`, { method: 'POST' });
        showToast('Solicitacao cancelada.');
        reload();
      } catch (error) {
        showToast(error.message, 'error');
      } finally {
        setPendingCancelId(null);
      }
    }, [reload, request, showToast]);

    return (
      <div className="pm-page-stack">
        {loading ? <LoadingState label={isRefreshing ? 'Atualizando solicitacoes...' : 'Carregando solicitacoes...'} /> : null}
        {error ? <RequestErrorState title="Nao foi possivel carregar as solicitacoes" description={error} onRetry={reload} /> : null}
        {data ? (
          <>
            <section className="pm-grid pm-grid-kpis">
              <article className="pm-kpi-card"><span>Total</span><strong>{data.stats.total}</strong></article>
              <article className="pm-kpi-card"><span>Pendentes</span><strong>{data.stats.pendentes}</strong></article>
              <article className="pm-kpi-card"><span>Aprovadas</span><strong>{data.stats.aprovadas}</strong></article>
              <article className="pm-kpi-card"><span>Recusadas</span><strong>{data.stats.recusadas}</strong></article>
            </section>
            <section className="pm-section-card">
              <div className="pm-form-grid pm-form-grid-compact">
                <label className="pm-field"><span>Status</span>
                  <select value={filters.status} onChange={(event) => updateFilters({ status: event.target.value, page: 1 })}>
                    <option value="">Todos</option>
                    {data.status_options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                  </select>
                </label>
              </div>
            </section>
            {data.items.length ? data.items.map((item) => (
              <article key={item.id} className="pm-section-card pm-request-card">
                <div className="pm-card-head">
                  <div>
                    <p className="pm-eyebrow">Solicitacao #{item.id}</p>
                    <h3>{item.rota}</h3>
                  </div>
                  <span className={`pm-badge pm-badge-${item.status}`}>{item.status_label}</span>
                </div>
                <div className="pm-info-list">
                  <p><strong>Veiculo:</strong> {item.placa_trator || 'Nao informado'}</p>
                  <p><strong>Disponibilidade:</strong> {item.data_disponibilidade_label}</p>
                  <p><strong>Enviado em:</strong> {item.created_at_label}</p>
                </div>
                <p className="pm-muted-copy">{item.mensagem || 'Sem mensagem adicional.'}</p>
                {item.resposta_admin ? <div className="pm-inline-alert">Resposta do administrativo: {item.resposta_admin}</div> : null}
                {item.can_cancel ? (
                  <div className="pm-card-actions">
                    <span />
                    <Button variant="ghost" onClick={() => cancelRequest(item)} disabled={pendingCancelId === item.id}>
                      {pendingCancelId === item.id ? 'Cancelando...' : 'Cancelar'}
                    </Button>
                  </div>
                ) : null}
              </article>
            )) : <EmptyState title="Nenhuma solicitacao encontrada" description="Suas solicitacoes aparecerao aqui assim que forem enviadas." />}
            <Pagination pagination={data.pagination} onPageChange={changePage} />
          </>
        ) : null}
      </div>
    );
  };
})();
