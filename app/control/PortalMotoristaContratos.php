<?php

use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;

class PortalMotoristaContratos extends TPage
{
    private $loaded;
    private $content;

    public function __construct()
    {
        parent::__construct();
        TPage::include_css('app/resources/css/portal_motorista.css');

        if (!TSession::getValue('portal_motorista_logged') && !TSession::getValue('logged')) {
            AdiantiCoreApplication::gotoPage('PortalMotoristaLogin');
            return;
        }

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(PortalMotoristaHelper::buildNav('contratos'));

        $this->content = new TElement('div');
        $this->content->class = 'portal-page-content';
        $container->add($this->content);
        parent::add($container);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');
            Contrato::addColumnsIfNotExists(TTransaction::get());

            $motorista = Motorista::getPortalMotorista();
            if (!$motorista) {
                TTransaction::close();
                new TMessage('error', 'Motorista nao encontrado para a sessao atual.');
                return;
            }

            $stmt = TTransaction::get()->prepare("
                SELECT c.id, c.conhecimento_numero, c.origem1, c.destino1, c.emissao, c.vencimento, c.frete1, c.saldo1, c.pago, c.dta_efet_pg,
                       v.placa_trator
                FROM contrato c
                INNER JOIN veiculo v ON v.id = c.veiculo_id
                WHERE v.motorista_id = ?
                ORDER BY c.id DESC
            ");
            $stmt->execute([$motorista->id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            TTransaction::close();

            $this->content->clearChildren();
            $this->content->add($this->buildTable($rows));
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

    public static function onPrint($param)
    {
        try {
            TTransaction::open('sample');
            $motorista = Motorista::getPortalMotorista();
            if (!$motorista) {
                throw new Exception('Motorista nao encontrado para a sessao atual.');
            }

            $stmt = TTransaction::get()->prepare("
                SELECT c.id
                FROM contrato c
                INNER JOIN veiculo v ON v.id = c.veiculo_id
                WHERE c.id = ? AND v.motorista_id = ?
                LIMIT 1
            ");
            $stmt->execute([(int) ($param['key'] ?? 0), (int) $motorista->id]);
            $allowed = (bool) $stmt->fetchColumn();

            if (!$allowed) {
                throw new Exception('Contrato nao localizado para este motorista.');
            }

            TTransaction::close();
            ContratoRelatorio::onGenerate($param);
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    private function buildTable(array $rows): string
    {
        $html = "<div class='portal-panel'><div class='portal-panel-title'>Contratos de Frete</div>";
        if (!$rows) {
            return $html . "<div class='portal-empty-note'>Nenhum contrato localizado para este motorista.</div></div>";
        }

        $html .= "<div class='portal-table-wrap'><table class='portal-table'><thead><tr><th>Contrato</th><th>CRT</th><th>Rota</th><th>Veiculo</th><th>Emissao</th><th>Vencimento</th><th>Frete</th><th>Saldo</th><th>Status</th><th></th></tr></thead><tbody>";

        foreach ($rows as $row) {
            $frete = 'R$ ' . number_format((float) ($row['frete1'] ?? 0), 2, ',', '.');
            $saldo = 'R$ ' . number_format((float) ($row['saldo1'] ?? 0), 2, ',', '.');
            $emissao = !empty($row['emissao']) ? date('d/m/Y', strtotime($row['emissao'])) : '-';
            $vencimento = !empty($row['vencimento']) ? date('d/m/Y', strtotime($row['vencimento'])) : '-';
            $route = htmlspecialchars(trim((string) (($row['origem1'] ?? '-') . ' -> ' . ($row['destino1'] ?? '-'))));
            $status = ((string) ($row['pago'] ?? '') === 'S' || !empty($row['dta_efet_pg'])) ? 'Pago' : 'Aberto';
            $statusClass = $status === 'Pago' ? 'ok' : 'pending';
            $printUrl = PortalMotoristaHelper::openPortalAction('PortalMotoristaContratos', 'onPrint', ['key' => $row['id']]);

            $html .= "<tr>
                <td>#{$row['id']}</td>
                <td>" . htmlspecialchars((string) ($row['conhecimento_numero'] ?: '-')) . "</td>
                <td>{$route}</td>
                <td>" . htmlspecialchars((string) ($row['placa_trator'] ?: '-')) . "</td>
                <td>{$emissao}</td>
                <td>{$vencimento}</td>
                <td>{$frete}</td>
                <td>{$saldo}</td>
                <td><span class='portal-doc-badge {$statusClass}'>{$status}</span></td>
                <td><a generator='adianti' href='{$printUrl}'>Imprimir</a></td>
            </tr>";
        }

        $html .= "</tbody></table></div></div>";
        return $html;
    }
}
