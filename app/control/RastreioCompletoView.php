<?php

class RastreioCompletoView extends TPage
{
    private $map;
    private $timeline;
    private $kpiContainer;
    private $headerCard;

    public function __construct($param = null)
    {
        parent::__construct();

        $container = new TVBox;
        $container->style = 'width: 100%; padding: 15px;';

        $container->add(new TXMLBreadCrumb('menu.xml', 'ConhecimentoList'));

        $this->headerCard = new TElement('div');
        $this->headerCard->class = 'card shadow-sm mb-4';

        $this->kpiContainer = new TElement('div');
        $this->kpiContainer->class = 'row mb-4';

        $this->timeline = new TTimeline;

        $this->map = new THtmlRenderer('app/resources/leaflet_map.html');

        $contentRow = new THBox;
        $contentRow->style = 'display: flex; gap: 20px; flex-wrap: wrap;';

        $mapPanel = new TPanelGroup('Localizacao Geografica');
        $mapPanel->style = 'flex: 2; min-width: 380px;';
        $mapPanel->add($this->map);

        $timelinePanel = new TPanelGroup('Historico de Eventos');
        $timelinePanel->style = 'flex: 1; min-width: 320px;';
        $timelinePanel->add($this->timeline);

        $contentRow->add($mapPanel);
        $contentRow->add($timelinePanel);

        $supportPanel = new TPanelGroup('Acoes de Suporte');
        $supportPanel->style = 'margin-top: 15px;';

        $btnAlan = new TActionLink('Falar com Alan (Brasil)', new TAction([$this, 'onCallAlan']), 'fab:whatsapp white', 14, 'white', 'btn btn-success');
        $btnJose = new TActionLink('Falar com Jose (Argentina)', new TAction([$this, 'onCallJose']), 'fab:whatsapp white', 14, 'white', 'btn btn-info');

        $buttons = new THBox;
        $buttons->style = 'display: flex; gap: 10px; flex-wrap: wrap;';
        $buttons->add($btnAlan);
        $buttons->add($btnJose);
        $supportPanel->add($buttons);

        $container->add($this->headerCard);
        $container->add($this->kpiContainer);
        $container->add($contentRow);
        $container->add($supportPanel);

        parent::add($container);

        $this->loadData($param ?? []);
    }

    public function onShow($param = null)
    {
        $this->loadData($param ?? []);
    }

    private function loadData(array $param): void
    {
        $data = $this->defaultData();

        try {
            $id = $param['key'] ?? $param['id'] ?? null;
            $numero = trim((string) ($param['numero'] ?? ''));

            if (!empty($id) || $numero !== '') {
                TTransaction::open('sample');
                $crt = $this->findConhecimento((string) $id, $numero);

                if ($crt) {
                    $statusNome = 'Sem status';
                    if (!empty($crt->status_crt_id)) {
                        try {
                            $statusNome = (new StatusCrt($crt->status_crt_id))->nome ?: $statusNome;
                        } catch (Exception $e) {
                            error_log('RastreioCompletoView status load error: ' . $e->getMessage());
                        }
                    }

                    $data['carga_id'] = (string) ($crt->id ?: $data['carga_id']);
                    $data['rota'] = trim((string) ($crt->local_responsabilidade ?: 'Uruguaiana (BR) > Santiago (CL)'));
                    $data['motorista'] = trim((string) ($crt->nome_transportador ?: 'Nao informado'));
                    $data['previsao'] = FollowUpService::formatDate((string) ($crt->data_entrega ?: '')) ?: $data['previsao'];
                    $data['status'] = $statusNome;
                    $data['placa'] = trim((string) ($crt->placa ?? 'Nao informada'));
                    $data['lat'] = (float) ($param['lat'] ?? $data['lat']);
                    $data['lon'] = (float) ($param['lon'] ?? $data['lon']);
                    $data['map_info'] = 'Ultimo checkpoint: ' . trim((string) ($crt->local_entrega ?: $data['map_info']));

                    $eventos = FollowUpService::getEventosTimeline($crt, $statusNome);
                    if (!empty($eventos)) {
                        $data['timeline'] = [];
                        foreach ($eventos as $idx => $evento) {
                            $data['timeline'][] = [
                                'id' => $idx + 1,
                                'title' => (string) ($evento['title'] ?? 'Movimento'),
                                'sub' => (string) ($evento['sub'] ?? ''),
                                'data' => (string) ($evento['data'] ?? ''),
                                'icon' => $this->mapIcon((string) ($evento['icon'] ?? '')),
                                'side' => ($idx % 2 === 0) ? 'left' : 'right',
                            ];
                        }
                    }
                }

                TTransaction::close();
            }
        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            new TMessage('error', $e->getMessage());
        }

        $this->buildHeaderCard($data);
        $this->buildKpis($data);
        $this->buildTimeline($data['timeline']);
        $this->map->enableSection('main', [
            'map_id' => 'map_track_' . uniqid(),
            'lat' => number_format((float) $data['lat'], 6, '.', ''),
            'lon' => number_format((float) $data['lon'], 6, '.', ''),
            'info' => htmlspecialchars((string) $data['map_info']),
        ]);
    }

