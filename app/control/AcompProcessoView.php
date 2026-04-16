<?php

class AcompProcessoView extends TPage
{
    private $html;

    public function __construct($param = null)
    {
        parent::__construct();


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
            $lastUpdateRaw = '';
            $currentStage = AcompProcesso::normalizeStageCode((string) ($proc->etapa ?? ''));
            if ($currentStage === '') {
                $currentStage = AcompProcesso::STAGE_COLETA;
            }

            foreach ($eventos as $i => $evt) {
                $raw = (string) ($evt->data_evento ?? '');
                $ts = strtotime($raw);
                $data = $ts ? date('d/m/Y H:i', $ts) : '-';

                if ($i === 0 && $ts) {
                    $lastUpdate = $data;
                    $lastUpdateRaw = date('Y-m-d H:i:s', $ts);
                }

                $evento = trim((string) ($evt->demora ?? ''));
                $eventStage = self::resolveStageFromEvent((string) ($evt->status_texto ?? ''), $currentStage);
                if ($i === 0) {
                    $currentStage = $eventStage;
                }
                $localizacao = trim((string) ($evt->localizacao ?? ''));
                $franquia = trim((string) ($evt->franquia ?? ''));

                $eventoLabel = $evento !== '' ? $evento : '-';
                $statusLabel = AcompProcesso::stageLabel($eventStage);
                $localizacaoLabel = $localizacao !== '' ? $localizacao : '-';
                $franquiaLabel = $franquia !== '' ? $franquia : '-';
                $badge = self::statusBadgeInline($statusLabel);

                $eventRows[] =
                    '<tr>' .
                        '<td>' . htmlspecialchars($data) . '</td>' .
                        '<td>' . $badge . '</td>' .
                        '<td>' . htmlspecialchars($eventoLabel) . '</td>' .
                        '<td>' . htmlspecialchars($localizacaoLabel) . '</td>' .
                        '<td>' . htmlspecialchars($franquiaLabel) . '</td>' .
                    '</tr>';
            }

            if (empty($eventRows)) {
                $eventRows[] =
                    '<tr>' .
                        '<td colspan="5" class="text-center text-muted">Sem movimentacoes cadastradas.</td>' .
                    '</tr>';
            }

            $msg = '';
            $stage = $currentStage;
            if (AcompProcesso::isTransitStage($stage)) {
                if ($lastUpdateRaw === '') {
                    $msg = 'ALERTA 24H: carga em transito sem atualizacao registrada.';
                } else {
                    $ageHours = floor((time() - strtotime($lastUpdateRaw)) / 3600);
                    if ($ageHours >= 24) {
                        $msg = 'ALERTA 24H: ultima atualizacao ha ' . (int) $ageHours . 'h.';
                    }
                }
            }

            $payload = [
                'mensagem' => $msg,
                'alert_style' => $msg ? '' : 'display:none;',
                'numero_processo' => (string) ($proc->numero_processo ?: '-'),
                'previsao_entrega' => self::fmtDate($proc->previsao_entrega),
                'exportador' => (string) ($proc->exportador ?: '-'),
                'importador' => (string) ($proc->importador ?: '-'),
                'local_entrega' => (string) ($proc->local_entrega ?: '-'),
                'crt' => (string) ($proc->crt ?: '-'),
                'fatura' => (string) ($proc->fatura ?: '-'),
                'etapa_atual' => AcompProcesso::stageLabel($currentStage),
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
            'alert_style' => $msg ? '' : 'display:none;',
            'numero_processo' => '-',
            'previsao_entrega' => '-',
            'exportador' => '-',
            'importador' => '-',
            'local_entrega' => '-',
            'crt' => '-',
            'fatura' => '-',
            'etapa_atual' => '-',
            'event_count' => '0',
            'event_rows' => '',
            'last_update' => '-',
        ];
    }

    private static function resolveStageFromEvent(string $rawStatus, string $fallback): string
    {
        $stage = AcompProcesso::normalizeStageCode($rawStatus);
        if ($stage !== '') {
            return $stage;
        }

        $fallbackStage = AcompProcesso::normalizeStageCode($fallback);
        if ($fallbackStage !== '') {
            return $fallbackStage;
        }

        return AcompProcesso::STAGE_COLETA;
    }

    private static function statusBadgeInline(string $status): string
    {
        $class = self::pickStatusClass($status);

        $badgeClass = match ($class) {
            'success' => 'bg-success',
            'warn'    => 'bg-warning text-dark',
            'error'   => 'bg-danger',
            default   => 'bg-primary',
        };

        return '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($status) . '</span>';
    }

    private static function pickStatusClass(string $status): string
    {
        $t = strtolower($status);

        if (strpos($t, 'liber') !== false || strpos($t, 'entreg') !== false) {
            return 'success';
        }

        if (strpos($t, 'canal') !== false || strpos($t, 'guarda') !== false || strpos($t, 'aduana') !== false) {
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
