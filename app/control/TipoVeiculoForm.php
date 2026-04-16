<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TScript;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TToast;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\THidden;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapFormBuilder;

class TipoVeiculoForm extends TPage
{
    private static string $formName = 'form_TipoVeiculo';
    private static string $database = 'sample';

    private $form;

    private static function normalizeName($value): string
    {
        return trim(mb_strtoupper((string) $value, 'UTF-8'));
    }

    public function __construct($param = null)
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder(self::$formName);
        $this->form->setFormTitle('Tipo de Veiculo');

        $id = new THidden('id');
        $nome = new TEntry('nome');
        $nome->setSize('100%');
        $nome->placeholder = 'Ex: CARRETA SIDER';

        $this->form->addFields([$id]);

        $row = $this->form->addFields(
            [new TLabel('Nome <span style="color:#dc2626">*</span>'), $nome]
        );
        $row->layout = ['col-sm-8'];

        $saveButton = $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save');
        $saveButton->class = 'btn btn-sm btn-primary';

        $this->form->addAction('Novo', new TAction([$this, 'onNew']), 'fa:plus green');
        $this->form->addAction('Voltar', new TAction(['TipoVeiculoList', 'onReload']), 'fa:arrow-left blue');

        $container = new TVBox;
        $container->style = 'width:100%';
        $container->add($this->form);

        parent::add($container);
    }

    public function onEdit($param = null): void
    {
        $param = is_array($param) ? $param : [];

        try {
            if (empty($param['key'])) {
                return;
            }

            TTransaction::open(self::$database);
            $object = new TipoVeiculo($param['key']);
            $this->form->setData($object);
            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onNew($param = null): void
    {
        TScript::create("__adianti_load_page('index.php?class=TipoVeiculoForm');");
    }

    public static function onSave($param = null): void
    {
        $param = is_array($param) ? $param : [];

        try {
            $nome = self::normalizeName($param['nome'] ?? '');
            if ($nome === '') {
                throw new Exception('Nome e obrigatorio.');
            }

            TTransaction::open(self::$database);

            $object = !empty($param['id']) ? new TipoVeiculo($param['id']) : new TipoVeiculo;
            $object->nome = $nome;
            $object->store();

            TTransaction::close();

            TToast::show('success', 'Tipo de veiculo salvo!', 'bottom right', 'far:check-circle');
            TScript::create("__adianti_load_page('index.php?class=TipoVeiculoList');");
        } catch (Exception $e) {
            try { TTransaction::rollback(); } catch (Exception $rollbackException) {}
            new TMessage('error', $e->getMessage());
        }
    }
}
