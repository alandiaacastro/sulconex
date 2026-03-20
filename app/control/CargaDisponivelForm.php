<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TNumeric;
use Adianti\Widget\Form\TText;
use Adianti\Wrapper\BootstrapFormBuilder;

class CargaDisponivelForm extends TPage
{
    protected $form;

    public function __construct($param)
    {
        parent::__construct($param);
        parent::setTargetContainer('adianti_right_panel');

        $this->form = new BootstrapFormBuilder('form_carga_disponivel');
        $this->form->setFormTitle('Cadastro de Carga Disponivel');

        $id               = new TEntry('id');
        $titulo           = new TEntry('titulo');
        $origem           = new TEntry('origem');
        $destino          = new TEntry('destino');
        $tipo_carga       = new TCombo('tipo_carga');
        $tipo_veiculo     = new TCombo('tipo_veiculo');
        $aduana_origem    = new TEntry('aduana_origem');
        $aduana_destino   = new TEntry('aduana_destino');
        $peso_estimado_kg = new TNumeric('peso_estimado_kg', 2, ',', '.', true);
        $volume_m3        = new TNumeric('volume_m3', 2, ',', '.', true);
        $valor_frete      = new TNumeric('valor_frete', 2, ',', '.', true);
        $quantidade       = new TEntry('quantidade');
        $quantidade->setInputType('number');
        $quantidade->setValue(1);
        $data_coleta      = new TDate('data_coleta');
        $data_entrega     = new TDate('data_entrega_prevista');
        $descricao        = new TText('descricao');
        $localizacao_maps = new TEntry('localizacao_maps');
        $observacoes      = new TText('observacoes');
        $status           = new TCombo('status');

        $id->setEditable(FALSE);
        $titulo->addValidation('Titulo', new TRequiredValidator);
        $origem->addValidation('Origem', new TRequiredValidator);
        $destino->addValidation('Destino', new TRequiredValidator);

        $tipo_carga->addItems(CargaDisponivel::getTipoCargaItems());
        $tipo_veiculo->addItems(CargaDisponivel::getTipoVeiculoItems());
        $aduana_origem->setSize('100%');
        $aduana_destino->setSize('100%');
        $status->addItems(CargaDisponivel::getStatusLabels());

        $data_coleta->setMask('dd/mm/yyyy');
        $data_coleta->setDatabaseMask('yyyy-mm-dd');
        $data_entrega->setMask('dd/mm/yyyy');
        $data_entrega->setDatabaseMask('yyyy-mm-dd');

        $descricao->setSize('100%', 80);
        $localizacao_maps->setSize('100%');
        $localizacao_maps->placeholder = 'Cole o link do Google Maps aqui';
        $observacoes->setSize('100%', 60);

        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Titulo', '#FF0000')], [$titulo]);
        $this->form->addFields([new TLabel('Origem', '#FF0000')], [$origem], [new TLabel('Destino', '#FF0000')], [$destino]);
        $this->form->addFields([new TLabel('Aduana Origem (Cruze)')], [$aduana_origem], [new TLabel('Aduana Destino')], [$aduana_destino]);
        $this->form->addFields([new TLabel('Tipo de Carga')], [$tipo_carga], [new TLabel('Tipo de Veiculo')], [$tipo_veiculo]);
        $this->form->addFields([new TLabel('Peso Estimado (kg)')], [$peso_estimado_kg], [new TLabel('Volume (m3)')], [$volume_m3]);
        $this->form->addFields([new TLabel('Valor do Frete (R$)')], [$valor_frete], [new TLabel('Qtd. Cargas')], [$quantidade]);
        $this->form->addFields([new TLabel('Data Coleta')], [$data_coleta], [new TLabel('Previsao Entrega')], [$data_entrega]);
        $this->form->addFields([new TLabel('Local de Descarga')], [$descricao]);
        $this->form->addFields([new TLabel('Localizacao Google Maps')], [$localizacao_maps]);
        $this->form->addFields([new TLabel('Observacoes')], [$observacoes]);
        $this->form->addFields([new TLabel('Status')], [$status]);

        // Auto-preencher titulo ao sair do campo destino
        $destino->setExitAction(new TAction([$this, 'onAutoTitulo']));

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');
        $this->form->addActionLink('Listagem', new TAction(['CargaDisponivelList', 'onReload']), 'fa:table blue');

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        parent::add($container);
    }

    public static function onAutoTitulo($param)
    {
        $titulo  = trim($param['titulo'] ?? '');
        $origem  = trim($param['origem'] ?? '');
        $destino = trim($param['destino'] ?? '');
        $tipo    = trim($param['tipo_carga'] ?? '');

        if (empty($titulo) && !empty($origem) && !empty($destino)) {
            $tipoCargaItems = CargaDisponivel::getTipoCargaItems();
            $tipoLabel = $tipoCargaItems[$tipo] ?? '';
            $novoTitulo = trim("{$tipoLabel} {$origem} - {$destino}");
            TEntry::setValue('form_carga_disponivel', 'titulo', $novoTitulo);
        }
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('sample');
            CargaDisponivel::ensureTables();

            $this->form->validate();
            $object = $this->form->getData('CargaDisponivel');

            $quantidade = max(1, (int) ($object->quantidade ?? 1));

            if (empty($object->created_by)) {
                $object->created_by = TSession::getValue('userid');
            }

            // Se e novo (sem ID) e quantidade > 1, gera multiplos registros
            if (empty($object->id) && $quantidade > 1) {
                $dados = (array) $object;
                unset($dados['id']);

                for ($i = 1; $i <= $quantidade; $i++) {
                    $carga = new CargaDisponivel;
                    foreach ($dados as $k => $v) {
                        if ($k !== 'quantidade') {
                            $carga->$k = $v;
                        }
                    }
                    $carga->quantidade = 1;
                    $carga->store();
                }

                $this->form->clear(true);
                TTransaction::close();
                new TMessage('info', "{$quantidade} cargas criadas com sucesso!");
            } else {
                // Edicao ou quantidade 1
                $object->quantidade = 1;
                $object->store();
                $this->form->setData($object);
                TTransaction::close();
                new TMessage('info', 'Carga salva com sucesso!');
            }
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onEdit($param)
    {
        try {
            if (isset($param['key'])) {
                TTransaction::open('sample');
                CargaDisponivel::ensureTables();
                $object = new CargaDisponivel($param['key']);
                $this->form->setData($object);
                TTransaction::close();
            } else {
                $this->onClear($param);
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
}
