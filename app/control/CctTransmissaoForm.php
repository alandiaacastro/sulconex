<?php



/**
 * CctTransmissaoForm
 * Formulário para transmissão de MIC/DTA ao Portal Único Siscomex
 *
 * Funcionalidades:
 * - Seleção de CRT (Conhecimento)
 * - Informação de chaves NF-e
 * - Valores de frete
 * - Preview do XML
 * - Transmissão e rastreamento de resultado
 */
class CctTransmissaoForm extends TPage
{
    private $form;
    private $datagrid;
    private $loaded_crt;

    public function __construct()
    {
        parent::__construct();

        try {
            // Container principal
            $container = new TPanelGroup("Transmissão de MIC/DTA");

            // Formulário
            $this->buildForm();
            $container->add($this->form);

            // Datagrid de items
            $this->buildDatagrid();
            $container->add($this->datagrid);

            // Botões de ação
            $this->buildActions();

            // Adicionar ao conteúdo
            parent::add($container);

        } catch (\Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Constrói o formulário de seleção de CRT
     */
    private function buildForm()
    {
        $this->form = new TForm('form_cct_transmissao');
        $fieldlist = new TFieldList();
        $this->form->add($fieldlist);

        // Seleção de CRT
        $crt_field = new TDBCombo(
            'conhecimento_id',
            'default',
            Conhecimento::class,
            'id',
            '{numero} - {descricao_mercadoria}'
        );
        $crt_field->setLabel("Conhecimento (CRT)");
        $crt_field->addValidation('Conhecimento', new TRequiredValidator);
        $fieldlist->addField('conhecimento_id', $crt_field);

        // Botão para carregar dados do CRT
        $load_button = new TButton('load_crt');
        $load_button->setLabel("Carregar Dados do CRT");
        $load_button->setImage('fa:arrow-down');
        $load_button->setAction(new TAction([$this, 'onLoadCRT']));
        $fieldlist->addField('load_btn', $load_button);
        $this->form->addField($load_button);

        // Informações do CRT (read-only)
        $this->buildCRTInfo($fieldlist);
    }

    /**
     * Constrói seção de informações do CRT (leitura apenas)
     */
    private function buildCRTInfo($fieldlist)
    {
        // Exportador
        $exportador = new TEntry('exportador_nome');
        $exportador->setLabel("Exportador");
        $exportador->setEditable(false);
        $exportador->setProperty('style', 'background-color: #f5f5f5;');
        $fieldlist->addField('exportador_nome', $exportador);

        // Importador
        $importador = new TEntry('importador_nome');
        $importador->setLabel("Importador");
        $importador->setEditable(false);
        $importador->setProperty('style', 'background-color: #f5f5f5;');
        $fieldlist->addField('importador_nome', $importador);

        // Motorista
        $motorista = new TEntry('motorista_nome');
        $motorista->setLabel("Motorista");
        $motorista->setEditable(false);
        $motorista->setProperty('style', 'background-color: #f5f5f5;');
        $fieldlist->addField('motorista_nome', $motorista);

        // Veículo
        $veiculo = new TEntry('veiculo_placa');
        $veiculo->setLabel("Veículo (Placa)");
        $veiculo->setEditable(false);
        $veiculo->setProperty('style', 'background-color: #f5f5f5;');
        $fieldlist->addField('veiculo_placa', $veiculo);

        // Mercadoria
        $mercadoria = new TEntry('descricao_mercadoria');
        $mercadoria->setLabel("Descrição da Mercadoria");
        $mercadoria->setEditable(false);
        $mercadoria->setProperty('style', 'background-color: #f5f5f5;');
        $fieldlist->addField('descricao_mercadoria', $mercadoria);

        // Peso
        $peso = new TEntry('peso_bruto_kg');
        $peso->setLabel("Peso Bruto (kg)");
        $peso->setEditable(false);
        $peso->setNumericMask(2, ',', '.');
        $peso->setProperty('style', 'background-color: #f5f5f5;');
        $fieldlist->addField('peso_bruto_kg', $peso);
    }

    /**
     * Constrói datagrid para items (NF-es) da transmissão
     */
    private function buildDatagrid()
    {
        $this->datagrid = new TDataGrid();

        // Coluna: Chave NF-e
        $col_chave = new TDataGridColumn('chave_acesso_nfe', 'Chave de Acesso (NF-e)', '40%');
        $col_chave->setEditableField(new TEntry('chave_acesso_nfe'));
        $col_chave->setTransformer(function($value) {
            return chunk_split($value, 4, ' ');
        });
        $this->datagrid->addColumn($col_chave);

        // Coluna: Valor do Frete
        $col_frete = new TDataGridColumn('valor_frete', 'Valor do Frete (R$)', '30%');
        $col_frete->setEditableField(new TNumeric('valor_frete', 2, ',', '.'));
        $col_frete->setTransformer(function($value) {
            return 'R$ ' . number_format($value, 2, ',', '.');
        });
        $this->datagrid->addColumn($col_frete);

        // Coluna: Ordem
        $col_ordem = new TDataGridColumn('ordem', 'Ord', '10%');
        $col_ordem->setEditable(false);
        $this->datagrid->addColumn($col_ordem);

        // Coluna: Ações
        $col_acoes = new TDataGridColumn('id', 'Ações', '20%');
        $action_delete = new TDataGridAction(array($this, 'onDeleteItem'));
        $action_delete->setLabel("Remover");
        $action_delete->setImage('fa:trash');
        $action_delete->setField('id');
        $col_acoes->addAction($action_delete);
        $this->datagrid->addColumn($col_acoes);

        // Datagrid configuracao
        $this->datagrid->allowOnlyNew(true);
        $this->datagrid->createModel();

        // Container para datagrid
        $datagrid_panel = new TPanelGroup('');
        $datagrid_panel->setTitle("Informações das NF-es a transmitir");
        $datagrid_panel->add($this->datagrid);
    }

    /**
     * Constrói botões de ação
     */
    private function buildActions()
    {
        $panel = new TPanelGroup('');

        // Botão: Adicionar NF-e
        $btn_add = new TButton('add_item');
        $btn_add->setLabel("Adicionar NF-e");
        $btn_add->setImage('fa:plus');
        $btn_add->setAction(new TAction([$this, 'onAddItem']));
        $panel->add($btn_add);

        // Botão: Preview XML
        $btn_preview = new TButton('preview_xml');
        $btn_preview->setLabel("Preview XML");
        $btn_preview->setImage('fa:file-code');
        $btn_preview->setAction(new TAction([$this, 'onPreviewXML']));
        $panel->add($btn_preview);

        // Botão: Transmitir
        $btn_send = new TButton('btn_transmit');
        $btn_send->setLabel("Transmitir MIC/DTA");
        $btn_send->setImage('fa:paper-plane');
        $btn_send->setAction(new TAction([$this, 'onTransmit']));
        $btn_send->setProperty('class', 'btn btn-primary');
        $panel->add($btn_send);

        // Botão: Voltar
        $btn_back = new TButton('btn_back');
        $btn_back->setLabel("Voltar");
        $btn_back->setImage('fa:arrow-left');
        $btn_back->setAction(new TAction(['CctTransmissaoList', 'onLoad']));
        $panel->add($btn_back);

        $this->form->addField($btn_add);
        $this->form->addField($btn_preview);
        $this->form->addField($btn_send);
        $this->form->addField($btn_back);

        parent::add($panel);
    }

    /**
     * Carrega dados do CRT
     */
    public function onLoadCRT()
    {
        try {
            $data = $this->form->getData();

            $conhecimento = new Conhecimento($data->conhecimento_id);
            if (!$conhecimento->id) {
                throw new \Exception("Conhecimento não encontrado");
            }

            // Carregar dados relacionados
            $exportador = $conhecimento->get_remetente();
            $importador = $conhecimento->get_destinatario();

            // Buscar contrato com veículo e motorista
            $db = \Adianti\Database\TDatabase::get();
            $contrato_result = $db->query(
                "SELECT * FROM contrato WHERE conhecimento_numero = ?",
                array($conhecimento->numero)
            );

            $motorista_nome = '';
            $veiculo_placa = '';

            if ($contrato_result && $contrato_result->rowCount() > 0) {
                $contrato = $contrato_result->fetch(\PDO::FETCH_ASSOC);
                $veiculo_id = $contrato['veiculo_id'];

                if ($veiculo_id) {
                    $veiculo = new \Adianti\Model\Veiculo($veiculo_id);
                    if ($veiculo->id) {
                        $veiculo_placa = $veiculo->placa_trator;
                        $motorista = $veiculo->get_motorista();
                        if ($motorista && $motorista->id) {
                            $motorista_nome = $motorista->nome;
                        }
                    }
                }
            }

            // Preencher campos
            $form_data = $this->form->getData();
            $form_data->exportador_nome = ($exportador && $exportador->id) ? $exportador->nome : '';
            $form_data->importador_nome = ($importador && $importador->id) ? $importador->nome : '';
            $form_data->motorista_nome = $motorista_nome;
            $form_data->veiculo_placa = $veiculo_placa;
            $form_data->descricao_mercadoria = $conhecimento->descricao_mercadoria;
            $form_data->peso_bruto_kg = $conhecimento->peso_bruto_kg;

            $this->form->setData($form_data);
            $this->loaded_crt = $conhecimento->id;

            new TMessage('info', 'Dados do CRT carregados com sucesso');

        } catch (\Exception $e) {
            new TMessage('error', 'Erro: ' . $e->getMessage());
        }
    }

    /**
     * Adiciona nova linha de NF-e no datagrid
     */
    public function onAddItem()
    {
        if (!isset($this->loaded_crt) || !$this->loaded_crt) {
            new TMessage('warning', 'Primeiro carregue um CRT');
            return;
        }

        try {
            // Criar novo item
            $item = new CctTransmissaoItem();
            $item->chave_acesso_nfe = '';
            $item->valor_frete = 0;
            $item->ordem = 1;

            // Adicionar ao datagrid
            // Nota: Implementação exata depende da versão do Adianti
            new TMessage('info', 'Adicione as chaves NF-e nos campos abaixo');

        } catch (\Exception $e) {
            new TMessage('error', 'Erro: ' . $e->getMessage());
        }
    }

    /**
     * Remove item do datagrid
     */
    public function onDeleteItem($param)
    {
        try {
            $id = $param['id'];
            $item = new CctTransmissaoItem($id);
            if ($item->id) {
                $item->delete();
                new TMessage('info', 'Item removido');
            }
        } catch (\Exception $e) {
            new TMessage('error', 'Erro ao remover: ' . $e->getMessage());
        }
    }

    /**
     * Mostra preview do XML antes de transmitir
     */
    public function onPreviewXML()
    {
        try {
            $data = $this->form->getData();

            if (!isset($this->loaded_crt) || !$this->loaded_crt) {
                throw new \Exception("Nenhum CRT carregado");
            }

            // Montar items
            $items = array();
            // TODO: Recuperar items do datagrid

            if (empty($items)) {
                throw new \Exception("Adicione pelo menos uma NF-e");
            }

            // Gerar XML
            $xml = MicDtaXmlBuilder::buildMicDta($this->loaded_crt, $items);

            // Mostrar em popup
            $dialog = new TDialog();
            $dialog->setTitle("Preview do XML MIC/DTA");
            $dialog->add(new TLabel("XML gerado:"));

            $textarea = new TText('xml_preview');
            $textarea->setValue($xml);
            $textarea->setEditable(false);
            $dialog->add($textarea);

            $dialog->show();

        } catch (\Exception $e) {
            new TMessage('error', 'Erro ao gerar XML: ' . $e->getMessage());
        }
    }

    /**
     * Realiza a transmissão
     */
    public function onTransmit()
    {
        try {
            TTransaction::open('default');

            $data = $this->form->getData();

            if (!isset($this->loaded_crt) || !$this->loaded_crt) {
                throw new \Exception("Nenhum CRT carregado");
            }

            // Montar items com chaves NF-e
            $items = array();
            // TODO: Recuperar items do datagrid

            if (empty($items)) {
                throw new \Exception("Adicione pelo menos uma NF-e");
            }

            // Transmitir
            $result = SiscomexTransmissionService::transmitMicDta(
                $this->loaded_crt,
                $items
            );

            TTransaction::close();

            if ($result->success) {
                $msg = "MIC/DTA transmitido com sucesso!\n";
                $msg .= "Protocolo Siscomex: " . $result->protocolo;
                new TMessage('info', $msg);

                // Voltar para lista
                TRegistry::setValue('cct_transmit_success', true);
                TApplication::gotoPage('CctTransmissaoList', 'onLoad');
            } else {
                $erros = implode("\n", $result->erros);
                new TMessage('error', "Erro na transmissão:\n" . $erros);
            }

        } catch (\Exception $e) {
            if (TTransaction::isOpen()) {
                TTransaction::close();
            }
            new TMessage('error', 'Erro: ' . $e->getMessage());
        }
    }
}
?>


