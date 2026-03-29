(() => {
  const { Button, Icon, LoadingState, EmptyState, Modal } = window.PortalMotoristaShared;
  const Pages = (window.PortalMotoristaPages = window.PortalMotoristaPages || {});

  Pages.AndamentoPage = function AndamentoPage({ request, showToast }) {
    const [state, setState] = React.useState({ loading: true, data: null, error: '' });
    const [modal, setModal] = React.useState({ open: false, contrato: null, arquivo: null, observacao: '' });

    const reload = React.useCallback ? React.useCallback(() => {
      request('/andamento')
        .then((data) => setState({ loading: false, data, error: '' }))
        .catch((error) => setState({ loading: false, data: null, error: error.message }));
    }, [request]) : () => {};

    React.useEffect(() => { reload(); }, [reload]);

    const sendLocation = (item) => {
      if (!navigator.geolocation) {
        showToast('Geolocalizacao nao suportada neste navegador.', 'error');
        return;
      }

      navigator.geolocation.getCurrentPosition(async (position) => {
        try {
          await request('/andamento/localizacao', {
            method: 'POST',
            body: {
              contrato_id: item.id,
              latitude: position.coords.latitude,
              longitude: position.coords.longitude,
              precisao: Math.round(position.coords.accuracy || 0),
            },
          });
          showToast('Localizacao enviada.');
          reload();
        } catch (error) {
          showToast(error.message, 'error');
        }
      }, () => showToast('Nao foi possivel obter sua localizacao.', 'error'), { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
    };

    const uploadComprovante = async (event) => {
      event.preventDefault();
      if (!modal.contrato || !modal.arquivo) {
        showToast('Selecione um comprovante.', 'error');
        return;
      }

      try {
        const formData = new FormData();
        formData.append('contrato_id', modal.contrato.id);
        formData.append('observacao', modal.observacao);
        formData.append('arquivo', modal.arquivo);
        await request('/andamento/comprovante', { method: 'POST', formData });
        showToast('Comprovante enviado com sucesso.');
        setModal({ open: false, contrato: null, arquivo: null, observacao: '' });
        reload();
      } catch (error) {
        showToast(error.message, 'error');
      }
    };

    if (state.loading) return <LoadingState label="Carregando andamento..." />;
    if (state.error) return <EmptyState title="Nao foi possivel carregar as viagens" description={state.error} />;
    if (!state.data.items.length) return <EmptyState title="Nenhuma carga em andamento" description="Quando um contrato estiver em curso ele aparecera aqui." />;

    return (
      <div className="pm-page-stack">
        {state.data.items.map((item) => (
          <article key={item.id} className="pm-section-card">
            <div className="pm-card-head">
              <div>
                <p className="pm-eyebrow">Contrato #{item.id}</p>
                <h3>{item.origem} &rarr; {item.destino}</h3>
              </div>
              <span className="pm-badge pm-badge-pendente">{item.status_label}</span>
            </div>
            <div className="pm-info-list pm-info-grid">
              <p><strong>CRT:</strong> {item.crt}</p>
              <p><strong>Veiculo:</strong> {item.placa_trator || '-'}</p>
              <p><strong>Emissao:</strong> {item.emissao_label}</p>
              <p><strong>Vencimento:</strong> {item.vencimento_label}</p>
              <p><strong>Saldo previsto:</strong> {item.saldo_previsto_label}</p>
            </div>
            {item.localizacao ? (
              <div className="pm-inline-success pm-location-status">
                <strong>Ultima localizacao enviada em {item.localizacao.created_at_label}.</strong>
                <span>{item.localizacao.localizacao_label || item.localizacao.coordenadas_label}</span>
                {item.localizacao.localizacao_detalhe ? <span className="pm-location-detail">{item.localizacao.localizacao_detalhe}</span> : null}
                {item.localizacao.maps_url ? <a className="pm-text-link pm-location-link" href={item.localizacao.maps_url} target="_blank" rel="noreferrer">Ver no mapa</a> : null}
              </div>
            ) : null}
            {item.comprovante ? <div className="pm-inline-success">Comprovante enviado em {item.comprovante.created_at_label}.</div> : null}
            <div className="pm-card-actions pm-card-actions-wrap">
              <Button variant="ghost" onClick={() => sendLocation(item)}>Enviar localizacao</Button>
              <Button variant="secondary" onClick={() => setModal({ open: true, contrato: item, arquivo: null, observacao: '' })}>Enviar comprovante</Button>
              <a className="pm-text-link" href={item.print_url} target="_blank" rel="noreferrer">Ver contrato</a>
            </div>
          </article>
        ))}
        <Modal open={modal.open} title="Comprovante de entrega" onClose={() => setModal({ open: false, contrato: null, arquivo: null, observacao: '' })}>
          <form className="pm-page-stack" onSubmit={uploadComprovante}>
            <div className="pm-detail-card">
              <p className="pm-eyebrow">Contrato selecionado</p>
              <strong>{modal.contrato?.origem} &rarr; {modal.contrato?.destino}</strong>
            </div>
            <label className="pm-field"><span>Arquivo</span><input type="file" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf" onChange={(e) => setModal((previous) => ({ ...previous, arquivo: e.target.files?.[0] || null }))} /></label>
            <label className="pm-field"><span>Observacao</span><textarea rows="4" value={modal.observacao} onChange={(e) => setModal((previous) => ({ ...previous, observacao: e.target.value }))} placeholder="Opcional" /></label>
            <div className="pm-inline-actions"><Button variant="ghost" onClick={() => setModal({ open: false, contrato: null, arquivo: null, observacao: '' })}>Cancelar</Button><Button type="submit">Enviar</Button></div>
          </form>
        </Modal>
      </div>
    );
  };

  Pages.DocumentosPage = function DocumentosPage({ request, showToast }) {
    const [selectedVehicleId, setSelectedVehicleId] = React.useState('');
    const [state, setState] = React.useState({ loading: true, data: null, error: '' });
    const [files, setFiles] = React.useState({});

    const reload = React.useCallback ? React.useCallback(() => {
      const query = selectedVehicleId ? `?veiculo_id=${selectedVehicleId}` : '';
      request(`/documentos${query}`)
        .then((data) => {
          setSelectedVehicleId(data.selected_vehicle_id ? String(data.selected_vehicle_id) : '');
          setState({ loading: false, data, error: '' });
        })
        .catch((error) => setState({ loading: false, data: null, error: error.message }));
    }, [request, selectedVehicleId]) : () => {};

    React.useEffect(() => { reload(); }, [reload]);

    const uploadDocument = async (slot) => {
      const file = files[slot.slot];
      if (!file) {
        showToast('Selecione um arquivo antes de enviar.', 'error');
        return;
      }

      try {
        const formData = new FormData();
        formData.append('tipo_documento', slot.type);
        if (slot.vehicle?.id) formData.append('veiculo_id', slot.vehicle.id);
        formData.append('arquivo', file);
        await request('/documentos', { method: 'POST', formData });
        setFiles((previous) => ({ ...previous, [slot.slot]: null }));
        showToast('Documento atualizado com sucesso.');
        reload();
      } catch (error) {
        showToast(error.message, 'error');
      }
    };

    const deleteDocument = async (record) => {
      if (!window.confirm('Deseja remover este documento?')) return;
      try {
        await request(`/documentos/${record.id}`, { method: 'DELETE' });
        showToast('Documento removido.');
        reload();
      } catch (error) {
        showToast(error.message, 'error');
      }
    };

    if (state.loading) return <LoadingState label="Carregando documentos..." />;
    if (state.error) return <EmptyState title="Nao foi possivel carregar os documentos" description={state.error} />;

    return (
      <div className="pm-page-stack">
        {state.data.alerts?.length ? <section className="pm-warning-card"><div className="pm-section-title"><Icon name="alert" /><span>Pendencias atuais</span></div><ul className="pm-alert-list">{state.data.alerts.map((item) => <li key={item}>{item}</li>)}</ul></section> : null}
        <section className="pm-section-card">
          <label className="pm-field"><span>Veiculo</span>
            <select value={selectedVehicleId} onChange={(e) => setSelectedVehicleId(e.target.value)}>
              <option value="">Selecione</option>
              {state.data.vehicles.map((vehicle) => <option key={vehicle.id} value={vehicle.id}>{vehicle.label}</option>)}
            </select>
          </label>
        </section>
        {state.data.documents.map((slot) => (
          <article key={slot.slot} className="pm-section-card">
            <div className="pm-card-head"><div><p className="pm-eyebrow">{slot.title}</p><h3>{slot.vehicle?.label || 'Documento pessoal'}</h3></div><span className={`pm-badge ${slot.record ? 'pm-badge-aprovado' : 'pm-badge-pendente'}`}>{slot.record ? 'Enviado' : 'Pendente'}</span></div>
            <p className="pm-muted-copy">{slot.hint}</p>
            {slot.record ? <div className="pm-inline-success">Arquivo atual: {slot.record.arquivo_original} ({slot.record.updated_at_label})</div> : null}
            <label className="pm-field"><span>Novo arquivo</span><input type="file" accept=".jpg,.jpeg,.png,.webp,.gif,.bmp,.svg,.pdf" onChange={(e) => setFiles((previous) => ({ ...previous, [slot.slot]: e.target.files?.[0] || null }))} /></label>
            <div className="pm-card-actions pm-card-actions-wrap">
              {slot.record ? <a className="pm-text-link" href={slot.record.download_url} target="_blank" rel="noreferrer">Visualizar arquivo</a> : <span />}
              <div className="pm-inline-actions">
                {slot.record ? <Button variant="ghost" onClick={() => deleteDocument(slot.record)}>Remover</Button> : null}
                <Button onClick={() => uploadDocument(slot)} disabled={!files[slot.slot]}>Enviar</Button>
              </div>
            </div>
          </article>
        ))}
      </div>
    );
  };

  Pages.ContratosPage = function ContratosPage({ request }) {
    const [state, setState] = React.useState({ loading: true, data: null, error: '' });

    React.useEffect(() => {
      let active = true;
      request('/contratos')
        .then((data) => active && setState({ loading: false, data, error: '' }))
        .catch((error) => active && setState({ loading: false, data: null, error: error.message }));
      return () => { active = false; };
    }, [request]);

    if (state.loading) return <LoadingState label="Carregando contratos..." />;
    if (state.error) return <EmptyState title="Nao foi possivel carregar os contratos" description={state.error} />;
    if (!state.data.items.length) return <EmptyState title="Nenhum contrato encontrado" description="Os contratos vinculados ao motorista aparecerao aqui." />;

    return (
      <div className="pm-page-stack">
        {state.data.items.map((item) => (
          <article key={item.id} className="pm-section-card">
            <div className="pm-card-head"><div><p className="pm-eyebrow">Contrato #{item.id}</p><h3>{item.rota}</h3></div><span className={`pm-badge ${item.status === 'pago' ? 'pm-badge-aprovado' : 'pm-badge-pendente'}`}>{item.status_label}</span></div>
            <div className="pm-info-list pm-info-grid">
              <p><strong>CRT:</strong> {item.conhecimento_numero || '-'}</p>
              <p><strong>Veiculo:</strong> {item.placa_trator || '-'}</p>
              <p><strong>Emissao:</strong> {item.emissao_label}</p>
              <p><strong>Vencimento:</strong> {item.vencimento_label}</p>
              <p><strong>Frete:</strong> {item.frete_label}</p>
              <p><strong>Saldo:</strong> {item.saldo_label}</p>
            </div>
            <div className="pm-card-actions"><span /><a className="pm-text-link" href={item.print_url} target="_blank" rel="noreferrer">Imprimir contrato</a></div>
          </article>
        ))}
      </div>
    );
  };
})();
