<?php

class ConhecimentoList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded;

    public function __construct()
    {
        parent::__construct();

        // FORMULARIO DE FILTRO
        $this->form = new BootstrapFormBuilder('form_search_conhecimento');
        $this->form->setFormTitle('CRTs - Conhecimentos de Transporte');
        $id             = new TEntry('id');
        $numero         = new TEntry('numero');
        $status_crt_id  = new TDBCombo('status_crt_id', 'sample', 'StatusCrt', 'id', 'nome');
        $nome_remetente = new TEntry('nome_remetente');

        foreach ([$id, $numero, $status_crt_id, $nome_remetente] as $field) {
            $field->setSize('100%');
        }

        $this->form->addFields([new TLabel('ID')], [$id],
                               [new TLabel('Numero CRT')], [$numero]);
        $this->form->addFields([new TLabel('Status')], [$status_crt_id],
                               [new TLabel('Remetente')], [$nome_remetente]);

        $this->form->addAction('Filtrar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addAction('Novo CRT', new TAction([$this, 'onNumerarCrt']), 'fa:plus green');

        $this->form->addAction('Recarregar', new TAction([$this, 'onReload']), 'fa:refresh');

        $this->form->setData(TSession::getValue(__CLASS__ . '_filter_data'));

        // DATAGRID
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->datatable = 'true';

        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'center', '5%'));

        $colPermisso = new TDataGridColumn('permisso_id', 'Permissao', 'left', '10%');
        $colPermisso->setTransformer(function ($value) {
            try {
                return (new Permisso($value))->permisso ?? '-';
            } catch (Exception $e) {
                return '-';
            }
        });
        $this->datagrid->addColumn($colPermisso);

        $this->datagrid->addColumn(new TDataGridColumn('numero', 'CRT', 'left', '10%'));

        $colData = new TDataGridColumn('data_transportador_assinatura', 'Data Transportador', 'center', '15%');
        $colData->setTransformer(function ($value) {
            return $value ? TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy') : '';
        });
        $this->datagrid->addColumn($colData);

        $colStatus = new TDataGridColumn('status_crt_id', 'Status', 'left', '10%');
        $colStatus->setTransformer(function ($value) {
            try {
                $status = new StatusCrt($value);
                return "<span style='color:{$status->cor};font-weight:bold;'>{$status->descricao}</span>";
            } catch (Exception $e) {
                return '-';
            }
        });
        $this->datagrid->addColumn($colStatus);

        $colRemetente = new TDataGridColumn('remetente->nome', 'Remetente', 'left', '25%');
        $colRemetente->setTransformer(function ($val, $obj) {
            return $obj->remetente->nome ?? '-';
        });
        $this->datagrid->addColumn($colRemetente);

        $colDestinatario = new TDataGridColumn('destinatario->nome', 'Destinatario', 'left', '25%');
        $colDestinatario->setTransformer(function ($val, $obj) {
            return $obj->destinatario->nome ?? '-';
        });
        $this->datagrid->addColumn($colDestinatario);

        // ACOES
        $actionEdit = new TDataGridAction(['ConhecimentoForm', 'onEdit'], ['key' => '{id}']);
        $this->datagrid->addAction($actionEdit, 'Editar', 'fa:edit blue');

        $actionDelete = new TDataGridAction([$this, 'onDelete'], ['key' => '{id}']);
        $this->datagrid->addAction($actionDelete, 'Excluir', 'fa:trash red');

        $actionPrint = new TDataGridAction([$this, 'onPrint'], ['key' => '{id}']);
        $this->datagrid->addAction($actionPrint, 'Imprimir', 'fa:print green');


        $actionCopy = new TDataGridAction([$this, 'onCopy'], ['key' => '{id}']);
        $actionCopy->setDisplayCondition(function ($obj) {
            return $obj->copiacrt === '1';
        });
        $this->datagrid->addAction($actionCopy, 'Copiar', 'fa:copy orange');

        $this->datagrid->createModel();

        // PAGINACAO
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth('100%');

        // CONTAINER FINAL
        $panel = new TPanelGroup('Listagem de CRTs');
        $panel->add($this->form);
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($panel);

        parent::add($container);

        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public function onSearch($param = null)
    {
        $data = $this->form->getData();

        TSession::setValue(__CLASS__.'_filter_id', $data->id ? new TFilter('id', '=', $data->id) : null);
        TSession::setValue(__CLASS__.'_filter_numero', $data->numero ? new TFilter('numero', 'like', "%{$data->numero}%") : null);
        TSession::setValue(__CLASS__.'_filter_status_crt_id', $data->status_crt_id ? new TFilter('status_crt_id', '=', $data->status_crt_id) : null);
        TSession::setValue(__CLASS__.'_filter_nome_remetente', $data->nome_remetente ? new TFilter('nome_remetente', 'like', "%{$data->nome_remetente}%") : null);

        TSession::setValue(__CLASS__.'_filter_data', $data);
        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');
            TTransaction::get()->exec('PRAGMA foreign_keys = ON');

            $repo = new TRepository('Conhecimento');
            $limit = 10;
            $criteria = new TCriteria;
            $criteria->setProperties($param);
            $criteria->setProperty('limit', $limit);

            foreach (['_filter_id','_filter_numero','_filter_status_crt_id','_filter_nome_remetente'] as $session_filter) {
                $filter = TSession::getValue(__CLASS__.$session_filter);
                if ($filter) {
                    $criteria->add($filter);
                }
            }

            $this->datagrid->clear();
            $objects = $repo->load($criteria, FALSE);
            if ($objects) {
                foreach ($objects as $object) {
                    $this->datagrid->addItem($object);
                }
            }

            $criteria->resetProperties();
            $count = $repo->count($criteria);
            $this->pageNavigation->setCount($count);
            $this->pageNavigation->setProperties($param);
            $this->pageNavigation->setLimit($limit);

            TTransaction::close();
            $this->loaded = true;
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public static function onDelete($param)
    {
        $action = new TAction([__CLASS__, 'delete'], $param);
        new TQuestion('Deseja realmente excluir este CRT?', $action);
    }

    public static function delete($param)
    {
        try {
            TTransaction::open('sample');
            TTransaction::get()->exec('PRAGMA foreign_keys = ON');

            $object = new Conhecimento($param['key']);
            $object->delete();

            TTransaction::close();
            new TMessage('info', 'Registro excluido com sucesso!', new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao excluir: ' . $e->getMessage());
        }
    }

    public function onPrint($param)
    {
        try {
            $pdf = new ConhecimentoPDFGenerator($param['key']);
            $pdf->gerarPDFArquivo();
        } catch (Exception $e) {
            new TMessage('error', 'Erro: ' . $e->getMessage());
        }
    }

    public static function onCopy($param)
    {
        try {
            TTransaction::open('sample');
            TTransaction::get()->exec('PRAGMA foreign_keys = ON');

            $original = new Conhecimento($param['key']);
            $permissao = new Permisso($original->permisso_id);

            $novoNumero = (int)$permissao->numerocrt + 1;
            $permissao->numerocrt = $novoNumero;
            $permissao->store();

            $copy = new Conhecimento;
            foreach ($original->toArray() as $attr => $val) {
                if ($attr !== 'id') {
                    $copy->$attr = $val;
                }
            }
            $copy->numero = $permissao->permisso . str_pad($novoNumero, 5, '0', STR_PAD_LEFT);
            $copy->copiacrt = null;
            $copy->store();

            $original->copiacrt = null;
            $original->store();

            TTransaction::close();
            new TMessage('info', 'CRT copiado com sucesso!', new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao copiar: ' . $e->getMessage());
        }
    }

    public static function onNumerarCrt()
    {
        try {
            TTransaction::open('sample');

            $form = new BootstrapFormBuilder('form_novo_crt');
            $form->setFormTitle('Novo CRT');
            $form->setProperty('style', 'width: 50%');

            $permisso_id = new TDBCombo('permisso_id', 'sample', 'Permisso', 'id', 'permisso');
            $permisso_id->enableSearch();
            $permisso_id->setSize('100%');

            $form->addFields([new TLabel('Permissao')], [$permisso_id]);

            $form->addAction('Gerar', new TAction([__CLASS__, 'gerarCrt']), 'fa:check green');
            $form->addAction('Cancelar', new TAction([__CLASS__, 'closeWindow']), 'fa:times red');

            new TInputDialog('form_novo_crt', $form);
            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public static function gerarCrt($param)
    {
        try {
            TTransaction::open('sample');
            TTransaction::get()->exec('PRAGMA foreign_keys = ON');

            $permissao = new Permisso($param['permisso_id']);
            $novoNumero = (int)$permissao->numerocrt + 1;
            $permissao->numerocrt = $novoNumero;
            $permissao->store();

            $crt = new Conhecimento;
            $crt->permisso_id = $permissao->id;
            $crt->numero = $permissao->permisso . str_pad($novoNumero, 5, '0', STR_PAD_LEFT);
            $crt->status_crt_id = 1;
            $crt->store();

            TTransaction::close();
            TWindow::closeWindow('form_novo_crt');
            new TMessage('info', 'CRT criado com sucesso!', new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro: ' . $e->getMessage());
        }
    }

    public static function closeWindow()
    {
        TWindow::closeWindow('form_novo_crt');
    }

    // ---------------------------------------------------------------
    // Importação de CRT via XML
    // ---------------------------------------------------------------

    public static function onImportXml($param = null)
    {
        $modelo = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<CartaPorteInternacional>
  <InformacoesGerais>
    <Numero></Numero>
    <DataEmissao></DataEmissao>
    <Permisso></Permisso>
    <FaturaCRT></FaturaCRT>
    <CopiarCRT>N</CopiarCRT>
    <Assinatura></Assinatura>
  </InformacoesGerais>
  <Remetente>
    <Nome></Nome>
    <Endereco></Endereco>
    <Cnpj></Cnpj>
    <Email></Email>
    <Telefone></Telefone>
    <Cidade></Cidade>
    <Estado></Estado>
    <Cep></Cep>
    <InscricaoEstadual></InscricaoEstadual>
    <Atividade></Atividade>
    <EmissaoCrt></EmissaoCrt>
    <Tipo>EXPORTADOR</Tipo>
  </Remetente>
  <Destinatario>
    <Nome></Nome>
    <Endereco></Endereco>
    <Cnpj></Cnpj>
    <Email></Email>
    <Telefone></Telefone>
    <Cidade></Cidade>
    <Estado></Estado>
    <Cep></Cep>
    <InscricaoEstadual></InscricaoEstadual>
    <Atividade></Atividade>
    <EmissaoCrt></EmissaoCrt>
    <Tipo>IMPORTADOR</Tipo>
  </Destinatario>
  <Consignatario>
    <Nome></Nome>
    <Endereco></Endereco>
    <Cnpj></Cnpj>
    <Email></Email>
    <Telefone></Telefone>
    <Cidade></Cidade>
    <Estado></Estado>
    <Cep></Cep>
    <InscricaoEstadual></InscricaoEstadual>
    <Atividade></Atividade>
    <EmissaoCrt></EmissaoCrt>
    <Tipo>CONSIGNATARIO</Tipo>
  </Consignatario>
  <Notificar>
    <Nome></Nome>
    <Endereco></Endereco>
    <Cnpj></Cnpj>
    <Email></Email>
    <Telefone></Telefone>
    <Cidade></Cidade>
    <Estado></Estado>
    <Cep></Cep>
    <InscricaoEstadual></InscricaoEstadual>
    <Atividade></Atividade>
    <EmissaoCrt></EmissaoCrt>
    <Tipo>NOTIFICAR</Tipo>
  </Notificar>
  <Locais>
    <Emissao></Emissao>
    <Responsabilidade></Responsabilidade>
    <Entrega></Entrega>
  </Locais>
  <Carga>
    <Descricao></Descricao>
    <PesoBrutoKg></PesoBrutoKg>
    <PesoLiquidoKg></PesoLiquidoKg>
    <VolumeM3></VolumeM3>
    <QuantidadeVolumes></QuantidadeVolumes>
    <EspecieVolume></EspecieVolume>
    <Incoterm></Incoterm>
    <Incoterm16></Incoterm16>
    <MoedaMercadoria></MoedaMercadoria>
    <ValorMercadoria></ValorMercadoria>
    <ValorDeclarado></ValorDeclarado>
    <ValorReembolso></ValorReembolso>
  </Carga>
  <Frete>
    <Moeda></Moeda>
    <ValorExterno></ValorExterno>
  </Frete>
  <Custos>
    <Moeda></Moeda>
    <Item1>
      <Descricao></Descricao>
      <CustoRemetente></CustoRemetente>
      <CustoDestinatario></CustoDestinatario>
    </Item1>
    <Item2>
      <Descricao></Descricao>
      <CustoRemetente></CustoRemetente>
      <CustoDestinatario></CustoDestinatario>
    </Item2>
    <Item3>
      <Descricao></Descricao>
      <CustoRemetente></CustoRemetente>
      <CustoDestinatario></CustoDestinatario>
    </Item3>
    <TotalRemetente></TotalRemetente>
    <TotalDestinatario></TotalDestinatario>
  </Custos>
  <Observacoes>
    <Observacoes></Observacoes>
    <InstrucoesAlfandega></InstrucoesAlfandega>
    <DocumentosAnexos></DocumentosAnexos>
  </Observacoes>
</CartaPorteInternacional>
XML;

        $win = TWindow::create('form_import_xml', 1, 1, 700, 560);
        $win->setTitle('Importar CRT via XML');

        $form = new BootstrapFormBuilder('form_import_xml');
        $form->setFormTitle('Cole o XML abaixo e clique em Importar');
        $form->style = 'padding:10px';

        $xml_content = new TText('xml_content');
        $xml_content->setSize('100%', 380);
        $xml_content->setValue($modelo);
        $xml_content->setProperty('style', 'font-family:monospace;font-size:12px;resize:vertical;');

        $form->addFields([$xml_content]);
        $form->addAction('Importar', new TAction([__CLASS__, 'processImportXml']), 'fa:upload green');
        $form->addAction('Cancelar', new TAction([__CLASS__, 'closeImportWindow']), 'fa:times red');

        $win->add($form);
        $win->show();
    }

    public static function closeImportWindow()
    {
        TWindow::closeWindow('form_import_xml');
    }

    public static function processImportXml($param)
    {
        try {
            $xmlContent = trim($param['xml_content'] ?? '');
            if (empty($xmlContent)) {
                throw new Exception('Nenhum conteúdo XML informado.');
            }

            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            if (!$dom->loadXML($xmlContent)) {
                $erros = libxml_get_errors();
                libxml_clear_errors();
                $msg = 'XML inválido';
                if (!empty($erros)) {
                    $msg .= ': ' . trim($erros[0]->message);
                }
                throw new Exception($msg);
            }
            libxml_clear_errors();

            $root = $dom->documentElement;
            if ($root->tagName !== 'CartaPorteInternacional') {
                throw new Exception('Formato inválido: elemento raiz esperado é <CartaPorteInternacional>.');
            }

            $xp = new DOMXPath($dom);

            $g = function (string $query) use ($xp): string {
                $nodes = $xp->query($query);
                return ($nodes && $nodes->length > 0) ? trim($nodes->item(0)->nodeValue) : '';
            };

            TTransaction::open('sample');
            TTransaction::get()->exec('PRAGMA foreign_keys = ON');

            Clientes::ensureSchema();

            // ---------- Helper: encontra cliente por CNPJ ou cria novo ----------
            $findOrCreateClient = function (string $section, string $tipoDefault) use ($g): ?int {
                $nome = $g("//{$section}/Nome");
                if (empty($nome)) return null;

                $cnpj = $g("//{$section}/Cnpj");

                // Tentar encontrar por CNPJ se informado
                if (!empty($cnpj)) {
                    $repo = new TRepository('Clientes');
                    $crit = new TCriteria;
                    $crit->add(new TFilter('cnpj', '=', $cnpj));
                    $found = $repo->load($crit);
                    if (!empty($found)) {
                        return (int) $found[0]->id;
                    }
                }

                // Não encontrou: criar novo cliente
                $cli = new Clientes;
                $cli->nome               = strtoupper($nome);
                $cli->endereco           = strtoupper($g("//{$section}/Endereco"));
                $cli->cnpj               = $cnpj;
                $cli->email              = $g("//{$section}/Email");
                $cli->telefone           = $g("//{$section}/Telefone");
                $cli->cidade             = strtoupper($g("//{$section}/Cidade"));
                $cli->estado             = strtoupper($g("//{$section}/Estado"));
                $cli->cep                = $g("//{$section}/Cep");
                $cli->inscricao_estadual = $g("//{$section}/InscricaoEstadual");
                $cli->atividade          = strtoupper($g("//{$section}/Atividade"));
                $cli->emissao_crt        = $g("//{$section}/EmissaoCrt");
                $cli->tipo               = strtoupper($g("//{$section}/Tipo")) ?: $tipoDefault;
                $cli->store();
                return (int) $cli->id;
            };

            $remetente_id    = $findOrCreateClient('Remetente',    'EXPORTADOR');
            $destinatario_id = $findOrCreateClient('Destinatario', 'IMPORTADOR');
            $consig_id       = $findOrCreateClient('Consignatario','CONSIGNATARIO');
            $notif_id        = $findOrCreateClient('Notificar',    'NOTIFICAR');

            $crt = new Conhecimento;
            $crt->numero               = $g('//InformacoesGerais/Numero');
            $crt->fatura_crt           = $g('//InformacoesGerais/FaturaCRT');
            $crt->permisso             = $g('//InformacoesGerais/Permisso');
            $crt->assinatura_nome      = $g('//InformacoesGerais/Assinatura');
            $crt->copiacrt             = $g('//InformacoesGerais/CopiarCRT') === 'S' ? '1' : null;

            $dataEmissao = $g('//InformacoesGerais/DataEmissao');
            if ($dataEmissao) {
                $ts = strtotime($dataEmissao);
                $crt->data_transportador_assinatura = $ts ? date('Y-m-d', $ts) : null;
            }

            // Vincular clientes
            $crt->remetente_id          = $remetente_id;
            $crt->destinatario_id       = $destinatario_id;
            $crt->consignatario_id      = $consig_id;
            $crt->notificar_id          = $notif_id;

            $crt->nome_remetente          = $g('//Remetente/Nome');
            $crt->endereco_remetente      = $g('//Remetente/Endereco');
            $crt->nome_destinatario       = $g('//Destinatario/Nome');
            $crt->endereco_destinatario   = $g('//Destinatario/Endereco');
            $crt->nome_consignatario      = $g('//Consignatario/Nome');
            $crt->endereco_consignatario  = $g('//Consignatario/Endereco');
            $crt->notificar_nome          = $g('//Notificar/Nome');
            $crt->notificar_endereco      = $g('//Notificar/Endereco');

            $crt->local_emissao           = $g('//Locais/Emissao');
            $crt->local_responsabilidade  = $g('//Locais/Responsabilidade');
            $crt->local_entrega           = $g('//Locais/Entrega');

            $crt->descricao_mercadoria    = $g('//Carga/Descricao');
            $crt->peso_bruto_kg           = $g('//Carga/PesoBrutoKg');
            $crt->peso_liq_kg             = $g('//Carga/PesoLiquidoKg');
            $crt->volume_m3               = $g('//Carga/VolumeM3');
            $crt->quantidade_volumes      = $g('//Carga/QuantidadeVolumes');
            $crt->especie_vol             = $g('//Carga/EspecieVolume');
            $crt->incoterm                = $g('//Carga/Incoterm');
            $crt->incoterm16              = $g('//Carga/Incoterm16');
            $crt->moeda_valor_mercadorias = $g('//Carga/MoedaMercadoria');
            $crt->valor_mercadorias       = $g('//Carga/ValorMercadoria');
            $crt->valor_declarado         = $g('//Carga/ValorDeclarado');
            $crt->valor_reembolso         = $g('//Carga/ValorReembolso');

            $crt->moeda_frete_externo     = $g('//Frete/Moeda');
            $crt->valor_frete_externo     = $g('//Frete/ValorExterno');

            $crt->gastosmoeda             = $g('//Custos/Moeda');
            for ($i = 1; $i <= 3; $i++) {
                $crt->{"textogasto{$i}"}    = $g("//Custos/Item{$i}/Descricao");
                $crt->{"custoremetente{$i}"} = $g("//Custos/Item{$i}/CustoRemetente");
                $crt->{"custodestino{$i}"}   = $g("//Custos/Item{$i}/CustoDestinatario");
            }
            $crt->total_custo_remetente    = $g('//Custos/TotalRemetente');
            $crt->total_custo_destinatario = $g('//Custos/TotalDestinatario');

            $crt->observacoes             = $g('//Observacoes/Observacoes');
            $crt->instrucoes_alfandega    = $g('//Observacoes/InstrucoesAlfandega');
            $crt->documentos_anexos       = $g('//Observacoes/DocumentosAnexos');

            $crt->status_crt_id = 1; // status padrão: primeiro status

            // Tentar vincular permisso_id pelo campo permisso
            if (!empty($crt->permisso)) {
                $repo = new TRepository('Permisso');
                $criteria = new TCriteria;
                $criteria->add(new TFilter('permisso', '=', $crt->permisso));
                $results = $repo->load($criteria);
                if (!empty($results)) {
                    $crt->permisso_id = $results[0]->id;
                }
            }

            $crt->store();
            $newId = $crt->id;

            TTransaction::close();
            TWindow::closeWindow('form_import_xml');

            $clientMsg = array_filter([$remetente_id ? "Remetente ID:{$remetente_id}" : null,
                                       $destinatario_id ? "Destinatário ID:{$destinatario_id}" : null,
                                       $consig_id ? "Consignatário ID:{$consig_id}" : null]);
            $extra = $clientMsg ? ' | Clientes: ' . implode(', ', $clientMsg) : '';

            new TMessage('info', "CRT importado com sucesso! ID: {$newId}{$extra}",
                new TAction(['ConhecimentoForm', 'onEdit'], ['key' => $newId]));

        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            new TMessage('error', 'Erro ao importar XML: ' . $e->getMessage());
        }
    }
}


