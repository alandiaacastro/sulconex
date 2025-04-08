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

        // 🔍 FORMULÁRIO DE FILTRO
        $this->form = new BootstrapFormBuilder('form_search_Conhecimento');
        $this->form->setFormTitle('📄 Listagem de CRTs');

        $id        = new TEntry('id');
        $numero    = new TEntry('numero');
        $status    = new TEntry('status_crt_id');
        $remetente = new TEntry('nome_remetente');

        foreach ([$id, $numero, $status, $remetente] as $field) {
            $field->setSize('100%');
        }

        $this->form->addFields([new TLabel('ID')], [$id],
                               [new TLabel('Número')], [$numero]);
        $this->form->addFields([new TLabel('Status')], [$status],
                               [new TLabel('Remetente')], [$remetente]);

        // 🔘 AÇÕES DO FORMULÁRIO
        $btn_filtrar = $this->form->addAction('Filtrar', new TAction([$this, 'onSearch']), 'fa:search');
        $btn_filtrar->class = 'btn btn-sm btn-primary';

        $btn_novo = $this->form->addAction('Novo CRT', new TAction(['ConhecimentoForm', 'onEdit']), 'fa:plus green');
        $btn_novo->class = 'btn btn-sm btn-success';

        $this->form->setData(TSession::getValue(__CLASS__ . '_filter_data'));

        // 📋 DATAGRID
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->datatable = 'true';

        // 🧱 COLUNAS
        $column_id           = new TDataGridColumn('id', 'ID', 'center');
        $column_status       = new TDataGridColumn('status_crt_id', 'Status', 'left');
        $column_crt          = new TDataGridColumn('numero', 'CRT', 'left');
        $column_data         = new TDataGridColumn('data_transportador_assinatura', 'Data', 'left');
        $column_fatura       = new TDataGridColumn('fatura_crt', 'Fatura', 'left');
        $column_pais         = new TDataGridColumn('pais_destino', 'País', 'left');
        $column_remetente    = new TDataGridColumn('nome_remetente', 'Remetente', 'left');
        $column_destinatario = new TDataGridColumn('nome_destinatario', 'Destinatário', 'left');

        $this->datagrid->addColumn($column_id);
        $this->datagrid->addColumn($column_status);
        $this->datagrid->addColumn($column_crt);
        $this->datagrid->addColumn($column_data);
        $this->datagrid->addColumn($column_fatura);
        $this->datagrid->addColumn($column_pais);
        $this->datagrid->addColumn($column_remetente);
        $this->datagrid->addColumn($column_destinatario);

        // 🎨 FORMATADORES
        $column_status->setTransformer(function ($value) {
            $labels = [
                1 => 'Aberto',
                2 => 'Em Andamento',
                3 => 'Concluído',
            ];
            $colors = [
                1 => 'green',
                2 => 'blue',
                3 => 'red',
            ];
            return isset($labels[$value])
                ? "<span style='color:{$colors[$value]};font-weight:bold;'>{$labels[$value]}</span>"
                : $value;
        });

        $column_data->setTransformer(function ($value) {
            if (!empty($value)) {
                $ts = strtotime($value);
                return $ts ? date('d/m/Y', $ts) : '';
            }
            return '';
        });

        // ⚙️ AÇÕES
        $this->datagrid->addAction(
            new TDataGridAction(['ConhecimentoForm', 'onEdit'], ['key' => '{id}']),
            'Editar',
            'fa:edit blue'
        );
        // Botão "Imprimir" – ação aberta em nova aba
        $action_print = new TDataGridAction([$this, 'onPrint'], ['key' => '{id}']);
        $action_print->setParameter('static', '1');
        $action_print->setParameter('target', '_blank');
        $this->datagrid->addAction($action_print, 'Imprimir', 'fa:print green');

        $this->datagrid->addAction(
            new TDataGridAction([$this, 'onDelete'], ['key' => '{id}']),
            'Excluir',
            'fa:trash red'
        );

        // Ação "Copiar": Exibe o botão somente se o atributo 'copiacrt' for igual a '1'
        $action_copy = new TDataGridAction([__CLASS__, 'onCopy'], ['key' => '{id}']);
        $action_copy->setDisplayCondition(function($object) {
            return ($object->copiacrt == '1');
        });
        $this->datagrid->addAction($action_copy, 'Copiar', 'fa:copy orange');

        $this->datagrid->createModel();

        // 📄 PAGINAÇÃO
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth('100%');

        // 🧩 PAINEL
        $panel = new TPanelGroup('📋 CRT - Listagem');
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        // 📦 CONTAINER FINAL
        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        $container->add($panel);

        parent::add($container);
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

        if (is_numeric($data->id)) {
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

            $repository = new TRepository('Conhecimento');
            $limit = 10;

            $criteria = new TCriteria;
            $criteria->setProperties($param);
            $criteria->setProperty('limit', $limit);

            foreach (['id', 'numero', 'status_crt_id', 'remetente'] as $field) {
                $filter = TSession::getValue(__CLASS__ . "_filter_{$field}");
                if ($filter instanceof TFilter) {
                    $criteria->add($filter);
                }
            }

            $objects = $repository->load($criteria, false);
            $this->datagrid->clear();

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
        $action = new TAction([__CLASS__, 'Delete']);
        $action->setParameters($param);
        new TQuestion('Deseja realmente excluir o registro?', $action);
    }

    public static function Delete($param)
    {
        try {
            TTransaction::open('sample');
            $object = new Conhecimento($param['key']);
            $object->delete();
            TTransaction::close();

            $reload = new TAction([__CLASS__, 'onReload']);
            new TMessage('info', 'Registro deletado com sucesso.', $reload);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    // Método adaptado para impressão via PDF
    public function onPrint($param = null)
    {
        try {
            $pdfGenerator = new ConhecimentoPDFGenerator($param['key']);
            $pdfGenerator->gerarPDFArquivo();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function show()
    {
        if (!$this->loaded && !isset($_GET['method'])) {
            $this->onReload(func_get_args());
        }
        parent::show();
    }

    public static function onCopy($param)
    {
        try {
            TTransaction::open('sample');
            // Carrega o registro original
            $original = new Conhecimento($param['key']);
            // Cria uma nova instância e copia os dados (exceto o ID)
            $new = new Conhecimento();
            $data = $original->toArray();
            unset($data['id']); // remove o identificador para gerar um novo
            $new->fromArray($data);
            $new->store();
            TTransaction::close();

            $reload = new TAction([__CLASS__, 'onReload']);
            new TMessage('info', 'Registro copiado com sucesso.', $reload);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}
?>
