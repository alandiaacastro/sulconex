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
                    // Suppress or log internal error while continuing
                    error_log('Error loading StatusCrt: ' . $e->getMessage());
                }
            }

            $faturaNumero = (string) ($crt->fatura_crt ?? '-');
            $crtNumero = (string) ($crt->numero ?? '-');
            $transportadora = trim((string) ($crt->nome_transportador ?? $crt->porteador ?? '-'));
            $dataCarregamento = FollowUpService::formatDate((string) ($crt->data_transportador_assinatura ?? ''));

            $exportador = trim((string) ($crt->nome_remetente ?? ''));
            $importador = trim((string) ($crt->nome_destinatario ?? ''));

            if (!$exportador && !empty($crt->remetente_id)) {
                try {
                    $exportador = (new Clientes($crt->remetente_id))->nome;
                } catch (Exception $e) {
                    error_log('Error loading Clientes (remetente): ' . $e->getMessage());
                }
            }

            if (!$importador && !empty($crt->destinatario_id)) {
                try {
                    $importador = (new Clientes($crt->destinatario_id))->nome;
                } catch (Exception $e) {
                    error_log('Error loading Clientes (destinatario): ' . $e->getMessage());
                }
            }

            $eventos = FollowUpService::getEventosTimeline($crt, $statusNome);
            $timelineHtml = $this->buildTimelineHtml($eventos);

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
                'timeline_html' => $timelineHtml ?: '<div class="fu-alert fu-alert--info" style="display:block;">Nenhum evento registrado.</div>',
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

    private function buildTimelineHtml(array $eventos): string
    {
        if (empty($eventos)) {
            return '';
        }

        $html = [];
        foreach ($eventos as $evento) {
            $rowClass = !empty($evento['highlight']) ? 'fu-tl-node fu-tl-node--highlight' : 'fu-tl-node';

            $date = htmlspecialchars((string) ($evento['data'] ?? '-'));
            $title = htmlspecialchars((string) ($evento['title'] ?? ''));
            $sub = htmlspecialchars((string) ($evento['sub'] ?? ''));
            $icon = (string) ($evento['icon'] ?? 'fas fa-circle');

            $html[] = '
                <div class="' . $rowClass . '">
                    <div class="fu-tl-icon"><i class="' . $icon . '"></i></div>
                    <div class="fu-tl-content">
                        <div class="fu-tl-header-row">
                            <div class="fu-tl-title">' . $title . '</div>
                            <div class="fu-tl-date">' . $date . '</div>
                        </div>
                        <div class="fu-tl-sub">' . $sub . '</div>
                    </div>
                </div>';
        }

        return implode('', $html);
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
            'timeline_html' => '-',
        ];
    }
}
