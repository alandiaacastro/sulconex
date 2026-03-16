<?php
/**
 * CaixaImportOFX - Importação de extrato bancário no formato OFX
 *
 * Fluxo:
 *  1. Usuário faz upload do arquivo .ofx
 *  2. Sistema faz parse e exibe preview das transações
 *  3. Usuário confirma → lançamentos inseridos no caixa
 */
class CaixaImportOFX extends TPage
{
    protected $form;
    private static $form_name = 'form_import_ofx';

    public function __construct($param = null)
    {
        parent::__construct();

        Caixa::createTableIfNotExists();

        $this->form = new TForm(self::$form_name);

        $arquivo = new TFile('ofx_file');
        $arquivo->setSize('100%');
        $arquivo->setAllowedExtensions(['ofx','OFX']);

        $form_builder = new BootstrapFormBuilder('form_ofx_inner');
        $form_builder->setFieldSizes('100%');
        $form_builder->setFormTitle('Importar Extrato Bancário OFX');
        $form_builder->addContent([
            '<div class="alert alert-info" style="font-size:.88rem;">
                <i class="fa fa-info-circle"></i>
                Selecione o arquivo <strong>.ofx</strong> exportado pelo seu banco (Bradesco, Itaú, BB, Santander, etc.).<br>
                O sistema irá detectar automaticamente entradas e saídas e evitar duplicatas.
            </div>'
        ]);
        $form_builder->addFields([new TLabel('Arquivo OFX (*)', 'red')], [$arquivo]);

        $btn_processar = TButton::create('processar', [$this, 'onProcessOFX'], 'Processar OFX', 'fa:cogs blue');
        $btn_list      = new TActionLink('Voltar ao Caixa', new TAction(['CaixaList', 'onReload']), null, null, null, 'fa:arrow-left gray');
        $btn_list->class = 'btn btn-default';

        $buttons_box = new THBox;
        $buttons_box->add($btn_processar);
        $buttons_box->add($btn_list);

        $panel = new TPanelGroup('Importação de Extrato OFX');
        $panel->add($form_builder);
        $panel->addFooter($buttons_box);
        $this->form->add($panel);

        $this->form->setFields([$arquivo, $btn_processar]);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(new TXMLBreadCrumb('menu.xml', 'CaixaList'));
        $container->add($this->form);
        parent::add($container);
    }

    /**
     * Exibe a tela de importação (chamado via menu/ação)
     */
    public function onShow($param = null)
    {
        // engine calls show() after run() — constructor already built the form
    }

