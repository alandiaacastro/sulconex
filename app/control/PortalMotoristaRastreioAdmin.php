<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapFormBuilder;

class PortalMotoristaRastreioAdmin extends TPage
{
    private $form;
    private $summary;
    private $map;
    private $list;
    private $loaded;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_portal_motorista_rastreio_admin');
        $this->form->setFormTitle('Rastreio dos Motoristas');

        $contratoId = new TEntry('contrato_id');
        $contratoId->setSize('100%');
        $contratoId->setNumericMask(0, '', '', true);

        $busca = new TEntry('busca');
        $busca->setSize('100%');
        $busca->placeholder = 'Motorista, placa, CRT, origem ou destino';

        $statusAtualizacao = new TCombo('status_atualizacao');
        $statusAtualizacao->addItems([
            'all' => 'Todos',
            'recent_24h' => 'Atualizados 24h',
            'stale_24h' => 'Sem atualizacao 24h',
        ]);
        $statusAtualizacao->setValue('all');
        $statusAtualizacao->setSize('100%');

        $this->form->addFields([new TLabel('Contrato')], [$contratoId], [new TLabel('Busca geral')], [$busca], [new TLabel('Status')], [$statusAtualizacao]);
        $this->form->addAction('Filtrar', new TAction([$this, 'onReload']), 'fa:search blue');
        $this->form->addActionLink('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');

        $this->summary = new TElement('div');
        $this->map = new THtmlRenderer('app/resources/portal_motorista_rastreio_map.html');
        $this->list = new TElement('div');

        $summaryPanel = new TPanelGroup('Visao Geral');
        $summaryPanel->add($this->summary);

        $mapPanel = new TPanelGroup('Mapa das Ultimas Posicoes');
        $mapPanel->add($this->map);

        $listPanel = new TPanelGroup('Ultimas Posicoes Recebidas');
        $listPanel->add($this->list);

        $box = new TVBox;
        $box->style = 'width: 100%';
        $box->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $box->add($this->buildStyles());
        $box->add($this->form);
        $box->add($summaryPanel);
        $box->add($mapPanel);
        $box->add($listPanel);

