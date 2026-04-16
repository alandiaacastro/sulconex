<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapFormBuilder;

/**
 * EstoqueView
 * Dashboard de controle de estoque de carga
 */
class EstoqueView extends TPage
{
    private BootstrapFormBuilder $form_filtro;
    private TElement $content_area;

    public function __construct($param = null)
    {
        parent::__construct();

        try {
            $this->buildFiltroForm();

            $this->content_area = new TElement('div');
            $this->content_area->id = 'estoque_view_content';

            $vbox = new TVBox();
            $vbox->setProperty('style', 'display:block; width:100%');
            $vbox->add($this->buildTopBar());
            $vbox->add($this->buildFiltroPanel());
            $vbox->add($this->content_area);

            parent::add($vbox);

            $this->onReload((array) $param);
        } catch (\Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    public function onShow($param = null)
    {
        $this->onReload((array) $param);
    }

    private function buildTopBar(): TElement
    {
        $bar = new TElement('div');
        $bar->class = 'd-flex align-items-center justify-content-between mb-3';

        $title = new TElement('div');
        $title->class = 'd-flex align-items-center gap-2';
        $title->add('<i class="fas fa-warehouse fa-lg text-primary"></i>');
        $h = new TElement('h5');
        $h->class = 'mb-0 fw-bold';
        $h->add('Controle de Estoque de Carga');
        $title->add($h);

        $btn = new TElement('a');
        $btn->href = '?class=EstoqueMovimentoForm';
        $btn->class = 'btn btn-success btn-sm';
        $btn->add('<i class="fas fa-plus me-1"></i> Lancar Movimento');

        $bar->add($title);
        $bar->add($btn);

        return $bar;
    }

    private function buildFiltroForm(): void
    {
        $this->form_filtro = new BootstrapFormBuilder('form_estoque_filtro');
        $this->form_filtro->setProperty('style', 'margin:0');

        $sentido = new TCombo('sentido');
        $sentido->addItems([
            'todos' => 'Todos',
            'entrada' => 'Entrada',
            'saida' => 'Saida',
        ]);
        $sentido->setValue('todos');

        $busca = new TEntry('busca');
        $busca->setProperty('placeholder', 'CRT, NF, chave NF, importador, exportador...');
        $busca->setSize('100%');

        $this->form_filtro->addFields(
            [new TLabel('Sentido')], [$sentido],
            [new TLabel('Buscar')], [$busca]
        );

        $this->form_filtro->addAction('Filtrar', new TAction([$this, 'onReload']), 'fas:search blue');
        $this->form_filtro->addAction('+ Entrada', new TAction(['EstoqueMovimentoForm', '__construct'], ['tipo' => 'entrada']), 'fas:arrow-down green');
        $this->form_filtro->addAction('+ Saida', new TAction(['EstoqueMovimentoForm', '__construct'], ['tipo' => 'saida']), 'fas:arrow-up orange');
    }

    private function buildFiltroPanel(): TElement
    {
        $panel = new TElement('div');
        $panel->class = 'card border-0 shadow-sm mb-3';

        $hdr = new TElement('div');
        $hdr->class = 'card-header py-2';
        $hdr->style = 'background:#343a40;color:#fff;';
        $hdr->add('<i class="fas fa-filter fa-fw me-1"></i><strong>Filtros</strong>');

        $body = new TElement('div');
        $body->class = 'card-body py-2';
        $body->add($this->form_filtro);

        $panel->add($hdr);
        $panel->add($body);

        return $panel;
    }

    public function onReload($param = null)
    {
        $param = (array) $param;
        $sentido = strtolower(trim((string) ($param['sentido'] ?? 'todos')));
        if (!in_array($sentido, ['todos', 'entrada', 'saida'], true)) {
            $sentido = 'todos';
        }

        $busca = trim((string) ($param['busca'] ?? ''));
        $crtRaw = trim((string) ($param['crt'] ?? ''));
        $crtNorm = $crtRaw !== '' ? EstoqueManifesto::normalizeCode($crtRaw) : '';
        if ($busca === '' && $crtRaw !== '') {
            $busca = $crtRaw;
        }

        try {
            TTransaction::open('sample');
            EstoqueManifesto::ensureTables();
            $conn = TTransaction::get();

            $summary = $conn->query("\n                SELECT\n                    COALESCE(SUM(CASE WHEN sentido_calc='entrada' THEN peso_bruto_calc ELSE -peso_bruto_calc END),0) AS saldo_peso_bruto,\n                    COALESCE(SUM(CASE WHEN sentido_calc='entrada' THEN peso_liquido_calc ELSE -peso_liquido_calc END),0) AS saldo_peso_liquido,\n                    COALESCE(SUM(CASE WHEN sentido_calc='entrada' THEN quantidade_calc ELSE -quantidade_calc END),0) AS saldo_quantidade,\n                    COALESCE(SUM(CASE WHEN sentido_calc='entrada' THEN 1 ELSE 0 END),0) AS tot_entradas,\n                    COALESCE(SUM(CASE WHEN sentido_calc='saida' THEN 1 ELSE 0 END),0) AS tot_saidas\n                FROM (\n                    SELECT\n                        CASE\n                            WHEN TRIM(COALESCE(data_saida,'')) <> ''\n                              OR TRIM(COALESCE(motorista_saida_nome,'')) <> ''\n                              OR TRIM(COALESCE(veiculo_saida_cavalo,'')) <> ''\n                              OR TRIM(COALESCE(veiculo_saida_carreta,'')) <> ''\n                            THEN 'saida'\n                            ELSE COALESCE(tipo, 'entrada')\n                        END AS sentido_calc,\n                        COALESCE(NULLIF(peso_bruto_kg,0), peso_kg, 0) AS peso_bruto_calc,\n                        COALESCE(NULLIF(peso_liquido_kg,0), 0) AS peso_liquido_calc,\n                        COALESCE(NULLIF(quantidade,0), bobinas, 0) AS quantidade_calc\n                    FROM estoque_movimento\n                ) base\n            ")->fetch(\PDO::FETCH_ASSOC) ?: [];

            $clientesRow = $conn->query("\n                SELECT COUNT(DISTINCT cliente_id) AS total_clientes\n                FROM (\n                    SELECT m.exportador_id AS cliente_id\n                    FROM estoque_manifesto m\n                    INNER JOIN estoque_movimento mov ON mov.manifesto_id = m.id\n                    GROUP BY m.id, m.exportador_id\n                    HAVING SUM(CASE\n                        WHEN (\n                            TRIM(COALESCE(mov.data_saida,'')) <> ''\n                            OR TRIM(COALESCE(mov.motorista_saida_nome,'')) <> ''\n                            OR TRIM(COALESCE(mov.veiculo_saida_cavalo,'')) <> ''\n                            OR TRIM(COALESCE(mov.veiculo_saida_carreta,'')) <> ''\n                        )\n                        THEN -COALESCE(NULLIF(mov.peso_bruto_kg,0), mov.peso_kg, 0)\n                        ELSE COALESCE(NULLIF(mov.peso_bruto_kg,0), mov.peso_kg, 0)\n                    END) > 0\n                    UNION\n                    SELECT m.importador_id AS cliente_id\n                    FROM estoque_manifesto m\n                    INNER JOIN estoque_movimento mov ON mov.manifesto_id = m.id\n                    GROUP BY m.id, m.importador_id\n                    HAVING SUM(CASE\n                        WHEN (\n                            TRIM(COALESCE(mov.data_saida,'')) <> ''\n                            OR TRIM(COALESCE(mov.motorista_saida_nome,'')) <> ''\n                            OR TRIM(COALESCE(mov.veiculo_saida_cavalo,'')) <> ''\n                            OR TRIM(COALESCE(mov.veiculo_saida_carreta,'')) <> ''\n                        )\n                        THEN -COALESCE(NULLIF(mov.peso_bruto_kg,0), mov.peso_kg, 0)\n                        ELSE COALESCE(NULLIF(mov.peso_bruto_kg,0), mov.peso_kg, 0)\n                    END) > 0\n                ) base\n            ")->fetch(\PDO::FETCH_ASSOC) ?: [];

            $searchSql = '';
            $searchBind = [];
            if ($busca !== '') {
                $searchSql = "\n                    AND (\n                        UPPER(COALESCE(man.crt_codigo,'')) LIKE :term\n                        OR UPPER(COALESCE(dan.danfes,'')) LIKE :term\n                        OR UPPER(COALESCE(exp.nome,'')) LIKE :term\n                        OR UPPER(COALESCE(imp.nome,'')) LIKE :term\n                        OR UPPER(COALESCE(mov.danfe,'')) LIKE :term\n                        OR UPPER(COALESCE(mov.chave_nfe,'')) LIKE :term\n                        OR UPPER(COALESCE(mov.motorista_nome,'')) LIKE :term\n                        OR UPPER(COALESCE(mov.motorista_saida_nome,'')) LIKE :term\n                        OR UPPER(COALESCE(mov.veiculo_cavalo,'')) LIKE :term\n                        OR UPPER(COALESCE(mov.veiculo_carreta,'')) LIKE :term\n                        OR UPPER(COALESCE(mov.veiculo_saida_cavalo,'')) LIKE :term\n                        OR UPPER(COALESCE(mov.veiculo_saida_carreta,'')) LIKE :term\n                    )\n                ";
                $searchBind[':term'] = '%' . strtoupper($busca) . '%';
            }
            if ($crtNorm !== '') {
                $searchSql .= "\n                    AND man.crt_normalizado = :crt_norm\n                ";
                $searchBind[':crt_norm'] = $crtNorm;
            }

            $sentidoSql = $sentido !== 'todos' ? 'AND mov.sentido_calc = :sentido' : '';
            if ($sentido !== 'todos') {
                $searchBind[':sentido'] = $sentido;
            }

            $stmt = $conn->prepare("\n                SELECT\n                    mov.id,\n                    mov.sentido_calc AS sentido,\n                    mov.data_ref AS data_mov,\n                    mov.peso_bruto_calc,\n                    mov.peso_liquido_calc,\n                    mov.quantidade_calc,\n                    mov.tipo_volume,\n                    mov.veiculo_cavalo_ref AS veiculo_cavalo,\n                    mov.veiculo_carreta_ref AS veiculo_carreta,\n                    mov.motorista_ref AS motorista_nome,\n                    mov.tipo_carga,\n                    COALESCE(mov.danfe, dan.danfes, '-') AS danfes,\n                    COALESCE(man.crt_codigo, '-') AS crt,\n                    COALESCE(exp.nome, mov.fornecedor_nome, 'SEM EXPORTADOR') AS cliente_de,\n                    COALESCE(imp.nome, 'SEM IMPORTADOR') AS cliente_para\n                FROM (\n                    SELECT\n                        m.*,\n                        CASE\n                            WHEN TRIM(COALESCE(m.data_saida,'')) <> ''\n                              OR TRIM(COALESCE(m.motorista_saida_nome,'')) <> ''\n                              OR TRIM(COALESCE(m.veiculo_saida_cavalo,'')) <> ''\n                              OR TRIM(COALESCE(m.veiculo_saida_carreta,'')) <> ''\n                            THEN 'saida'\n                            ELSE COALESCE(m.tipo, 'entrada')\n                        END AS sentido_calc,\n                        CASE\n                            WHEN TRIM(COALESCE(m.data_saida,'')) <> '' THEN m.data_saida\n                            ELSE m.data_movimento\n                        END AS data_ref,\n                        COALESCE(NULLIF(m.motorista_saida_nome,''), m.motorista_nome) AS motorista_ref,\n                        COALESCE(NULLIF(m.veiculo_saida_cavalo,''), m.veiculo_cavalo) AS veiculo_cavalo_ref,\n                        COALESCE(NULLIF(m.veiculo_saida_carreta,''), m.veiculo_carreta) AS veiculo_carreta_ref,\n                        COALESCE(NULLIF(m.peso_bruto_kg,0), m.peso_kg, 0) AS peso_bruto_calc,\n                        COALESCE(NULLIF(m.peso_liquido_kg,0), 0) AS peso_liquido_calc,\n                        COALESCE(NULLIF(m.quantidade,0), m.bobinas, 0) AS quantidade_calc\n                    FROM estoque_movimento m\n                ) mov\n                INNER JOIN estoque_manifesto man ON man.id = mov.manifesto_id\n                LEFT JOIN clientes exp ON exp.id = man.exportador_id\n                LEFT JOIN clientes imp ON imp.id = man.importador_id\n                LEFT JOIN (\n                    SELECT manifesto_id, GROUP_CONCAT(danfe_codigo, ' / ') AS danfes\n                    FROM estoque_manifesto_danfe\n                    GROUP BY manifesto_id\n                ) dan ON dan.manifesto_id = man.id\n                WHERE 1=1 {$searchSql} {$sentidoSql}\n                ORDER BY mov.data_ref DESC, mov.id DESC\n                LIMIT 200\n            ");

            foreach ($searchBind as $k => $v) {
                $stmt->bindValue($k, $v);
            }

            $stmt->execute();
            $movs = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            TTransaction::close();

            $html = $this->renderKpis($summary, $clientesRow);
            $html .= $this->renderTabela($movs, $sentido);

            $this->content_area->clearChildren();
            $div = new TElement('div');
            $div->add($html);
            $this->content_area->add($div);
        } catch (\Exception $e) {
            try { TTransaction::rollback(); } catch (\Exception $ignore) {}
            new TMessage('error', $e->getMessage());
        }
    }

    private function renderKpis(array $summary, array $clientesRow): string
    {
        $saldoPesoBruto = number_format(max((float) ($summary['saldo_peso_bruto'] ?? 0), 0), 3, ',', '.');
        $saldoPesoLiquido = number_format(max((float) ($summary['saldo_peso_liquido'] ?? 0), 0), 3, ',', '.');
        $saldoQtd = number_format(max((float) ($summary['saldo_quantidade'] ?? 0), 0), 3, ',', '.');
        $totE = (int) ($summary['tot_entradas'] ?? 0);
        $totS = (int) ($summary['tot_saidas'] ?? 0);
        $totCli = (int) ($clientesRow['total_clientes'] ?? 0);

        $kpis = [
            ['icon' => 'fas fa-arrow-circle-down', 'color' => '#28a745', 'bg' => '#d4edda', 'val' => "{$totE} mov.", 'label' => 'ENTRADAS'],
            ['icon' => 'fas fa-arrow-circle-up', 'color' => '#ffc107', 'bg' => '#fff3cd', 'val' => "{$totS} mov.", 'label' => 'SAIDAS'],
            ['icon' => 'fas fa-weight-hanging', 'color' => '#17a2b8', 'bg' => '#d1ecf1', 'val' => "{$saldoPesoBruto} kg", 'label' => 'SALDO PESO BRUTO'],
            ['icon' => 'fas fa-balance-scale', 'color' => '#6f42c1', 'bg' => '#ede7f6', 'val' => "{$saldoPesoLiquido} kg", 'label' => 'SALDO PESO LIQUIDO'],
            ['icon' => 'fas fa-boxes', 'color' => '#fd7e14', 'bg' => '#fff3cd', 'val' => "{$saldoQtd}", 'label' => 'SALDO QUANTIDADE'],
            ['icon' => 'fas fa-users', 'color' => '#0d6efd', 'bg' => '#e7f1ff', 'val' => "{$totCli} clientes", 'label' => 'COM SALDO'],
        ];

        $cols = '';
        foreach ($kpis as $k) {
            $cols .= "\n            <div class='col'>\n                <div class='card border-0 h-100' style='background:{$k['bg']};border-left:4px solid {$k['color']} !important;'>\n                    <div class='card-body d-flex align-items-center gap-3 py-3'>\n                        <i class='{$k['icon']} fa-2x' style='color:{$k['color']}'></i>\n                        <div>\n                            <div class='fw-bold fs-5'>{$k['val']}</div>\n                            <div class='text-muted small'>{$k['label']}</div>\n                        </div>\n                    </div>\n                </div>\n            </div>";
        }

        return "<div class='row g-3 mb-3 row-cols-2 row-cols-md-3 row-cols-xl-6'>{$cols}</div>";
    }

    private function renderTabela(array $movs, string $sentido): string
    {
        $entradas = array_filter($movs, fn($m) => ($m['sentido'] ?? '') === 'entrada');
        $saidas = array_filter($movs, fn($m) => ($m['sentido'] ?? '') === 'saida');

        $colHeader = '
            <tr style="background:#f8f9fa;font-size:0.78rem;">
                <th style="width:14%">Importador</th>
                <th style="width:8%">Data</th>
                <th style="width:7%">CRT</th>
                <th style="width:8%" class="text-center">Nota Fiscal</th>
                <th style="width:10%">Motorista</th>
                <th style="width:6%" class="text-center">Cavalo</th>
                <th style="width:6%" class="text-center">Carreta</th>
                <th style="width:8%" class="text-end">Peso Bruto</th>
                <th style="width:8%" class="text-end">Peso Liquido</th>
                <th style="width:6%" class="text-end">Qtd</th>
                <th style="width:7%" class="text-center">Tipo Volume</th>
                <th style="width:8%" class="text-center">Tipo Merc.</th>
                <th style="width:4%" class="text-center">Acao</th>
            </tr>';

        $html = '';

        if ($sentido !== 'saida') {
            $rows = $this->buildRows($entradas, 'entrada');
            $totalPesoBrutoE = array_sum(array_map(fn($m) => (float) ($m['peso_bruto_calc'] ?? 0), $entradas));
            $totalPesoLiquidoE = array_sum(array_map(fn($m) => (float) ($m['peso_liquido_calc'] ?? 0), $entradas));
            $totalQtdE = array_sum(array_map(fn($m) => (float) ($m['quantidade_calc'] ?? 0), $entradas));

            $peb = number_format($totalPesoBrutoE, 3, ',', '.');
            $pel = number_format($totalPesoLiquidoE, 3, ',', '.');
            $qe = number_format($totalQtdE, 3, ',', '.');

            $html .= "\n            <div class='card border-0 shadow-sm mb-3'>\n                <div class='card-header py-2 d-flex align-items-center gap-2' style='background:#155724;color:#fff;font-size:0.95rem;letter-spacing:.5px;'>\n                    <i class='fas fa-arrow-circle-down fa-fw'></i>\n                    <strong>ENTRADA ESTOQUE</strong>\n                    <span class='ms-auto small'>P. Bruto: <strong>{$peb}</strong> kg | P. Liquido: <strong>{$pel}</strong> kg | Qtd: <strong>{$qe}</strong></span>\n                </div>\n                <div class='table-responsive'>\n                    <table class='table table-sm table-hover mb-0' style='font-size:0.82rem;'>\n                        <thead>{$colHeader}</thead>\n                        <tbody>{$rows}</tbody>\n                    </table>\n                </div>\n            </div>";
        }

        if ($sentido !== 'entrada') {
            $rows = $this->buildRows($saidas, 'saida');
            $totalPesoBrutoS = array_sum(array_map(fn($m) => (float) ($m['peso_bruto_calc'] ?? 0), $saidas));
            $totalPesoLiquidoS = array_sum(array_map(fn($m) => (float) ($m['peso_liquido_calc'] ?? 0), $saidas));
            $totalQtdS = array_sum(array_map(fn($m) => (float) ($m['quantidade_calc'] ?? 0), $saidas));

            $psb = number_format($totalPesoBrutoS, 3, ',', '.');
            $psl = number_format($totalPesoLiquidoS, 3, ',', '.');
            $qs = number_format($totalQtdS, 3, ',', '.');

            $html .= "\n            <div class='card border-0 shadow-sm mb-3'>\n                <div class='card-header py-2 d-flex align-items-center gap-2' style='background:#856404;color:#fff;font-size:0.95rem;letter-spacing:.5px;'>\n                    <i class='fas fa-arrow-circle-up fa-fw'></i>\n                    <strong>SAIDA ESTOQUE</strong>\n                    <span class='ms-auto small'>P. Bruto: <strong>{$psb}</strong> kg | P. Liquido: <strong>{$psl}</strong> kg | Qtd: <strong>{$qs}</strong></span>\n                </div>\n                <div class='table-responsive'>\n                    <table class='table table-sm table-hover mb-0' style='font-size:0.82rem;'>\n                        <thead>{$colHeader}</thead>\n                        <tbody>{$rows}</tbody>\n                    </table>\n                </div>\n            </div>";
        }

        if ($html === '') {
            $html = "<div class='alert alert-info'>Nenhuma movimentacao encontrada.</div>";
        }

        return $html;
    }

    private function buildRows(array $movs, string $tipo): string
    {
        if (!$movs) {
            return "<tr><td colspan='13' class='text-center text-muted py-3'>Nenhum registro de {$tipo}.</td></tr>";
        }

        $colorCrt = '#28a745';
        $colorDanfe = '#0d6efd';
        $colorPeso = $tipo === 'entrada' ? '#28a745' : '#ffc107';
        $rows = '';

        foreach ($movs as $mov) {
            $id = (int) ($mov['id'] ?? 0);
            $data = $this->formatDate((string) ($mov['data_mov'] ?? ''));
            $pesoBruto = number_format((float) ($mov['peso_bruto_calc'] ?? 0), 3, ',', '.');
            $pesoLiquido = number_format((float) ($mov['peso_liquido_calc'] ?? 0), 3, ',', '.');
            $qtd = number_format((float) ($mov['quantidade_calc'] ?? 0), 3, ',', '.');

            $crt = htmlspecialchars((string) ($mov['crt'] ?? '-'));
            $danfes = htmlspecialchars((string) ($mov['danfes'] ?? '-'));
            $importador = htmlspecialchars((string) ($mov['cliente_para'] ?? '-'));
            $motor = htmlspecialchars((string) ($mov['motorista_nome'] ?? '-'));
            $cavalo = strtoupper(htmlspecialchars((string) ($mov['veiculo_cavalo'] ?? '-')));
            $carreta = strtoupper(htmlspecialchars((string) ($mov['veiculo_carreta'] ?? '-')));
            $tipoVolume = strtoupper(htmlspecialchars((string) ($mov['tipo_volume'] ?? '-')));
            $tipoMercadoria = strtoupper(htmlspecialchars((string) ($mov['tipo_carga'] ?? '-')));

            $rows .= "\n            <tr>\n                <td><strong>{$importador}</strong></td>\n                <td style='color:#dc3545;font-weight:bold;'>{$data}</td>\n                <td class='text-center'><span class='badge' style='background:{$colorCrt};font-size:0.8em;'>{$crt}</span></td>\n                <td class='text-center'><span class='badge' style='background:{$colorDanfe};font-size:0.78em;'>{$danfes}</span></td>\n                <td>{$motor}</td>\n                <td class='text-center'><span class='fw-bold text-primary' style='letter-spacing:1px'>{$cavalo}</span></td>\n                <td class='text-center'><span class='fw-bold text-info' style='letter-spacing:1px'>{$carreta}</span></td>\n                <td class='text-end'><span class='fw-bold' style='color:{$colorPeso}'>{$pesoBruto}</span></td>\n                <td class='text-end'>{$pesoLiquido}</td>\n                <td class='text-end'>{$qtd}</td>\n                <td class='text-center'>{$tipoVolume}</td>\n                <td class='text-center'><span class='badge bg-secondary'>{$tipoMercadoria}</span></td>\n                <td class='text-center'>\n                    <a href='?class=EstoqueMovimentoForm&key={$id}' title='Editar' class='btn btn-xs btn-outline-primary' style='padding:1px 5px;font-size:0.75rem;'><i class='fas fa-edit'></i></a>\n                </td>\n            </tr>";
        }

        return $rows;
    }

    private function formatDate(string $date): string
    {
        if ($date === '') {
            return '-';
        }

        $ts = strtotime($date);
        return $ts !== false ? date('d/m/Y', $ts) : htmlspecialchars($date);
    }
}