    /**
     * Processa o arquivo OFX e exibe o preview
     */
    public function onProcessOFX($param)
    {
        try {
            $data = $this->form->getData();
            $uploaded = $data->ofx_file ?? ($param['ofx_file'] ?? null);
            $path = $uploaded ? ('tmp/' . $uploaded) : null;

            if (empty($uploaded) || empty($path) || !file_exists($path)) {
                new TMessage('error', 'Por favor, selecione um arquivo OFX válido.');
                return;
            }

            $conteudo = file_get_contents($path);
            if ($conteudo === false || trim($conteudo) === '') {
                new TMessage('error', 'Não foi possível ler o arquivo OFX enviado.');
                return;
            }
            $transacoes = $this->parseOFX($conteudo);

            if (empty($transacoes)) {
                new TMessage('warning', 'Nenhuma transação encontrada no arquivo OFX.');
                return;
            }

            // Verifica duplicatas
            TTransaction::open('sample');
            $conn = TTransaction::get();
            $fitids_existentes = $conn->query(
                "SELECT ofx_fitid FROM caixa WHERE ofx_fitid IS NOT NULL AND ofx_fitid != ''"
            )->fetchAll(\PDO::FETCH_COLUMN);
            TTransaction::close();

            $novas    = [];
            $duplicatas = 0;
            foreach ($transacoes as $t) {
                if (in_array($t['fitid'], $fitids_existentes)) {
                    $duplicatas++;
                } else {
                    $novas[] = $t;
                }
            }

            // Salva no session para confirmar depois
            TSession::setValue('ofx_preview', $novas);

            // Monta HTML de preview
            $html_rows = '';
            foreach ($novas as $t) {
                $cor   = $t['tipo'] === 'ENTRADA' ? '#198754' : '#dc3545';
                $sinal = $t['tipo'] === 'ENTRADA' ? '+' : '-';
                $html_rows .= "<tr>
                    <td>" . htmlspecialchars($t['data']) . "</td>
                    <td style='max-width:320px;word-break:break-word;'>" . htmlspecialchars($t['descricao']) . "</td>
                    <td style='color:{$cor};font-weight:600;text-align:right;white-space:nowrap;'>{$sinal} R$ " . number_format($t['valor'], 2, ',', '.') . "</td>
                    <td><span class='badge' style='background:{$cor};color:#fff;'>" . htmlspecialchars($t['tipo']) . "</span></td>
                </tr>";
            }

            if (empty($html_rows)) {
                $html_rows = '<tr><td colspan="4" class="text-center text-muted">Todas as transações já foram importadas anteriormente.</td></tr>';
            }

            $total_novas  = count($novas);
            $alert_dup = $duplicatas > 0
                ? "<div class='alert alert-warning mt-2'><i class='fa fa-exclamation-triangle'></i> {$duplicatas} transação(ões) duplicada(s) ignorada(s).</div>"
                : '';

            $preview_html = <<<HTML
{$alert_dup}
<div class="alert alert-success"><i class="fa fa-check-circle"></i> <strong>{$total_novas}</strong> transação(ões) novas encontradas. Confirme a importação abaixo.</div>
<div style="max-height:400px;overflow-y:auto;">
<table class="table table-sm table-striped table-bordered" style="font-size:.83rem;table-layout:fixed;width:100%;">
  <colgroup>
    <col style="width:90px;">
    <col>
    <col style="width:120px;">
    <col style="width:80px;">
  </colgroup>
  <thead class="table-dark">
    <tr><th>Data</th><th>Descrição</th><th class="text-end">Valor</th><th>Tipo</th></tr>
  </thead>
  <tbody>{$html_rows}</tbody>
</table>
</div>
HTML;

            // Mostra formulário de confirmação
            $form_confirm = new BootstrapFormBuilder('form_confirm_ofx');
            $form_confirm->addContent([$preview_html]);

            $btn_confirmar = TButton::create('confirmar', [$this, 'onConfirmarOFX'], 'Confirmar Importação', 'fa:check green');
            $btn_cancelar  = new TActionLink('Cancelar', new TAction(['CaixaList', 'onReload']), null, null, null, 'fa:times red');
            $btn_cancelar->class = 'btn btn-default';

            $btns = new THBox;
            $btns->add($btn_confirmar);
            $btns->add($btn_cancelar);

            $form_confirm->setFields([$btn_confirmar]);

            $panel_preview = new TPanelGroup('Preview das Transações OFX');
            $panel_preview->add($form_confirm);
            $panel_preview->addFooter($btns);

            $container = new TVBox;
            $container->style = 'width: 100%';
            $container->add(new TXMLBreadCrumb('menu.xml', 'CaixaList'));
            $container->add($panel_preview);

            parent::add($container);

        } catch (Exception $e) {
            new TMessage('error', 'Erro ao processar OFX: ' . $e->getMessage());
        }
    }

