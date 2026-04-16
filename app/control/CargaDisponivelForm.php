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
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TNumeric;
use Adianti\Widget\Form\TText;
use Adianti\Widget\Wrapper\TDBCombo;
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
        $frete_id         = new TDBCombo('frete_id', 'sample', 'TabelaFrete', 'id', TabelaFrete::COMBO_MASK_WITH_TYPE, 'id desc');
        $tipo_carga       = new TCombo('tipo_carga');
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
        $frete_id->addValidation('Tabela de Frete', new TRequiredValidator);

        $frete_id->setSize('100%');
        $frete_id->enableSearch();
        $frete_id->setChangeAction(new TAction([__CLASS__, 'onTabelaFreteSelect']));
        $tipo_carga->addItems(CargaDisponivel::getTipoCargaItems());
        $status->addItems(CargaDisponivel::getStatusLabels());

        $data_coleta->setMask('dd/mm/yyyy');
        $data_coleta->setDatabaseMask('yyyy-mm-dd');
        $data_entrega->setMask('dd/mm/yyyy');
        $data_entrega->setDatabaseMask('yyyy-mm-dd');

        $descricao->setSize('100%', 80);
        $localizacao_maps->setSize('100%');
        $localizacao_maps->placeholder = 'Cole o link do Google Maps aqui';
        $observacoes->setSize('100%', 60);
        $valor_frete->setEditable(false);

        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Titulo', '#FF0000')], [$titulo]);
        $this->form->addFields([new TLabel('Tabela de Frete', '#FF0000')], [$frete_id]);
        $this->form->addFields([new TLabel('Tipo de Carga')], [$tipo_carga]);
        $this->form->addFields([new TLabel('Peso Estimado (kg)')], [$peso_estimado_kg], [new TLabel('Volume (m3)')], [$volume_m3]);
        $this->form->addFields([new TLabel('Valor do Frete (R$)')], [$valor_frete], [new TLabel('Qtd. Cargas')], [$quantidade]);
        $this->form->addFields([new TLabel('Data Coleta')], [$data_coleta], [new TLabel('Previsao Entrega')], [$data_entrega]);
        $this->form->addFields([new TLabel('Local de Descarga')], [$descricao]);
        $this->form->addFields([new TLabel('Localizacao Google Maps')], [$localizacao_maps]);
        $this->form->addFields([new TLabel('Observacoes')], [$observacoes]);
        $this->form->addFields([new TLabel('Status')], [$status]);

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
            $data = new stdClass;
            $data->titulo = $novoTitulo;
            TForm::sendData('form_carga_disponivel', $data, false, false);
        }
    }

    public static function onTabelaFreteSelect($param)
    {
        $freteId = $param['frete_id'] ?? null;
        if (is_array($freteId)) {
            $freteId = reset($freteId);
        }

        if (empty($freteId)) {
            return;
        }

        try {
            TTransaction::open('sample');
            $frete = new TabelaFrete((int) $freteId);
            TTransaction::close();

            if (!empty($frete->id)) {
                $data = new stdClass;
                $data->frete_id = (int) $frete->id;
                $data->origem = (string) $frete->origem;
                $data->destino = (string) $frete->destino;
                $data->valor_frete = number_format((float) $frete->valor_frete, 2, ',', '.');
                TForm::sendData('form_carga_disponivel', $data, false, false);

                $param['origem'] = $data->origem;
                $param['destino'] = $data->destino;
                self::onAutoTitulo($param);
            }
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
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

                if (empty($object->frete_id) && !empty($object->origem) && !empty($object->destino)) {
                    $object->frete_id = TabelaFrete::findLatestRouteIdByData(
                        TTransaction::get(),
                        (string) $object->origem,
                        (string) $object->destino,
                        (string) ($object->tipo_veiculo ?? ''),
                        isset($object->valor_frete) ? (float) $object->valor_frete : null
                    );
                }

                if (empty($object->aduana_destino) && !empty($object->aduana_origem)) {
                    $object->aduana_destino = $object->aduana_origem;
                }

                $this->form->setData($object);
                TTransaction::close();

                if (!empty($object->frete_id)) {
                    self::onTabelaFreteSelect([
                        'frete_id' => $object->frete_id,
                        'titulo' => $object->titulo,
                        'tipo_carga' => $object->tipo_carga,
                    ]);
                }
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
