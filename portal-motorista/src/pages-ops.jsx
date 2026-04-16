(() => {
  const { Button, EmptyState, Icon, LoadingState, Modal, RequestErrorState, buildQueryString, useRemoteData } = window.PortalMotoristaShared;
  const Pages = (window.PortalMotoristaPages = window.PortalMotoristaPages || {});

  Pages.AndamentoPage = function AndamentoPage({ request, showToast }) {
    const { data, error, isInitialLoading, isRefreshing, reload } = useRemoteData(React.useCallback(() => request('/andamento'), [request]));
    const [modal, setModal] = React.useState({ open: false, contrato: null, arquivo: null, observacao: '' });
    const [locationBusyId, setLocationBusyId] = React.useState(null);
    const [uploadBusy, setUploadBusy] = React.useState(false);

    const closeModal = React.useCallback(() => {
      setModal({ open: false, contrato: null, arquivo: null, observacao: '' });
    }, []);

    const openUploadModal = React.useCallback((item) => {
      setModal({ open: true, contrato: item, arquivo: null, observacao: '' });
    }, []);

    const sendLocation = React.useCallback((item) => {
      if (!navigator.geolocation) {
        showToast('Geolocalizacao nao suportada neste navegador.', 'error');
        return;
      }

      setLocationBusyId(item.id);
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
        } finally {
          setLocationBusyId(null);
        }
      }, () => {
        setLocationBusyId(null);
        showToast('Nao foi possivel obter sua localizacao.', 'error');
      }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
    }, [reload, request, showToast]);

    const uploadComprovante = React.useCallback(async (event) => {
      event.preventDefault();
      if (!modal.contrato || !modal.arquivo) {
        showToast('Selecione um comprovante.', 'error');
        return;
      }

      setUploadBusy(true);
      try {
        const formData = new FormData();
        formData.append('contrato_id', modal.contrato.id);
        formData.append('observacao', modal.observacao);
        formData.append('arquivo', modal.arquivo);
        await request('/andamento/comprovante', { method: 'POST', formData });
        showToast('Comprovante enviado com sucesso.');
        closeModal();
        reload();
      } catch (error) {
        showToast(error.message, 'error');
      } finally {
        setUploadBusy(false);
      }
    }, [closeModal, modal.arquivo, modal.contrato, modal.observacao, reload, request, showToast]);

    if (isInitialLoading) return <LoadingState label="Carregando andamento..." />;
    if (error) return <RequestErrorState title="Nao foi possivel carregar as viagens" description={error} onRetry={reload} />;
    if (!data.items.length) return <EmptyState title="Nenhuma carga em andamento" description="Quando um contrato estiver em curso ele aparecera aqui." />;

    return (
      <div className="pm-page-stack">
        {isRefreshing ? <LoadingState label="Atualizando viagens..." /> : null}
        {data.items.map((item) => (
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
              <Button variant="danger" className="pm-location-button" onClick={() => sendLocation(item)} disabled={locationBusyId !== null}>
                {locationBusyId === item.id ? 'Enviando localizacao...' : 'Enviar localizacao'}
              </Button>
              <Button variant="secondary" onClick={() => openUploadModal(item)} disabled={uploadBusy}>Enviar comprovante</Button>
              <a className="pm-text-link" href={item.print_url} target="_blank" rel="noreferrer">Ver contrato</a>
            </div>
          </article>
        ))}
        <Modal open={modal.open} title="Comprovante de entrega" onClose={closeModal}>
          <form className="pm-page-stack" onSubmit={uploadComprovante}>
            <div className="pm-detail-card">
              <p className="pm-eyebrow">Contrato selecionado</p>
              <strong>{modal.contrato?.origem} &rarr; {modal.contrato?.destino}</strong>
            </div>
            <label className="pm-field"><span>Arquivo</span><input type="file" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf" onChange={(event) => setModal((previous) => ({ ...previous, arquivo: event.target.files?.[0] || null }))} disabled={uploadBusy} /></label>
            <label className="pm-field"><span>Observacao</span><textarea rows="4" value={modal.observacao} onChange={(event) => setModal((previous) => ({ ...previous, observacao: event.target.value }))} placeholder="Opcional" disabled={uploadBusy} /></label>
            <div className="pm-inline-actions">
              <Button variant="ghost" onClick={closeModal} disabled={uploadBusy}>Cancelar</Button>
              <Button type="submit" disabled={uploadBusy}>{uploadBusy ? 'Enviando...' : 'Enviar'}</Button>
            </div>
          </form>
        </Modal>
      </div>
    );
  };

  Pages.DocumentosPage = function DocumentosPage({ request, showToast }) {
    const [selectedVehicleId, setSelectedVehicleId] = React.useState(null);
    const [pendingUploadSlot, setPendingUploadSlot] = React.useState('');
    const [pendingDeleteId, setPendingDeleteId] = React.useState(null);
    const inputRefs = React.useRef({});

    const queryString = React.useMemo(() => buildQueryString({ veiculo_id: selectedVehicleId }), [selectedVehicleId]);

    const { data, error, isInitialLoading, isRefreshing, reload } = useRemoteData(React.useCallback(() => {
      return request(queryString ? `/documentos?${queryString}` : '/documentos');
    }, [queryString, request]));

    const documents = data?.documents || [];
    const vehicles = data?.vehicles || [];
    const activeVehicleValue = selectedVehicleId ?? String(data?.selected_vehicle_id || '');

    const activeVehicle = React.useMemo(() => {
      if (!activeVehicleValue) return null;
      return vehicles.find((vehicle) => String(vehicle.id) === String(activeVehicleValue)) || null;
    }, [activeVehicleValue, vehicles]);

    const personalDocuments = React.useMemo(() => documents.filter((slot) => !slot.requires_vehicle), [documents]);
    const vehicleDocuments = React.useMemo(() => documents.filter((slot) => slot.requires_vehicle), [documents]);
    const visibleDocuments = React.useMemo(() => (vehicles.length ? documents : personalDocuments), [documents, personalDocuments, vehicles.length]);
    const blockedCount = vehicles.length ? 0 : vehicleDocuments.length;
    const uploadedCount = React.useMemo(() => visibleDocuments.filter((slot) => Boolean(slot.record)).length, [visibleDocuments]);
    const pendingCount = visibleDocuments.length - uploadedCount;

    const openFilePicker = React.useCallback((slot) => {
      const input = inputRefs.current[slot.slot];
      if (input) input.click();
    }, []);

    const uploadDocument = React.useCallback(async (slot, file) => {
      if (!file) {
        return;
      }

      const vehicleId = activeVehicle?.id || slot.vehicle?.id || null;
      if (slot.requires_vehicle && !vehicleId) {
        showToast('Selecione um veiculo antes de enviar este arquivo.', 'error');
        return;
      }

      setPendingUploadSlot(slot.slot);
      try {
        const formData = new FormData();
        formData.append('tipo_documento', slot.type);
        if (vehicleId) formData.append('veiculo_id', vehicleId);
        formData.append('arquivo', file);
        await request('/documentos', { method: 'POST', formData });
        showToast(slot.kind === 'photo' ? 'Foto enviada com sucesso.' : 'Documento atualizado com sucesso.');
        reload();
      } catch (error) {
        showToast(error.message, 'error');
      } finally {
        setPendingUploadSlot('');
        const input = inputRefs.current[slot.slot];
        if (input) input.value = '';
      }
    }, [activeVehicle?.id, reload, request, showToast]);

    const handleFileChange = React.useCallback((slot, event) => {
      const file = event.target.files?.[0] || null;
      uploadDocument(slot, file);
    }, [uploadDocument]);

    const deleteDocument = React.useCallback(async (record) => {
      if (!window.confirm('Deseja remover este documento?')) return;

      setPendingDeleteId(record.id);
      try {
        await request(`/documentos/${record.id}`, { method: 'DELETE' });
        showToast('Documento removido.');
        reload();
      } catch (error) {
        showToast(error.message, 'error');
      } finally {
        setPendingDeleteId(null);
      }
    }, [reload, request, showToast]);

    if (isInitialLoading) return <LoadingState label="Carregando documentos..." />;
    if (error) return <RequestErrorState title="Nao foi possivel carregar os documentos" description={error} onRetry={reload} />;
    if (!documents.length) return <EmptyState title="Nenhum documento configurado" description="Os campos de envio ainda nao foram liberados para este portal." action={<Button onClick={reload}>Atualizar</Button>} />;

    const renderDocumentCard = (slot) => {
      const isPhoto = slot.kind === 'photo';
      const isUploading = pendingUploadSlot === slot.slot;
      const isDeleting = pendingDeleteId === slot.record?.id;
      const actionLabel = isUploading
        ? 'Enviando...'
        : isPhoto
          ? (slot.record ? 'Trocar foto' : 'Tirar ou enviar foto')
          : (slot.record ? 'Trocar arquivo' : 'Selecionar arquivo');
      const previewLabel = isPhoto ? 'Visualizar foto' : 'Visualizar arquivo';
      const helperCopy = isPhoto
        ? 'No celular, a camera pode abrir automaticamente para agilizar a foto.'
        : 'Aceita PDF ou imagem.';
      const contextLabel = slot.requires_vehicle
        ? (activeVehicle?.label || slot.vehicle?.label || 'Veiculo selecionado')
        : 'Documento do motorista';

      return (
        <article key={slot.slot} className="pm-section-card pm-doc-card">
          <div className="pm-doc-card-head">
            <div>
              <p className="pm-eyebrow">{slot.title}</p>
              <h3>{contextLabel}</h3>
            </div>
            <span className={`pm-badge ${slot.record ? 'pm-badge-aprovado' : 'pm-badge-pendente'}`}>{slot.record ? 'Enviado' : 'Pendente'}</span>
          </div>
          <p className="pm-doc-hint">{slot.hint}</p>
          {slot.requires_vehicle && activeVehicle ? <p className="pm-doc-vehicle">Veiculo ativo: {activeVehicle.label}</p> : null}
          {isPhoto ? <p className="pm-doc-caption">Use a camera do celular ou escolha uma foto da galeria.</p> : null}
          {slot.record ? (
            <div className="pm-inline-success">Arquivo atual: {slot.record.arquivo_original} ({slot.record.updated_at_label})</div>
          ) : (
            <div className="pm-doc-empty">Nenhum arquivo enviado ainda.</div>
          )}
          <input
            className="pm-hidden-input"
            ref={(element) => {
              if (element) {
                inputRefs.current[slot.slot] = element;
                return;
              }

              delete inputRefs.current[slot.slot];
            }}
            type="file"
            accept={slot.accept || '.jpg,.jpeg,.png,.webp,.gif,.bmp,.svg,.pdf'}
            capture={slot.capture || undefined}
            onChange={(event) => handleFileChange(slot, event)}
            disabled={isUploading || isDeleting || isRefreshing}
          />
          <div className="pm-card-actions pm-card-actions-wrap">
            <div className="pm-inline-actions">
              <Button variant={isPhoto ? 'secondary' : 'primary'} onClick={() => openFilePicker(slot)} disabled={isUploading || isDeleting || isRefreshing}>
                {actionLabel}
              </Button>
              {slot.record ? <Button variant="ghost" onClick={() => deleteDocument(slot.record)} disabled={isDeleting || isUploading || isRefreshing}>{isDeleting ? 'Removendo...' : 'Remover'}</Button> : null}
            </div>
            {slot.record ? (
              <a className="pm-text-link" href={slot.record.download_url} target="_blank" rel="noreferrer">{previewLabel}</a>
            ) : (
              <span className="pm-doc-action-copy">{helperCopy}</span>
            )}
          </div>
        </article>
      );
    };

    return (
      <div className="pm-page-stack">
        {isRefreshing ? <LoadingState label="Atualizando documentos..." /> : null}
        <section className="pm-hero-card pm-documents-hero">
          <div className="pm-documents-hero-copy">
            <div>
              <p className="pm-eyebrow">Documentos do cadastro</p>
              <h2>Envio rapido de documentos e fotos</h2>
            </div>
            <p>Escolha o veiculo uma vez e envie cada item direto pelo cartao. As fotos do veiculo foram separadas para ficar mais pratico no celular.</p>
          </div>
          <div className="pm-documents-stats">
            <div className="pm-doc-stat">
              <span>Enviados</span>
              <strong>{uploadedCount}/{visibleDocuments.length}</strong>
              <small>Itens ja atualizados no portal.</small>
            </div>
            <div className="pm-doc-stat">
              <span>Pendentes</span>
              <strong>{pendingCount}</strong>
              <small>{blockedCount ? `${blockedCount} itens aguardam veiculo para liberar o envio.` : 'Itens que ainda precisam de envio.'}</small>
            </div>
            <div className="pm-doc-stat">
              <span>Veiculo ativo</span>
              <strong>{activeVehicle?.placa_trator || 'Sem veiculo'}</strong>
              <small>{activeVehicle ? activeVehicle.label : 'Cadastre um veiculo para liberar documentos do conjunto.'}</small>
            </div>
          </div>
        </section>

        {data.alerts?.length ? (
          <section className="pm-warning-card">
            <div className="pm-section-title"><Icon name="alert" /><span>Pendencias atuais</span></div>
            <ul className="pm-alert-list">{data.alerts.map((item) => <li key={item}>{item}</li>)}</ul>
          </section>
        ) : null}

        {vehicles.length ? (
          <section className="pm-section-card pm-documents-panel">
            <div className="pm-section-title"><Icon name="truck" /><span>Escolha o veiculo para documentos e fotos</span></div>
            <div className="pm-vehicle-chip-list">
              {vehicles.map((vehicle) => {
                const isActive = String(vehicle.id) === String(activeVehicleValue);
                return (
                  <button
                    key={vehicle.id}
                    type="button"
                    className={`pm-vehicle-chip ${isActive ? 'is-active' : ''}`.trim()}
                    onClick={() => setSelectedVehicleId(String(vehicle.id))}
                  >
                    <strong>{vehicle.placa_trator || 'Sem placa'}</strong>
                    <span>{vehicle.label}</span>
                  </button>
                );
              })}
            </div>
          </section>
        ) : (
          <section className="pm-warning-card">
            <div className="pm-section-title"><Icon name="truck" /><span>Veiculo ainda nao vinculado</span></div>
            <p className="pm-muted-copy">Os documentos do motorista ja podem ser enviados. Para liberar cavalo, semi-reboque e fotos do conjunto, vincule um veiculo ao cadastro.</p>
          </section>
        )}

        {personalDocuments.length ? (
          <section className="pm-page-stack">
            <div className="pm-section-title"><Icon name="docs" /><span>Documentos do motorista</span></div>
            <div className="pm-documents-grid">
              {personalDocuments.map(renderDocumentCard)}
            </div>
          </section>
        ) : null}

        {vehicles.length && vehicleDocuments.length ? (
          <section className="pm-page-stack">
            <div className="pm-section-title"><Icon name="upload" /><span>Documentos e fotos do veiculo</span></div>
            <div className="pm-documents-grid">
              {vehicleDocuments.map(renderDocumentCard)}
            </div>
          </section>
        ) : null}
      </div>
    );
  };

  Pages.ContratosPage = function ContratosPage({ request }) {
    const { data, error, isInitialLoading, isRefreshing, reload } = useRemoteData(React.useCallback(() => request('/contratos'), [request]));

    if (isInitialLoading) return <LoadingState label="Carregando contratos..." />;
    if (error) return <RequestErrorState title="Nao foi possivel carregar os contratos" description={error} onRetry={reload} />;
    if (!data.items.length) return <EmptyState title="Nenhum contrato encontrado" description="Os contratos vinculados ao motorista aparecerao aqui." />;

    return (
      <div className="pm-page-stack">
        {isRefreshing ? <LoadingState label="Atualizando contratos..." /> : null}
        {data.items.map((item) => (
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

          </article>
        ))}
      </div>
    );
  };
})();
