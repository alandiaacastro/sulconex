<?php

use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Dialog\TMessage;

class AcompOrdemColetaReport extends TPage
{
    public static function onGenerate($param): void
    {
        try {
            $processoId = $param['processo_id'] ?? $param['key'] ?? $param['id'] ?? null;
            if (empty($processoId)) {
                throw new Exception('Processo nao informado para gerar ordem de coleta.');
            }

            TTransaction::open('sample');
            AcompProcesso::ensureTables();
            EstoqueManifesto::ensureTables();

            $processo = new AcompProcesso($processoId);
            if (empty($processo->id)) {
                throw new Exception('Processo nao encontrado.');
            }

            $stage = AcompProcesso::normalizeStageCode((string) ($processo->etapa ?? ''));
            if ($stage !== AcompProcesso::STAGE_COLETA) {
                $label = AcompProcesso::stageLabel($stage ?: '');
                throw new Exception(
                    'A Ordem de Coleta so pode ser gerada quando o status estiver em COLETA. ' .
                    'Status atual: ' . ($label ?: '-')
                );
            }

            $payload = self::loadPayload($processo);
            TTransaction::close();

            $html = self::buildHtml($payload);
            if (!is_dir('tmp')) {
                mkdir('tmp', 0775, true);
            }

            $file = 'tmp/ordem_coleta_' . $processo->id . '_' . date('Ymd_His') . '.html';
            file_put_contents($file, $html);
            TPage::openFile($file);
        } catch (Exception $e) {
            try {
                TTransaction::rollback();
            } catch (Exception $ee) {
            }
            new TMessage('error', $e->getMessage());
        }
    }