    private function findConhecimento(string $id, string $numero)
    {
        if ($id !== '') {
            $obj = new Conhecimento($id);
            return !empty($obj->id) ? $obj : null;
        }

        if ($numero === '') {
            return null;
        }

        $repo = new TRepository('Conhecimento');
        $criteria = new TCriteria;
        $criteria->add(new TFilter('numero', '=', $numero));
        $criteria->setProperty('limit', 1);
        $rows = $repo->load($criteria, false);

        return !empty($rows) ? $rows[0] : null;
    }

    private function buildHeaderCard(array $data): void
    {
        $this->headerCard->clearChildren();

        $header = new TElement('div');
        $header->class = 'card-header bg-primary text-white';
        $header->add('<b>Carga ID: #' . htmlspecialchars($data['carga_id']) . ' - Rota: ' . htmlspecialchars($data['rota']) . '</b>');

        $body = new TElement('div');
        $body->class = 'card-body';
        $body->add('<b>Motorista:</b> ' . htmlspecialchars($data['motorista']) . '<br>');
        $body->add('<b>Previsao de Entrega:</b> ' . htmlspecialchars($data['previsao']) . '<br>');
        $body->add('<b>Status Atual:</b> <span class="badge bg-warning text-dark">' . htmlspecialchars(strtoupper($data['status'])) . '</span>');

        $this->headerCard->add($header);
        $this->headerCard->add($body);
    }

    private function buildKpis(array $data): void
    {
        $this->kpiContainer->clearChildren();

        $html = '
        <div class="col-md-3 mb-3">
            <div class="card shadow h-100 py-2 border-start border-4 border-primary">
                <div class="card-body">
                    <div class="text-uppercase text-primary small fw-bold">Status Atual</div>
                    <div class="h6 mb-0 fw-bold">' . htmlspecialchars(strtoupper($data['status'])) . '</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow h-100 py-2 border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-uppercase text-success small fw-bold">Previsao</div>
                    <div class="h6 mb-0 fw-bold">' . htmlspecialchars($data['previsao']) . '</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card shadow h-100 py-2">
                <div class="card-body">
                    <b>Motorista:</b> ' . htmlspecialchars($data['motorista']) . ' | <b>Placa:</b> ' . htmlspecialchars($data['placa']) . '
                </div>
            </div>
        </div>';

        $this->kpiContainer->add($html);
    }

    private function buildTimeline(array $events): void
    {
        $this->timeline->clearChildren();

        foreach ($events as $event) {
            $this->timeline->addItem(
                $event['id'],
                $event['title'],
                $event['sub'],
                $event['data'],
                $event['icon'],
                $event['side']
            );
        }
    }

    private function mapIcon(string $source): string
    {
        $s = strtolower($source);

        if (strpos($s, 'check') !== false || strpos($s, 'ok') !== false) {
            return 'fa:check bg-green';
        }
        if (strpos($s, 'flag') !== false || strpos($s, 'aduana') !== false) {
            return 'fa:flag bg-orange';
        }
        if (strpos($s, 'truck') !== false || strpos($s, 'transito') !== false) {
            return 'fa:truck bg-blue';
        }
        return 'fa:circle bg-gray';
    }

    private function defaultData(): array
    {
        return [
            'carga_id' => '88293',
            'rota' => 'Uruguaiana (BR) > Santiago (CL)',
            'motorista' => 'Marcio Diaz',
            'previsao' => '15/03/2026',
            'status' => 'Em Transito',
            'placa' => 'ABC-1234 (BR)',
            'lat' => -29.759997,
            'lon' => -57.085609,
            'map_info' => 'Aduana Brasil/Argentina',
            'timeline' => [
                [
                    'id' => 1,
                    'title' => 'Coletado',
                    'sub' => 'Origem: Porto Alegre',
                    'data' => '10/03/2026 08:00',
                    'icon' => 'fa:box-open bg-blue',
                    'side' => 'left',
                ],
                [
                    'id' => 2,
                    'title' => 'Em Transito',
                    'sub' => 'Passagem por Uruguaiana',
                    'data' => '11/03/2026 14:00',
                    'icon' => 'fa:truck bg-orange',
                    'side' => 'right',
                ],
                [
                    'id' => 3,
                    'title' => 'Aduana',
                    'sub' => 'Liberacao de documentos',
                    'data' => '11/03/2026 16:30',
                    'icon' => 'fa:file-invoice bg-red',
                    'side' => 'left',
                ],
            ],
        ];
    }

    public static function onCallAlan($param)
    {
        TScript::create("window.open('https://wa.me/5555984672434')");
    }

    public static function onCallJose($param)
    {
        TScript::create("window.open('https://wa.me/5491134299732')");
    }
}
