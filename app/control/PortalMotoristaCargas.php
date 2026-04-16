<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TText;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Form\TUniqueSearch;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Wrapper\TDBCombo;

class PortalMotoristaCargas extends TPage
{
    private $form;
    private $cardsContainer;
    private $pageNavigation;
    private $loaded;

    public function __construct()
    {
        parent::__construct();


        // Verifica autenticação do portal
        if (!TSession::getValue('portal_motorista_logged') && !TSession::getValue('logged')) {
            AdiantiCoreApplication::gotoPage('PortalMotoristaLogin');
            return;
        }

        // Garante tabelas
        try {
            TTransaction::open('sample');
            CargaDisponivel::ensureTables();
            SolicitacaoCarga::ensureTables();
            Motorista::ensureTables();
            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
        }

        // Filtros
        $this->form = new BootstrapFormBuilder('form_portal_cargas');
        $this->form->setFormTitle('Cargas Disponiveis');

        $cidades = [];
        try {
            $cidades = TabelaFrete::loadCidadeOptions();
        } catch (Exception $e) {
        }

        $origem       = new TUniqueSearch('origem');
        $destino      = new TUniqueSearch('destino');
        $tipo_veiculo = new TCombo('tipo_veiculo');

        $origem->addItems($cidades);
        $origem->setMinLength(2);
        $origem->setSize('100%');
        $destino->addItems($cidades);
        $destino->setMinLength(2);
        $destino->setSize('100%');
        $tipo_veiculo->addItems(CargaDisponivel::getTipoVeiculoItems());

        $this->form->addFields([new TLabel('Origem')], [$origem], [new TLabel('Destino')], [$destino], [new TLabel('Tipo Veiculo')], [$tipo_veiculo]);
        $this->form->addAction('Filtrar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');

        // Restaura filtros
        $filterData = TSession::getValue('PortalMotoristaCargas_filter_data');
        if ($filterData) {
            $this->form->setData($filterData);
        }

        // Container dos cards
        $this->cardsContainer = new TElement('div');
        $this->cardsContainer->id = 'cargas-grid-container';

        // Paginacao
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));

        $contentWrap = new TElement('div');
        $contentWrap->class = 'portal-page-content';
        $contentWrap->add($this->form);
        $contentWrap->add($this->cardsContainer);
        $contentWrap->add($this->pageNavigation);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(PortalMotoristaHelper::buildNav('cargas'));
        $container->add($contentWrap);
        parent::add($container);
    }

    public function onSearch($param = null)
    {
        $data = $this->form->getData();
        TSession::setValue('PortalMotoristaCargas_filter_data', $data);
        TSession::setValue('PortalMotoristaCargas_filter_origem', !empty($data->origem) ? $data->origem : null);
        TSession::setValue('PortalMotoristaCargas_filter_destino', !empty($data->destino) ? $data->destino : null);
        TSession::setValue('PortalMotoristaCargas_filter_tipo_veiculo', !empty($data->tipo_veiculo) ? $data->tipo_veiculo : null);

        $this->form->setData($data);
        $this->onReload();
    }

    public function onClear($param = null)
    {
        TSession::setValue('PortalMotoristaCargas_filter_data', null);
        TSession::setValue('PortalMotoristaCargas_filter_origem', null);
        TSession::setValue('PortalMotoristaCargas_filter_destino', null);
        TSession::setValue('PortalMotoristaCargas_filter_tipo_veiculo', null);
        $this->form->clear(true);
        $this->onReload();
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');
            CargaDisponivel::ensureTables();

            $repository = new TRepository('CargaDisponivel');
            $criteria = new TCriteria;
            $criteria->add(new TFilter('status', '=', CargaDisponivel::STATUS_DISPONIVEL));

            // Filtros
            $origem = TSession::getValue('PortalMotoristaCargas_filter_origem');
            if ($origem) {
                $criteria->add(new TFilter('origem', 'like', "%{$origem}%"));
            }
            $destino = TSession::getValue('PortalMotoristaCargas_filter_destino');
            if ($destino) {
                $criteria->add(new TFilter('destino', 'like', "%{$destino}%"));
            }
            $tipo = TSession::getValue('PortalMotoristaCargas_filter_tipo_veiculo');
            if ($tipo) {
                $criteria->add(new TFilter('tipo_veiculo', '=', $tipo));
            }

            $criteria->setProperty('order', 'id desc');
            $criteria->setProperty('limit', 9);

            $offset = $param['offset'] ?? 0;
            $criteria->setProperty('offset', $offset);

            $objects = $repository->load($criteria, FALSE);
            $count   = $repository->count($criteria);

            // Verifica motorista logado
            $motorista = Motorista::getPortalMotorista();

            // Limpa container
            $this->cardsContainer->clearChildren();

            if ($objects) {
                $grid = new TElement('div');
                $grid->class = 'cargas-grid';

                foreach ($objects as $carga) {
                    $grid->add($this->buildCard($carga, $motorista));
                }

                $this->cardsContainer->add($grid);
            } else {
                $empty = new TElement('div');
                $empty->class = 'cargas-empty';
                $empty->add('<i class="fas fa-truck"></i>');
                $empty->add('Nenhuma carga disponivel no momento.');
                $this->cardsContainer->add($empty);
            }

            // Paginacao
            $this->pageNavigation->setCount($count);
            $this->pageNavigation->setProperties($param);
            $this->pageNavigation->setLimit(9);

            TTransaction::close();
            $this->loaded = true;
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    private function buildCard($carga, $motorista)
    {
        $card = new TElement('div');
        $card->class = 'carga-card';

        // Header
        $header = new TElement('div');
        $header->class = 'carga-card-header';
        $origemEsc  = htmlspecialchars($carga->origem);
        $destinoEsc = htmlspecialchars($carga->destino);
        $urgenteBadge = '';
        if (!empty($carga->data_coleta)) {
            $diffDias = (strtotime($carga->data_coleta) - time()) / 86400;
            if ($diffDias <= 3 && $diffDias >= 0) {
                $urgenteBadge = " <span class='badge' style='background:#dc3545;color:#fff;font-size:.7rem;padding:3px 8px;margin-left:6px'>URGENTE</span>";
            }
        }
        $header->add("<i class='fas fa-truck'></i><span class='rota'>{$origemEsc}<span class='arrow'>&rarr;</span>{$destinoEsc}</span>{$urgenteBadge}");
        $card->add($header);

        // Body
        $body = new TElement('div');
        $body->class = 'carga-card-body';

        $tipoCarga = CargaDisponivel::getTipoCargaItems()[$carga->tipo_carga] ?? ($carga->tipo_carga ?: '-');
        $tipoVeic  = CargaDisponivel::getTipoVeiculoItems()[$carga->tipo_veiculo] ?? ($carga->tipo_veiculo ?: '-');
        $peso      = is_numeric($carga->peso_estimado_kg) ? number_format((float) $carga->peso_estimado_kg, 0, ',', '.') . ' kg' : '-';
        $coleta    = $carga->data_coleta ? date('d/m/Y', strtotime($carga->data_coleta)) : '-';
        $entrega   = $carga->data_entrega_prevista ? date('d/m/Y', strtotime($carga->data_entrega_prevista)) : '-';

        $aduanaOrig = null;
        $aduanaDest = $carga->getAduanaDestinoDisplay();
        $aduanaDest = $aduanaDest !== '' ? htmlspecialchars($aduanaDest) : null;

        $body->add("<div class='info-row'><i class='fas fa-box'></i><span class='label'>Carga:</span> " . htmlspecialchars($tipoCarga) . "</div>");
        $body->add("<div class='info-row'><i class='fas fa-truck-moving'></i><span class='label'>Veiculo:</span> " . htmlspecialchars($tipoVeic) . "</div>");
        if ($aduanaOrig) {
            $body->add("<div class='info-row'><i class='fas fa-flag'></i><span class='label'>Cruze:</span> {$aduanaOrig}</div>");
        }
        if ($aduanaDest) {
            $body->add("<div class='info-row'><i class='fas fa-map-marker-alt'></i><span class='label'>Aduana Destino:</span> {$aduanaDest}</div>");
        }
        $body->add("<div class='info-row'><i class='fas fa-weight-hanging'></i><span class='label'>Peso:</span> {$peso}</div>");
        $body->add("<div class='info-row'><i class='fas fa-calendar-alt'></i><span class='label'>Coleta:</span> {$coleta}</div>");
        $body->add("<div class='info-row'><i class='fas fa-calendar-check'></i><span class='label'>Entrega:</span> {$entrega}</div>");

        if (!empty($carga->descricao)) {
            $desc = htmlspecialchars(mb_substr($carga->descricao, 0, 80));
            $body->add("<div class='info-row'><i class='fas fa-warehouse'></i><span class='label'>Descarga:</span> {$desc}</div>");
        }

        if (!empty($carga->localizacao_maps)) {
            $mapsUrl = htmlspecialchars($carga->localizacao_maps);
            $body->add("<div class='info-row'><a href='{$mapsUrl}' target='_blank' style='color:#4285F4;text-decoration:none;font-weight:bold'><i class='fas fa-map-marked-alt'></i> Ver no Google Maps</a></div>");
        }

        $card->add($body);

        // Footer
        $footer = new TElement('div');
        $footer->class = 'carga-card-footer';

        $valorFrete = is_numeric($carga->valor_frete) ? 'R$ ' . number_format((float) $carga->valor_frete, 2, ',', '.') : 'A combinar';
        $footer->add("<span class='valor-frete'>{$valorFrete}</span>");

        // Botao solicitar
        if ($motorista) {
            $action = new TAction([$this, 'onSolicitarForm'], ['carga_id' => $carga->id]);
            $url = $action->serialize();
            $footer->add("<a class='btn-solicitar' generator='adianti' href='{$url}'>Solicitar</a>");
        } else {
            $footer->add("<span class='btn-solicitar disabled' title='Vincule seu usuario a um motorista'>Solicitar</span>");
        }

        $card->add($footer);

        return $card;
    }

    /**
     * Abre janela para solicitar a carga
     */
    public function onSolicitarForm($param)
    {
        try {
            TTransaction::open('sample');
            $carga = new CargaDisponivel($param['carga_id']);
            $motorista = Motorista::getPortalMotorista();

            if (!$motorista) {
                TTransaction::close();
                new TMessage('error', 'Seu usuario nao esta vinculado a nenhum motorista. Contacte o administrador.');
                return;
            }

            // Verifica se ja solicitou
            $repo = new TRepository('SolicitacaoCarga');
            $criteria = new TCriteria;
            $criteria->add(new TFilter('carga_disponivel_id', '=', $carga->id));
            $criteria->add(new TFilter('motorista_id', '=', $motorista->id));
            $criteria->add(new TFilter('status', 'IN', ['pendente', 'em_analise']));
            $existe = $repo->count($criteria);

            if ($existe > 0) {
                TTransaction::close();
                new TMessage('info', 'Voce ja possui uma solicitacao pendente para esta carga.');
                return;
            }

            TTransaction::close();

            $form = new BootstrapFormBuilder('form_solicitar_carga');

            $info = new TLabel("<b>Carga:</b> {$carga->origem} &rarr; {$carga->destino}");

            $mensagem = new TText('mensagem');
            $mensagem->setSize('100%', 80);
            $mensagem->placeholder = 'Descreva sua experiencia com este tipo de carga, disponibilidade, etc.';

            $veiculo_id = new TDBCombo('veiculo_id', 'sample', 'Veiculo', 'id', 'placa_trator', 'placa_trator');

            $data_disp = new TDate('data_disponibilidade');
            $data_disp->setMask('dd/mm/yyyy');
            $data_disp->setDatabaseMask('yyyy-mm-dd');

            $carga_id = new THidden('carga_id');
            $carga_id->setValue($carga->id);

            $form->addFields([$info]);
            $form->addFields([$carga_id]);
            $form->addFields([new TLabel('Veiculo')], [$veiculo_id]);
            $form->addFields([new TLabel('Disponivel a partir de')], [$data_disp]);
            $form->addFields([new TLabel('Mensagem')], [$mensagem]);

            $form->addAction('Enviar Solicitacao', new TAction([$this, 'onSolicitarSave']), 'fa:paper-plane green');

            $window = TWindow::create('Solicitar Carga', 520, 380);
            $window->add($form);
            $window->show();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Salva a solicitacao do motorista
     */
    public function onSolicitarSave($param)
    {
        try {
            TTransaction::open('sample');
            SolicitacaoCarga::ensureTables();

            $motorista = Motorista::getPortalMotorista();
            if (!$motorista) {
                throw new Exception('Motorista nao encontrado para o usuario logado.');
            }

            $solicitacao = new SolicitacaoCarga;
            $solicitacao->carga_disponivel_id = $param['carga_id'];
            $solicitacao->motorista_id        = $motorista->id;
            $solicitacao->veiculo_id          = !empty($param['veiculo_id']) ? $param['veiculo_id'] : null;
            $solicitacao->mensagem            = $param['mensagem'] ?? '';
            $solicitacao->data_disponibilidade = !empty($param['data_disponibilidade']) ? $param['data_disponibilidade'] : null;
            $solicitacao->store();

            TTransaction::close();
            new TMessage('info', 'Solicitacao enviada com sucesso! Acompanhe em "Minhas Solicitacoes".');
            $this->onReload($param);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload();
        }
        parent::show();
    }
}
