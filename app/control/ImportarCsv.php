<?php
/**
 * ImportarCsv
 * Importa Clientes, CRTs (Conhecimentos) e Faturas via arquivo CSV.
 *
 * Acesse: ?class=ImportarCsv
 */
class ImportarCsv extends TPage
{
    protected $notebook;

    // ── TEMPLATES ────────────────────────────────────────────────────────────

    private static function templateClientes(): array
    {
        return [
            'nome', 'cnpj', 'inscricao_estadual', 'email', 'telefone',
            'endereco', 'cidade', 'estado', 'cep', 'atividade', 'tipo',
        ];
    }

    private static function templateCrt(): array
    {
        return [
            'numero', 'data_transportador_assinatura', 'permisso', 'fatura_crt', 'status_crt_id',
            'remetente_cnpj', 'destinatario_cnpj', 'consignatario_cnpj', 'notificar_cnpj', 'pagador_cnpj',
            'pais_destino',
            'descricao_mercadoria', 'peso_bruto_kg', 'peso_liq_kg', 'quantidade_volumes',
            'valor_mercadorias', 'moeda_valor_mercadorias',
            'valor_frete_externo', 'moeda_frete_externo',
            'incoterm',
            'observacoes',
        ];
    }

    private static function templateFatura(): array
    {
        return [
            'numero_fatura', 'numero_crt', 'fatura_cliente',
            'cliente_cnpj',
            'emissao', 'vencimento', 'prazo', 'taxa',
            'nota_fiscal',
            'descricao1', 'valor1',
            'descricao2', 'valor2',
            'descricao3', 'valor3',
            'valor_fatura', 'valor_extenso',
            'ORIGEM', 'DESTINO',
            'REMETENTE', 'DESTINATARIO',
            'PESO_BRUTO', 'PRODUTO',
            'texto_observacao',
        ];
    }

    // ── CONSTRUTOR ────────────────────────────────────────────────────────────

    public function __construct($param = [])
    {
        parent::__construct($param);

        $this->notebook = new TNotebook('nb_import', '100%', 420);

        // ── ABA CLIENTES ──────────────────────────────────────────────────
        $form_cli = new BootstrapFormBuilder('form_import_clientes');
        $form_cli->setFormTitle('Importar Clientes via CSV');

        $file_cli = new TFile('csv_clientes');
        $file_cli->setSize('100%');
        $file_cli->setAllowedExtensions(['csv', 'txt']);

        $form_cli->addFields([new TLabel('Arquivo CSV de Clientes <span style="color:red">*</span>', null, null, null, '100%')], [$file_cli]);
        $form_cli->addContent([$this->infoAlert('clientes', self::templateClientes())]);

        $form_cli->addAction('⬇ Baixar Modelo', new TAction([$this, 'downloadTemplateClientes']), 'fa:download gray');
        $form_cli->addAction('🔍 Pré-visualizar', new TAction([$this, 'previewClientes']), 'fa:eye blue');
        $form_cli->addAction('⬆ Importar Clientes', new TAction([$this, 'importClientes']), 'fa:upload green');

        $this->notebook->appendPage('👥 Clientes', $form_cli);

        // ── ABA CRT ───────────────────────────────────────────────────────
        $form_crt = new BootstrapFormBuilder('form_import_crt');
        $form_crt->setFormTitle('Importar CRTs via CSV');

        $file_crt = new TFile('csv_crt');
        $file_crt->setSize('100%');
        $file_crt->setAllowedExtensions(['csv', 'txt']);

        $form_crt->addFields([new TLabel('Arquivo CSV de CRTs <span style="color:red">*</span>', null, null, null, '100%')], [$file_crt]);
        $form_crt->addContent([$this->infoAlert('crt', self::templateCrt())]);

        $form_crt->addAction('⬇ Baixar Modelo', new TAction([$this, 'downloadTemplateCrt']), 'fa:download gray');
        $form_crt->addAction('🔍 Pré-visualizar', new TAction([$this, 'previewCrt']), 'fa:eye blue');
        $form_crt->addAction('⬆ Importar CRTs', new TAction([$this, 'importCrt']), 'fa:upload green');

        $this->notebook->appendPage('📦 CRTs', $form_crt);

        // ── ABA FATURAS ───────────────────────────────────────────────────
        $form_fat = new BootstrapFormBuilder('form_import_faturas');
        $form_fat->setFormTitle('Importar Faturas via CSV');

        $file_fat = new TFile('csv_faturas');
        $file_fat->setSize('100%');
        $file_fat->setAllowedExtensions(['csv', 'txt']);

        $form_fat->addFields([new TLabel('Arquivo CSV de Faturas <span style="color:red">*</span>', null, null, null, '100%')], [$file_fat]);
        $form_fat->addContent([$this->infoAlert('faturas', self::templateFatura())]);

        $form_fat->addAction('⬇ Baixar Modelo', new TAction([$this, 'downloadTemplateFatura']), 'fa:download gray');
        $form_fat->addAction('🔍 Pré-visualizar', new TAction([$this, 'previewFatura']), 'fa:eye blue');
        $form_fat->addAction('⬆ Importar Faturas', new TAction([$this, 'importFatura']), 'fa:upload green');

        $this->notebook->appendPage('🧾 Faturas', $form_fat);

        // ── LAYOUT ────────────────────────────────────────────────────────
        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add(TElement::tag('h4', 'Importação de Dados via CSV', ['style' => 'margin-bottom:16px']));
        $vbox->add($this->notebook);

        parent::add($vbox);
    }

