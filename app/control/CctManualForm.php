<?php



/**
 * CctManualForm
 * Formulário para emissão manual de MIC/DTA sem depender de CRT registrado
 *
 * Permite:
 * - Preenchimento manual de todos os dados
 * - Informação de múltiplas NF-es
 * - Geração e transmissão de MIC/DTA customizado
 */
class CctManualForm extends TPage
{
    private $form;
    private $datagrid;

    public function __construct()
    {
        parent::__construct();

        try {
            // Container principal
            $container = new TPanelGroup("Emissão Manual de MIC/DTA");

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
     * Constrói o formulário de dados manuais
     */
    private function buildForm()
    {
        $this->form = new TForm('form_cct_manual');
        $fieldlist = new TFieldList();
        $this->form->add($fieldlist);

        // ===== SEÇÃO: MANIFESTO =====
        $this->addSectionLabel($fieldlist, "Dados do Manifesto");

        $crt_numero = new TEntry('crt_numero');
        $crt_numero->setLabel("Número do CRT");
        $crt_numero->setSize('30%');
        $fieldlist->addField('crt_numero', $crt_numero);

        $data_mic = new TDate('data_mic');
        $data_mic->setLabel("Data do Manifesto");
        $data_mic->setValue(date('Y-m-d'));
        $fieldlist->addField('data_mic', $data_mic);

        // ===== SEÇÃO: TRANSPORTADOR =====
        $this->addSectionLabel($fieldlist, "Dados do Transportador");

        $transp_cnpj = new TEntry('transp_cnpj');
        $transp_cnpj->setLabel("CNPJ do Transportador");
        $transp_cnpj->setMask('999.999.999/9999-99');
        $transp_cnpj->addValidation('CNPJ do Transportador', new TRequiredValidator);
        $fieldlist->addField('transp_cnpj', $transp_cnpj);

        $transp_nome = new TEntry('transp_nome');
        $transp_nome->setLabel("Razão Social");
        $transp_nome->addValidation('Razão Social', new TRequiredValidator);
        $fieldlist->addField('transp_nome', $transp_nome);

        $transp_permissao = new TEntry('transp_permissao');
        $transp_permissao->setLabel("Número da Permissão ANTT");
        $fieldlist->addField('transp_permissao', $transp_permissao);

        // ===== SEÇÃO: EXPORTADOR =====
        $this->addSectionLabel($fieldlist, "Dados do Exportador");

        $exp_cnpj = new TEntry('exp_cnpj');
        $exp_cnpj->setLabel("CNPJ");
        $exp_cnpj->setMask('999.999.999/9999-99');
        $exp_cnpj->addValidation('CNPJ', new TRequiredValidator);
        $fieldlist->addField('exp_cnpj', $exp_cnpj);

        $exp_nome = new TEntry('exp_nome');
        $exp_nome->setLabel("Nome / Razão Social");
        $exp_nome->addValidation('Nome / Razão Social', new TRequiredValidator);
        $fieldlist->addField('exp_nome', $exp_nome);

        $exp_endereco = new TEntry('exp_endereco');
        $exp_endereco->setLabel("Endereço");
        $fieldlist->addField('exp_endereco', $exp_endereco);

        $exp_cidade = new TEntry('exp_cidade');
        $exp_cidade->setLabel("Cidade");
        $fieldlist->addField('exp_cidade', $exp_cidade);

        $exp_estado = new TCombo('exp_estado');
        $exp_estado->setLabel("Estado");
        $this->populateStates($exp_estado);
        $fieldlist->addField('exp_estado', $exp_estado);

        $exp_cep = new TEntry('exp_cep');
        $exp_cep->setLabel("CEP");
        $exp_cep->setMask('99999-999');
        $fieldlist->addField('exp_cep', $exp_cep);

        // ===== SEÇÃO: IMPORTADOR =====
        $this->addSectionLabel($fieldlist, "Dados do Importador");

        $imp_cnpj = new TEntry('imp_cnpj');
        $imp_cnpj->setLabel("CNPJ (opcional se exterior)");
        $imp_cnpj->setMask('999.999.999/9999-99');
        $fieldlist->addField('imp_cnpj', $imp_cnpj);

        $imp_nome = new TEntry('imp_nome');
        $imp_nome->setLabel("Nome / Razão Social");
        $imp_nome->addValidation('Nome / Razão Social', new TRequiredValidator);
        $fieldlist->addField('imp_nome', $imp_nome);

        $imp_endereco = new TEntry('imp_endereco');
        $imp_endereco->setLabel("Endereço");
        $fieldlist->addField('imp_endereco', $imp_endereco);

        $imp_cidade = new TEntry('imp_cidade');
        $imp_cidade->setLabel("Cidade / País");
        $fieldlist->addField('imp_cidade', $imp_cidade);

        $imp_estado = new TEntry('imp_estado');
        $imp_estado->setLabel("Estado / Provincia");
        $fieldlist->addField('imp_estado', $imp_estado);

        // ===== SEÇÃO: CARGA =====
        $this->addSectionLabel($fieldlist, "Dados da Carga");

        $descricao = new TText('descricao_carga');
        $descricao->setLabel("Descrição da Mercadoria");
        $descricao->addValidation('Descrição da Mercadoria', new TRequiredValidator);
        $fieldlist->addField('descricao_carga', $descricao);

        $peso_bruto = new TNumeric('peso_bruto', 2, ',', '.');
        $peso_bruto->setLabel("Peso Bruto (kg)");
        $peso_bruto->addValidation('Peso Bruto', new TRequiredValidator);
        $fieldlist->addField('peso_bruto', $peso_bruto);

        $peso_liquido = new TNumeric('peso_liquido', 2, ',', '.');
        $peso_liquido->setLabel("Peso Líquido (kg)");
        $fieldlist->addField('peso_liquido', $peso_liquido);

        $volume = new TNumeric('volume', 2, ',', '.');
        $volume->setLabel("Volume (m³)");
        $fieldlist->addField('volume', $volume);

        $quantidade_vol = new TEntry('quantidade_vol');
        $quantidade_vol->setLabel("Quantidade de Volumes");
        $quantidade_vol->setNumericMask(0, '', '.');
        $fieldlist->addField('quantidade_vol', $quantidade_vol);

        $especie = new TEntry('especie_vol');
        $especie->setLabel("Espécie (Caixa, Saco, Pallet, etc)");
        $fieldlist->addField('especie_vol', $especie);

        $valor_mercadoria = new TNumeric('valor_mercadoria', 2, ',', '.');
        $valor_mercadoria->setLabel("Valor da Mercadoria (USD)");
        $fieldlist->addField('valor_mercadoria', $valor_mercadoria);

        // ===== SEÇÃO: VEÍCULO =====
        $this->addSectionLabel($fieldlist, "Dados do Veículo");

        $placa_trator = new TEntry('placa_trator');
        $placa_trator->setLabel("Placa do Trator");
        $placa_trator->addValidation('Placa do Trator', new TRequiredValidator);
        $placa_trator->setMask('AAA-9999');
        $fieldlist->addField('placa_trator', $placa_trator);

        $prop_cnpj = new TEntry('prop_cnpj');
        $prop_cnpj->setLabel("CNPJ do Proprietário");
        $prop_cnpj->setMask('999.999.999/9999-99');
        $fieldlist->addField('prop_cnpj', $prop_cnpj);

        $prop_nome = new TEntry('prop_nome');
        $prop_nome->setLabel("Razão Social Proprietário");
        $fieldlist->addField('prop_nome', $prop_nome);

        // ===== SEÇÃO: MOTORISTA =====
        $this->addSectionLabel($fieldlist, "Dados do Motorista");

        $motor_cpf = new TEntry('motor_cpf');
        $motor_cpf->setLabel("CPF");
        $motor_cpf->setMask('999.999.999-99');
        $motor_cpf->addValidation('CPF', new TRequiredValidator);
        $fieldlist->addField('motor_cpf', $motor_cpf);

        $motor_nome = new TEntry('motor_nome');
        $motor_nome->setLabel("Nome Completo");
        $motor_nome->addValidation('Nome Completo', new TRequiredValidator);
        $fieldlist->addField('motor_nome', $motor_nome);

        $motor_cnh = new TEntry('motor_cnh');
        $motor_cnh->setLabel("Número da CNH");
        $motor_cnh->addValidation('Número da CNH', new TRequiredValidator);
        $fieldlist->addField('motor_cnh', $motor_cnh);

        $motor_categoria = new TCombo('motor_categoria');
        $motor_categoria->setLabel("Categoria da CNH");
        $motor_categoria->addItems([
            '' => '-- Selecione --',
            'A' => 'A - Motocicleta',
            'B' => 'B - Veículos leves',
            'C' => 'C - Veículos médios',
            'D' => 'D - Veículos pesados',
            'E' => 'E - Combinações de veículos'
        ]);
        $motor_categoria->addValidation('Categoria da CNH', new TRequiredValidator);
        $fieldlist->addField('motor_categoria', $motor_categoria);
    }

    /**
     * Helper para adicionar labels de seção
     */
    private function addSectionLabel($fieldlist, $text)
    {
        $label = new TLabel($text);
        $label->setProperty('style', 'font-weight: bold; font-size: 14px; margin-top: 15px; border-bottom: 1px solid #ccc; padding-bottom: 5px;');
        $fieldlist->addField('section_' . strtolower(str_replace(' ', '_', $text)), $label);
    }

    /**
     * Popula combo de estados brasileiros
     */
    private function populateStates($combo)
    {
        $states = array(
            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
            'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
            'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
            'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
            'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
        );

        $items = ['' => '-- Selecione --'];
        foreach ($states as $code => $name) {
            $items[$code] = $name . " ({$code})";
        }
        $combo->addItems($items);
    }

    /**
     * Constrói datagrid para items (NF-es)
     */
    private function buildDatagrid()
    {
        $this->datagrid = new TDataGrid();

        // Coluna: Chave NF-e
        $col_chave = new TDataGridColumn('chave_nfe', 'Chave de Acesso (NF-e)', '45%');
        $col_chave->setTransformer(function($value) {
            return chunk_split($value, 4, ' ');
        });
        $this->datagrid->addColumn($col_chave);

        // Coluna: Valor do Frete
        $col_frete = new TDataGridColumn('valor_frete', 'Valor do Frete (R$)', '30%');
        $col_frete->setTransformer(function($value) {
            return 'R$ ' . number_format($value, 2, ',', '.');
        });
        $this->datagrid->addColumn($col_frete);

        // Coluna: Ações
        $col_acoes = new TDataGridColumn('id', 'Ação', '25%');
        $action_delete = new TDataGridAction(array($this, 'onDeleteItem'));
        $action_delete->setLabel("Remover");
        $action_delete->setImage('fa:trash');
        $action_delete->setField('id');
        $this->datagrid->addAction($action_delete);
        $this->datagrid->addColumn($col_acoes);

        $this->datagrid->createModel();
    }

    /**
     * Constrói botões de ação
     */
    private function buildActions()
    {
        $panel = new TPanelGroup('');

        // Botão: Adicionar NF-e
        $btn_add = new TButton('add_nfe');
        $btn_add->setLabel("Adicionar NF-e");
        $btn_add->setImage('fa:plus');
        $btn_add->setAction(new TAction([$this, 'onAddNFe']));
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
     * Adiciona nova NF-e
     */
    public function onAddNFe()
    {
        new TMessage('info', 'Preencha a chave de acesso e valor do frete no grid abaixo');
    }

    /**
     * Remove item do datagrid
     */
    public function onDeleteItem($param)
    {
        try {
            $id = $param['id'];
            // TODO: Remover do datagrid
            new TMessage('info', 'Item removido');
        } catch (\Exception $e) {
            new TMessage('error', 'Erro: ' . $e->getMessage());
        }
    }

    /**
     * Mostra preview do XML
     */
    public function onPreviewXML()
    {
        try {
            $data = $this->form->getData();

            // Validar dados obrigatórios
            if (empty($data->crt_numero)) {
                throw new \Exception("Número do CRT é obrigatório");
            }

            // Montar objeto de conhecimento manualmente
            $conhecimento = new \stdClass();
            $conhecimento->numero = $data->crt_numero;
            $conhecimento->data_transportador_assinatura = $data->data_mic;
            $conhecimento->descricao_mercadoria = $data->descricao_carga;
            $conhecimento->peso_bruto_kg = $data->peso_bruto;
            $conhecimento->peso_liq_kg = $data->peso_liquido;
            $conhecimento->volume_m3 = $data->volume;
            $conhecimento->quantidade_volumes = $data->quantidade_vol;
            $conhecimento->especie_vol = $data->especie_vol;
            $conhecimento->valor_mercadorias = $data->valor_mercadoria;

            // Montar dados relacionados (exportador, importador, etc)
            // TODO: Implementar construção de XML manual via MicDtaXmlBuilder estendido

            new TMessage('warning', 'Preview XML: Função em desenvolvimento. Veja a documentação do layout.');

        } catch (\Exception $e) {
            new TMessage('error', 'Erro: ' . $e->getMessage());
        }
    }

    /**
     * Realiza transmissão
     */
    public function onTransmit()
    {
        try {
            TTransaction::open('default');

            $data = $this->form->getData();

            // Validações
            if (empty($data->crt_numero)) {
                throw new \Exception("Número do CRT é obrigatório");
            }

            // TODO: Montar e transmitir MIC/DTA manual

            new TMessage('warning', 'Transmissão manual: Função em desenvolvimento');

            TTransaction::close();

        } catch (\Exception $e) {
            if (TTransaction::isOpen()) {
                TTransaction::close();
            }
            new TMessage('error', 'Erro: ' . $e->getMessage());
        }
    }
}
?>





