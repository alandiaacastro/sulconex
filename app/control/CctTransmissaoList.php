<?php

/**
 * CctTransmissaoList
 * Lista de transmissões MIC/DTA ao Siscomex
 *
 * Funcionalidades:
 * - Listagem com filtros (data, status, CRT)
 * - Visualização de XML e resposta
 * - Reenvio de transmissões rejeitadas
 * - Exclusão de registros
 */
class CctTransmissaoList extends TPage
{
    private $form;
    private $datagrid;
    private $pagesize = 20;

    public function __construct()
    {
        parent::__construct();

        try {
            $this->setTitle("Histórico de Transmissões MIC/DTA");
            $this->setDescription("Acompanhamento de envios para Portal Único Siscomex");

            // Formulário de filtros
            $this->buildFilterForm();
            parent::add($this->form);

            // Datagrid de transmissões
            $this->buildDatagrid();
            parent::add($this->datagrid);

            // Botão Nova Transmissão
            $panel = new TPanel();
            $btn_novo = new TButton('btn_novo');
            $btn_novo->setLabel("Nova Transmissão");
            $btn_novo->setImage('fa:plus');
            $btn_novo->setAction(new TControllerAction('CctTransmissaoForm', 'onLoad'));
            $btn_novo->setStyle('primary');
            $panel->addControl($btn_novo);
            parent::add($panel);

            // Carregar dados
            $this->onLoad();

        } catch (\Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Constrói formulário de filtros
     */
    private function buildFilterForm()
    {
        $this->form = new TForm('form_filter_cct');
        $fieldlist = new TFieldList();
        $this->form->add($fieldlist);

        // Filtro: Data de
        $data_de = new \Adianti\Widgets\Form\TDate('filter_data_de');
        $data_de->setLabel("Data De");
        $fieldlist->addField('filter_data_de', $data_de);

        // Filtro: Data até
        $data_ate = new \Adianti\Widgets\Form\TDate('filter_data_ate');
        $data_ate->setLabel("Data Até");
        $fieldlist->addField('filter_data_ate', $data_ate);

        // Filtro: Status
        $status_combo = new TCombo('filter_status');
        $status_combo->setLabel("Status");
        $status_combo->addOption('', '-- Todos --');
        $status_combo->addOption(CctTransmissao::STATUS_PENDENTE, 'Pendente');
        $status_combo->addOption(CctTransmissao::STATUS_ENVIADO, 'Enviado');
        $status_combo->addOption(CctTransmissao::STATUS_ACEITO, 'Aceito');
        $status_combo->addOption(CctTransmissao::STATUS_REJEITADO, 'Rejeitado');
        $status_combo->addOption(CctTransmissao::STATUS_CANCELADO, 'Cancelado');
        $fieldlist->addField('filter_status', $status_combo);

        // Filtro: Número CRT
        $numero_crt = new TEntry('filter_numero_crt');
        $numero_crt->setLabel("Número CRT");
        $fieldlist->addField('filter_numero_crt', $numero_crt);

        // Botões
        $btn_search = new TButton('btn_search');
        $btn_search->setLabel("Filtrar");
        $btn_search->setImage('fa:search');
        $btn_search->setAction(new TControllerAction('CctTransmissaoList', 'onSearch'));
        $fieldlist->addField('btn_search', $btn_search);

        $btn_clear = new TButton('btn_clear');
        $btn_clear->setLabel("Limpar Filtros");
        $btn_clear->setImage('fa:times');
        $btn_clear->setAction(new TControllerAction('CctTransmissaoList', 'onClearFilters'));
        $fieldlist->addField('btn_clear', $btn_clear);
    }

    /**
     * Constrói datagrid de transmissões
     */
    private function buildDatagrid()
    {
        $this->datagrid = new TDataGrid();
        $this->datagrid->setHeight('400px');

        // Coluna: ID
        $col_id = new TDataGridColumn('id', 'ID', '5%');
        $col_id->setAlignment('right');
        $this->datagrid->addColumn($col_id);

        // Coluna: CRT Número
        $col_crt = new TDataGridColumn('conhecimento_id', 'CRT', '10%');
        $col_crt->setFormatter(function($value) {
            try {
                $conhecimento = new \Adianti\Model\Conhecimento($value);
                return $conhecimento->numero;
            } catch (\Exception $e) {
                return $value;
            }
        });
        $this->datagrid->addColumn($col_crt);

        // Coluna: Data Transmissão
        $col_data = new TDataGridColumn('data_transmissao', 'Data Transmissão', '15%');
        $col_data->setFormatter(function($value) {
            if ($value instanceof \DateTime) {
                return $value->format('d/m/Y H:i:s');
            } elseif (!empty($value)) {
                return (new \DateTime($value))->format('d/m/Y H:i:s');
            }
            return '';
        });
        $this->datagrid->addColumn($col_data);

        // Coluna: Status
        $col_status = new TDataGridColumn('status', 'Status', '12%');
        $col_status->setFormatter(function($value) {
            $colors = array(
                'pendente' => '#FF9800',    // Amber
                'enviado' => '#2196F3',    // Blue
                'aceito' => '#4CAF50',     // Green
                'rejeitado' => '#F44336',  // Red
                'cancelado' => '#9E9E9E',  // Gray
                'erro' => '#F44336'        // Red
            );

            $color = $colors[$value] ?? '#999';
            $label = ucfirst($value);

            return "<span style='background-color: {$color}; color: white; padding: 3px 8px; border-radius: 3px;'>{$label}</span>";
        });
        $this->datagrid->addColumn($col_status);

        // Coluna: Protocolo
        $col_protocolo = new TDataGridColumn('protocolo_siscomex', 'Protocolo', '15%');
        $this->datagrid->addColumn($col_protocolo);

        // Coluna: Tentativas
        $col_tentativas = new TDataGridColumn('tentativas', 'Tent.', '8%');
        $col_tentativas->setAlignment('center');
        $this->datagrid->addColumn($col_tentativas);

        // Coluna: Ações
        $col_acoes = new TDataGridColumn('id', 'Ações', '35%');

        // Ação: Visualizar XML
        $action_xml = new TDataGridAction(array($this, 'onViewXML'));
        $action_xml->setLabel("Ver XML");
        $action_xml->setImage('fa:file-code');
        $action_xml->setField('id');
        $col_acoes->addAction($action_xml);

        // Ação: Visualizar Resposta
        $action_resposta = new TDataGridAction(array($this, 'onViewResponse'));
        $action_resposta->setLabel("Ver Resposta");
        $action_resposta->setImage('fa:comment');
        $action_resposta->setField('id');
        $col_acoes->addAction($action_resposta);

        // Ação: Reenviar (apenas rejeitadas)
        $action_retry = new TDataGridAction(array($this, 'onRetry'));
        $action_retry->setLabel("Reenviar");
        $action_retry->setImage('fa:refresh');
        $action_retry->setField('id');
        $col_acoes->addAction($action_retry);

        // Ação: Deletar
        $action_delete = new TDataGridAction(array($this, 'onDelete'));
        $action_delete->setLabel("Deletar");
        $action_delete->setImage('fa:trash');
        $action_delete->setField('id');
        $col_acoes->addAction($action_delete);

        $this->datagrid->addColumn($col_acoes);

        // Paginação
        $this->datagrid->addColumn(new TPaginatorColumn($this->pagesize));

        // Configuração
        $this->datagrid->setModel(CctTransmissao::class);
        $this->datagrid->setRepository(new \Adianti\Database\TCriteria());
    }

    /**
     * Carrega dados iniciais
     */
    public function onLoad()
    {
        try {
            TTransaction::open('default');

            // Construir critério
            $criteria = new \Adianti\Database\TCriteria();
            $criteria->setOrder('id', 'desc');

            $this->datagrid->setRepository($criteria);
            $this->datagrid->loadData();

            TTransaction::close();

            // Verificar se transmissão foi bem-sucedida
            if (TRegistry::getValue('cct_transmit_success')) {
                new TMessage('info', 'Transmissão realizada com sucesso!');
                TRegistry::setValue('cct_transmit_success', false);
            }

        } catch (\Exception $e) {
            new TMessage('error', 'Erro ao carregar dados: ' . $e->getMessage());
        }
    }

    /**
     * Aplica filtros
     */
    public function onSearch()
    {
        try {
            TTransaction::open('default');

            $data = $this->form->getData();

            // Construir critério com filtros
            $criteria = new \Adianti\Database\TCriteria();

            if (!empty($data->filter_data_de)) {
                $criteria->add(
                    new TFilter('data_transmissao', '>=', $data->filter_data_de)
                );
            }

            if (!empty($data->filter_data_ate)) {
                $criteria->add(
                    new TFilter('data_transmissao', '<=', $data->filter_data_ate . ' 23:59:59')
                );
            }

            if (!empty($data->filter_status)) {
                $criteria->add(
                    new TFilter('status', '=', $data->filter_status)
                );
            }

            if (!empty($data->filter_numero_crt)) {
                $criteria->add(
                    new TFilter('conhecimento_id', 'in',
                        "(SELECT id FROM conhecimento WHERE numero LIKE '%{$data->filter_numero_crt}%')")
                );
            }

            $criteria->setOrder('id', 'desc');

            $this->datagrid->setRepository($criteria);
            $this->datagrid->loadData();

            TTransaction::close();

        } catch (\Exception $e) {
            new TMessage('error', 'Erro ao filtrar: ' . $e->getMessage());
        }
    }

    /**
     * Limpa filtros
     */
    public function onClearFilters()
    {
        $this->form->clear();
        $this->onLoad();
    }

    /**
     * Visualiza XML da transmissão
     */
    public function onViewXML($param)
    {
        try {
            $id = $param['id'];
            $transmissao = new CctTransmissao($id);

            if (!$transmissao->id) {
                throw new \Exception("Transmissão não encontrada");
            }

            // Mostrar XML em dialog
            $dialog = new \Adianti\Dialogs\TDialog();
            $dialog->setTitle("XML Transmitido - Transmissão #{$id}");

            $textarea = new \Adianti\Widgets\Form\TTextArea('xml_view');
            $textarea->setValue($transmissao->xml_enviado);
            $textarea->setEditable(false);
            $textarea->setHeight('500px');
            $dialog->add($textarea);

            $dialog->show();

        } catch (\Exception $e) {
            new TMessage('error', 'Erro: ' . $e->getMessage());
        }
    }

    /**
     * Visualiza resposta do Siscomex
     */
    public function onViewResponse($param)
    {
        try {
            $id = $param['id'];
            $transmissao = new CctTransmissao($id);

            if (!$transmissao->id) {
                throw new \Exception("Transmissão não encontrada");
            }

            $resposta = $transmissao->resposta_siscomex ? json_decode($transmissao->resposta_siscomex, true) : array();

            // Mostrar resposta em dialog
            $dialog = new \Adianti\Dialogs\TDialog();
            $dialog->setTitle("Resposta Siscomex - Transmissão #{$id}");

            $textarea = new \Adianti\Widgets\Form\TTextArea('response_view');
            $textarea->setValue(json_encode($resposta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $textarea->setEditable(false);
            $textarea->setHeight('500px');
            $dialog->add($textarea);

            $dialog->show();

        } catch (\Exception $e) {
            new TMessage('error', 'Erro: ' . $e->getMessage());
        }
    }

    /**
     * Retenta transmissão rejeitada
     */
    public function onRetry($param)
    {
        try {
            TTransaction::open('default');

            $id = $param['id'];

            $result = SiscomexTransmissionService::retryTransmission($id);

            TTransaction::close();

            if ($result->success) {
                new TMessage('info', "Retentativa enviada com sucesso!\nProtocolo: " . $result->protocolo);
                $this->onLoad();
            } else {
                $erros = implode(", ", $result->erros);
                new TMessage('error', "Erro na retentativa:\n" . $erros);
            }

        } catch (\Exception $e) {
            if (\Adianti\Database\TTransaction::isOpen()) {
                \Adianti\Database\TTransaction::close();
            }
            new TMessage('error', 'Erro: ' . $e->getMessage());
        }
    }

    /**
     * Deleta transmissão
     */
    public function onDelete($param)
    {
        try {
            TTransaction::open('default');

            $id = $param['id'];
            $transmissao = new CctTransmissao($id);

            if (!$transmissao->id) {
                throw new \Exception("Transmissão não encontrada");
            }

            // Deletar items associados
            $items = $transmissao->get_items();
            foreach ($items as $item) {
                $item->delete();
            }

            // Deletar transmissão
            $transmissao->delete();

            TTransaction::close();

            new TMessage('info', 'Transmissão deletada com sucesso');
            $this->onLoad();

        } catch (\Exception $e) {
            if (\Adianti\Database\TTransaction::isOpen()) {
                \Adianti\Database\TTransaction::close();
            }
            new TMessage('error', 'Erro: ' . $e->getMessage());
        }
    }
}
?>
