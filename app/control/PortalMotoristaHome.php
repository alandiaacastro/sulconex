<?php

use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;

class PortalMotoristaHome extends TPage
{
    private $loaded;
    private $content;

    public function __construct()
    {
        parent::__construct();

        if (!TSession::getValue('portal_motorista_logged') && !TSession::getValue('logged')) {
            AdiantiCoreApplication::gotoPage('PortalMotoristaLogin');
            return;
        }

        $container = new TVBox;
        $container->style = 'width:100%';
        $container->add(PortalMotoristaHelper::buildNav('inicio'));

        $this->content = new TElement('div');
        $this->content->class = 'portal-page-content';
        $container->add($this->content);
        parent::add($container);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');
            CargaDisponivel::ensureTables();
            SolicitacaoCarga::ensureTables();
            Motorista::ensureTables();
            PortalMotoristaDocumento::ensureTables();
            Contrato::addColumnsIfNotExists(TTransaction::get());

            $motorista = Motorista::getPortalMotorista();
            if (!$motorista) {
                TTransaction::close();
                new TMessage('error', 'Motorista não encontrado para a sessão atual.');
                return;
            }

            $conn = TTransaction::get();

            $cargasDisponiveis = (int) $conn->query(
                "SELECT COUNT(*) FROM carga_disponivel WHERE status = 'disponivel'"
            )->fetchColumn();

            $stmt = $conn->prepare("SELECT COUNT(*) FROM solicitacao_carga WHERE motorista_id = ?");
            $stmt->execute([$motorista->id]);
            $minhasSolicitacoes = (int) $stmt->fetchColumn();

            $stmt2 = $conn->prepare("
                SELECT COUNT(*) FROM contrato c
                INNER JOIN veiculo v ON v.id = c.veiculo_id
                WHERE v.motorista_id = ?
                  AND (COALESCE(c.pago,'') <> 'S' OR c.dta_efet_pg IS NULL OR c.dta_efet_pg = '')
            ");
            $stmt2->execute([$motorista->id]);
            $emAndamento = (int) $stmt2->fetchColumn();

            $stmt3 = $conn->prepare("SELECT COUNT(*) FROM portal_motorista_documento WHERE motorista_id = ?");
            $stmt3->execute([$motorista->id]);
            $documentos = (int) $stmt3->fetchColumn();

            $documentAlerts = $this->buildDocumentAlerts((int) $motorista->id);

            TTransaction::close();

            $firstName = explode(' ', htmlspecialchars((string) ($motorista->nome ?? 'Motorista')))[0];

            $this->content->clearChildren();
            $this->content->add($this->buildGreeting($firstName, !empty($documentAlerts)));
            if (!empty($documentAlerts)) {
                $this->content->add($this->buildAlertCard($documentAlerts));
            }
            $this->content->add($this->buildKpiGrid($cargasDisponiveis, $emAndamento, $minhasSolicitacoes, $documentos));
            $this->content->add($this->buildFeatureList());

            $this->loaded = true;
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload();
        }
        parent::show();
    }

    // ── Saudação ──────────────────────────────────────────────────────────
    private function buildGreeting(string $firstName, bool $hasPending): string
    {
        $h     = (int) date('H');
        $greet = $h < 12 ? 'Bom dia' : ($h < 18 ? 'Boa tarde' : 'Boa noite');

        $days   = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
        $months = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
        $date   = $days[date('w')] . ', ' . date('d') . ' de ' . $months[(int) date('n') - 1] . ' de ' . date('Y');

        $bellDot = $hasPending
            ? '<span class="position-absolute top-0 end-0 translate-middle p-1 bg-danger border border-2 border-white rounded-circle" style="width:10px;height:10px"></span>'
            : '';

        return "
        <div class='mb-4'>
            <div class='d-flex align-items-start justify-content-between'>
                <div>
                    <h2 class='fw-bold mb-1' style='font-size:1.75rem;color:#0F172A;letter-spacing:-.02em;line-height:1.2'>
                        {$greet}, {$firstName}!
                    </h2>
                    <div class='text-muted d-flex align-items-center gap-1' style='font-size:.85rem'>
                        <i class='far fa-calendar-alt'></i> {$date}
                    </div>
                </div>
                <div class='position-relative mt-1' style='cursor:pointer'>
                    <i class='far fa-bell' style='font-size:1.5rem;color:#94A3B8'></i>
                    {$bellDot}
                </div>
            </div>
        </div>";
    }

