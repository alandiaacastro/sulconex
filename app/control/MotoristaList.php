<?php

class MotoristaList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded;

    public function __construct()
    {
        parent::__construct();

        // Formulário de busca
        $this->form = new BootstrapFormBuilder('form_search_Motorista');
        $this->form->setFormTitle('ðŸ§‘â€âœˆï¸ Lista de Motoristas');

        $nome = new TEntry('nome');
        $cpf  = new TEntry('cpf');

        $nome->setSize('70%');
        $cpf->setMask('999.999.999-99');

        $this->form->addFields([new TLabel('Nome')], [$nome], [new TLabel('CPF')], [$cpf]);

        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search green');
        $this->form->addAction('Novo', new TAction(['MotoristaForm', 'onEdit']), 'fa:plus blue');
        $this->form->addAction('Importar XML', new TAction([$this, 'onImportXML']), 'fa:file-code orange');

        // Datagrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width:100%';
        $this->datagrid->datatable = 'true';

        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'center'));
        $this->datagrid->addColumn(new TDataGridColumn('nome', 'Nome', 'left'));
        $this->datagrid->addColumn(new TDataGridColumn('cpf', 'CPF', 'center'));
        $this->datagrid->addColumn(new TDataGridColumn('cnh_numero', 'CNH', 'center'));
        $this->datagrid->addColumn(new TDataGridColumn('categoria', 'Categoria', 'center'));

        // Datas formatadas
        $this->datagrid->addColumn(new TDataGridColumn('data_emissao_cnh', 'Emissão CNH', 'center'))
            ->setTransformer([$this, 'formatDate']);
        $this->datagrid->addColumn(new TDataGridColumn('data_validade_cnh', 'Validade CNH', 'center'))
            ->setTransformer([$this, 'formatDate']);
        $this->datagrid->addColumn(new TDataGridColumn('data_nascimento', 'Nascimento', 'center'))
            ->setTransformer([$this, 'formatDate']);

        $action_edit   = new TDataGridAction(['MotoristaForm', 'onEdit'],   ['id' => '{id}']);
        $action_delete = new TDataGridAction([$this, 'onDelete'],           ['id' => '{id}']);

        $this->datagrid->addAction($action_edit,   'Editar',   'fa:edit blue');
        $this->datagrid->addAction($action_delete, 'Excluir',  'fa:trash red');

        $this->datagrid->createModel();

        // Paginação
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth($this->datagrid->getWidth());

        // Painel
        $panel = new TPanelGroup('ðŸ§‘â€âœˆï¸ Lista de Motoristas');
        $panel->add($this->form);
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        parent::add($panel);
    }

    /**
     * Formata datas na grid (YYYY-MM-DD â†’ DD/MM/YYYY)
     */
    public function formatDate($value)
    {
        if (!empty($value) && $value != '0000-00-00') {
            try {
                $date = new DateTime($value);
                return $date->format('d/m/Y');
            } catch (Exception $e) {
                return $value;
            }
        }
        return '';
    }

    /**
     * Conversão e validação de datas
     */
    public static function convertDateToDB($date)
    {
        $date = trim($date);

        // dd/mm/yyyy â†’ yyyy-mm-dd
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
            if (checkdate($matches[2], $matches[1], $matches[3])) {
                return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
            } else {
                return null;
            }
        }

        // yyyy-mm-dd â†’ yyyy-mm-dd (validando)
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
            if (checkdate($matches[2], $matches[3], $matches[1])) {
                return $date;
            } else {
                return null;
            }
        }

        // Data inválida
        return null;
    }

    /**
     * Busca registros
     */
    public function onSearch($param = null)
    {
        $data = $this->form->getData();

        TSession::setValue('MotoristaList_filter_nome', (!empty($data->nome)) ? new TFilter('nome', 'like', "%{$data->nome}%") : NULL);
        TSession::setValue('MotoristaList_filter_cpf', (!empty($data->cpf)) ? new TFilter('cpf', 'like', "%{$data->cpf}%") : NULL);

        $this->form->setData($data);
        $this->onReload();
    }

    /**
     * Carrega registros na datagrid
     */
    public function onReload($param = null)
    {
        try
        {
            TTransaction::open('sample');

            $repository = new TRepository('Motorista');
            $criteria = new TCriteria;

            if ($filter = TSession::getValue('MotoristaList_filter_nome')) {
                $criteria->add($filter);
            }
            if ($filter = TSession::getValue('MotoristaList_filter_cpf')) {
                $criteria->add($filter);
            }

            $criteria->setProperty('order', 'id desc');
            $criteria->setProperty('limit', 10);

            $objects = $repository->load($criteria, FALSE);

            $this->datagrid->clear();

            if ($objects) {
                foreach ($objects as $object) {
                    $this->datagrid->addItem($object);
                }
            }

            $count = $repository->count($criteria);

            $this->pageNavigation->setCount($count);
            $this->pageNavigation->setProperties($param);
            $this->pageNavigation->setLimit(10);

            TTransaction::close();

            $this->loaded = true;
        }
        catch (Exception $e)
        {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Confirma a exclusão
     */
    public function onDelete($param = null)
    {
        $action = new TAction([$this, 'Delete']);
        $action->setParameters($param);
        new TQuestion('Deseja realmente excluir?', $action);
    }

    /**
     * Executa a exclusão
     */
    public function Delete($param = null)
    {
        try
        {
            TTransaction::open('sample');

            $object = new Motorista($param['id']);
            $object->delete();

            TTransaction::close();

            $this->onReload();
            new TMessage('info', 'Registro excluído com sucesso');
        }
        catch (Exception $e)
        {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Abre janela de importação de XML
     */
    public function onImportXML($param = null)
    {
        try
        {
            $modelo_xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<motoristas>
    <motorista>
        <cnh_numero>9876543210</cnh_numero>
        <data_emissao_cnh>2020-01-01</data_emissao_cnh>
        <data_validade_cnh>2030-01-01</data_validade_cnh>
        <categoria>B</categoria>
        <registro_num>123456</registro_num>
        <nome>João Silva</nome>
        <data_nascimento>1985-05-20</data_nascimento>
        <local_nascimento>São Paulo</local_nascimento>
        <cpf>123.456.789-00</cpf>
        <rg_numero>12345678</rg_numero>
        <rg_emissor>SSP</rg_emissor>
        <rg_uf>SP</rg_uf>
        <filiacao_pai>José Silva</filiacao_pai>
        <filiacao_mae>Maria Silva</filiacao_mae>
    </motorista>
</motoristas>
XML;

            $form = new BootstrapFormBuilder('form_import_motorista');
            $form->setFormTitle('ðŸ“„ Importar Motoristas via XML');

            $xml_text = new TText('xml_text');
            $xml_text->setSize('100%', 200);
            $xml_text->setValue($modelo_xml);

            $form->addFields([new TLabel('Cole ou edite o XML abaixo')], [$xml_text]);

            $form->addAction('Importar', new TAction([$this, 'processImportXML']), 'fa:upload green');
            $form->addAction('Fechar', new TAction([$this, 'onReload']), 'fa:times red');

            $window = TWindow::create('ðŸ“„ ImportaÃ§Ã£o de Motoristas', 600, 400);
            $window->add($form);
            $window->show();
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Processa o XML colado
     */
    public function processImportXML($param = null)
    {
        try
        {
            TTransaction::open('sample');

            if (!empty($param['xml_text']))
            {
                $xml_string = $param['xml_text'];

                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($xml_string);

                if ($xml === false) {
                    $errors = libxml_get_errors();
                    $error_message = 'Erro no XML:<br>';
                    foreach ($errors as $error) {
                        $error_message .= htmlspecialchars($error->message) . '<br>';
                    }
                    throw new Exception($error_message);
                }

                $count = 0;
                foreach ($xml->motorista as $item)
                {
                    $motorista = new Motorista;
                    $motorista->cnh_numero        = (string) $item->cnh_numero;
                    $motorista->data_emissao_cnh  = self::convertDateToDB((string) $item->data_emissao_cnh);
                    $motorista->data_validade_cnh = self::convertDateToDB((string) $item->data_validade_cnh);
                    $motorista->categoria         = (string) $item->categoria;
                    $motorista->registro_num      = (string) $item->registro_num;
                    $motorista->nome              = (string) $item->nome;
                    $motorista->data_nascimento   = self::convertDateToDB((string) $item->data_nascimento);
                    $motorista->local_nascimento  = (string) $item->local_nascimento;
                    $motorista->cpf               = (string) $item->cpf;
                    $motorista->rg_numero         = (string) $item->rg_numero;
                    $motorista->rg_emissor        = (string) $item->rg_emissor;
                    $motorista->rg_uf             = (string) $item->rg_uf;
                    $motorista->filiacao_pai      = (string) $item->filiacao_pai;
                    $motorista->filiacao_mae      = (string) $item->filiacao_mae;

                    // Validação de datas
                    if (!$motorista->data_emissao_cnh || !$motorista->data_validade_cnh || !$motorista->data_nascimento) {
                        throw new Exception('Data inválida no registro de ' . $motorista->nome);
                    }

                    $motorista->store();
                    $count++;
                }

                new TMessage('info', "Importação concluída com sucesso! {$count} registros importados.");
            }
            else
            {
                throw new Exception('O campo XML está vazio!');
            }

            TTransaction::close();
            $this->onReload();
        }
        catch (Exception $e)
        {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Exibe a página
     */
    public function show()
    {
        if (!$this->loaded) {
            $this->onReload();
        }
        parent::show();
    }
}

?>



