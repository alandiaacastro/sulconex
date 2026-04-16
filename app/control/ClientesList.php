<?php

class ClientesList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded = false;

    private static $database = 'sample';
    private static $activeRecord = 'Clientes';
    private static $primaryKey = 'id';
    private static $formName = 'formList_Clientes';

    public function __construct()
    {
        parent::__construct();

        Clientes::ensureSchema();

        // 🔎 Formulário de filtros
        $this->form = new BootstrapFormBuilder(self::$formName);
        $this->form->setFormTitle('🔍 Buscar Clientes');

        $nome = new TEntry('nome');
        $nome->setSize('70%');

        $this->form->addFields([new TLabel('Nome')], [$nome]);

        // 🎯 Ações do formulário
        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search green');
        $this->form->addAction('Novo', new TAction(['ClientesForm', 'onEdit']), 'fa:plus-circle blue');
        $this->form->addAction('Atualizar', new TAction([$this, 'onReload']), 'fa:sync blue');

        // 📥 Ações no cabeçalho
       // $this->form->addHeaderAction('Importar XML (Arquivo)', new TAction([$this, 'onImportXml']), 'fa:file-import blue');
        $this->form->addHeaderAction('Formato XML', new TAction([$this, 'onFormatoXml']), 'fa:file-code green');
        //$this->form->addHeaderAction('🔄 Atualizar', new TAction([$this, 'onReload']), 'fa:sync blue');

        // 📊 Datagrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->datatable = 'true';

        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'right'));
        $this->datagrid->addColumn(new TDataGridColumn('nome', 'Nome', 'left'));
        $this->datagrid->addColumn(new TDataGridColumn('cnpj', 'CNPJ', 'left'));
        $this->datagrid->addColumn(new TDataGridColumn('cidade', 'Cidade', 'left'));
        $col_telefone = new TDataGridColumn('telefone', 'Telefone', 'left');
        $col_telefone->setTransformer(function ($value) {
            if (empty($value)) return '<span style="color:#999">-</span>';
            $fone = preg_replace('/\D/', '', $value);
            $foneBR = (strlen($fone) <= 11) ? '55' . $fone : $fone;
            $display = htmlspecialchars($value);
            return "<a href='tel:+{$foneBR}' title='Ligar' style='text-decoration:none'>{$display}</a> "
                 . "<a href='https://wa.me/{$foneBR}' target='_blank' title='WhatsApp' style='color:#25D366;font-size:1.1rem;margin-left:4px'><i class='fab fa-whatsapp'></i></a>";
        });
        $this->datagrid->addColumn($col_telefone);

        $col_tipo = new TDataGridColumn('tipo', 'Classificação', 'center');
        $col_tipo->setTransformer(function($value) {
            if (empty($value)) return '';
            $labels = [
                'EXPORTADOR'    => '<span class="badge badge-success">Exportador</span>',
                'IMPORTADOR'    => '<span class="badge badge-primary">Importador</span>',
                'CONSIGNATARIO' => '<span class="badge badge-warning">Consignatário</span>',
                'NOTIFICAR'     => '<span class="badge badge-info">Notificar</span>',
            ];
            $parts = explode(',', $value);
            $html = '';
            foreach ($parts as $p) {
                $p = trim($p);
                $html .= ($labels[$p] ?? '<span class="badge badge-secondary">'.$p.'</span>') . ' ';
            }
            return $html;
        });
        $this->datagrid->addColumn($col_tipo);

        // ✏️ Ações do grid
        $actionEdit = new TDataGridAction(['ClientesForm', 'onEdit'], ['id' => '{id}']);
        $actionDelete = new TDataGridAction([$this, 'onDelete'], ['id' => '{id}']);

        $this->datagrid->addAction($actionEdit, 'Editar', 'fa:edit blue');
        $this->datagrid->addAction($actionDelete, 'Excluir', 'fa:trash red');

        $this->datagrid->createModel();

        // 📄 Paginação
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->enableCounters();
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth($this->datagrid->getWidth());

        // 🎯 Painel final
        $panel = new TPanelGroup('📑 Listagem de Clientes');
        $panel->add($this->form);
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        parent::add($panel);
    }

    // 🔁 Recarregar a grid
    public function onReload($param = null)
    {
        try {
            TTransaction::open(self::$database);

            $repository = new TRepository(self::$activeRecord);
            $limit = 10;
            $criteria = new TCriteria;
            $criteria->setProperties($param);
            $criteria->setProperty('limit', $limit);

            $filter = $this->form->getData();

            if (!empty($filter->nome)) {
                $criteria->add(new TFilter('nome', 'like', "%{$filter->nome}%"));
            }

            $this->datagrid->clear();
            $objects = $repository->load($criteria, FALSE);

            if ($objects) {
                foreach ($objects as $object) {
                    $this->datagrid->addItem($object);
                }
            }

            $criteria->resetProperties();
            $count = $repository->count($criteria);

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

    public function onSearch($param = null)
    {
        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public function onDelete($param = null)
    {
        $key = $param['id'];
        $action = new TAction([$this, 'Delete']);
        $action->setParameters(['id' => $key]);

        new TQuestion('Deseja realmente excluir o registro?', $action);
    }

    public function Delete($param = null)
    {
        try {
            TTransaction::open(self::$database);

            $object = new Clientes($param['id']);
            $object->delete();

            TTransaction::close();

            $this->onReload();
            new TMessage('info', 'Registro excluído com sucesso!');
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    // 📥 Importar XML por arquivo
    public function onImportXml($param = null)
    {
        try {
            TTransaction::open(self::$database);

            $file = TFile::openFile(['xml'], 'Selecione um arquivo XML');

            if (!$file) {
                throw new Exception('Nenhum arquivo selecionado');
            }

            $xml = simplexml_load_file($file);
            if (!$xml) {
                throw new Exception('Arquivo XML inválido');
            }

            foreach ($xml->cliente as $cliente) {
                $object = new Clientes;
                $object->nome                = strtoupper((string) $cliente->nome);
                $object->email               = strtoupper((string) $cliente->email);
                $object->telefone            = strtoupper((string) $cliente->telefone);
                $object->endereco            = strtoupper((string) $cliente->endereco);
                $object->cidade              = strtoupper((string) $cliente->cidade);
                $object->estado              = strtoupper((string) $cliente->estado);
                $object->cep                 = strtoupper((string) $cliente->cep);
                $object->cnpj                = strtoupper((string) $cliente->cnpj);
                $object->inscricao_estadual  = strtoupper((string) $cliente->inscricao_estadual);
                $object->atividade           = strtoupper((string) $cliente->atividade);
                $object->emissao_crt         = strtoupper((string) $cliente->emissao_crt);
                $object->tipo                = strtoupper((string) $cliente->tipo);
                $object->store();
            }

            TTransaction::close();

            new TMessage('info', 'Clientes importados com sucesso!', new TAction(['ClientesList', 'onReload']));
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    // 📄 Mostrar formato XML de exemplo
    public function onFormatoXml()
    {
        $xml = <<<XML
<clientes>
    <cliente>
        <nome>EMPRESA EXEMPLO LTDA</nome>
        <email>CONTATO@EMPRESA.COM</email>
        <telefone>(11)99999-9999</telefone>
        <endereco>RUA EXEMPLO, 123</endereco>
        <cidade>SAO PAULO</cidade>
        <estado>SP</estado>
        <cep>01000-000</cep>
        <cnpj>00.000.000/0000-00</cnpj>
        <inscricao_estadual>123456789</inscricao_estadual>
        <atividade>TRANSPORTE</atividade>
        <emissao_crt></emissao_crt>
        <tipo>EXPORTADOR,IMPORTADOR</tipo>
    </cliente>
</clientes>
XML;

        new TMessage('info', '<pre>'.htmlspecialchars($xml).'</pre>');
    }

    // 📥 Importação XML por texto
    public static function onImportXmlText($param = null)
    {
        $form = new BootstrapFormBuilder('form_import_xml');
        $form->setFormTitle('Importar XML por Texto');

        $xml_text = new TText('xml_text');
        $xml_text->setSize('100%', 300);

        $form->addFields([new TLabel('Cole o conteúdo do XML aqui:')], [$xml_text]);

        $form->addAction('Importar', new TAction([__CLASS__, 'processImportXmlText']), 'fa:check green');
        $form->addAction('Fechar', new TAction([__CLASS__, 'onClose']), 'fa:times red');

        $window = TWindow::create('Importar XML por Texto', 0.7, 0.7);
        $window->add($form);
        $window->show();
    }

    public static function processImportXmlText($param)
    {
        try {
            TTransaction::open(self::$database);

            if (empty($param['xml_text'])) {
                throw new Exception('O campo XML está vazio.');
            }

            // Adianti strip o primeiro '<' — restaurar se necessário
            $xmlText = trim($param['xml_text']);
            if ($xmlText !== '' && $xmlText[0] !== '<') {
                $xmlText = '<' . $xmlText;
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlText);
            libxml_clear_errors();
            if (!$xml) {
                throw new Exception('XML inválido. Verifique se o conteúdo está correto.');
            }

            foreach ($xml->cliente as $cliente) {
                $object = new Clientes;
                $object->nome                = strtoupper((string) $cliente->nome);
                $object->email               = strtoupper((string) $cliente->email);
                $object->telefone            = strtoupper((string) $cliente->telefone);
                $object->endereco            = strtoupper((string) $cliente->endereco);
                $object->cidade              = strtoupper((string) $cliente->cidade);
                $object->estado              = strtoupper((string) $cliente->estado);
                $object->cep                 = strtoupper((string) $cliente->cep);
                $object->cnpj                = strtoupper((string) $cliente->cnpj);
                $object->inscricao_estadual  = strtoupper((string) $cliente->inscricao_estadual);
                $object->atividade           = strtoupper((string) $cliente->atividade);
                $object->emissao_crt         = strtoupper((string) $cliente->emissao_crt);
                $object->tipo                = strtoupper((string) $cliente->tipo);
                $object->store();
            }

            TTransaction::close();

            new TMessage('info', 'Importação realizada com sucesso', new TAction(['ClientesList', 'onReload']));
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onClose()
    {
        TScript::create("Template.closeRightPanel();");
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload();
        }
        parent::show();
    }
}
?>
