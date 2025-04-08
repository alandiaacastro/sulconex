<?php

class ConhecimentoList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded;
    private $column_status;
    private $column_data;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_search_Conhecimento');
        $this->form->setFormTitle('📄 Listagem de CRTs');

        $id = new TEntry('id');
        $numero = new TEntry('numero');
        $status = new TEntry('status_crt_id');
        $remetente = new TEntry('nome_remetente');

        foreach ([$id, $numero, $status, $remetente] as $field) {
            $field->setSize('100%');
        }

        $this->form->addFields([new TLabel('ID')], [$id], [new TLabel('Número')], [$numero]);
        $this->form->addFields([new TLabel('Status')], [$status], [new TLabel('Remetente')], [$remetente]);

        $this->form->addAction('Filtrar', new TAction([$this, 'onSearch']), 'fa:search')->class = 'btn btn-sm btn-primary';
        $this->form->addAction('Novo CRT', new TAction(['ConhecimentoForm', 'onEdit']), 'fa:plus green')->class = 'btn btn-sm btn-success';

        $this->form->setData(TSession::getValue(__CLASS__ . '_filter_data'));

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->datatable = 'true';

        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'center'));
        $this->column_status = new TDataGridColumn('status_crt_id', 'Status', 'left');
        $this->datagrid->addColumn($this->column_status);
        $this->datagrid->addColumn(new TDataGridColumn('numero', 'CRT', 'left'));
        $this->column_data = new TDataGridColumn('data_transportador_assinatura', 'Data', 'left');
        $this->datagrid->addColumn($this->column_data);
        $this->datagrid->addColumn(new TDataGridColumn('fatura_crt', 'Fatura', 'left'));
        $this->datagrid->addColumn(new TDataGridColumn('pais_destino', 'País', 'left'));
        $this->datagrid->addColumn(new TDataGridColumn('nome_remetente', 'Remetente', 'left'));
        $this->datagrid->addColumn(new TDataGridColumn('nome_destinatario', 'Destinatário', 'left'));

        $this->formatStatusColumn();
        $this->formatDateColumn();

        $this->addDatagridActions();

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth('100%');

        $panel = new TPanelGroup('📋 CRT - Listagem');
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        $container->add($panel);

        parent::add($container);
    }

    private function formatStatusColumn()
    {
        $this->column_status->setTransformer(function ($value) {
            $labels = [1 => 'Aberto', 2 => 'Em Andamento', 3 => 'Concluído'];
            $colors = [1 => 'green', 2 => 'blue', 3 => 'red'];
            return isset($labels[$value])
                ? "<span style='color:{$colors[$value]};font-weight:bold;'>{$labels[$value]}</span>"
                : $value;
        });
    }

    private function formatDateColumn()
    {
        $this->column_data->setTransformer(function ($value) {
            return !empty($value) && ($ts = strtotime($value)) ? date('d/m/Y', $ts) : '';
        });
    }

    private function addDatagridActions()
    {
        $this->datagrid->addAction(new TDataGridAction(['ConhecimentoForm', 'onEdit'], ['key' => '{id}']), 'Editar', 'fa:edit blue');

        $action_print = new TDataGridAction([$this, 'onPrint'], ['key' => '{id}']);
        $action_print->setParameter('static', '1');
        $action_print->setParameter('target', '_blank');
        $this->datagrid->addAction($action_print, 'Imprimir', 'fa:print green');

        $this->datagrid->addAction(new TDataGridAction([$this, 'onDelete'], ['key' => '{id}']), 'Excluir', 'fa:trash red');

        $action_copy = new TDataGridAction([__CLASS__, 'onCopy'], ['key' => '{id}']);
        $action_copy->setDisplayCondition(function ($object) {
            return $object->copiacrt == '1';
        });
        $this->datagrid->addAction($action_copy, 'Copiar', 'fa:copy orange');
    }

    private function applyFilter($field, $operator, $sessionKey, $value)
    {
        if (!empty($value)) {
            TSession::setValue(__CLASS__ . "_$sessionKey", new TFilter($field, $operator, $value));
        }
    }

    public function onSearch($param = null)
    {
        $data = $this->form->getData();

        foreach (['id', 'numero', 'status_crt_id', 'remetente'] as $field) {
            TSession::setValue(__CLASS__ . "_filter_{$field}", null);
        }

        if (!empty($data->id) && ctype_digit((string)$data->id)) {
            $this->applyFilter('id', '=', 'filter_id', $data->id);
        }
        $this->applyFilter('numero', 'like', 'filter_numero', "%{$data->numero}%");
        $this->applyFilter('status_crt_id', '=', 'filter_status_crt_id', $data->status_crt_id);
        $this->applyFilter('nome_remetente', 'like', 'filter_remetente', "%{$data->nome_remetente}%");

        $this->form->setData($data);
        TSession::setValue(__CLASS__ . '_filter_data', $data);

        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');

            $repository = new TRepository('conhecimento'); // Ajustado para 'conhecimento'
            $limit = 10;
            $criteria = new TCriteria;
            $criteria->setProperties($param);
            $criteria->setProperty('limit', $limit);

            foreach (['id', 'numero', 'status_crt_id', 'remetente'] as $field) {
                if ($filter = TSession::getValue(__CLASS__ . "_filter_{$field}")) {
                    $criteria->add($filter);
                }
            }

            $this->datagrid->clear();
            $objects = $repository->load($criteria, false);
            if ($objects) {
                foreach ($objects as $object) {
                    $this->datagrid->addItem($object);
                }
            }

            $criteria->resetProperties();
            $this->pageNavigation->setCount($repository->count($criteria));
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
        if (empty($param['key'])) {
            new TMessage('error', 'ID do registro não fornecido.');
            return;
        }

        $action = new TAction([__CLASS__, 'delete']);
        $action->setParameters($param);
        new TQuestion('Deseja realmente excluir o registro?', $action);
    }

    public static function delete($param)
    {
        try {
            if (empty($param['key'])) {
                throw new Exception('ID do registro não fornecido.');
            }

            TTransaction::open('sample');
            $object = new Conhecimento($param['key']);
            if (!$object) {
                throw new Exception('Registro não encontrado.');
            }

            $object->delete();
            TTransaction::close();

            $reload = new TAction([__CLASS__, 'onReload']);
            new TMessage('info', 'Registro deletado com sucesso.', $reload);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onPrint($param = null)
    {
        try {
            if (empty($param['key'])) {
                throw new Exception('ID do CRT não fornecido.');
            }

            $pdfGenerator = new ConhecimentoPDFGenerator($param['key']);
            $pdfGenerator->gerarPDFArquivo();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onCopy($param = null)
    {
        try {
            if (empty($param['key'])) {
                throw new Exception('ID do registro não fornecido.');
            }

            TTransaction::open('sample');

            // Carrega o registro original
            $original = new Conhecimento($param['key']);
            if (!$original) {
                throw new Exception('Registro original não encontrado.');
            }

            // Busca o registro em Permissox
            $criteria = new TCriteria;
            $criteria->add(new TFilter('id', '=', $original->permisso));
            $repository = new TRepository('permissox');
            $permissoes = $repository->load($criteria);

            if (empty($permissoes)) {
                throw new Exception("Nenhum registro encontrado em 'permissox' para 'permisso' = '{$original->permisso}'.");
            }

            $permissao = $permissoes[0];
            // Garante que numerocrt seja tratado como inteiro, com valor inicial 0 se nulo
            $currentNumeroCRT = !empty($permissao->numerocrt) ? (int)$permissao->numerocrt : 0;
            $novoNumeroCRT = $currentNumeroCRT + 1;
            $novoCRT = $permissao->permisso . str_pad($novoNumeroCRT, 5, '0', STR_PAD_LEFT);

            // Atualiza o registro em Permissox
            $permissao->numerocrt = $novoNumeroCRT;
            $permissao->store();

            // Cria a cópia do Conhecimento
            $copy = new Conhecimento();
            foreach ($original->toArray() as $attribute => $value) {
                if ($attribute !== 'id') {
                    $copy->$attribute = $value;
                }
            }

            $copy->numero = $novoCRT;
            $copy->copiacrt = null;
          //  $copy->status_crt_id = 4;
            $copy->store();

            // Atualiza o original
            $original->copiacrt = null;
            $original->store();

            TTransaction::close();

            $this->form->setData($copy);
            $this->onReload();
            new TMessage('info', "Novo CRT criado com sucesso: {$novoCRT}");
        } catch (Exception $e) {
            TTransaction::rollback();
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                preg_match("/Duplicate entry '(.+?)' for key 'numero_crt'/", $e->getMessage(), $matches);
                $numero_crt = $matches[1] ?? 'desconhecido';
                new TMessage('error', "Número CRT '{$numero_crt}' já existe no sistema.");
            } else {
                new TMessage('error', $e->getMessage());
            }
        }
    }

    public function show()
    {
        if (!$this->loaded && !isset($_GET['method'])) {
            $this->onReload();
        }
        parent::show();
    }
}

?>