        parent::add($box);
    }

    public function onReload($param = null)
    {
        $param = is_array($param) ? $param : [];

        try {
            TTransaction::open('sample');
            $snapshot = PortalMotoristaRastreioAdminService::fetchSnapshot($param);
            TTransaction::close();

            $filters = (object) $snapshot['filters'];
            $this->form->setData($filters);
            $this->summary->clearChildren();
            $this->summary->add($this->buildSummaryHtml($snapshot['summary']));

            $markersJson = json_encode($snapshot['markers'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->map->enableSection('main', [
                'map_id' => 'portal_motorista_rastreio_map_' . uniqid(),
                'markers_b64' => base64_encode($markersJson ?: '[]'),
                'summary_text' => htmlspecialchars((string) $snapshot['summary']['summary_text'], ENT_QUOTES, 'UTF-8'),
            ]);

            $this->list->clearChildren();
            $this->list->add($this->buildListHtml($snapshot['items']));
            $this->loaded = true;
        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            new TMessage('error', $e->getMessage());
        }
    }

    public function onClear($param = null)
    {
        $data = new stdClass;
        $data->contrato_id = '';
        $data->busca = '';
        $data->status_atualizacao = 'all';
        $this->form->setData($data);
        $this->onReload([]);
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload($_GET);
        }
        parent::show();
    }

    private function buildStyles(): TElement
    {
        $style = new TElement('style');
        $style->add('
            .pmra-kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; margin-bottom:4px; }
            .pmra-kpi-card { background:linear-gradient(135deg,#f8fafc 0%,#eef2ff 100%); border:1px solid #dbe4f0; border-radius:14px; padding:16px 18px; }
            .pmra-kpi-card span { display:block; font-size:12px; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:700; margin-bottom:8px; }
            .pmra-kpi-card strong { display:block; font-size:26px; color:#0f172a; line-height:1.1; }
            .pmra-kpi-card small { display:block; color:#475569; margin-top:6px; font-size:12px; }
            .pmra-list { display:grid; gap:14px; }
            .pmra-card { border:1px solid #dbe4f0; border-radius:16px; background:#fff; padding:16px 18px; box-shadow:0 10px 30px rgba(15,23,42,.04); }
            .pmra-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
            .pmra-card-head h4 { margin:0; font-size:18px; color:#0f172a; }
            .pmra-route { color:#334155; font-size:14px; margin-top:4px; }
            .pmra-badge { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; }
            .pmra-badge.is-fresh { background:#dcfce7; color:#166534; }
            .pmra-badge.is-stale { background:#ffedd5; color:#9a3412; }
            .pmra-info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:10px; margin-top:14px; }
            .pmra-info-box { background:#f8fafc; border-radius:12px; padding:10px 12px; }
            .pmra-info-box span { display:block; font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:700; margin-bottom:4px; }
            .pmra-info-box strong { color:#0f172a; font-size:14px; }
            .pmra-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:16px; }
            .pmra-actions a { display:inline-flex; align-items:center; gap:6px; border-radius:10px; padding:8px 12px; font-size:13px; font-weight:700; text-decoration:none; }
            .pmra-actions .is-primary { background:#dbeafe; color:#1d4ed8; }
            .pmra-actions .is-neutral { background:#e2e8f0; color:#334155; }
            .pmra-actions .is-success { background:#dcfce7; color:#166534; }
            .pmra-empty { border:1px dashed #cbd5e1; border-radius:16px; padding:24px; text-align:center; color:#64748b; background:#f8fafc; }
        ');
        return $style;
    }

    private function buildSummaryHtml(array $summary): string
    {
        $cards = [
            ['label' => 'Contratos no mapa', 'value' => (int) ($summary['total_contratos'] ?? 0), 'hint' => 'Ultima atualizacao por contrato'],
            ['label' => 'Motoristas monitorados', 'value' => (int) ($summary['motoristas_monitorados'] ?? 0), 'hint' => 'Motoristas com posicao registrada'],
            ['label' => 'Atualizados em 24h', 'value' => (int) ($summary['atualizados_24h'] ?? 0), 'hint' => 'Posicoes consideradas recentes'],
            ['label' => 'Ultima chegada', 'value' => htmlspecialchars((string) ($summary['latest_update_label'] ?? '-'), ENT_QUOTES, 'UTF-8'), 'hint' => 'Mais recente evento de GPS recebido'],
        ];

        $html = '<div class="pmra-kpi-grid">';
        foreach ($cards as $card) {
            $html .= '<div class="pmra-kpi-card">'
                . '<span>' . htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') . '</span>'
                . '<strong>' . $card['value'] . '</strong>'
                . '<small>' . htmlspecialchars($card['hint'], ENT_QUOTES, 'UTF-8') . '</small>'
                . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    private function buildListHtml(array $items): string
    {
        if (!$items) {
            return '<div class="pmra-empty">Nenhuma posicao encontrada para os filtros atuais.</div>';
        }

        $html = '<div class="pmra-list">';
        foreach ($items as $item) {
            $rastreioButton = '';
            if (!empty($item['tracking_url'])) {
                $rastreioButton = '<a generator="adianti" href="' . htmlspecialchars((string) $item['tracking_url'], ENT_QUOTES, 'UTF-8') . '" class="is-primary"><i class="fas fa-route"></i> Tracking</a>';
            }

            $html .= '<article class="pmra-card">'
                . '<div class="pmra-card-head">'
                . '<div>'
                . '<h4>Contrato #' . (int) $item['contrato_id'] . ' - CRT ' . htmlspecialchars((string) $item['crt'], ENT_QUOTES, 'UTF-8') . '</h4>'
                . '<div class="pmra-route">' . htmlspecialchars((string) $item['rota'], ENT_QUOTES, 'UTF-8') . '</div>'
                . '</div>'
                . '<span class="pmra-badge ' . htmlspecialchars((string) $item['status_class'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $item['update_label'], ENT_QUOTES, 'UTF-8') . '</span>'
                . '</div>'
                . '<div class="pmra-info-grid">'
                . '<div class="pmra-info-box"><span>Motorista</span><strong>' . htmlspecialchars((string) $item['motorista_nome'], ENT_QUOTES, 'UTF-8') . '</strong></div>'
                . '<div class="pmra-info-box"><span>Telefone</span><strong>' . htmlspecialchars((string) $item['motorista_telefone'] ?: '-', ENT_QUOTES, 'UTF-8') . '</strong></div>'
                . '<div class="pmra-info-box"><span>Veiculo</span><strong>' . htmlspecialchars((string) $item['placa_trator'], ENT_QUOTES, 'UTF-8') . '</strong></div>'
                . '<div class="pmra-info-box"><span>Ultima posicao</span><strong>' . htmlspecialchars((string) $item['created_at_label'], ENT_QUOTES, 'UTF-8') . '</strong></div>'
                . '<div class="pmra-info-box"><span>Precisao</span><strong>' . htmlspecialchars((string) $item['precisao_label'], ENT_QUOTES, 'UTF-8') . '</strong></div>'
                . '<div class="pmra-info-box"><span>Saldo previsto</span><strong>' . htmlspecialchars((string) $item['saldo_label'], ENT_QUOTES, 'UTF-8') . '</strong></div>'
                . '</div>'
                . '<div class="pmra-actions">'
                . '<a href="' . htmlspecialchars((string) $item['maps_url'], ENT_QUOTES, 'UTF-8') . '" class="is-success" target="_blank" rel="noreferrer"><i class="fas fa-map-marked-alt"></i> Ver no mapa</a>'
                . '<a generator="adianti" href="' . htmlspecialchars((string) $item['contrato_edit_url'], ENT_QUOTES, 'UTF-8') . '" class="is-neutral"><i class="fas fa-file-alt"></i> Abrir contrato</a>'
                . '<a generator="adianti" href="' . htmlspecialchars((string) $item['contrato_pdf_url'], ENT_QUOTES, 'UTF-8') . '" class="is-neutral"><i class="fas fa-print"></i> Imprimir</a>'
                . $rastreioButton
                . '</div>'
                . '</article>';
        }
        $html .= '</div>';

        return $html;
    }
}