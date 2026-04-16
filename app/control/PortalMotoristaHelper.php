<?php

use Adianti\Control\TAction;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;

class PortalMotoristaHelper
{
    public static function openPortalAction(string $class, string $method = 'onReload', array $params = []): string
    {
        $action = new TAction([$class, $method], $params);
        return $action->serialize();
    }

    /**
     * Gera o cabeçalho azul + bottom tab bar estilo app mobile
     */
    public static function buildNav(string $active): TElement
    {
        $nome        = htmlspecialchars((string) (TSession::getValue('portal_motorista_nome') ?: 'Motorista'));
        $nomeShort   = mb_strlen($nome) > 20 ? mb_substr($nome, 0, 20) . '…' : $nome;
        $motoristaId = (int) (TSession::getValue('portal_motorista_id') ?: 0);
        $pendentes   = $motoristaId > 0 ? self::countPendingDocuments($motoristaId) : 0;
        $logoutUrl   = (new TAction(['PortalMotoristaLogin', 'onLogout']))->serialize();

        $wrapper = new TElement('div');

        // ── Padding bottom para não cobrir conteúdo com o bottom nav ────────
        $styleEl = new TElement('style');
        $styleEl->add('
            .portal-page-content { padding-bottom: 90px; }
            .pm-bottom-nav-item { text-decoration:none; display:flex; flex-direction:column;
                align-items:center; gap:3px; padding:6px 12px; border-radius:12px;
                transition:all .2s; flex:1; }
            .pm-bottom-nav-item:active { transform:scale(.92); }
        ');
        $wrapper->add($styleEl);

        // ── Cabeçalho azul sticky ────────────────────────────────────────────
        $header = new TElement('div');
        $header->class = 'd-flex align-items-center justify-content-between px-3 py-3 mb-3';
        $header->style = 'background:linear-gradient(135deg,#1565C0 0%,#1976D2 100%);'
                       . 'border-radius:0 0 20px 20px;margin:-15px -15px 0;'
                       . 'box-shadow:0 4px 16px rgba(21,101,192,.35);';
        $header->add("
            <div class='d-flex align-items-center gap-3'>
                <div class='d-flex align-items-center justify-content-center rounded-circle flex-shrink-0'
                     style='width:44px;height:44px;background:rgba(255,255,255,.18);border:2px solid rgba(255,255,255,.3)'>
                    <i class='fas fa-user text-white' style='font-size:1.1rem'></i>
                </div>
                <div>
                    <div style='font-size:.68rem;color:rgba(255,255,255,.75);font-weight:500;letter-spacing:.04em;text-transform:uppercase'>
                        Portal do Motorista
                    </div>
                    <div class='fw-bold text-white' style='font-size:.95rem;letter-spacing:-.01em'>
                        {$nomeShort}
                    </div>
                </div>
            </div>
            <a generator='adianti' href='{$logoutUrl}'
               class='text-white text-decoration-none d-flex align-items-center justify-content-center rounded-circle'
               style='width:40px;height:40px;background:rgba(255,255,255,.15);font-size:1.1rem;transition:.2s;border:1.5px solid rgba(255,255,255,.25)'
               title='Sair'>
                <i class='fas fa-sign-out-alt'></i>
            </a>
        ");
        $wrapper->add($header);

        // ── Bottom Tab Bar (fixed) ───────────────────────────────────────────
        // Items: inicio | cargas | andamento (FAB center) | solicitacoes | documentos
        $urlInicio       = self::openPortalAction('PortalMotoristaHome');
        $urlCargas       = self::openPortalAction('PortalMotoristaCargas');
        $urlAndamento    = self::openPortalAction('PortalMotoristaAndamento');
        $urlSolicitacoes = self::openPortalAction('PortalMotoristaSolicitacoes');
        $urlDocumentos   = self::openPortalAction('PortalMotoristaDocumentos');

        $docBadge = $pendentes > 0
            ? "<span style='position:absolute;top:-2px;right:-2px;background:#EF4444;color:#fff;"
            . "font-size:.55rem;width:16px;height:16px;border-radius:50%;display:flex;align-items:center;"
            . "justify-content:center;border:2px solid #fff;font-weight:700'>{$pendentes}</span>"
            : '';

        $tabs = [
            'inicio'       => [$urlInicio,       'fa-home',          'Início',      false],
            'cargas'       => [$urlCargas,        'fa-search',        'Cargas',      false],
            'andamento'    => [$urlAndamento,     'fa-route',         'Em Curso',    true],  // FAB center
            'solicitacoes' => [$urlSolicitacoes,  'fa-clipboard-list','Pedidos',     false],
            'documentos'   => [$urlDocumentos,    'fa-folder-open',   'Docs',        false],
        ];

        $navHtml = '<div style="position:fixed;bottom:0;left:0;right:0;z-index:9999;'
                 . 'background:#fff;border-top:1px solid #E2E8F0;'
                 . 'display:flex;align-items:center;justify-content:space-around;'
                 . 'padding:6px 8px 10px;box-shadow:0 -4px 16px rgba(0,0,0,.1);">';

        foreach ($tabs as $key => [$url, $icon, $label, $isFab]) {
            $isActive = ($active === $key);

            if ($isFab) {
                // Center FAB button (elevated)
                $fabBg    = $isActive ? '#0F3FA6' : '#1565C0';
                $navHtml .= "
                <a generator='adianti' href='{$url}'
                   style='text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:2px;flex:1;'>
                    <div style='width:52px;height:52px;border-radius:50%;background:{$fabBg};
                                display:flex;align-items:center;justify-content:center;
                                margin-top:-22px;box-shadow:0 4px 16px rgba(21,101,192,.5);
                                border:3px solid #F1F5F9;transition:all .2s'>
                        <i class='fas {$icon}' style='font-size:1.1rem;color:#fff'></i>
                    </div>
                    <span style='font-size:.6rem;font-weight:700;color:" . ($isActive ? '#1565C0' : '#94A3B8') . ";letter-spacing:.02em;margin-top:2px'>{$label}</span>
                </a>";
            } else {
                $iconColor  = $isActive ? '#1565C0' : '#94A3B8';
                $labelColor = $isActive ? '#1565C0' : '#94A3B8';
                $extraBadge = ($key === 'documentos') ? $docBadge : '';

                $navHtml .= "
                <a generator='adianti' href='{$url}'
                   style='text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:3px;flex:1;padding:4px 0;position:relative'>
                    <div style='position:relative;display:inline-block'>
                        <i class='fas {$icon}' style='font-size:1.15rem;color:{$iconColor};transition:color .2s'></i>
                        {$extraBadge}
                    </div>
                    <span style='font-size:.62rem;font-weight:" . ($isActive ? '700' : '500') . ";color:{$labelColor};letter-spacing:.01em'>{$label}</span>
                    " . ($isActive ? "<div style='width:18px;height:3px;border-radius:2px;background:#1565C0;margin-top:1px'></div>" : '') . "
                </a>";
            }
        }

        $navHtml .= '</div>';

        $bottomNav = new TElement('div');
        $bottomNav->add($navHtml);
        $wrapper->add($bottomNav);

        return $wrapper;
    }

    /* ── buildShell (mantido para compatibilidade) ─────────────────────── */
    public static function buildShell(string $active, ?TElement $content = null): TElement
    {
        $wrapper = new TElement('div');
        $wrapper->add(self::buildNav($active));
        if ($content) {
            $wrapper->add($content);
        }
        return $wrapper;
    }

    /* ── countPendingDocuments ─────────────────────────────────────────── */
    public static function countPendingDocuments(int $motoristaId): int
    {
        $opened = false;
        try {
            if (!TTransaction::get()) {
                TTransaction::open('sample');
                $opened = true;
            }
            PortalMotoristaDocumento::ensureTables();
            Motorista::ensureTables();

            $pending = 0;
            if (!PortalMotoristaDocumento::findByContext($motoristaId, PortalMotoristaDocumento::TIPO_CNH)) {
                $pending++;
            }
            $repo     = new TRepository('Veiculo');
            $criteria = new TCriteria;
            $criteria->add(new TFilter('motorista_id', '=', $motoristaId));
            $veiculos = $repo->load($criteria, false) ?: [];

            foreach ($veiculos as $veiculo) {
                if (!PortalMotoristaDocumento::findByContext($motoristaId, PortalMotoristaDocumento::TIPO_CAVALO, (int) $veiculo->id)) {
                    $pending++;
                }
                $semi = (string) ($veiculo->antt_consulta_semi_reboque->placa ?? '');
                if ($semi !== '' && !PortalMotoristaDocumento::findByContext($motoristaId, PortalMotoristaDocumento::TIPO_SEMI_REBOQUE, (int) $veiculo->id)) {
                    $pending++;
                }
            }
            if ($opened) { TTransaction::close(); }
            return $pending;
        } catch (Throwable $e) {
            if ($opened) { TTransaction::rollback(); }
            return 0;
        }
    }
}
