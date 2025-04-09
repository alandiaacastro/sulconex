<?php

class ConhecimentoFormnumerar extends TWindow
{
    protected $form;
    private static $database = 'sample';
    private static $activeRecord = 'Conhecimento';
    private static $primaryKey = 'id';
    private static $formName = 'ConhecimentoFormnumerar';

    public function __construct($param)
    {
        parent::__construct();
        parent::setSize(0.5, null);
        parent::setTitle("NOVO CRT - GERAR NUMERAÇÃO");
        parent::setProperty('class', 'window_modal');

        if (!empty($param['target_container'])) {
            $this->adianti_target_container = $param['target_container'];
        }

        $this->form = new BootstrapFormBuilder(self::$formName);
        $this->form->setFormTitle("NOVO CRT - GERAR NUMERAÇÃO");

        $permisso = new TSeekButton('permisso');
        $button_gerar = new TButton('button_gerar');

        $permisso->setSize('90%');

        // Ação do botão GERAR CRT
        $button_gerar->setAction(new TAction([$this, 'onnumerarcrt']), "GERAR");
        $button_gerar->addStyleClass('btn-danger');
        $button_gerar->setImage('fas:sort-numeric-up #FFFFFF');

        // 🔧 AQUI ESTÁ A CORREÇÃO DO SEEKBUTTON
        $seek_action = new TAction(['TStandardSeek', 'show']);

        $seed = AdiantiApplicationConfig::get()['general']['seed'];
        $seekFields = base64_encode(serialize([
            ['name' => 'permisso', 'column' => '{permisso}']
        ]));
        $seekFilters = base64_encode(serialize([]));

        $seek_action->setParameters([
            'class' => 'PermissoxSeek', // importante: nome da classe do Seek real
            '_seek_filter_column' => 'permisso',
            '_seek_fields' => $seekFields,
            '_seek_filters' => $seekFilters,
            '_seek_hash' => md5($seed . $seekFields . $seekFilters)
        ]);

        $permisso->setAction($seek_action);

        // Monta o formulário
        $this->form->addFields([
            new TLabel("Permisso", null, '14px', null),
            $permisso
        ], [
            new TLabel("Numeração CRT", null, '14px', null),
            $button_gerar
        ])->layout = ['col-sm-6', 'col-sm-6'];

        $btn_voltar = $this->form->addAction("Voltar", new TAction(['ConhecimentoList', 'onReload']), 'fas:arrow-left #000000');

        parent::add($this->form);
    }

    public static function onnumerarcrt($param = null)
    {
        try {
            TTransaction::open(self::$database);

            if (empty($param['permisso'])) {
                throw new Exception('Permisso não fornecido.');
            }

            $criteria = new TCriteria;
            $criteria->add(new TFilter('permisso', '=', $param['permisso']));
            $repo = new TRepository('Permissox');
            $permisso_obj = $repo->load($criteria)[0] ?? null;

            if (!$permisso_obj) {
                throw new Exception('Permisso não encontrada.');
            }

            $novoNumero = $permisso_obj->numerocrt + 1;
            $novoCRT = $permisso_obj->permisso . str_pad($novoNumero, 5, '0', STR_PAD_LEFT);

            $permisso_obj->numerocrt = $novoNumero;
            $permisso_obj->store();

            $crt = new Conhecimento;
            $crt->permisso = $permisso_obj->permisso;
            $crt->pais_destino = $permisso_obj->pais_destino;
            $crt->numero = $novoCRT;
            $crt->status_crt_id = 4;
            $crt->store();

            TTransaction::close();

            TWindow::closeWindow();
            new TMessage('info', "CRT gerado com sucesso: {$novoCRT}");

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onEdit($param)
    {
        try {
            if (isset($param['key'])) {
                TTransaction::open(self::$database);
                $object = new Conhecimento($param['key']);
                $this->form->setData($object);
                TTransaction::close();
            } else {
                $this->form->clear();
            }
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onClear($param)
    {
        $this->form->clear(true);
    }

    public function onShow($param = null)
    {
    }

    public static function getFormName()
    {
        return self::$formName;
    }
}