    // ── HELPERS ───────────────────────────────────────────────────────────────

    private function infoAlert(string $tipo, array $colunas): string
    {
        $cols = implode(', ', array_map(fn($c) => "<code>{$c}</code>", $colunas));
        return <<<HTML
<div class="alert alert-info" style="font-size:.82rem;margin-top:8px">
    <i class="fa fa-info-circle"></i>
    <strong>Formato esperado:</strong> CSV com separador <code>;</code> (ponto-e-vírgula), codificação UTF-8.<br>
    <strong>Primeira linha:</strong> cabeçalho com os nomes das colunas.<br>
    <strong>Colunas reconhecidas para {$tipo}:</strong><br>{$cols}<br><br>
    Use <strong>⬇ Baixar Modelo</strong> para obter o CSV de exemplo pronto para preenchimento.
</div>
HTML;
    }

    /** Lê o arquivo enviado, retorna array de rows associativas */
    private static function parseCsv(string $inputName, array $param = []): array
    {
        // Raiz absoluta da aplicação (app/control → app → raiz)
        $appRoot = defined('APPLICATION_PATH')
            ? APPLICATION_PATH
            : realpath(__DIR__ . '/../../');

        // Adianti TFile: faz upload via Ajax e salva em tmp/, passando só o nome no $param
        $filename = $param[$inputName] ?? ($_POST[$inputName] ?? null);
        $uploadedFile = null;

        if (!empty($filename)) {
            $basename = basename($filename);
            // Candidatos em ordem de prioridade (absolutos primeiro)
            $candidates = [
                $appRoot . '/tmp/' . $basename,           // ex: C:/wamp64/www/sulconex81/tmp/file.csv
                $appRoot . '/' . ltrim($filename, '/\\'), // caminho relativo à raiz
                'tmp/' . $basename,                       // relativo ao cwd
                $filename,                                // caminho literal
            ];
            foreach ($candidates as $c) {
                if (file_exists($c)) {
                    $uploadedFile = $c;
                    break;
                }
            }
        }

        // Fallback: $_FILES (upload tradicional / multipart)
        if (!$uploadedFile && !empty($_FILES[$inputName]['tmp_name'])) {
            $uploadedFile = $_FILES[$inputName]['tmp_name'];
        }

        // Debug: ajuda a identificar o que foi recebido em caso de falha
        if (empty($uploadedFile) || !file_exists($uploadedFile)) {
            $received = !empty($filename) ? " (recebido: '{$filename}')" : '';
            throw new Exception('Nenhum arquivo enviado' . $received . '. Selecione um arquivo CSV e aguarde o upload completar antes de clicar em Importar.');
        }

        $handle = fopen($uploadedFile, 'r');
        if (!$handle) {
            throw new Exception('Não foi possível abrir o arquivo enviado.');
        }

        // Detect BOM UTF-8
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = null;
        $rows   = [];
        while (($line = fgetcsv($handle, 4096, ';')) !== false) {
            if ($header === null) {
                // Limpar espaços e BOM
                $header = array_map('trim', $line);
                continue;
            }
            if (count(array_filter($line)) === 0) continue; // linha vazia
            $rows[] = array_combine(
                $header,
                array_pad(array_map('trim', $line), count($header), '')
            );
        }
        fclose($handle);

        if (empty($rows)) {
            throw new Exception('O arquivo CSV não contém dados (somente o cabeçalho ou está vazio).');
        }

        return $rows;
    }

