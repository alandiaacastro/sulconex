<?php
/**
 * ImportarSqlMigration
 * Importa clientes e faturacobranca de um arquivo .sql externo
 * para as tabelas clientes e fatura do sistema.
 *
 * Acesse: ?class=ImportarSqlMigration
 */
class ImportarSqlMigration extends TPage
{
    // Caminho padrão do arquivo SQL (pode ser alterado no formulário)
    private static $SQL_DEFAULT = 'C:/Users/alan/OneDrive/Desktop/importar.sql';

    protected $form;

    public function __construct($param = [])
    {
        parent::__construct($param);

        $this->form = new BootstrapFormBuilder('form_import_sql');
        $this->form->setFormTitle('Importar SQL → Clientes + Faturas');

        $sql_path = new TEntry('sql_path');
        $sql_path->setSize('100%');
        $sql_path->setValue(self::$SQL_DEFAULT);

        $this->form->addFields([new TLabel('Caminho do arquivo .sql', '#FF0000')], [$sql_path]);
        $this->form->addContent(['<div class="alert alert-info" style="font-size:.85rem;">
            <i class="fa fa-info-circle"></i>
            Clica em <strong>Pré-visualizar</strong> para ver quantos registros serão importados antes de confirmar.
            Clientes já existentes (mesmo ID) serão ignorados. Faturas serão sempre inseridas como novos registros.
        </div>']);

        $this->form->addAction('Pré-visualizar', new TAction([$this, 'onPreview']), 'fa:eye blue');
        $this->form->addAction('Importar Agora', new TAction([$this, 'onImport']), 'fa:upload green');
        $this->form->addActionLink('Voltar', new TAction(['FaturaList', 'onReload']), 'fa:arrow-left gray');

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        parent::add($container);
    }

    // ── CARREGA SQL NUM SQLITE TEMPORÁRIO ─────────────────────────────────

    private static function loadTempDb(string $sqlPath): PDO
    {
        if (!file_exists($sqlPath)) {
            throw new Exception("Arquivo não encontrado: {$sqlPath}");
        }
        $sql = file_get_contents($sqlPath);
        if (!$sql) {
            throw new Exception("Não foi possível ler o arquivo.");
        }
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec($sql);
        return $pdo;
    }

    // ── PRÉ-VISUALIZAÇÃO ──────────────────────────────────────────────────

    public function onPreview($param)
    {
        try {
            $sqlPath = trim($param['sql_path'] ?? self::$SQL_DEFAULT);
            $temp    = self::loadTempDb($sqlPath);

            $qtd_cli  = $temp->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
            $qtd_fat  = $temp->query("SELECT COUNT(*) FROM faturacobranca")->fetchColumn();

            // Mostra amostra das faturas
            $rows = $temp->query("
                SELECT f.numero, f.emissao, f.vencimento, f.total,
                       c.nome AS cliente
                FROM faturacobranca f
                LEFT JOIN clientes c ON c.id = f.cliente_id
                ORDER BY f.id
                LIMIT 10
            ")->fetchAll(PDO::FETCH_ASSOC);

            $html_rows = '';
            foreach ($rows as $r) {
                $html_rows .= "<tr>
                    <td>" . htmlspecialchars($r['numero'] ?? '') . "</td>
                    <td>" . htmlspecialchars($r['cliente'] ?? '') . "</td>
                    <td>" . htmlspecialchars($r['emissao'] ?? '') . "</td>
                    <td>" . htmlspecialchars($r['vencimento'] ?? '') . "</td>
                    <td style='text-align:right'>R$ " . number_format((float)$r['total'], 2, ',', '.') . "</td>
                </tr>";
            }

            $preview = <<<HTML
<div class="alert alert-success mt-2">
    <i class="fa fa-check-circle"></i>
    Arquivo lido com sucesso.<br>
    <strong>{$qtd_cli}</strong> cliente(s) encontrado(s) &nbsp;|&nbsp;
    <strong>{$qtd_fat}</strong> fatura(s) encontrada(s)
</div>
<p style="font-size:.85rem;color:#555">Amostra das primeiras 10 faturas:</p>
<table class="table table-sm table-bordered" style="font-size:.82rem;">
  <thead class="table-dark">
    <tr><th>Número</th><th>Cliente</th><th>Emissão</th><th>Vencimento</th><th>Total</th></tr>
  </thead>
  <tbody>{$html_rows}</tbody>
</table>
<p class="text-muted" style="font-size:.8rem">Clique em <strong>Importar Agora</strong> para confirmar.</p>
HTML;
            $panel = new TPanelGroup('Pré-visualização');
            $panel->add(TElement::tag('div', $preview));

            $container = new TVBox;
            $container->style = 'width: 100%';
            $container->add($this->form);
            $container->add($panel);
            parent::add($container);

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    // ── IMPORTAÇÃO REAL ───────────────────────────────────────────────────

    public function onImport($param)
    {
        try {
            $sqlPath = trim($param['sql_path'] ?? self::$SQL_DEFAULT);
            $temp    = self::loadTempDb($sqlPath);

            TTransaction::open('sample');
            $conn = TTransaction::get();
            $conn->exec('PRAGMA foreign_keys = OFF');

            // ── 1. CLIENTES ───────────────────────────────────────────────
            $cli_stmt = $conn->prepare("
                INSERT OR IGNORE INTO clientes
                    (id, nome, inscricao_estadual, cnpj, cidade, estado,
                     email, telefone, endereco, cep, atividade, dados_crt)
                VALUES
                    (:id, :nome, :inscricao_estadual, :cnpj, :cidade, :estado,
                     :email, :telefone, :endereco, :cep, :atividade, :dados_crt)
            ");

            $clientes = $temp->query("SELECT * FROM clientes")->fetchAll(PDO::FETCH_ASSOC);
            $cnt_cli  = 0;
            foreach ($clientes as $c) {
                $cli_stmt->execute([
                    ':id'                => $c['id'],
                    ':nome'              => $c['nome'] ?? '',
                    ':inscricao_estadual'=> $c['inscricao_estadual'] ?? null,
                    ':cnpj'              => $c['cnpj'] ?? null,
                    ':cidade'            => $c['cidade'] ?? null,
                    ':estado'            => $c['estado'] ?? null,
                    ':email'             => $c['email'] ?? null,
                    ':telefone'          => $c['telefone'] ?? null,
                    ':endereco'          => $c['endereco'] ?? null,
                    ':cep'               => $c['cep'] ?? null,
                    ':atividade'         => $c['atividade'] ?? null,
                    ':dados_crt'         => $c['dados_crt'] ?? null,
                ]);
                if ($cli_stmt->rowCount() > 0) $cnt_cli++;
            }

            // ── 2. FATURACOBRANCA → FATURA ────────────────────────────────
            $fat_stmt = $conn->prepare("
                INSERT INTO fatura
                    (pessoa_id, conhecimento_id, numero_fatura, emissao, vencimento,
                     nota_fiscal, descricao1, valor1, descricao2, valor2,
                     descricao3, valor3, valor_fatura, valor_extenso,
                     PRODUTO, texto_observacao)
                VALUES
                    (:pessoa_id, :conhecimento_id, :numero_fatura, :emissao, :vencimento,
                     :nota_fiscal, :descricao1, :valor1, :descricao2, :valor2,
                     :descricao3, :valor3, :valor_fatura, :valor_extenso,
                     :PRODUTO, :texto_observacao)
            ");

            $faturas = $temp->query("SELECT * FROM faturacobranca")->fetchAll(PDO::FETCH_ASSOC);
            $cnt_fat = 0;
            foreach ($faturas as $f) {
                $fat_stmt->execute([
                    ':pessoa_id'       => $f['cliente_id'] ?? null,
                    ':conhecimento_id' => $f['conhecimento'] ?? null,
                    ':numero_fatura'   => $f['numero'] ?? null,
                    ':emissao'         => $f['emissao'] ?? null,
                    ':vencimento'      => $f['vencimento'] ?? null,
                    ':nota_fiscal'     => $f['notafiscal'] ?? null,
                    ':descricao1'      => $f['descricao1'] ?? null,
                    ':valor1'          => $f['valor1'] ?? null,
                    ':descricao2'      => $f['descricao2'] ?? null,
                    ':valor2'          => $f['valor2'] ?? null,
                    ':descricao3'      => $f['descricao3'] ?? null,
                    ':valor3'          => $f['valor3'] ?? null,
                    ':valor_fatura'    => $f['total'] ?? null,
                    ':valor_extenso'   => $f['extenso'] ?? null,
                    ':PRODUTO'         => $f['prod'] ?? null,
                    ':texto_observacao'=> $f['obs'] ?? null,
                ]);
                $cnt_fat++;
            }

            $conn->exec('PRAGMA foreign_keys = ON');
            TTransaction::close();

            new TMessage('info',
                "<b>{$cnt_cli}</b> cliente(s) importado(s) (novos).<br>" .
                "<b>{$cnt_fat}</b> fatura(s) importada(s) com sucesso!",
                new TAction(['FaturaList', 'onReload'])
            );

        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro na importação: ' . $e->getMessage());
        }
    }
}
