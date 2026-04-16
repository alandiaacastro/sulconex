<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Control\TWindow;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TScript;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TToast;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\THidden;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TNumeric;
use Adianti\Widget\Form\TUniqueSearch;
use Adianti\Widget\Wrapper\TDBCombo;
use Adianti\Wrapper\BootstrapFormBuilder;

class TabelaFreteForm extends TPage
{
    private $form;
    private static string $formName   = 'form_TabelaFrete';
    private static string $windowName = 'win_TabelaFrete';
    private static string $database   = 'sample';

    private static function normalizeRouteData(array $param): array
    {
        return [
            'id'           => $param['id'] ?? null,
            'tipo_veiculo' => TabelaFrete::normalizeUpper($param['tipo_veiculo'] ?? ''),
            'tipo'         => TabelaFrete::normalizeUpper($param['tipo'] ?? ''),
            'origem'       => TabelaFrete::normalizeUpper($param['origem'] ?? ''),
            'destino'      => TabelaFrete::normalizeUpper($param['destino'] ?? ''),
            'valor_frete'  => TabelaFrete::parseMoney($param['valor_frete'] ?? '0'),
        ];
    }

    private static function validateRouteData(array $data): void
    {
        if ($data['tipo_veiculo'] === '') {
            throw new Exception('Tipo de Veiculo e obrigatorio.');
        }

        if ($data['tipo'] !== '' && !in_array($data['tipo'], TabelaFrete::TIPOS, true)) {
            throw new Exception('Tipo invalido. Use NAC ou INTL.');
        }

        if ($data['origem'] === '') {
            throw new Exception('Origem e obrigatoria.');
        }

        if ($data['destino'] === '') {
            throw new Exception('Destino e obrigatorio.');
        }

        if ($data['valor_frete'] <= 0) {
            throw new Exception('Valor do frete e obrigatorio.');
        }
    }

    private static function validateUniqueRoute(array $data): void
    {
        if (!empty($data['id'])) {
            return;
        }

        $existingId = TabelaFrete::findExistingRouteId(
            TTransaction::get(),
            $data['origem'],
            $data['destino'],
            $data['tipo_veiculo']
        );

        if ($existingId !== null) {
            throw new Exception("Ja existe uma rota com esta combinacao de Origem, Destino e Tipo de Veiculo (ID #{$existingId}).");
        }
    }

    public function __construct($param = null)
    {
        parent::__construct();

        $fromProposta = is_array($param) && !empty($param['from_proposta']);
        if (!$fromProposta) {
            parent::setTargetContainer('adianti_right_panel');
        }

        $this->form = new BootstrapFormBuilder(self::$formName);

        $id            = new THidden('id');
        $fromPropostaH = new THidden('from_proposta');
        $propostaForm  = new THidden('proposta_form');

        $tipoVeiculo = new TDBCombo('tipo_veiculo', self::$database, 'TipoVeiculo', 'nome', '{nome}', 'nome asc');
        $tipoVeiculo->setSize('100%');
        $tipoVeiculo->enableSearch();

        $tipo = new TCombo('tipo');
        $tipo->addItems(array_combine(TabelaFrete::TIPOS, TabelaFrete::TIPOS));
        $tipo->setSize('100%');

        $cidades = TabelaFrete::loadCidadeOptions();

        $origem = new TUniqueSearch('origem');
        $origem->addItems($cidades);
        $origem->setMinLength(2);
        $origem->setTip('Cidade de origem');
        $origem->setSize('100%');

        $destino = new TUniqueSearch('destino');
        $destino->addItems($cidades);
        $destino->setMinLength(2);
        $destino->setTip('Cidade de destino');
        $destino->setSize('100%');

        $valorFrete = new TNumeric('valor_frete', 2, ',', '.', true);
        $valorFrete->setTip('Valor do frete para este trecho');
        $valorFrete->setSize('100%');

        $this->form->addFields([$id], [$fromPropostaH], [$propostaForm]);

        $row = $this->form->addFields(
            [new TLabel('Tipo de Veiculo <span style="color:#dc2626">*</span>'), $tipoVeiculo],
            [new TLabel('Tipo'), $tipo]
        );
        $row->layout = ['col-sm-6', 'col-sm-3'];

        $row = $this->form->addFields(
            [new TLabel('Origem <span style="color:#dc2626">*</span>'), $origem],
            [new TLabel('Destino <span style="color:#dc2626">*</span>'), $destino]
        );
        $row->layout = ['col-sm-6', 'col-sm-6'];

        $row = $this->form->addFields(
            [new TLabel('Frete (R$) <span style="color:#dc2626">*</span>'), $valorFrete]
        );
        $row->layout = ['col-sm-6'];

        $saveButton = $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save');
        $saveButton->class = 'btn btn-sm btn-primary';
        $this->form->addAction('Voltar', new TAction([$this, 'onCancelar']), 'fa:arrow-left blue');

        if (!$fromProposta) {
            $this->form->addHeaderActionLink('Fechar', new TAction([$this, 'onClose']), 'fa:times red');
        }

        if (is_array($param) && !empty($param)) {
            $this->form->setData((object) $param);
        }

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);

