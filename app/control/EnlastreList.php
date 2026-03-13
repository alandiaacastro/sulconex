<?php
class EnlastreList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;

    public function __construct()
    {
        parent::__construct();

        // --- Formulário de filtro ---
        $this->form = new BootstrapFormBuilder('form_search_enlastre');
        $this->form->setFormTitle('ðŸ” Filtro de Enlastres');

        $numero = new TEntry('numeroenlastre');
        $numero->setSize('100%');
        $this->form->addFields([new TLabel('Número Enlastre')], [$numero]);

        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search green');
        $this->form->addAction('Numerar Enlastre', new TAction([$this, 'onEnlastreNumerar']), 'fa:plus-circle blue');

        // --- Datagrid ---
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->addColumn(new TDataGridColumn('id',             'ID',               'center',  50));
        $this->datagrid->addColumn(new TDataGridColumn('permisso',      'Permissão',        'left',   100));
        $this->datagrid->addColumn(new TDataGridColumn('numeroenlastre','Número Enlastre',  'center', 150));
        $this->datagrid->addColumn(new TDataGridColumn('trator',         'Trator',           'left',   100));
        $this->datagrid->addColumn(new TDataGridColumn('semi',           'Semi-reboque',     'left',   100));
        $this->datagrid->addColumn(new TDataGridColumn('motorista',      'Motorista',        'left',   150));

        // ações de edição e exclusão
        $edit = new TDataGridAction([$this, 'onEdit'],   ['id'=>'{id}']);
        $edit->setImage('fa:edit blue');
        $del  = new TDataGridAction([$this, 'onDelete'], ['id'=>'{id}']);
        $del->setImage('fa:trash red');

        $this->datagrid->addAction($edit);
        $this->datagrid->addAction($del);
        $this->datagrid->createModel();

        // --- Paginação ---
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth('100%');

        // --- Layout ---
        $container = new TVBox;
        $container->style = 'width:100%';
        $container->add($this->form);
        $container->add($this->datagrid);
        $container->add($this->pageNavigation);

        parent::add($container);

        // carrega dados
        $this->onReload();
    }

    public function onSearch($param)
    {
        // grava filtro na sessão
        TSession::setValue('Enlastre_filter_numeroenlastre', $this->form->getField('numeroenlastre')->getValue());
        $this->onReload($param);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');

            $repo     = new TRepository('Enlastre');
            $criteria = new TCriteria;

            // aplica filtro
            $numero = TSession::getValue('Enlastre_filter_numeroenlastre');
            if ($numero) {
                $criteria->add(new TFilter('numeroenlastre', '=', $numero));
            }

            // ordenação e limite
            $criteria->setProperty('order', 'id');
            $criteria->setProperty('limit',  10);

            // carrega objetos e preenche o datagrid
            $objects = $repo->load($criteria);
            $this->datagrid->clear();
            if ($objects) {
                foreach ($objects as $obj) {
                    // busca texto da permissão
                    $perm = new Permisso($obj->permisso_id);
                    $obj->permisso = strtoupper($perm->permisso);
                    $this->datagrid->addItem($obj);
                }
            }

            // paginação
            $count = $repo->count($criteria);
            $this->pageNavigation->setCount($count);
            $this->pageNavigation->setProperties($param);
            $this->pageNavigation->setLimit(10);

            TTransaction::close();
        }
        catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onEdit($param)
    {
        TApplication::loadPage('EnlastreForm', 'onEdit', ['id'=>$param['id']]);
    }

    public function onDelete($param)
    {
        $action = new TAction([$this, 'deleteConfirmed']);
        $action->setParameters($param);
        new TQuestion('Deseja realmente excluir este registro?', $action);
    }

    public function deleteConfirmed($param)
    {
        try {
            TTransaction::open('sample');
            $object = new Enlastre($param['id']);
            $object->delete();
            TTransaction::close();

            new TMessage('info', 'Registro excluído com sucesso');
            $this->onReload($param);
        }
        catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Abre o painel lateral para gerar um novo enlastre.
     */
    public function onEnlastreNumerar($param)
    {
        try {
            TTransaction::open('sample');

            // monta o form
            $form = new BootstrapFormBuilder('form_novo_enlastre');
            $form->setFormTitle('Gerar Novo Enlastre');
            $form->setFieldSizes('100%');

            // campos
            $fields = [
                ['Permissão',    'permisso_id', 'Permisso',    'id',    'permisso',    true ],
                ['Trator',       'trator',      'AnttConsulta','placa', 'placa',       false],
                ['Semi-reboque', 'reboque',     'AnttConsulta','placa', 'placa',       false],
            ];
            foreach ($fields as list($lblTxt,$name,$model,$key,$disp,$req)) {
                $label = new TLabel($lblTxt);
                $label->setProperty('style','display:block;font-weight:bold;margin-bottom:5px;');

                $combo = new TDBCombo($name, 'sample', $model, $key, $disp);
                $combo->enableSearch();
                if ($req) {
                    $combo->addValidation($lblTxt, new TRequiredValidator);
                }
                $combo->setProperty('style','width:100%;margin-bottom:12px;');
                $combo->setProperty('placeholder','Selecione');

                $form->addFields([$label], [$combo]);
            }

            // motorista
            $lblM = new TLabel('Motorista');
            $lblM->setProperty('style','display:block;font-weight:bold;margin-bottom:5px;');
            $entM = new TEntry('motorista');
            $entM->setProperty('style','width:100%;margin-bottom:15px;');
            $entM->setProperty('placeholder','Digite o nome do Motorista');
            $form->addFields([$lblM], [$entM]);

            // ações do form
            $form->addAction(
                'Gerar Enlastre',
                new TAction([$this, 'gerarEnlastreComRegistro']),
                'fa:check-circle green'
            );
            $form->addAction(
                'Cancelar',
                new TAction([$this, 'closeDrawer']),
                'fa:times-circle red'
            );

            TTransaction::close();

            // monta o drawer
            $drawer = new TElement('div');
            $drawer->id = 'enlastre_drawer';
            $drawer->setProperty('style',
                'position:fixed;
                 top:0;
                 right:0;
                 width:700px;
                 height:100%;
                 overflow:auto;
                 padding:10px 15px;
                 background:#fff;
                 box-shadow:-4px 0 12px rgba(0,0,0,0.1);
                 z-index:1060;'
            );

            // botão fechar
            $btn = new TElement('button');
            $btn->add('<i class="fa fa-times"></i>');
            $btn->setProperty('type','button');
            $btn->setProperty('style',
                'position:absolute;
                 top:10px; right:10px;
                 border:none; background:transparent;
                 font-size:1.2em; cursor:pointer;'
            );
            $btn->onclick = "enlastre_closeDrawer()";
            $drawer->add($btn);

            // título interno
            $drawer->add( TElement::tag('h4','Gerar Novo Enlastre') );

            // adiciona form
            $drawer->add($form);

            // insere na página
            parent::add($drawer);

            // JS para fechar
            TScript::create("
                function enlastre_closeDrawer() {
                    document.getElementById('enlastre_drawer').remove();
                }
            ");
        }
        catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Gera e salva o enlastre numerado.
     */
    public function gerarEnlastreComRegistro($param)
    {
        try {
            TTransaction::open('sample');
            $data   = (object) $param;
            $perm   = new Permisso($data->permisso_id);
            $prefix = preg_replace('/[^A-Z0-9]/','', strtoupper($perm->permisso));

            // obtém o último número
            $conn  = TTransaction::get();
            $stmt  = $conn->prepare(
                "SELECT MAX(numeroenlastre) AS last
                   FROM enlastre
                  WHERE numeroenlastre LIKE :pfx"
            );
            $stmt->execute([':pfx'=> "{$prefix}-%"]);
            $row   = $stmt->fetchObject();
            $lastN = $row->last ?? null;
            $num   = 0;
            if ($lastN && preg_match("/{$prefix}-(\d+)/",$lastN,$m)) {
                $num = (int) $m[1];
            }
            $newNum = sprintf('%s-%05d', $prefix, $num+1);

            // persiste
            $en = new Enlastre;
            $en->permisso_id     = $data->permisso_id;
            $en->numeroenlastre  = $newNum;
            $en->trator          = strtoupper($data->trator ?? '');
            $en->semi            = strtoupper($data->semi ?? '');
            $en->motorista       = strtoupper($data->motorista ?? '');
            $en->store();

            TTransaction::close();

            new TMessage('info', "Enlastre gerado: <b>{$newNum}</b>");
            // fecha drawer
            TScript::create("enlastre_closeDrawer()");
            // recarrega lista
            AdiantiCoreApplication::loadPage(__CLASS__, 'onReload');
        }
        catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Callback do botÃ£o â€œCancelarâ€ dentro do drawer.
     */
    public function closeDrawer($param)
    {
        TScript::create("enlastre_closeDrawer()");
    }

    /**
     * Garante recarga de dados ao exibir a página.
     */
    public function show()
    {
        $this->onReload();
        parent::show();
    }
}