    // ── Card de alertas de documentos ─────────────────────────────────────
    private function buildAlertCard(array $alerts): string
    {
        $url   = PortalMotoristaHelper::openPortalAction('PortalMotoristaDocumentos');
        $items = '';
        foreach ($alerts as $alert) {
            $items .= '<li class="mb-1">' . htmlspecialchars($alert) . '</li>';
        }

        return "
        <div class='mb-4 p-3 rounded-3 border-start border-4 border-warning'
             style='background:#FFFBEB;box-shadow:0 1px 8px rgba(0,0,0,.07)'>
            <div class='d-flex align-items-center gap-2 mb-2'>
                <i class='fas fa-exclamation-triangle text-warning'></i>
                <strong style='font-size:.9rem;color:#92400E'>Pendências de Documentos</strong>
            </div>
            <ul class='mb-3 ps-3' style='font-size:.83rem;color:#B45309'>
                {$items}
            </ul>
            <a generator='adianti' href='{$url}'
               class='btn fw-bold w-100 rounded-3 text-white'
               style='background:#F59E0B;border:none;font-size:.9rem;padding:11px'>
                Regularizar Agora
            </a>
        </div>";
    }

    // ── Grid de KPIs ──────────────────────────────────────────────────────
    private function buildKpiGrid(int $cargas, int $andamento, int $solicitacoes, int $documentos): string
    {
        $kpis = [
            [$cargas,       'Cargas Disponíveis', 'fa-truck',          'PortalMotoristaCargas',       '#4F46E5', '#EEF2FF'],
            [$andamento,    'Em Andamento',        'fa-route',          'PortalMotoristaAndamento',    '#059669', '#ECFDF5'],
            [$solicitacoes, 'Solicitações',        'fa-clipboard-list', 'PortalMotoristaSolicitacoes', '#D97706', '#FFFBEB'],
            [$documentos,   'Documentos',          'fa-folder-open',    'PortalMotoristaDocumentos',   '#7C3AED', '#F5F3FF'],
        ];

        $html = "<div class='row g-3 mb-4'>";
        foreach ($kpis as [$val, $lbl, $icon, $class, $color, $bg]) {
            $url = PortalMotoristaHelper::openPortalAction($class);
            $html .= "
            <div class='col-6'>
                <a generator='adianti' href='{$url}'
                   class='card border-0 rounded-3 h-100 text-decoration-none'
                   style='box-shadow:0 1px 6px rgba(0,0,0,.07);transition:.2s'
                   onmouseover=\"this.style.transform='translateY(-2px)'\"
                   onmouseout=\"this.style.transform=''\">
                    <div class='card-body p-3 d-flex flex-column gap-2'>
                        <div class='d-flex align-items-center justify-content-center rounded-3 flex-shrink-0'
                             style='width:44px;height:44px;background:{$bg}'>
                            <i class='fas {$icon}' style='font-size:1.1rem;color:{$color}'></i>
                        </div>
                        <div class='fw-bold' style='font-size:1.75rem;color:{$color};letter-spacing:-.04em;line-height:1'>
                            {$val}
                        </div>
                        <div class='text-uppercase fw-bold text-muted' style='font-size:.68rem;letter-spacing:.04em;line-height:1.3'>
                            {$lbl}
                        </div>
                    </div>
                </a>
            </div>";
        }
        $html .= '</div>';
        return $html;
    }