        parent::add($container);
    }

    public static function openPanel(string $parentFormName): void
    {
        $window = TWindow::create(self::$windowName, 660, 560);
        $window->setTitle('&#xf0d1;&nbsp; Cadastrar Nova Rota de Frete');

        $window->add(new self([
            'from_proposta' => '1',
            'proposta_form' => $parentFormName,
        ]));
        $window->show();

        TScript::create("
            setTimeout(function() {
                var dialog = $('.ui-dialog[aria-describedby*=\"" . self::$windowName . "\"]').last();
                if (!dialog.length) {
                    dialog = $('.ui-dialog').last();
                }

                dialog.css({
                    position: 'fixed',
                    top: '0',
                    right: '0',
                    left: 'auto',
                    bottom: '0',
                    margin: '0',
                    height: '100vh',
                    maxHeight: '100vh',
                    borderRadius: '0',
                    boxShadow: '-4px 0 24px rgba(0,0,0,.18)'
                });

                dialog.find('.ui-dialog-content').css({
                    height: 'calc(100vh - 60px)',
                    maxHeight: 'calc(100vh - 60px)',
                    overflowY: 'auto'
                });

                dialog.find('.ui-dialog-titlebar').css({
                    background: 'linear-gradient(135deg,#198754,#0d6efd)',
                    color: '#fff',
                    borderRadius: '0'
                });

                dialog.find('.ui-dialog-titlebar-close').css({ color: '#fff', opacity: 1 });
            }, 80);
        ");
    }

    public static function onSave($param = null): void
    {
        $param = is_array($param) ? $param : [];

        try {
            $data = self::normalizeRouteData($param);
            self::validateRouteData($data);

            TTransaction::open(self::$database);
            self::validateUniqueRoute($data);

            $object = !empty($data['id']) ? new TabelaFrete($data['id']) : new TabelaFrete;
            $object->tipo_veiculo = $data['tipo_veiculo'];
            $object->tipo         = $data['tipo'] ?: null;
            $object->origem       = $data['origem'];
            $object->destino      = $data['destino'];
            $object->valor_frete  = $data['valor_frete'];
            $object->atualizacao  = date('Y-m-d H:i:s');
            $object->store();
            TTransaction::close();

            $parentForm = (string) ($param['proposta_form'] ?? '');

            TToast::show('success', 'Rota cadastrada com sucesso!', 'bottom right', 'far:check-circle');

            if ($parentForm !== '') {
                TWindow::closeWindow();
                TScript::create("
                    setTimeout(function() {
                        __adianti_post_data('{$parentForm}', 'class=PropostaForm&method=onReloadFretesCombos');
                    }, 400);
                ");
                return;
            }

            TScript::create("Template.closeRightPanel(); __adianti_load_page('index.php?class=TabelaFreteList');");
        } catch (Exception $e) {
            try { TTransaction::rollback(); } catch (Exception $rollbackException) {}
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onCancelar($param = null): void
    {
        $param = is_array($param) ? $param : [];
        $parentForm = (string) ($param['proposta_form'] ?? '');

        if ($parentForm !== '') {
            TWindow::closeWindow();
            return;
        }

        TScript::create('Template.closeRightPanel();');
    }

    public static function onClose($param = null): void
    {
        TScript::create('Template.closeRightPanel();');
    }

    public function onEdit($param = null): void
    {
        $param = is_array($param) ? $param : [];

        try {
            if (empty($param['key'])) {
                return;
            }

            TTransaction::open(self::$database);
            $object = new TabelaFrete($param['key']);
            TTransaction::close();

            $this->form->setData($object);

            $tipoVeiculo = addslashes((string) $object->tipo_veiculo);
            $origem      = addslashes((string) $object->origem);
            $destino     = addslashes((string) $object->destino);

            TScript::create("
                setTimeout(function() {
                    $('#tipo_veiculo').val('{$tipoVeiculo}').trigger('change');
                    $('#origem').val(['{$origem}']).trigger('change');
                    $('#destino').val(['{$destino}']).trigger('change');
                }, 300);
            ");
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}