    /**
     * Confirma e salva as transações OFX no caixa
     */
    public function onConfirmarOFX($param)
    {
        try {
            $transacoes = TSession::getValue('ofx_preview');
            if (empty($transacoes)) {
                new TMessage('warning', 'Nenhuma transação para importar. Processe o OFX novamente.');
                return;
            }

            TTransaction::open('sample');
            $count = 0;
            foreach ($transacoes as $t) {
                $caixa = new Caixa;
                $caixa->data_lancamento = $t['data_db'];
                $caixa->descricao  = $t['descricao'];
                $caixa->tipo       = $t['tipo'];
                $caixa->valor      = $t['valor'];
                $caixa->categoria  = 'EXTRATO';
                $caixa->status     = 'PENDENTE';
                $caixa->ofx_fitid  = $t['fitid'];
                $caixa->store();
                $count++;
            }
            TTransaction::close();

            TSession::setValue('ofx_preview', null);
            new TMessage('info', "{$count} transação(ões) importada(s) com sucesso!");

            // Redireciona para o caixa
            TApplication::gotoPage('CaixaList');

        } catch (Exception $e) {
            new TMessage('error', 'Erro ao importar: ' . $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Parse simples de arquivo OFX (SGML/OFX 1.x e 2.x)
     */
    private function parseOFX(string $conteudo): array
    {
        $transacoes = [];

        // Remove cabeçalho OFX 1.x (linhas antes de <OFX>)
        $pos = stripos($conteudo, '<OFX>');
        if ($pos !== false) {
            $conteudo = substr($conteudo, $pos);
        }

        // OFX 2.x: blocos com tag de fechamento </STMTTRN>
        preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/si', $conteudo, $matches);

        // OFX 1.x (SGML brasileiro): sem tag de fechamento — delimita pelo próximo <STMTTRN> ou </BANKTRANLIST>
        if (empty($matches[1])) {
            preg_match_all('/<STMTTRN>(.*?)(?=<STMTTRN>|<\/BANKTRANLIST>|$)/si', $conteudo, $matches);
        }

        foreach ($matches[1] as $bloco) {
            $tipo_ofx  = $this->ofxTag($bloco, 'TRNTYPE');
            $dtposted  = $this->ofxTag($bloco, 'DTPOSTED');
            $trnamt    = $this->ofxTag($bloco, 'TRNAMT');
            $fitid     = $this->ofxTag($bloco, 'FITID');
            $memo      = $this->ofxTag($bloco, 'MEMO') ?: $this->ofxTag($bloco, 'NAME') ?: 'Extrato';

            if (empty($trnamt)) continue;

            $valor = (float) str_replace(',', '.', $trnamt);
            $tipo  = $valor >= 0 ? 'ENTRADA' : 'SAIDA';
            $valor = abs($valor);

            // Parse da data OFX: YYYYMMDDHHMMSS[.mmm][+|-HH:mm]
            $data_db  = $this->parseOFXDate($dtposted);
            $data_fmt = $data_db ? date('d/m/Y', strtotime($data_db)) : '-';

            $transacoes[] = [
                'data'     => $data_fmt,
                'data_db'  => $data_db ?? date('Y-m-d'),
                'descricao'=> trim($memo),
                'tipo'     => $tipo,
                'valor'    => $valor,
                'fitid'    => trim($fitid),
            ];
        }

        // Ordena por data
        usort($transacoes, fn($a, $b) => strcmp($a['data_db'], $b['data_db']));

        return $transacoes;
    }

    /**
     * Extrai valor de uma tag OFX simples (sem atributos)
     */
    private function ofxTag(string $bloco, string $tag): string
    {
        // OFX 1.x: <TAG>valor (sem fechamento)
        if (preg_match('/<' . $tag . '>([^\r\n<]+)/i', $bloco, $m)) {
            return trim($m[1]);
        }
        // OFX 2.x: <TAG>valor</TAG>
        if (preg_match('/<' . $tag . '>([^<]+)<\/' . $tag . '>/i', $bloco, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /**
     * Converte data OFX (YYYYMMDD...) para yyyy-mm-dd
     */
    private function parseOFXDate(string $dt): ?string
    {
        $dt = trim($dt);
        if (strlen($dt) >= 8) {
            $y = substr($dt, 0, 4);
            $m = substr($dt, 4, 2);
            $d = substr($dt, 6, 2);
            if (checkdate((int)$m, (int)$d, (int)$y)) {
                return "{$y}-{$m}-{$d}";
            }
        }
        return null;
    }
}