    // ── Lista de acesso rápido ─────────────────────────────────────────────
    private function buildFeatureList(): string
    {
        $features = [
            ['PortalMotoristaCargas',        'fa-search',        'Buscar Cargas',        'Encontre fretes disponíveis para você',          '#4F46E5', '#EEF2FF'],
            ['PortalMotoristaAndamento',     'fa-route',         'Em Andamento',         'Acompanhe suas viagens em curso',                '#059669', '#ECFDF5'],
            ['PortalMotoristaDocumentos',    'fa-id-card',       'Meus Documentos',      'CNH e documentos do veículo',                    '#D97706', '#FFFBEB'],
            ['PortalMotoristaSolicitacoes',  'fa-clipboard-list','Minhas Solicitações',  'Veja o status de todas as suas solicitações',    '#7C3AED', '#F5F3FF'],
        ];

        $html  = "<div class='fw-bold text-uppercase text-muted mb-2' style='font-size:.72rem;letter-spacing:.09em'>Acesso Rápido</div>";
        $html .= "<div class='d-flex flex-column gap-2 mb-3'>";

        foreach ($features as [$class, $icon, $title, $desc, $color, $bg]) {
            $url = PortalMotoristaHelper::openPortalAction($class);
            $html .= "
            <a generator='adianti' href='{$url}'
               class='bg-white rounded-3 d-flex align-items-center gap-3 text-decoration-none p-3'
               style='box-shadow:0 1px 6px rgba(0,0,0,.07);border:1px solid rgba(0,0,0,.04);transition:.2s;color:#0F172A'
               onmouseover=\"this.style.transform='translateX(4px)'\"
               onmouseout=\"this.style.transform=''\">
                <div class='d-flex align-items-center justify-content-center rounded-3 flex-shrink-0'
                     style='width:46px;height:46px;background:{$bg}'>
                    <i class='fas {$icon}' style='font-size:1.1rem;color:{$color}'></i>
                </div>
                <div class='flex-grow-1'>
                    <div class='fw-bold' style='font-size:.92rem'>{$title}</div>
                    <div class='text-muted' style='font-size:.78rem;margin-top:2px'>{$desc}</div>
                </div>
                <i class='fas fa-chevron-right text-muted' style='font-size:.8rem'></i>
            </a>";
        }

        $html .= '</div>';
        return $html;
    }

    // ── buildDocumentAlerts ───────────────────────────────────────────────
    private function buildDocumentAlerts(int $motoristaId): array
    {
        $alerts = [];
        if (!PortalMotoristaDocumento::findByContext($motoristaId, PortalMotoristaDocumento::TIPO_CNH)) {
            $alerts[] = 'CNH ainda não enviada.';
        }
        $repo     = new TRepository('Veiculo');
        $criteria = new TCriteria;
        $criteria->add(new TFilter('motorista_id', '=', $motoristaId));
        $criteria->setProperty('order', 'id desc');
        $veiculos = $repo->load($criteria, false) ?: [];

        foreach ($veiculos as $veiculo) {
            $placa = (string) ($veiculo->placa_trator ?: ('#' . $veiculo->id));
            if (!PortalMotoristaDocumento::findByContext($motoristaId, PortalMotoristaDocumento::TIPO_CAVALO, (int) $veiculo->id)) {
                $alerts[] = "Documento do cavalo faltando para o veículo {$placa}.";
            }
            $semi = (string) ($veiculo->antt_consulta_semi_reboque->placa ?? '');
            if ($semi !== '' && !PortalMotoristaDocumento::findByContext($motoristaId, PortalMotoristaDocumento::TIPO_SEMI_REBOQUE, (int) $veiculo->id)) {
                $alerts[] = "Documento do semi-reboque faltando para o veículo {$placa}.";
            }
        }
        return $alerts;
    }
}