    private static function csvContent(array $header, array $sampleRow): string
    {
        $lines = [implode(';', $header), implode(';', $sampleRow)];
        return implode("\r\n", $lines) . "\r\n";
    }

    private static function sendCsvDownload(string $filename, string $content): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo "\xEF\xBB\xBF"; // BOM UTF-8 para Excel
        echo $content;
        exit;
    }

    // ── PREVIEW HELPER ────────────────────────────────────────────────────────

    private static function renderPreviewTable(array $rows, int $limit = 10): string
    {
        if (empty($rows)) return '<p class="text-muted">Nenhum dado encontrado.</p>';

        $headers = array_keys($rows[0]);
        $head    = '<tr>' . implode('', array_map(fn($h) => "<th>{$h}</th>", $headers)) . '</tr>';

        $bodyRows = array_slice($rows, 0, $limit);
        $body     = '';
        foreach ($bodyRows as $r) {
            $cells = implode('', array_map(fn($v) => '<td>' . htmlspecialchars((string)$v) . '</td>', $r));
            $body .= "<tr>{$cells}</tr>";
        }

        $total = count($rows);
        $shown = min($total, $limit);
        $note  = $total > $limit ? "<p class='text-muted' style='font-size:.8rem'>Mostrando {$shown} de {$total} registros.</p>" : '';

        return <<<HTML
{$note}
<div style="overflow-x:auto">
<table class="table table-sm table-bordered table-hover" style="font-size:.8rem;white-space:nowrap">
    <thead class="table-dark">{$head}</thead>
    <tbody>{$body}</tbody>
</table>
</div>
HTML;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ── CLIENTES ──────────────────────────────────────────────────────────────
    // ═══════════════════════════════════════════════════════════════════════════

    public function downloadTemplateClientes($param)
    {
        $header = self::templateClientes();
        $sample = [
            'Empresa Exemplo Ltda', '12.345.678/0001-99', 'IE-123456',
            'contato@exemplo.com', '+55 11 99999-9999',
            'Rua das Flores, 100', 'São Paulo', 'SP', '01310-100',
            'Exportação de grãos', 'EXPORTADOR',
        ];
        self::sendCsvDownload('modelo_clientes.csv', self::csvContent($header, $sample));
    }

    public function previewClientes($param)
    {
        try {
            $rows = self::parseCsv('csv_clientes', $param);
            $total = count($rows);
            $table = self::renderPreviewTable($rows);

            $html = <<<HTML
<div class="alert alert-success">
    <i class="fa fa-check-circle"></i>
    <strong>{$total}</strong> cliente(s) encontrado(s) no arquivo.
</div>
{$table}
<p class="text-muted" style="font-size:.82rem">Clique em <strong>⬆ Importar Clientes</strong> para confirmar.</p>
HTML;
            $panel = new TPanelGroup('Pré-visualização — Clientes');
            $panel->add(TElement::tag('div', $html));

            $vbox = new TVBox;
            $vbox->style = 'width:100%';
            $vbox->add($this->notebook);
            $vbox->add($panel);
            parent::add($vbox);

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    public function importClientes($param)
    {
        try {
            $rows = self::parseCsv('csv_clientes', $param);

            TTransaction::open('sample');
            $conn = TTransaction::get();

            $stmt = $conn->prepare("
                INSERT OR IGNORE INTO clientes (nome, cnpj, inscricao_estadual, email, telefone,
                    endereco, cidade, estado, cep, atividade, tipo)
                VALUES (:nome, :cnpj, :inscricao_estadual, :email, :telefone,
                    :endereco, :cidade, :estado, :cep, :atividade, :tipo)
            ");

            $inserted = 0;
            $skipped  = 0;
            $errors   = [];

            // Controla IEs já usadas neste lote (evita duplicata intra-CSV)
            $ieUsadas = [];

            foreach ($rows as $i => $row) {
                $nome = trim($row['nome'] ?? '');
                if (empty($nome)) {
                    $errors[] = "Linha " . ($i + 2) . ": coluna 'nome' obrigatória.";
                    $skipped++;
                    continue;
                }

                // Verificar duplicata por CNPJ
                $cnpj = preg_replace('/\D/', '', $row['cnpj'] ?? '');
                if ($cnpj) {
                    $exists = $conn->query("SELECT id FROM clientes WHERE REPLACE(REPLACE(REPLACE(cnpj,'.',''),'-',''),'/','') = '{$cnpj}' LIMIT 1")->fetchColumn();
                    if ($exists) {
                        $skipped++;
                        continue;
                    }
                }

                // Converter strings vazias em NULL para colunas UNIQUE
                $nullIfEmpty = fn($v) => (trim((string)($v ?? '')) === '') ? null : trim($v);

                $ie  = $nullIfEmpty($row['inscricao_estadual'] ?? null);
                $cpj = $nullIfEmpty($row['cnpj'] ?? null);

                // Evitar duplicata de IE dentro do próprio CSV
                if ($ie !== null) {
                    if (isset($ieUsadas[$ie])) {
                        $ie = null; // segunda ocorrência: salva como null
                    } else {
                        $ieUsadas[$ie] = true;
                    }
                }

                $stmt->execute([
                    ':nome'               => $nome,
                    ':cnpj'              => $cpj,
                    ':inscricao_estadual' => $ie,
                    ':email'             => $nullIfEmpty($row['email'] ?? null),
                    ':telefone'          => $nullIfEmpty($row['telefone'] ?? null),
                    ':endereco'          => $nullIfEmpty($row['endereco'] ?? null),
                    ':cidade'            => $nullIfEmpty($row['cidade'] ?? null),
                    ':estado'            => $nullIfEmpty($row['estado'] ?? null),
                    ':cep'               => $nullIfEmpty($row['cep'] ?? null),
                    ':atividade'         => $nullIfEmpty($row['atividade'] ?? null),
                    ':tipo'              => $nullIfEmpty($row['tipo'] ?? null),
                ]);
                if ($stmt->rowCount() > 0) {
                    $inserted++;
                } else {
                    $skipped++;
                }
            }

            TTransaction::close();

            $msg = "<b>{$inserted}</b> cliente(s) importado(s) com sucesso.<br>"
                 . "<b>{$skipped}</b> ignorado(s) (duplicado ou sem nome).";
            if ($errors) {
                $msg .= '<br><small>' . implode('<br>', array_slice($errors, 0, 10)) . '</small>';
            }

            new TMessage('info', $msg, new TAction(['ClientesList', 'onReload']));

        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao importar clientes: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ── CRT (CONHECIMENTO) ────────────────────────────────────────────────────
    // ═══════════════════════════════════════════════════════════════════════════

    public function downloadTemplateCrt($param)
    {
        $header = self::templateCrt();
        $sample = [
            'CRT-001', '2026-01-15', 'BR5875', 'FAT-001', '1',
            '12.345.678/0001-99', '98.765.432/0001-11', '', '', '12.345.678/0001-99',
            'AR',
            'Grãos de soja', '25000', '24500', '100',
            '50000.00', 'BRL',
            '3500.00', 'BRL',
            'CIF',
            'Observações gerais',
        ];
        self::sendCsvDownload('modelo_crt.csv', self::csvContent($header, $sample));
    }

    /** Busca ID do cliente pelo CNPJ (retorna null se não encontrado) */
    private static function clienteIdByCnpj(PDO $conn, ?string $cnpj): ?int
    {
        if (empty($cnpj)) return null;
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (empty($cnpj)) return null;
        $id = $conn->query("
            SELECT id FROM clientes
            WHERE REPLACE(REPLACE(REPLACE(cnpj,'.',''),'-',''),'/','') = '{$cnpj}'
            LIMIT 1
        ")->fetchColumn();
        return $id ? (int)$id : null;
    }

    /** Busca ID de permisso pelo código (ex: 'BR5875') */
    private static function permissoIdByCodigo(PDO $conn, ?string $codigo): ?int
    {
        if (empty($codigo)) return null;
        $codigo = trim($codigo);
        $id = $conn->query("
            SELECT id FROM permisso WHERE permisso = " . $conn->quote($codigo) . " LIMIT 1
        ")->fetchColumn();
        return $id ? (int)$id : null;
    }

    public function previewCrt($param)
    {
        try {
            $rows  = self::parseCsv('csv_crt', $param);
            $total = count($rows);
            $table = self::renderPreviewTable($rows);

            $html = <<<HTML
<div class="alert alert-success">
    <i class="fa fa-check-circle"></i>
    <strong>{$total}</strong> CRT(s) encontrado(s) no arquivo.
</div>
{$table}
<p class="text-muted" style="font-size:.82rem">Clique em <strong>⬆ Importar CRTs</strong> para confirmar.</p>
HTML;
            $panel = new TPanelGroup('Pré-visualização — CRTs');
            $panel->add(TElement::tag('div', $html));

            $vbox = new TVBox;
            $vbox->style = 'width:100%';
            $vbox->add($this->notebook);
            $vbox->add($panel);
            parent::add($vbox);

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    public function importCrt($param)
    {
        try {
            $rows = self::parseCsv('csv_crt', $param);

            TTransaction::open('sample');
            $conn = TTransaction::get();

            $stmt = $conn->prepare("
                INSERT INTO conhecimento
                    (numero, data_transportador_assinatura, permisso_id, fatura_crt, status_crt_id,
                     remetente_id, destinatario_id, consignatario_id, notificar_id, pagador_id,
                     pais_destino,
                     descricao_mercadoria, peso_bruto_kg, peso_liq_kg, quantidade_volumes,
                     valor_mercadorias, moeda_valor_mercadorias,
                     valor_frete_externo, moeda_frete_externo,
                     incoterm,
                     observacoes)
                VALUES
                    (:numero, :data_transportador_assinatura, :permisso_id, :fatura_crt, :status_crt_id,
                     :remetente_id, :destinatario_id, :consignatario_id, :notificar_id, :pagador_id,
                     :pais_destino,
                     :descricao_mercadoria, :peso_bruto_kg, :peso_liq_kg, :quantidade_volumes,
                     :valor_mercadorias, :moeda_valor_mercadorias,
                     :valor_frete_externo, :moeda_frete_externo,
                     :incoterm,
                     :observacoes)
            ");

            $inserted = 0;
            $skipped  = 0;
            $errors   = [];

            foreach ($rows as $i => $row) {
                $numero = trim($row['numero'] ?? '');
                if (empty($numero)) {
                    $errors[] = "Linha " . ($i + 2) . ": coluna 'numero' obrigatória.";
                    $skipped++;
                    continue;
                }

                // Verificar duplicata por número
                $exists = $conn->query("SELECT id FROM conhecimento WHERE numero = " . $conn->quote($numero) . " LIMIT 1")->fetchColumn();
                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Resolver IDs de clientes por CNPJ
                $remetente_id     = self::clienteIdByCnpj($conn, $row['remetente_cnpj'] ?? null);
                $destinatario_id  = self::clienteIdByCnpj($conn, $row['destinatario_cnpj'] ?? null);
                $consignatario_id = self::clienteIdByCnpj($conn, $row['consignatario_cnpj'] ?? null);
                $notificar_id     = self::clienteIdByCnpj($conn, $row['notificar_cnpj'] ?? null);
                $pagador_id       = self::clienteIdByCnpj($conn, $row['pagador_cnpj'] ?? null);

                // Resolver permisso_id pelo código (ex: 'BR5875')
                $permisso_id = self::permissoIdByCodigo($conn, $row['permisso'] ?? null);

                $stmt->execute([
                    ':numero'                       => $numero,
                    ':data_transportador_assinatura'=> self::parseDate($row['data_transportador_assinatura'] ?? null),
                    ':permisso_id'                  => $permisso_id,
                    ':fatura_crt'                   => $row['fatura_crt'] ?? null,
                    ':status_crt_id'                => ($row['status_crt_id'] ?? '') ?: null,
                    ':remetente_id'                 => $remetente_id,
                    ':destinatario_id'              => $destinatario_id,
                    ':consignatario_id'             => $consignatario_id,
                    ':notificar_id'                 => $notificar_id,
                    ':pagador_id'                   => $pagador_id,
                    ':pais_destino'                 => $row['pais_destino'] ?? null,
                    ':descricao_mercadoria'         => $row['descricao_mercadoria'] ?? null,
                    ':peso_bruto_kg'                => self::parseDecimal($row['peso_bruto_kg'] ?? null),
                    ':peso_liq_kg'                  => self::parseDecimal($row['peso_liq_kg'] ?? null),
                    ':quantidade_volumes'           => ($row['quantidade_volumes'] ?? '') ?: null,
                    ':valor_mercadorias'            => self::parseDecimal($row['valor_mercadorias'] ?? null),
                    ':moeda_valor_mercadorias'      => $row['moeda_valor_mercadorias'] ?? 'BRL',
                    ':valor_frete_externo'          => self::parseDecimal($row['valor_frete_externo'] ?? null),
                    ':moeda_frete_externo'          => $row['moeda_frete_externo'] ?? null,
                    ':incoterm'                     => $row['incoterm'] ?? null,
                    ':observacoes'                  => $row['observacoes'] ?? null,
                ]);
                $inserted++;
            }

            TTransaction::close();

            $msg = "<b>{$inserted}</b> CRT(s) importado(s) com sucesso.<br>"
                 . "<b>{$skipped}</b> ignorado(s) (duplicado ou sem número).";
            if ($errors) {
                $msg .= '<br><small>' . implode('<br>', array_slice($errors, 0, 10)) . '</small>';
            }

            new TMessage('info', $msg, new TAction(['ConhecimentoList', 'onReload']));

        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao importar CRTs: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ── FATURAS ───────────────────────────────────────────────────────────────
    // ═══════════════════════════════════════════════════════════════════════════

    public function downloadTemplateFatura($param)
    {
        $header = self::templateFatura();
        $sample = [
            'FAT-2026-001', 'CRT-001', 'CLI-001',
            '12.345.678/0001-99',
            '2026-01-15', '2026-02-15', '30', '0',
            'NF-12345',
            'Frete internacional', '3500.00',
            'Seguro', '500.00',
            '', '',
            '4000.00', 'quatro mil reais',
            'São Paulo/SP', 'Buenos Aires/AR',
            'Empresa Exportadora Ltda', 'Empresa Importadora SA',
            '25000', 'Grãos de soja',
            '',
        ];
        self::sendCsvDownload('modelo_faturas.csv', self::csvContent($header, $sample));
    }

    public function previewFatura($param)
    {
        try {
            $rows  = self::parseCsv('csv_faturas', $param);
            $total = count($rows);
            $table = self::renderPreviewTable($rows);

            $html = <<<HTML
<div class="alert alert-success">
    <i class="fa fa-check-circle"></i>
    <strong>{$total}</strong> fatura(s) encontrada(s) no arquivo.
</div>
{$table}
<p class="text-muted" style="font-size:.82rem">Clique em <strong>⬆ Importar Faturas</strong> para confirmar.</p>
HTML;
            $panel = new TPanelGroup('Pré-visualização — Faturas');
            $panel->add(TElement::tag('div', $html));

            $vbox = new TVBox;
            $vbox->style = 'width:100%';
            $vbox->add($this->notebook);
            $vbox->add($panel);
            parent::add($vbox);

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    public function importFatura($param)
    {
        try {
            $rows = self::parseCsv('csv_faturas', $param);

            TTransaction::open('sample');
            $conn = TTransaction::get();

            $stmt = $conn->prepare("
                INSERT INTO fatura
                    (pessoa_id, conhecimento_id, numero_fatura, numero_crt, fatura_cliente,
                     emissao, vencimento, prazo, taxa,
                     nota_fiscal,
                     descricao1, valor1, descricao2, valor2, descricao3, valor3,
                     valor_fatura, valor_extenso,
                     ORIGEM, DESTINO, REMETENTE, DESTINATARIO,
                     PESO_BRUTO, PRODUTO, texto_observacao)
                VALUES
                    (:pessoa_id, :conhecimento_id, :numero_fatura, :numero_crt, :fatura_cliente,
                     :emissao, :vencimento, :prazo, :taxa,
                     :nota_fiscal,
                     :descricao1, :valor1, :descricao2, :valor2, :descricao3, :valor3,
                     :valor_fatura, :valor_extenso,
                     :ORIGEM, :DESTINO, :REMETENTE, :DESTINATARIO,
                     :PESO_BRUTO, :PRODUTO, :texto_observacao)
            ");

            $inserted = 0;
            $skipped  = 0;
            $errors   = [];

            foreach ($rows as $i => $row) {
                $numero_fatura = trim($row['numero_fatura'] ?? '');
                if (empty($numero_fatura)) {
                    $errors[] = "Linha " . ($i + 2) . ": coluna 'numero_fatura' obrigatória.";
                    $skipped++;
                    continue;
                }

                // Verificar duplicata por número de fatura
                $exists = $conn->query("SELECT id FROM fatura WHERE numero_fatura = " . $conn->quote($numero_fatura) . " LIMIT 1")->fetchColumn();
                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Resolver pessoa_id via CNPJ
                $pessoa_id = self::clienteIdByCnpj($conn, $row['cliente_cnpj'] ?? null);

                // Resolver conhecimento_id via numero_crt
                $numero_crt    = trim($row['numero_crt'] ?? '');
                $conhecimento_id = null;
                if ($numero_crt) {
                    $conhecimento_id = $conn->query("
                        SELECT id FROM conhecimento WHERE numero = " . $conn->quote($numero_crt) . " LIMIT 1
                    ")->fetchColumn() ?: null;
                }

                $stmt->execute([
                    ':pessoa_id'       => $pessoa_id,
                    ':conhecimento_id' => $conhecimento_id,
                    ':numero_fatura'   => $numero_fatura,
                    ':numero_crt'      => $numero_crt ?: null,
                    ':fatura_cliente'  => $row['fatura_cliente'] ?? null,
                    ':emissao'         => self::parseDate($row['emissao'] ?? null),
                    ':vencimento'      => self::parseDate($row['vencimento'] ?? null),
                    ':prazo'           => ($row['prazo'] ?? '') ?: null,
                    ':taxa'            => self::parseDecimal($row['taxa'] ?? null),
                    ':nota_fiscal'     => $row['nota_fiscal'] ?? null,
                    ':descricao1'      => $row['descricao1'] ?? null,
                    ':valor1'          => self::parseDecimal($row['valor1'] ?? null),
                    ':descricao2'      => $row['descricao2'] ?? null,
                    ':valor2'          => self::parseDecimal($row['valor2'] ?? null),
                    ':descricao3'      => $row['descricao3'] ?? null,
                    ':valor3'          => self::parseDecimal($row['valor3'] ?? null),
                    ':valor_fatura'    => self::parseDecimal($row['valor_fatura'] ?? null),
                    ':valor_extenso'   => $row['valor_extenso'] ?? null,
                    ':ORIGEM'          => $row['ORIGEM'] ?? null,
                    ':DESTINO'         => $row['DESTINO'] ?? null,
                    ':REMETENTE'       => $row['REMETENTE'] ?? null,
                    ':DESTINATARIO'    => $row['DESTINATARIO'] ?? null,
                    ':PESO_BRUTO'      => self::parseDecimal($row['PESO_BRUTO'] ?? null),
                    ':PRODUTO'         => $row['PRODUTO'] ?? null,
                    ':texto_observacao'=> $row['texto_observacao'] ?? null,
                ]);
                $inserted++;
            }

            TTransaction::close();

            $msg = "<b>{$inserted}</b> fatura(s) importada(s) com sucesso.<br>"
                 . "<b>{$skipped}</b> ignorada(s) (duplicada ou sem número).";
            if ($errors) {
                $msg .= '<br><small>' . implode('<br>', array_slice($errors, 0, 10)) . '</small>';
            }

            new TMessage('info', $msg, new TAction(['FaturaList', 'onReload']));

        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao importar faturas: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ── UTILITÁRIOS ───────────────────────────────────────────────────────────
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Converte data do CSV (dd/mm/yyyy ou yyyy-mm-dd) para yyyy-mm-dd (formato banco).
     */
    private static function parseDate(?string $value): ?string
    {
        if (empty($value)) return null;
        $value = trim($value);

        // dd/mm/yyyy → yyyy-mm-dd
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        // yyyy-mm-dd (já correto)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        return null;
    }

    /**
     * Converte decimal do CSV (vírgula ou ponto como separador) para float.
     */
    private static function parseDecimal(?string $value): ?float
    {
        if (is_null($value) || trim($value) === '') return null;
        // Remove pontos de milhar e substitui vírgula por ponto
        $value = str_replace(['.', ','], ['', '.'], trim($value));
        // Tratamento alternativo: se só há vírgula como decimal
        if (!is_numeric($value)) {
            $value = str_replace(',', '.', trim($value));
        }
        return is_numeric($value) ? (float)$value : null;
    }
}