    private static function loadPayload(AcompProcesso $processo): array
    {
        $conn = TTransaction::get();
        $crt = trim((string) ($processo->crt ?? ''));

        $motorista = [
            'nome' => '-',
            'documento' => '-',
        ];
        $veiculo = [
            'cavalo' => '-',
            'cavalo_ano' => '-',
            'carreta' => '-',
            'carreta_ano' => '-',
            'tipo' => '-',
            'tara' => '-',
            'eixos' => '-',
            'marca_modelo' => '-',
        ];

        if ($crt !== '') {
            $stmt = $conn->prepare("
                SELECT
                    mo.nome AS motorista_nome,
                    mo.cpf AS motorista_cpf,
                    mo.rg_numero AS motorista_rg,
                    v.placa_trator,
                    v.ano_fabricacao,
                    v.modelo,
                    at.placa AS trator_placa_antt,
                    at.ano AS trator_ano_antt,
                    asr.placa AS semi_placa_antt,
                    asr.tipo AS semi_tipo,
                    asr.eixos AS semi_eixos,
                    asr.marca AS semi_marca_modelo
                FROM contrato c
                LEFT JOIN veiculo v ON v.id = c.veiculo_id
                LEFT JOIN motorista mo ON mo.id = v.motorista_id
                LEFT JOIN antt_consulta at ON at.id = v.antt_consulta_trator_id
                LEFT JOIN antt_consulta asr ON asr.id = v.antt_consulta_semi_reboque_id
                WHERE UPPER(COALESCE(c.conhecimento_numero, '')) = :crt
                   OR UPPER(COALESCE(c.danfeoumic, '')) = :crt
                ORDER BY c.id DESC
                LIMIT 1
            ");
            $stmt->execute([':crt' => strtoupper($crt)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $motorista['nome'] = self::orDash($row['motorista_nome'] ?? '');
                $motorista['documento'] = self::orDash(trim((string) ($row['motorista_rg'] ?: $row['motorista_cpf'])));

                $veiculo['cavalo'] = self::orDash($row['placa_trator'] ?? '');
                if ($veiculo['cavalo'] === '-' && !empty($row['trator_placa_antt'])) {
                    $veiculo['cavalo'] = self::orDash($row['trator_placa_antt']);
                }

                $veiculo['cavalo_ano'] = self::orDash($row['ano_fabricacao'] ?: $row['trator_ano_antt']);
                $veiculo['carreta'] = self::orDash($row['semi_placa_antt'] ?? '');
                $veiculo['carreta_ano'] = self::orDash($row['ano_fabricacao'] ?: $row['trator_ano_antt']);
                $veiculo['tipo'] = self::orDash($row['semi_tipo'] ?? '');
                $veiculo['eixos'] = self::orDash($row['semi_eixos'] ?? '');
                $veiculo['marca_modelo'] = self::orDash($row['semi_marca_modelo'] ?: $row['modelo']);
            }
        }

        $danfes = '-';
        if ($crt !== '') {
            $crtNorm = EstoqueManifesto::normalizeCode($crt);
            $stmtDan = $conn->prepare("
                SELECT GROUP_CONCAT(d.danfe_codigo, ', ') AS danfes
                FROM estoque_manifesto m
                INNER JOIN estoque_manifesto_danfe d ON d.manifesto_id = m.id
                WHERE m.crt_normalizado = :crt
            ");
            $stmtDan->execute([':crt' => $crtNorm]);
            $danRes = $stmtDan->fetch(PDO::FETCH_ASSOC);
            if (!empty($danRes['danfes'])) {
                $danfes = $danRes['danfes'];
            }
        }

        return [
            'motorista_nome' => self::orDash($motorista['nome']),
            'motorista_doc' => self::orDash($motorista['documento']),
            'veiculo_cavalo' => self::orDash($veiculo['cavalo']),
            'veiculo_cavalo_ano' => self::orDash($veiculo['cavalo_ano']),
            'veiculo_carreta' => self::orDash($veiculo['carreta']),
            'veiculo_carreta_ano' => self::orDash($veiculo['carreta_ano']),
            'veiculo_tipo' => self::orDash($veiculo['tipo']),
            'veiculo_tara' => self::orDash($veiculo['tara']),
            'veiculo_eixos' => self::orDash($veiculo['eixos']),
            'veiculo_marca_modelo' => self::orDash($veiculo['marca_modelo']),
            'pedido_ordem' => self::orDash($processo->numero_processo ?? ''),
            'danfes' => self::orDash($danfes),
            'proforma' => self::orDash($processo->fatura ?? ''),
            'exportador' => self::orDash($processo->exportador ?? ''),
            'importador' => self::orDash($processo->importador ?? ''),
            'produto' => self::orDash($processo->produto ?? ''),
            'crt' => self::orDash($processo->crt ?? ''),
        ];
    }

    private static function buildHtml(array $p): string
    {
        $esc = function ($v) {
            return htmlspecialchars((string) $v);
        };

        return '<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ordem de Coleta</title>
<style>
body{margin:0;background:#efefef;font-family:Arial,Helvetica,sans-serif;color:#111;padding:16px}
.sheet{max-width:920px;margin:0 auto}
.box{border:2px solid #222;background:#f4f4f4;margin-bottom:22px}
.box h3{margin:0;background:#ddd;padding:6px 10px;font-size:34px;font-weight:700;border-bottom:2px solid #222}
.content{padding:10px 12px}
.line{margin:9px 0;font-size:35px;line-height:1.25}
.label{font-weight:700;text-decoration:underline}
.value{color:#2f4c7a}
table{width:100%;border-collapse:collapse}
td{border:2px solid #6f6f6f;padding:6px 10px;font-size:35px;vertical-align:top}
.half{width:50%}
@media print{body{padding:0;background:#fff}.sheet{max-width:none}}
</style>
</head>
<body>
<div class="sheet">
  <section class="box">
    <h3>Dados do motorista / Datos del chofer</h3>
    <div class="content">
      <div class="line"><span class="label">Nome/Sobrenome - Nombre/Apellido:</span> <span class="value">' . $esc($p['motorista_nome']) . '</span></div>
      <div class="line"><span class="label">RG / C.I. / CPF /DNI:</span> <span class="value">' . $esc($p['motorista_doc']) . '</span></div>
    </div>
  </section>

  <section class="box">
    <h3 style="text-align:center">Dados do Veiculo / Datos del Vehiculo</h3>
    <table>
      <tr>
        <td class="half"><span class="label">Cavalo / Tractor:</span> <span class="value">' . $esc($p['veiculo_cavalo']) . '</span></td>
        <td class="half"><span class="label">Ano / Año:</span> <span class="value">' . $esc($p['veiculo_cavalo_ano']) . '</span></td>
      </tr>
      <tr>
        <td><span class="label">Carreta / Semi - Reboque:</span> <span class="value">' . $esc($p['veiculo_carreta']) . '</span></td>
        <td><span class="label">Ano / Año:</span> <span class="value">' . $esc($p['veiculo_carreta_ano']) . '</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="label">Veiculo / Vehículo:</span> <span class="value">' . $esc($p['veiculo_tipo']) . '</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="label">Tara :</span> <span class="value">' . $esc($p['veiculo_tara']) . '</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="label">Quantidade de eixos / Cantidad de Ejes:</span> <span class="value">' . $esc($p['veiculo_eixos']) . '</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="label">Marca/modelo :</span> <span class="value">' . $esc($p['veiculo_marca_modelo']) . '</span></td>
      </tr>
    </table>
  </section>

  <section class="box">
    <h3>Dados da carga / Datos de la Carga :</h3>
    <div class="content">
      <div class="line"><span class="label">Pedido / Nº Ordem :</span> <span class="value">' . $esc($p['pedido_ordem']) . '</span></div>
      <div class="line"><span class="label">Nº Danfe:</span> <span class="value">' . $esc($p['danfes']) . '</span></div>
      <div class="line"><span class="label">Proforma / Invoice / Factura :</span> <span class="value">' . $esc($p['proforma']) . '</span></div>
      <div class="line"><span class="label">Exportador:</span> <span class="value">' . $esc($p['exportador']) . '</span></div>
      <div class="line"><span class="label">Importador:</span> <span class="value">' . $esc($p['importador']) . '</span></div>
      <div class="line"><span class="label">Mercadoria / Producto:</span> <span class="value">' . $esc($p['produto']) . '</span></div>
      <div class="line"><span class="label">CRT :</span> <span class="value">' . $esc($p['crt']) . '</span></div>
    </div>
  </section>
</div>
</body>
</html>';
    }

    private static function orDash($value): string
    {
        $v = trim((string) $value);
        return $v === '' ? '-' : $v;
    }
}
