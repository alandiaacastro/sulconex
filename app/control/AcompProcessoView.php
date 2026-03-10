<?php

class AcompProcessoView extends TPage
{
    private $html;

    public function __construct($param = null)
    {
        parent::__construct();

        // CSS externo nao e necessario para colar no corpo do Gmail (o template usa estilos inline)
        // Mantemos a chamada para nao quebrar outros usos, mas o layout principal ja eh inline.
        TPage::include_css('app/resources/css/acomp_processo_view.css');

        $this->html = new THtmlRenderer('app/resources/acomp_processo_view.html');
        $this->html->enableSection('main', $this->emptyData('Selecione um processo para visualizar.'));

        $box = new TVBox;
        $box->style = 'width:100%';
        $box->add(new TXMLBreadCrumb('menu.xml', 'AcompProcessoKanban'));
        $box->add($this->html);

        parent::add($box);

        if (!empty($param['key'])) {
            $this->onShow($param);
        }
    }

    public function onShow($param)
    {
        try {
            $id = $param['key'] ?? null;
            if (empty($id)) {
                $this->html->enableSection('main', $this->emptyData('ID do processo nao informado.'));
                return;
            }

            TTransaction::open('sample');
            AcompProcesso::ensureTables();

            $proc = new AcompProcesso($id);
            if (empty($proc->id)) {
                TTransaction::close();
                $this->html->enableSection('main', $this->emptyData('Processo nao encontrado.'));
                return;
            }

            $repo = new TRepository('AcompEvento');
            $criteria = new TCriteria;
            $criteria->add(new TFilter('processo_id', '=', $proc->id));
            $criteria->setProperty('order', 'data_evento');
            $criteria->setProperty('direction', 'desc');
            $eventos = $repo->load($criteria, false) ?: [];

            $eventRows = [];
            $eventCount = count($eventos);
            $lastUpdate = '-';

            foreach ($eventos as $i => $evt) {
                $raw = (string) ($evt->data_evento ?? '');
                $ts = strtotime($raw);

                $date = $ts ? date('d/m/Y', $ts) : '-';
                $time = $ts ? date('H:i', $ts) : '-';

                if ($i === 0 && $ts) {
                    $lastUpdate = $date . ' às ' . $time;
                }
                // AcompEventoForm usa: status_texto = Status (combo) e demora = Evento/Local
                $localRaw = trim((string) ($evt->demora ?? ''));
                $local = ($localRaw !== '') ? $localRaw : '-';

                $status = trim((string) ($evt->status_texto ?? ''));
                $obs = trim((string) ($evt->franquia ?? ''));

                // Se vier no formato "LOCAL -> STATUS", separa para preencher a tabela
                $arrowPos = strpos($localRaw, '->');
                if ($arrowPos !== false) {
                    $left = trim(substr($localRaw, 0, $arrowPos));
                    $right = trim(substr($localRaw, $arrowPos + 2));

                    if ($left !== '') {
                        $local = $left;
                    }
                    if ($status === '' && $right !== '') {
                        $status = $right;
                    }
                }

                $statusLabel = ($status !== '') ? $status : '-';
                $badge = self::statusBadgeInline($statusLabel, $local);

                $eventRows[] =
                    '<tr>' .
                        '<td style="padding:12px 14px;border-bottom:1px solid #f1f5f9;color:#0f172a;font-size:9pt;font-weight:400;font-size:9pt;font-weight:400;">' . sprintf('%02d', $i + 1) . '</td>' .
                        '<td style="padding:12px 14px;border-bottom:1px solid #f1f5f9;color:#0f172a;font-size:9pt;font-weight:400;">' . htmlspecialchars($date) . '</td>' .
                        '<td style="padding:12px 14px;border-bottom:1px solid #f1f5f9;color:#0f172a;font-size:9pt;font-weight:400;">' . htmlspecialchars($time) . '</td>' .
                        '<td style="padding:12px 14px;border-bottom:1px solid #f1f5f9;color:#0f172a;font-size:9pt;font-weight:400;font-size:9pt;font-weight:400;">' . $badge . '</td>' .
                        '<td style="padding:12px 14px;border-bottom:1px solid #f1f5f9;color:#0f172a;font-size:9pt;font-weight:400;font-size:9pt;font-weight:400;">' . htmlspecialchars($local ?: '-') . '</td>' .
                        '<td style="padding:12px 14px;border-bottom:1px solid #f1f5f9;color:#0f172a;font-size:9pt;font-weight:400;">' . htmlspecialchars($obs ?: '-') . '</td>' .
                    '</tr>';
            }

            if (empty($eventRows)) {
                $eventRows[] =
                    '<tr>' .
                        '<td colspan="6" style="padding:14px;color:#94a3b8;font-weight:700;">Sem movimentações cadastradas.</td>' .
                    '</tr>';
            }

            $payload = [
                'mensagem' => '',
                'numero_processo' => (string) ($proc->numero_processo ?: '-'),
                'previsao_entrega' => self::fmtDate($proc->previsao_entrega),
                'exportador' => (string) ($proc->exportador ?: '-'),
                'importador' => (string) ($proc->importador ?: '-'),
                'crt' => (string) ($proc->crt ?: '-'),
                'fatura' => (string) ($proc->fatura ?: '-'),
                'event_count' => (string) $eventCount,
                'event_rows' => implode('', $eventRows),
                'last_update' => $lastUpdate,
            ];

            $this->html->enableSection('main', $payload);
            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    private function emptyData(string $msg): array
    {
        return [
            'mensagem' => $msg,
            'numero_processo' => '-',
            'previsao_entrega' => '-',
            'exportador' => '-',
            'importador' => '-',
            'crt' => '-',
            'fatura' => '-',
            'event_count' => '0',
            'event_rows' => '',
            'last_update' => '-',
        ];
    }

    private static function statusBadgeInline(string $status, string $local): string
    {
        $class = self::pickStatusClass($status, $local);

        $bg = '#eff6ff';
        $border = '#93c5fd';
        $color = '#1d4ed8';

        if ($class === 'success') {
            $bg = '#ecfdf5';
            $border = '#86efac';
            $color = '#166534';
        } elseif ($class === 'warn') {
            $bg = '#fff7ed';
            $border = '#fdba74';
            $color = '#9a3412';
        } elseif ($class === 'error') {
            $bg = '#fef2f2';
            $border = '#fecaca';
            $color = '#b91c1c';
        }

        $label = htmlspecialchars($status);

        return '<span style="display:inline-block;padding:6px 12px;border-radius:10px;border:1px solid ' . $border . ';background:' . $bg . ';color:' . $color . ';font-size:9pt;font-weight:400;white-space:nowrap;">' . $label . '</span>';
    }

    private static function pickStatusClass(string $status, string $local): string
    {
        $t = strtolower($status . ' ' . $local);

        if (strpos($t, 'liber') !== false || strpos($t, 'entreg') !== false) {
            return 'success';
        }

        if (strpos($t, 'canal') !== false || strpos($t, 'guarda') !== false) {
            return 'warn';
        }

        if (strpos($t, 'exce') !== false || strpos($t, 'atras') !== false || strpos($t, 'erro') !== false) {
            return 'error';
        }

        return 'info';
    }

    private static function fmtDate($value): string
    {
        $value = (string) $value;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy');
        }
        return $value ?: '-';
    }
}



