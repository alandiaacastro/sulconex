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
}


