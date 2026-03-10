<?php

class FollowUpCargaView extends TPage
{
    private $form;
    private $html;

    public function __construct($param = null)
    {
        parent::__construct();

        TPage::include_css('app/resources/css/followup_carga.css');

        $this->form = new BootstrapFormBuilder('form_followup_carga');
        $this->form->setFormTitle('Follow-up de Carga');

        $id = new TEntry('id');
        $numero = new TEntry('numero');

        $id->setSize('100%');
        $numero->setSize('100%');

        $this->form->addFields([new TLabel('ID CRT')], [$id], [new TLabel('Numero CRT')], [$numero]);
        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search blue');

        $this->html = new THtmlRenderer('app/resources/followup_carga.html');
        $this->html->enableSection('main', $this->emptyPayload());

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(new TXMLBreadCrumb('menu.xml', 'ConhecimentoList'));
        $container->add($this->form);
        $container->add($this->html);

        parent::add($container);

        if (!empty($param['key']) || !empty($param['id']) || !empty($param['numero'])) {
            $this->loadFollowup($param);
        }
    }

    public function onShow($param = null)
    {
        if (!empty($param['key']) || !empty($param['id']) || !empty($param['numero'])) {
            $this->loadFollowup($param);
        }
    }

    public function onSearch($param = null)
    {
        $data = $this->form->getData();
        $this->form->setData($data);
        $this->loadFollowup((array) $data);
    }

    private function loadFollowup($param): void
    {
        try {
            TTransaction::open('sample');

            $crt = $this->findConhecimento($param);

            if (!$crt || empty($crt->id)) {
                $this->html->enableSection('main', $this->emptyPayload('CRT nao encontrado para os filtros informados.'));
                TTransaction::close();
                return;
            }

            $statusNome = 'Sem status';
            if (!empty($crt->status_crt_id)) {
                try {
                    $status = new StatusCrt($crt->status_crt_id);
                    $statusNome = $status->nome ?: $statusNome;
                } catch (Exception $e) {
                }
            }

            $faturaNumero = (string) ($crt->fatura_crt ?? '-');
            $crtNumero = (string) ($crt->numero ?? '-');
            $transportadora = trim((string) ($crt->nome_transportador ?? $crt->porteador ?? '-'));
            $dataCarregamento = self::formatDate((string) ($crt->data_transportador_assinatura ?? ''));

            $exportador = trim((string) ($crt->nome_remetente ?? ''));
            $importador = trim((string) ($crt->nome_destinatario ?? ''));

            if (!$exportador && !empty($crt->remetente_id)) {
                try {
                    $exportador = (new Clientes($crt->remetente_id))->nome;
                } catch (Exception $e) {
                }
            }

            if (!$importador && !empty($crt->destinatario_id)) {
                try {
                    $importador = (new Clientes($crt->destinatario_id))->nome;
                } catch (Exception $e) {
                }
            }

            $eventos = $this->buildTimeline($crt, $statusNome);

            $payload = [
                'mensagem' => '',
                'fatura' => $faturaNumero ?: '-',
                'transporte' => $transportadora ?: '-',
                'crt' => $crtNumero ?: '-',
                'data_carregamento' => $dataCarregamento ?: '-',
                'aduana_origem' => (string) ($crt->local_responsabilidade ?: '-'),
                'porto_destino' => (string) ($crt->local_entrega ?: '-'),
                'exportador' => $exportador ?: '-',
                'importador' => $importador ?: '-',
                'timeline_rows' => $eventos ?: '<tr><td colspan="3">Nenhum evento informado.</td></tr>',
            ];

            $this->html->enableSection('main', $payload);
            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    private function findConhecimento(array $param): ?Conhecimento
    {
        $id = $param['key'] ?? $param['id'] ?? null;
        if (!empty($id)) {
            $obj = new Conhecimento($id);
            return !empty($obj->id) ? $obj : null;
        }

        $numero = trim((string) ($param['numero'] ?? ''));
        if ($numero === '') {
            return null;
        }

        $repo = new TRepository('Conhecimento');
        $criteria = new TCriteria;
        $criteria->add(new TFilter('numero', '=', $numero));
        $criteria->setProperty('limit', 1);
        $rows = $repo->load($criteria);

        if (!empty($rows)) {
            return $rows[0];
        }

        return null;
    }

    private function buildTimeline(Conhecimento $crt, string $statusNome): string
    {
        $eventos = [];

        if (!empty($crt->data_transportador_assinatura)) {
            $eventos[] = [
                'ts' => strtotime((string) $crt->data_transportador_assinatura . ' 12:00:00') ?: 0,
                'data' => self::formatDateTime((string) $crt->data_transportador_assinatura . ' 12:00:00'),
                'texto' => 'CRT emitido / assinado pelo transportador.',
                'hl' => true,
            ];
        }

        $eventos[] = [
            'ts' => time(),
            'data' => date('d/m/Y H\hi'),
            'texto' => 'Status atual: ' . $statusNome . '.',
            'hl' => false,
        ];

        $eventos = array_merge($eventos, $this->parseNotesToEvents((string) ($crt->observacoes ?? '')));
        $eventos = array_merge($eventos, $this->parseNotesToEvents((string) ($crt->documentos_anexos ?? '')));

        usort($eventos, function ($a, $b) {
            return ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0);
        });

        $html = [];
        foreach ($eventos as $i => $evento) {
            $rowClass = ($i === 0 || !empty($evento['hl'])) ? 'fu-tl-row fu-tl-row--highlight' : 'fu-tl-row';

            $rawDate = (string) ($evento['data'] ?? '-');
            $rawText = (string) ($evento['texto'] ?? '');

            [$title, $sub] = self::splitTimelineText($rawText);
            $icon = self::pickTimelineIcon($title . ' ' . $sub);

            $date = htmlspecialchars($rawDate);
            $title = htmlspecialchars($title);
            $sub = htmlspecialchars($sub);

            $html[] =
                '<tr class="' . $rowClass . '">' .
                    '<td>' .
                        '<div class="fu-tl-obj">' .
                            '<span class="fu-tl-ico"><i class="' . $icon . '"></i></span>' .
                            '<div><div class="fu-tl-title">' . $title . '</div></div>' .
                        '</div>' .
                    '</td>' .
                    '<td><div class="fu-tl-sub">' . $sub . '</div></td>' .
                    '<td class="fu-tl-date">' . $date . '</td>' .
                '</tr>';
        }

        return implode('', $html);
    }

    private static function splitTimelineText(string $texto): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return ['-', ''];
        }

