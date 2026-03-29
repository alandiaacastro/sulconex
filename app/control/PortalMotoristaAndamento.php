<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TFile;
use Adianti\Widget\Form\THidden;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapFormBuilder;

class PortalMotoristaAndamento extends TPage
{
    private $loaded;
    private $content;
    private $comprovanteForm;

    private static $ALLOWED_EXT = ['jpg','jpeg','png','webp','gif','pdf'];

    public function __construct()
    {
        parent::__construct();

        if (!TSession::getValue('portal_motorista_logged') && !TSession::getValue('logged')) {
            AdiantiCoreApplication::gotoPage('PortalMotoristaLogin');
            return;
        }

        // Garante tabelas de rastreio e comprovantes
        try {
            TTransaction::open('sample');
            self::ensureTables(TTransaction::get());
            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
        }

        // ── Conteúdo principal ────────────────────────────────────────────
        $this->content = new TElement('div');
        $this->content->class = 'portal-page-content';

        // ── Formulário de comprovante (colapsável) ────────────────────────
        $this->comprovanteForm = $this->buildComprovanteForm();

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(PortalMotoristaHelper::buildNav('andamento'));
        $container->add($this->content);
        $container->add($this->comprovanteForm);
        parent::add($container);
    }

    // ── Métodos públicos ────────────────────────────────────────────────────

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');
            Contrato::addColumnsIfNotExists(TTransaction::get());
            self::ensureTables(TTransaction::get());

            $motorista = Motorista::getPortalMotorista();
            if (!$motorista) {
                TTransaction::close();
                new TMessage('error', 'Motorista não encontrado para a sessão atual.');
                return;
            }

