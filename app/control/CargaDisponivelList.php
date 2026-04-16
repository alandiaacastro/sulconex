<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Dialog\TInputDialog;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TQuestion;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\THidden;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TUniqueSearch;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Wrapper\TQuickGrid;

class CargaDisponivelList extends TPage
{
    protected $form;
    protected $datagrid;
    protected $pageNavigation;
    protected $loaded;

    use \Adianti\Base\AdiantiStandardListTrait;

    public function __construct()
    {
        parent::__construct();

        $this->setDatabase('sample');
        $this->setActiveRecord('CargaDisponivel');
        $this->setDefaultOrder('id', 'desc');

        $this->addFilterField('origem', 'like', 'origem');
        $this->addFilterField('destino', 'like', 'destino');
        $this->addFilterField('status', '=', 'status');
        $this->addFilterField('tipo_veiculo', '=', 'tipo_veiculo');

        // Garante tabelas
        try {
            TTransaction::open('sample');
            CargaDisponivel::ensureTables();
            SolicitacaoCarga::ensureTables();
            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
        }

        $this->form = new BootstrapFormBuilder('form_search_carga_disponivel');
        $this->form->setFormTitle('Cargas Disponiveis');

        $cidades = [];
        try {
            $cidades = TabelaFrete::loadCidadeOptions();
        } catch (Exception $e) {
        }

        $origem       = new TUniqueSearch('origem');
        $destino      = new TUniqueSearch('destino');
        $status       = new TCombo('status');
        $tipo_veiculo = new TCombo('tipo_veiculo');

        $origem->addItems($cidades);
        $origem->setMinLength(2);
        $origem->setSize('100%');
        $destino->addItems($cidades);
        $destino->setMinLength(2);
        $destino->setSize('100%');
        $status->addItems(CargaDisponivel::getStatusLabels());
        $tipo_veiculo->addItems(CargaDisponivel::getTipoVeiculoItems());

        $this->form->addFields([new TLabel('Origem')], [$origem], [new TLabel('Destino')], [$destino]);
        $this->form->addFields([new TLabel('Status')], [$status], [new TLabel('Tipo Veiculo')], [$tipo_veiculo]);

        $this->form->setData(TSession::getValue($this->activeRecord . '_filter_data'));
        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addActionLink('Nova Carga', new TAction(['CargaDisponivelForm', 'onClear']), 'fa:plus green');

        $this->datagrid = new BootstrapDatagridWrapper(new TQuickGrid);
        $this->datagrid->style = 'width: 100%';

        $this->datagrid->addQuickColumn('ID', 'id', 'center', '5%');
        $this->datagrid->addQuickColumn('Titulo', 'titulo', 'left', '18%');
        $col_rota = $this->datagrid->addQuickColumn('Rota', 'origem', 'left', '20%');
        $this->datagrid->addQuickColumn('Veiculo', 'tipo_veiculo', 'center', '10%');
        $col_peso = $this->datagrid->addQuickColumn('Peso (kg)', 'peso_estimado_kg', 'right', '10%');
        $col_frete = $this->datagrid->addQuickColumn('Frete', 'valor_frete', 'right', '12%');
        $col_coleta = $this->datagrid->addQuickColumn('Coleta', 'data_coleta', 'center', '10%');
        $col_solic = $this->datagrid->addQuickColumn('Solicit.', 'id', 'center', '7%');
        $col_status = $this->datagrid->addQuickColumn('Status', 'status', 'center', '10%');

        $col_rota->setTransformer(function ($value, $object) {
            return htmlspecialchars($object->origem) . ' &rarr; ' . htmlspecialchars($object->destino);
        });

        $col_peso->setTransformer(function ($value) {
            return is_numeric($value) ? number_format((float) $value, 0, ',', '.') : '';
        });

        $col_frete->setTransformer(function ($value) {
            return is_numeric($value) ? 'R$ ' . number_format((float) $value, 2, ',', '.') : '';
        });

        $col_coleta->setTransformer(function ($value) {
            return $value ? TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy') : '';
        });

        $col_status->setTransformer(function ($value) {
            $colors = [
                'disponivel' => '#198754',
                'reservada'  => '#fd7e14',
                'encerrada'  => '#6c757d',
                'cancelada'  => '#dc3545',
            ];
            $labels = CargaDisponivel::getStatusLabels();
            $color = $colors[$value] ?? '#6c757d';
            $label = $labels[$value] ?? ucfirst($value);
            return "<span class='badge' style='background:{$color};color:#fff;font-size:.78rem;padding:4px 8px'>{$label}</span>";
        });

        $col_solic->setTransformer(function ($value, $object) {
            $count = $object->countSolicitacoesPendentes();
            $bg = $count > 0 ? '#fd7e14' : '#6c757d';
            return "<span class='badge' style='background:{$bg};color:#fff;font-size:.78rem;padding:4px 8px'>{$count}</span>";
        });

        $action_edit = new TDataGridAction(['CargaDisponivelForm', 'onEdit']);
        $action_del  = new TDataGridAction([$this, 'onDelete']);
        $action_copy = new TDataGridAction([$this, 'onCopiar']);

        $this->datagrid->addQuickAction('Editar', $action_edit, 'id', 'fa:edit blue');
        $this->datagrid->addQuickAction('Excluir', $action_del, 'id', 'fa:trash red');
        $this->datagrid->addQuickAction('Copiar e Colar', $action_copy, 'id', 'fab:whatsapp green');

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));

        $panel = new TPanelGroup;
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        $container->add($panel);
        parent::add($container);

        $action_copy->setDisplayCondition(function ($object) {
            return $object->status === 'disponivel';
        });

        TScript::create("
            (function() {
                var \$card   = \$('#form_search_carga_disponivel').closest('.card');
                var \$header = \$card.find('.card-header').first();
                var \$body   = \$card.find('.card-body').first();
                if (!\$header.length || !\$body.length) return;
                \$header.css('cursor','pointer');
                \$header.append('<span style=\"float:right;margin-left:8px\"><i class=\"fa fa-chevron-up\" id=\"carga-filter-icon\"></i></span>');
                \$header.on('click', function() {
                    \$body.slideToggle(180);
                    \$('#carga-filter-icon').toggleClass('fa-chevron-up fa-chevron-down');
                });
            })();
        ");
    }

    /**
     * Confirmação antes de excluir
     */
    public function onDelete($param)
    {
        $action = new TAction([$this, 'Delete']);
        $action->setParameters($param);
        new TQuestion('Deseja realmente excluir esta carga?', $action);
    }

    /**
     * Executa a exclusão
     */
    public function Delete($param)
    {
        try {
            TTransaction::open('sample');
            $object = new CargaDisponivel($param['key']);
            $object->delete();
            TTransaction::close();
            $this->onReload($param);
            new TMessage('info', 'Carga excluida com sucesso!');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Monta texto da carga
     */
    private static function buildCargaText($carga, bool $ocultarFrete = false)
    {
        $tipoVeic  = CargaDisponivel::getTipoVeiculoItems()[$carga->tipo_veiculo] ?? $carga->tipo_veiculo;
        $tipoCarga = CargaDisponivel::getTipoCargaItems()[$carga->tipo_carga] ?? $carga->tipo_carga;
        $peso  = is_numeric($carga->peso_estimado_kg) ? number_format((float) $carga->peso_estimado_kg, 0, ',', '.') . ' kg' : '';
        $frete = $ocultarFrete
            ? 'A combinar'
            : (is_numeric($carga->valor_frete) ? 'R$ ' . number_format((float) $carga->valor_frete, 2, ',', '.') : 'A combinar');
        $coleta = $carga->data_coleta ? date('d/m/Y', strtotime($carga->data_coleta)) : '';
        $carga->aduana_destino = $carga->getAduanaDestinoDisplay();
        $carga->aduana_origem = null;
        $observacao = trim((string) preg_replace('/\s+/', ' ', (string) ($carga->observacoes ?? '')));

        $msg = "*CARGA DISPONIVEL*\n\n";
        $msg .= "🚛 *Rota:* {$carga->origem} → {$carga->destino}\n";
        if (!empty($carga->aduana_origem))  $msg .= "🏁 *Cruze:* {$carga->aduana_origem}\n";
        if (!empty($carga->aduana_destino)) $msg .= "📍 *Aduana Destino:* {$carga->aduana_destino}\n";
        if (!empty($tipoCarga))             $msg .= "📦 *Carga:* {$tipoCarga}\n";
        if (!empty($tipoVeic))              $msg .= "🚚 *Veiculo:* {$tipoVeic}\n";
        if (!empty($peso))                  $msg .= "⚖️ *Peso:* {$peso}\n";
        $msg .= "💰 *Frete:* {$frete}\n";
        if (!empty($coleta))                $msg .= "📅 *Coleta:* {$coleta}\n";
        if (!empty($carga->descricao))      $msg .= "📍 *Local Descarga:* {$carga->descricao}\n";
        if (!empty($carga->localizacao_maps)) $msg .= "🗺️ *Maps:* {$carga->localizacao_maps}\n";
        if ($observacao !== '')             $msg .= "\n*Observacao:* {$observacao}";
        $msg .= "\n_Interessado? Entre em contato!_";

        return $msg;
    }

    /**
     * Copia texto da carga para area de transferencia
     */
    public function onCopiar($param)
    {
        $form = new BootstrapFormBuilder('form_copiar_carga');
        $modoFrete = new TCombo('modo_frete');
        $modoFrete->addItems([
            'mostrar' => 'Mostrar frete',
            'ocultar' => 'Ocultar frete (A combinar)',
        ]);
        $modoFrete->setValue('mostrar');
        $modoFrete->setSize('100%');

        $key = new THidden('key');
        $key->setValue($param['key'] ?? null);

        $form->addFields([new TLabel('Frete na copia')], [$modoFrete]);
        $form->addFields([$key]);
        $form->addAction('Copiar', new TAction([$this, 'onProcessarCopia']), 'fa:copy green');
        $form->addAction('Cancelar', new TAction([$this, 'onReload']), 'fa:times red');

        new TInputDialog('Copiar Carga', $form);
    }

    public function onProcessarCopia($param)
    {
        try {
            TTransaction::open('sample');
            $carga = new CargaDisponivel($param['key']);
            TTransaction::close();

            $ocultarFrete = (($param['modo_frete'] ?? 'mostrar') === 'ocultar');
            $msg = self::buildCargaText($carga, $ocultarFrete);
            $msgJs = addslashes($msg);
            $msgJs = str_replace("\n", "\\n", $msgJs);

            TScript::create("
                navigator.clipboard.writeText('{$msgJs}').then(function() {
                    Swal.fire({icon:'success', title:'Copiado!', text:'Mensagem copiada para a area de transferencia.', timer:2000, showConfirmButton:false});
                }).catch(function() {
                    var ta = document.createElement('textarea');
                    ta.value = '{$msgJs}';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    Swal.fire({icon:'success', title:'Copiado!', text:'Mensagem copiada para a area de transferencia.', timer:2000, showConfirmButton:false});
                });
            ");
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Envia carga via WhatsApp
     */
    public function onWhatsApp($param)
    {
        try {
            TTransaction::open('sample');
            $carga = new CargaDisponivel($param['key']);
            TTransaction::close();

            $msg = self::buildCargaText($carga);
            $url = 'https://wa.me/?text=' . rawurlencode($msg);
            TScript::create("window.open('{$url}', '_blank')");
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}