        if (preg_match('/^(.+?):\s*(.+)$/', $texto, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        if (preg_match('/^(.+?\.)\s+(.+)$/', $texto, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [$texto, ''];
    }

    private static function pickTimelineIcon(string $texto): string
    {
        $t = strtolower($texto);

        if (strpos($t, 'entreg') !== false) {
            return 'fas fa-check';
        }

        if (strpos($t, 'transit') !== false || strpos($t, 'rota') !== false) {
            return 'fas fa-truck';
        }

        if (strpos($t, 'aguard') !== false) {
            return 'fas fa-clock';
        }

        if (strpos($t, 'post') !== false || strpos($t, 'emitido') !== false || strpos($t, 'assinado') !== false) {
            return 'fas fa-file-alt';
        }

        if (strpos($t, 'status atual') !== false) {
            return 'fas fa-info-circle';
        }

        return 'fas fa-circle';
    }

    private function parseNotesToEvents(string $texto): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return [];
        }

        $linhas = preg_split('/\\r\\n|\\r|\\n/', $texto) ?: [];
        $eventos = [];

        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if ($linha === '') {
                continue;
            }

            $data = date('d/m/Y H\\hi');
            $ts = time() - 60;
            $mensagem = $linha;

            if (preg_match('/^(\\d{2}\\/\\d{2}\\/\\d{4})(?:\\s+(\\d{2}[:h]\\d{2}))?\\s*[-:]\\s*(.+)$/', $linha, $m)) {
                $hora = !empty($m[2]) ? str_replace('h', ':', $m[2]) : '12:00';
                $raw = $m[1] . ' ' . $hora;
                $parsed = strtotime(str_replace('/', '-', $raw));
                if ($parsed) {
                    $ts = $parsed;
                    $data = date('d/m/Y H\\hi', $parsed);
                }
                $mensagem = trim($m[3]);
            }

            $eventos[] = [
                'ts' => $ts,
                'data' => $data,
                'texto' => $mensagem,
                'hl' => false
            ];
        }

        return $eventos;
    }

    private function emptyPayload(string $msg = 'Informe o ID CRT ou numero CRT para visualizar o acompanhamento.'): array
    {
        return [
            'mensagem' => $msg,
            'fatura' => '-',
            'transporte' => '-',
            'crt' => '-',
            'data_carregamento' => '-',
            'aduana_origem' => '-',
            'porto_destino' => '-',
            'exportador' => '-',
            'importador' => '-',
            'timeline_rows' => '<tr><td colspan="3">-</td></tr>',
        ];
    }

    private static function formatDate(string $value): string
    {
        if (!$value) {
            return '';
        }

        if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) {
            return TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy');
        }

        return $value;
    }

    private static function formatDateTime(string $value): string
    {
        $ts = strtotime($value);
        if ($ts) {
            return date('d/m/Y H\\hi', $ts);
        }

        return $value;
    }
}