            $stmt = TTransaction::get()->prepare("
                SELECT c.id, c.conhecimento_numero, c.origem1, c.destino1,
                       c.emissao, c.vencimento, c.saldo1, c.pago, c.dta_efet_pg,
                       v.placa_trator
                FROM contrato c
                INNER JOIN veiculo v ON v.id = c.veiculo_id
                WHERE v.motorista_id = ?
                  AND (COALESCE(c.pago, '') <> 'S' OR c.dta_efet_pg IS NULL OR c.dta_efet_pg = '')
                ORDER BY c.id DESC
            ");
            $stmt->execute([$motorista->id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Última localização enviada por contrato
            $locStmt = TTransaction::get()->prepare("
                SELECT contrato_id, latitude, longitude, precisao, created_at
                FROM portal_motorista_rastreio
                WHERE motorista_id = ?
                GROUP BY contrato_id
                HAVING id = MAX(id)
            ");
            $locStmt->execute([$motorista->id]);
            $ultimasLocs = [];
            foreach ($locStmt->fetchAll(PDO::FETCH_ASSOC) as $loc) {
                $resolvedLoc = array_merge(
                    $loc,
                    PortalMotoristaLocationResolver::describe(
                        TTransaction::get(),
                        (float) ($loc['latitude'] ?? 0),
                        (float) ($loc['longitude'] ?? 0)
                    )
                );
                $ultimasLocs[(int)$loc['contrato_id']] = $resolvedLoc;
            }

            // Comprovantes enviados por contrato
            $compStmt = TTransaction::get()->prepare("
                SELECT contrato_id, arquivo_original, created_at
                FROM portal_motorista_comprovante
                WHERE motorista_id = ?
                ORDER BY id DESC
            ");
            $compStmt->execute([$motorista->id]);
            $comprovantes = [];
            foreach ($compStmt->fetchAll(PDO::FETCH_ASSOC) as $comp) {
                $comprovantes[(int)$comp['contrato_id']] = $comp;
            }

            TTransaction::close();

            $this->content->clearChildren();
            $this->content->add($this->buildCards($rows, $ultimasLocs, $comprovantes));
            $this->loaded = true;
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onEnviarLocalizacao($param)
    {
        try {
            $lat      = isset($param['latitude'])   ? (float) $param['latitude']   : null;
            $lng      = isset($param['longitude'])  ? (float) $param['longitude']  : null;
            $prec     = isset($param['precisao'])   ? (float) $param['precisao']   : null;
            $contrato = isset($param['contrato_id']) ? (int) $param['contrato_id'] : 0;

            if (!$lat || !$lng || !$contrato) {
                return; // silently ignore bad data
            }

            TTransaction::open('sample');
            self::ensureTables(TTransaction::get());

            $motorista = Motorista::getPortalMotorista();
            if (!$motorista) {
                TTransaction::close();
                return;
            }

            // Valida que o contrato pertence ao motorista
            $check = TTransaction::get()->prepare("
                SELECT c.id FROM contrato c
                INNER JOIN veiculo v ON v.id = c.veiculo_id
                WHERE c.id = ? AND v.motorista_id = ?
            ");
            $check->execute([$contrato, $motorista->id]);
            if (!$check->fetch()) {
                TTransaction::close();
                return;
            }

            TTransaction::get()->prepare("
                INSERT INTO portal_motorista_rastreio
                    (motorista_id, contrato_id, latitude, longitude, precisao, created_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$motorista->id, $contrato, $lat, $lng, $prec, date('Y-m-d H:i:s')]);

            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
        }
    }

    public function onEnviarComprovante($param)
    {
        try {
            TTransaction::open('sample');
            self::ensureTables(TTransaction::get());

            $motorista = Motorista::getPortalMotorista();
            if (!$motorista) {
                throw new Exception('Motorista não encontrado para a sessão atual.');
            }

            $contratoId = (int) ($param['contrato_id'] ?? 0);
            if (!$contratoId) {
                throw new Exception('Selecione uma carga antes de enviar o comprovante.');
            }

            // Valida contrato do motorista
            $check = TTransaction::get()->prepare("
                SELECT c.id FROM contrato c
                INNER JOIN veiculo v ON v.id = c.veiculo_id
                WHERE c.id = ? AND v.motorista_id = ?
            ");
            $check->execute([$contratoId, $motorista->id]);
            if (!$check->fetch()) {
                throw new Exception('Contrato não encontrado.');
            }

            $uploadedName = $param['arquivo_comprovante'] ?? null;
            if (empty($uploadedName)) {
                throw new Exception('Nenhum arquivo selecionado.');
            }

            $source = $this->resolveUploadPath($uploadedName);
            if (!$source || !file_exists($source)) {
                throw new Exception('Arquivo enviado não encontrado. Tente novamente.');
            }

            $ext = strtolower((string) pathinfo($uploadedName, PATHINFO_EXTENSION));
            if (!in_array($ext, self::$ALLOWED_EXT, true)) {
                throw new Exception('Formato inválido. Use JPG, PNG, WEBP, GIF ou PDF.');
            }

            $dir = 'tmp/portal_motorista_comprovantes';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            $safeName = sprintf('comp_%d_%d_%s.%s', $motorista->id, $contratoId, date('YmdHis'), $ext);
            $dest = $dir . '/' . $safeName;

            if (!@copy($source, $dest)) {
                throw new Exception('Não foi possível salvar o comprovante.');
            }

            $obs = htmlspecialchars(strip_tags((string) ($param['observacao'] ?? '')));

            TTransaction::get()->prepare("
                INSERT INTO portal_motorista_comprovante
                    (motorista_id, contrato_id, arquivo, arquivo_original, mime_type, observacao, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $motorista->id,
                $contratoId,
                $dest,
                basename($uploadedName),
                function_exists('mime_content_type') ? mime_content_type($dest) : null,
                $obs ?: null,
                date('Y-m-d H:i:s'),
            ]);

            TTransaction::close();
            new TMessage('info', '<i class="fas fa-check-circle text-success me-2"></i>Comprovante enviado com sucesso!');
            $this->onReload();
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    // ── Construção dos cards ───────────────────────────────────────────────

    private function buildCards(array $rows, array $ultimasLocs, array $comprovantes): TElement
    {
        $wrap = new TElement('div');
        $wrap->class = 'portal-panel';

        $title = new TElement('div');
        $title->class = 'portal-panel-title';
        $title->add('<i class="fas fa-route me-2 text-primary"></i>Cargas em Andamento');
        $wrap->add($title);

        if (!$rows) {
            $empty = new TElement('div');
            $empty->class = 'portal-empty-note';
            $empty->add('Nenhuma carga em andamento no momento.');
            $wrap->add($empty);
            return $wrap;
        }

        $grid = new TElement('div');
        $grid->class = 'portal-progress-grid';
        $wrap->add($grid);

        foreach ($rows as $row) {
            $grid->add($this->buildCard($row, $ultimasLocs, $comprovantes));
        }

        return $wrap;
    }

    private function buildCard(array $row, array $ultimasLocs, array $comprovantes): TElement
    {
        $id          = (int)  $row['id'];
        $crt         = htmlspecialchars((string) ($row['conhecimento_numero'] ?: '-'));
        $origem      = htmlspecialchars(trim((string) ($row['origem1']  ?? '-')));
        $destino     = htmlspecialchars(trim((string) ($row['destino1'] ?? '-')));
        $placa       = htmlspecialchars((string) ($row['placa_trator'] ?: '-'));
        $saldo       = 'R$ ' . number_format((float) ($row['saldo1'] ?? 0), 2, ',', '.');
        $emissao     = !empty($row['emissao'])    ? date('d/m/Y', strtotime($row['emissao']))    : '-';
        $vencimento  = !empty($row['vencimento']) ? date('d/m/Y', strtotime($row['vencimento'])) : '-';
        $printUrl    = PortalMotoristaHelper::openPortalAction('PortalMotoristaContratos', 'onPrint', ['key' => $id]);

        $locInfo     = $ultimasLocs[$id] ?? null;
        $compInfo    = $comprovantes[$id] ?? null;

        $card = new TElement('div');
        $card->class = 'portal-progress-card';

        // ── Cabeçalho: número contrato + CRT ────────────────────────────
        $head = new TElement('div');
        $head->class = 'portal-progress-head';
        $head->add("<strong>Contrato #$id</strong><span>CRT $crt</span>");
        $card->add($head);

        // ── Rota: origem → destino em destaque ──────────────────────────
        $card->add("
            <div style='background:linear-gradient(135deg,#EFF6FF 0%,#DBEAFE 100%);
                        border-radius:10px;padding:10px 14px;margin:8px 0 4px;'>
                <div style='font-size:.67rem;color:#60A5FA;font-weight:700;letter-spacing:.06em;
                            text-transform:uppercase;margin-bottom:4px'>Rota</div>
                <div style='display:flex;align-items:center;gap:6px;flex-wrap:wrap'>
                    <span style='background:#fff;border:1.5px solid #BFDBFE;border-radius:8px;
                                 padding:4px 10px;font-size:.82rem;font-weight:700;color:#1E40AF;
                                 max-width:45%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap'
                          title='$origem'>
                        <i class='fas fa-circle-dot me-1' style='font-size:.6rem;color:#3B82F6'></i>$origem
                    </span>
                    <i class='fas fa-arrow-right' style='color:#93C5FD;font-size:.75rem;flex-shrink:0'></i>
                    <span style='background:#1E40AF;border-radius:8px;
                                 padding:4px 10px;font-size:.82rem;font-weight:700;color:#fff;
                                 max-width:45%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap'
                          title='$destino'>
                        <i class='fas fa-location-dot me-1' style='font-size:.6rem'></i>$destino
                    </span>
                </div>
            </div>
        ");

        // ── Detalhes ─────────────────────────────────────────────────────
        $body = new TElement('div');
        $body->class = 'portal-progress-body';
        $body->add("
            <div><span>Veículo</span><strong>$placa</strong></div>
            <div><span>Emissão</span><strong>$emissao</strong></div>
            <div><span>Vencimento</span><strong>$vencimento</strong></div>
            <div><span>Saldo Previsto</span><strong>$saldo</strong></div>
        ");
        $card->add($body);

        // ── Status localização ───────────────────────────────────────────
        if ($locInfo) {
            $locDate = date('d/m H:i', strtotime($locInfo['created_at']));
            $mapsUrl = 'https://www.google.com/maps?q=' . $locInfo['latitude'] . ',' . $locInfo['longitude'];
            $locLabel = htmlspecialchars((string) ($locInfo['localizacao_label'] ?? 'Coordenadas capturadas'), ENT_QUOTES, 'UTF-8');
            $locDetail = htmlspecialchars((string) ($locInfo['localizacao_detalhe'] ?? ''), ENT_QUOTES, 'UTF-8');
            $locDetailHtml = $locDetail !== ''
                ? "<div style='margin-top:2px;color:#166534'>$locDetail</div>"
                : '';
            $card->add("
                <div style='background:#F0FDF4;border-radius:8px;padding:9px 12px;margin:4px 0;font-size:.78rem'>
                    <div style='display:flex;align-items:center;gap:8px'>
                        <i class='fas fa-map-marker-alt' style='color:#22C55E'></i>
                        <span style='color:#166534;font-weight:600'>Localiza��o enviada em $locDate</span>
                        <a href='$mapsUrl' target='_blank' rel='noopener noreferrer'
                           style='margin-left:auto;color:#16A34A;text-decoration:none;font-size:.72rem;font-weight:700'>
                            Ver no mapa
                        </a>
                    </div>
                    <div style='margin-top:6px;color:#14532D;font-weight:700'>$locLabel</div>
                    $locDetailHtml
                </div>
            ");
        }

        // ── Status comprovante ───────────────────────────────────────────
        if ($compInfo) {
            $compDate = date('d/m H:i', strtotime($compInfo['created_at']));
            $card->add("
                <div style='display:flex;align-items:center;gap:8px;background:#F0FDF4;
                             border-radius:8px;padding:7px 12px;margin:4px 0;font-size:.78rem'>
                    <i class='fas fa-file-check' style='color:#22C55E'></i>
                    <span style='color:#166534;font-weight:600'>Comprovante enviado em $compDate</span>
                </div>
            ");
        }

        // ── Botões de ação ───────────────────────────────────────────────
        $foot = new TElement('div');
        $foot->class = 'portal-progress-foot';
        $foot->style = 'flex-direction:column;gap:8px;padding-top:10px';

        // Linha 1: status badge + ver contrato
        $foot->add("
            <div style='display:flex;align-items:center;justify-content:space-between'>
                <span class='portal-doc-badge pending'>Em andamento</span>
                <a generator='adianti' href='$printUrl'
                   style='font-size:.78rem;color:#1565C0;font-weight:600;text-decoration:none'>
                    <i class='fas fa-file-alt me-1'></i>Ver contrato
                </a>
            </div>
        ");

        // Linha 2: GPS + Comprovante
        $locBtnLabel = $locInfo
            ? '<i class="fas fa-map-marker-alt me-1"></i>Atualizar Localização'
            : '<i class="fas fa-map-marker-alt me-1"></i>Enviar Localização';

        $compBtnLabel = $compInfo
            ? '<i class="fas fa-file-upload me-1"></i>Novo Comprovante'
            : '<i class="fas fa-file-upload me-1"></i>Comprovante de Entrega';

        $foot->add("
            <div style='display:flex;gap:8px'>
                <button type='button'
                        id='btn-loc-$id'
                        onclick='pmEnviarLocalizacao($id)'
                        style='flex:1;padding:9px 6px;border:none;border-radius:10px;
                               font-size:.78rem;font-weight:700;cursor:pointer;
                               background:#1565C0;color:#fff;
                               display:flex;align-items:center;justify-content:center;gap:4px;
                               box-shadow:0 2px 8px rgba(21,101,192,.3);transition:all .2s'>
                    $locBtnLabel
                </button>
                <button type='button'
                        onclick='pmAbrirComprovante($id, \"" . addslashes("$origem → $destino") . "\")'
                        style='flex:1;padding:9px 6px;border:none;border-radius:10px;
                               font-size:.78rem;font-weight:700;cursor:pointer;
                               background:" . ($compInfo ? '#059669' : '#0284C7') . ";color:#fff;
                               display:flex;align-items:center;justify-content:center;gap:4px;
                               box-shadow:0 2px 8px rgba(2,132,199,.3);transition:all .2s'>
                    $compBtnLabel
                </button>
            </div>
        ");

        $card->add($foot);
        return $card;
    }

    // ── Formulário de comprovante ──────────────────────────────────────────

    private function buildComprovanteForm(): TElement
    {
        $wrap = new TElement('div');
        $wrap->id    = 'painel-comprovante';
        $wrap->style = 'display:none';
        $wrap->class = 'portal-page-content';

        $form = new BootstrapFormBuilder('form_comprovante_entrega');
        $form->setFormTitle('<i class="fas fa-file-upload me-2" style="color:#0284C7"></i>Enviar Comprovante de Entrega');

        $contratoIdField = new THidden('contrato_id');

        $arquivo = new TFile('arquivo_comprovante');
        $arquivo->setSize('100%');
        $arquivo->setAllowedExtensions(self::$ALLOWED_EXT);

        $obs = new TEntry('observacao');
        $obs->setSize('100%');
        $obs->setPlaceHolder('Ex.: Entregue ao responsável João na portaria.');

        $hidden = new TElement('div');
        $hidden->style = 'display:none';
        $hidden->add($contratoIdField);
        $form->add($hidden);

        $form->addFields(
            [new TLabel('<i class="fas fa-info-circle me-1 text-primary"></i>Carga selecionada')],
            ['<div id="comprovante-rota-label" style="font-size:.83rem;font-weight:600;color:#1E40AF;padding:6px 0">—</div>']
        );
        $form->addFields(
            [new TLabel('<i class="fas fa-paperclip me-1"></i>Arquivo <small class="text-muted">(foto ou PDF)</small>')],
            [$arquivo]
        );
        $form->addFields(
            [new TLabel('<i class="fas fa-comment-alt me-1"></i>Observação <small class="text-muted">(opcional)</small>')],
            [$obs]
        );

        $form->setFields([$contratoIdField, $arquivo, $obs]);
        $form->addAction('Enviar Comprovante', new TAction([$this, 'onEnviarComprovante']), 'fa:upload green');
        $form->addAction('Cancelar', new TAction([$this, 'onCancelarComprovante']), 'fa:times red');

        $wrap->add($form);
        return $wrap;
    }

    public function onCancelarComprovante($param = null)
    {
        TScript::create("document.getElementById('painel-comprovante').style.display='none';");
    }

    // ── JavaScript injetado ────────────────────────────────────────────────

    private function injectJs(): void
    {
        $actionUrl = json_encode(
            (new TAction([$this, 'onEnviarLocalizacao']))->serialize()
        );

        TScript::create("
(function() {
    // ── GPS: Enviar Localização ─────────────────────────────────────────
    window.pmEnviarLocalizacao = function(contratoId) {
        if (!navigator.geolocation) {
            alert('Geolocalização não suportada neste dispositivo.');
            return;
        }
        var btn = document.getElementById('btn-loc-' + contratoId);
        if (!btn) return;
        var origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class=\"fas fa-spinner fa-spin me-1\"></i>Obtendo GPS...';
        btn.style.background = '#6B7280';

        navigator.geolocation.getCurrentPosition(
            function(pos) {
                var params = {
                    class:        'PortalMotoristaAndamento',
                    method:       'onEnviarLocalizacao',
                    contrato_id:  contratoId,
                    latitude:     pos.coords.latitude,
                    longitude:    pos.coords.longitude,
                    precisao:     Math.round(pos.coords.accuracy)
                };
                var body = Object.keys(params).map(function(k) {
                    return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
                }).join('&');

                fetch('engine.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: body
                }).then(function() {
                    btn.innerHTML = '<i class=\"fas fa-check-circle me-1\"></i>Localiza��o Enviada!';
                    btn.style.background = '#16A34A';
                    setTimeout(function() {
                        window.location.reload();
                    }, 900);
                }).catch(function() {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    btn.style.background = '#1565C0';
                    alert('Erro ao enviar localização. Tente novamente.');
                });
            },
            function(err) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                btn.style.background = '#1565C0';
                var msg = 'Não foi possível obter sua localização.';
                if (err.code === 1) msg = 'Permissão de localização negada. Habilite nas configurações do navegador.';
                else if (err.code === 3) msg = 'Tempo esgotado ao obter localização. Tente novamente.';
                alert(msg);
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    };

    // ── Comprovante: abrir painel ───────────────────────────────────────
    window.pmAbrirComprovante = function(contratoId, rota) {
        var painel = document.getElementById('painel-comprovante');
        var rotaLabel = document.getElementById('comprovante-rota-label');
        var contratoField = document.querySelector('#form_comprovante_entrega [name=\"contrato_id\"]');
        if (!painel || !contratoField) return;

        if (rotaLabel) rotaLabel.textContent = rota;
        contratoField.value = contratoId;
        painel.style.display = 'block';
        painel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
})();
        ");
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function resolveUploadPath(string $name): ?string
    {
        $basename = basename($name);
        foreach (['tmp/' . $basename, $name] as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }
        return null;
    }

    private static function ensureTables(PDO $conn): void
    {
        $conn->exec("CREATE TABLE IF NOT EXISTS portal_motorista_rastreio (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            motorista_id INTEGER NOT NULL,
            contrato_id  INTEGER NOT NULL,
            latitude     REAL    NOT NULL,
            longitude    REAL    NOT NULL,
            precisao     REAL,
            created_at   TEXT
        )");

        $conn->exec("CREATE INDEX IF NOT EXISTS idx_pmr_motorista
            ON portal_motorista_rastreio(motorista_id)");
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_pmr_contrato
            ON portal_motorista_rastreio(contrato_id)");

        $conn->exec("CREATE TABLE IF NOT EXISTS portal_motorista_comprovante (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            motorista_id INTEGER NOT NULL,
            contrato_id  INTEGER NOT NULL,
            arquivo      TEXT    NOT NULL,
            arquivo_original TEXT,
            mime_type    TEXT,
            observacao   TEXT,
            created_at   TEXT
        )");

        $conn->exec("CREATE INDEX IF NOT EXISTS idx_pmc_motorista
            ON portal_motorista_comprovante(motorista_id)");
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_pmc_contrato
            ON portal_motorista_comprovante(contrato_id)");
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload();
        }
        $this->injectJs();
        parent::show();
    }
}


