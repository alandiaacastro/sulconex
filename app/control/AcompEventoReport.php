<?php

use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Dialog\TMessage;

class AcompEventoReport extends TPage
{
    public static function onGenerate($param)
    {
        try {
            $processoId = $param['processo_id'] ?? $param['key'] ?? $param['id'] ?? null;
            if (empty($processoId)) {
                $processoId = TSession::getValue('AcompEventoList_processo_id');
            }
            if (empty($processoId)) {
                throw new Exception('Processo nao informado para gerar relatorio.');
            }

            TTransaction::open('sample');
            AcompProcesso::ensureTables();

            $processo = new AcompProcesso($processoId);
            if (empty($processo->id)) {
                throw new Exception('Processo nao encontrado.');
            }

            $repo = new TRepository('AcompEvento');
            $criteria = new TCriteria;
            $criteria->add(new TFilter('processo_id', '=', $processoId));
            $criteria->setProperty('order', 'data_evento');
            $criteria->setProperty('direction', 'desc');
            $eventos = $repo->load($criteria, false) ?: [];

            $latest = !empty($eventos) ? $eventos[0] : null;
            $points = self::extractRoutePoints($eventos, $processo);

            TTransaction::close();

            $html = self::buildHtml($processo, $eventos, $latest, $points);

            if (!is_dir('tmp')) {
                mkdir('tmp', 0775, true);
            }

            $file = 'tmp/acomp_evento_report_' . $processoId . '_' . date('Ymd_His') . '.html';
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

    private static function buildHtml($processo, array $eventos, $latest, array $points): string
    {
        $statusAtual = trim((string) ($latest->status_texto ?? $processo->etapa ?? 'Sem status'));
        $ultimaAtualizacao = '-';
        if (!empty($latest->data_evento)) {
            $ts = strtotime((string) $latest->data_evento);
            $ultimaAtualizacao = $ts ? date('d/m/Y H:i', $ts) : (string) $latest->data_evento;
        }

        $previsao = self::fmtDate((string) ($processo->previsao_entrega ?? ''));

        $routeCoords = [];
        foreach ($points as $p) {
            $routeCoords[] = [(float) $p['lat'], (float) $p['lon']];
        }
        $current = !empty($points) ? $points[count($points) - 1] : ['lat' => -29.759997, 'lon' => -57.085609, 'label' => 'Sem ponto'];

        $rows = [];
        foreach ($eventos as $evt) {
            $rawDate = (string) ($evt->data_evento ?? '');
            $ts = strtotime($rawDate);
            $date = $ts ? date('d/m/Y H:i', $ts) : $rawDate;

            $rows[] = '<tr>'
                . '<td>' . htmlspecialchars($date ?: '-') . '</td>'
                . '<td>' . htmlspecialchars((string) ($evt->status_texto ?? '-')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($evt->localizacao ?? '-')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($evt->demora ?? '-')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($evt->franquia ?? '-')) . '</td>'
                . '</tr>';
        }

        if (empty($rows)) {
            $rows[] = '<tr><td colspan="5">Sem eventos cadastrados.</td></tr>';
        }

        $vars = [
            '{{NUMERO}}' => htmlspecialchars((string) ($processo->numero_processo ?? '-')),
            '{{CRT}}' => htmlspecialchars((string) ($processo->crt ?? '-')),
            '{{EXPORTADOR}}' => htmlspecialchars((string) ($processo->exportador ?? '-')),
            '{{IMPORTADOR}}' => htmlspecialchars((string) ($processo->importador ?? '-')),
            '{{STATUS}}' => htmlspecialchars($statusAtual ?: 'Sem status'),
            '{{PREVISAO}}' => htmlspecialchars($previsao ?: '-'),
            '{{ATUALIZACAO}}' => htmlspecialchars($ultimaAtualizacao),
            '{{ROWS}}' => implode('', $rows),
            '{{ROUTE_JSON}}' => json_encode($routeCoords),
            '{{CUR_LAT}}' => number_format((float) $current['lat'], 6, '.', ''),
            '{{CUR_LON}}' => number_format((float) $current['lon'], 6, '.', ''),
            '{{CUR_LABEL}}' => htmlspecialchars((string) ($current['label'] ?? 'Ponto atual')),
        ];

        $template = <<<'HTML'
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Relatorio de Rastreio</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
<style>
body{font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;color:#111;margin:0;padding:20px}
.wrap{max-width:1200px;margin:0 auto}
.card{background:#fff;border:1px solid #ddd;border-radius:10px;padding:14px;margin-bottom:12px}
.kpi{display:flex;gap:12px;flex-wrap:wrap}
.kpi .item{flex:1;min-width:220px;border-left:4px solid #0ea5e9;background:#fff;padding:12px;border-radius:8px;border:1px solid #e5e7eb}
.label{font-size:12px;color:#0c4a6e;text-transform:uppercase;font-weight:700}
.value{font-size:20px;font-weight:700;margin-top:4px}
#map{height:420px;border-radius:8px;border:1px solid #d1d5db}
table{width:100%;border-collapse:collapse;background:#fff}
th,td{border:1px solid #e5e7eb;padding:8px;text-align:left;font-size:13px}
th{background:#f9fafb}
@media print{.noprint{display:none}}
</style>
</head>
<body>
<div class="wrap">
<div class="card">
<h2 style="margin:0 0 8px">Relatorio de Rastreio do Processo {{NUMERO}} - CRT {{CRT}}</h2>
<div><b>Exportador:</b> {{EXPORTADOR}} | <b>Importador:</b> {{IMPORTADOR}}</div>
<div><b>Status Atual:</b> {{STATUS}}</div>
</div>

<div class="kpi noprint">
<div class="item"><div class="label">Previsao de Entrega</div><div class="value">{{PREVISAO}}</div></div>
<div class="item"><div class="label">Ultima Atualizacao</div><div class="value">{{ATUALIZACAO}}</div></div>
</div>

<div class="card"><div id="map"></div></div>

<div class="card">
<table>
<thead><tr><th>Data</th><th>Status</th><th>Localizacao</th><th>Evento</th><th>Franquia</th></tr></thead>
<tbody>{{ROWS}}</tbody>
</table>
</div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  var routeCoords = {{ROUTE_JSON}} || [];
  var map = L.map('map', {zoomControl:true, scrollWheelZoom:false});
  var seedBounds = routeCoords.length ? L.latLngBounds(routeCoords) : L.latLngBounds([[{{CUR_LAT}}, {{CUR_LON}}]]);
  map.fitBounds(seedBounds, {padding:[20,20]});

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution:'&copy; OpenStreetMap contributors',
    maxZoom:18
  }).addTo(map);

  function toOsrmPath(points){return points.map(function(p){return p[1]+','+p[0];}).join(';');}
  async function fetchRoadGeometry(points){
    if(!Array.isArray(points) || points.length < 2){return points || [];}
    var merged=[];
    for(var i=0;i<points.length-1;i++){
      var segment=[points[i], points[i+1]];
      var url='https://router.project-osrm.org/route/v1/driving/' + toOsrmPath(segment) + '?overview=full&geometries=geojson';
      var res=await fetch(url);
      if(!res.ok){throw new Error('OSRM');}
      var data=await res.json();
      if(!data || data.code!=='Ok' || !data.routes || !data.routes[0]){throw new Error('OSRM');}
      var coords=data.routes[0].geometry.coordinates.map(function(c){return [c[1],c[0]];});
      if(i>0 && coords.length){coords.shift();}
      merged=merged.concat(coords);
    }
    return merged;
  }

  function drawFallback(){
    if(routeCoords.length>1){
      L.polyline(routeCoords,{color:'#1f8b4c',weight:5,opacity:.9}).addTo(map);
    }
  }

  (async function(){
    try{
      var road=await fetchRoadGeometry(routeCoords);
      if(road.length>1){
        L.polyline(road,{color:'#1f8b4c',weight:5,opacity:.9}).addTo(map);
        map.fitBounds(L.latLngBounds(road), {padding:[20,20]});
      } else {
        drawFallback();
      }
    }catch(e){
      drawFallback();
    }
  })();

  var truckIcon=L.divIcon({
    html:'<div style="width:34px;height:34px;border-radius:50%;background:#1f8b4c;color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.35)"><i class="fas fa-truck" style="font-size:16px"></i></div>',
    className:'',
    iconSize:[34,34],
    iconAnchor:[17,17]
  });

  L.marker([{{CUR_LAT}}, {{CUR_LON}}], {icon:truckIcon}).addTo(map)
    .bindPopup('{{STATUS}} - {{CUR_LABEL}}')
    .openPopup();
})();
</script>
</body>
</html>
HTML;

        return strtr($template, $vars);
    }

    private static function extractRoutePoints(array $events, $processo): array
    {
        $sorted = $events;
        usort($sorted, function ($a, $b) {
            return strtotime((string) ($a->data_evento ?? '')) <=> strtotime((string) ($b->data_evento ?? ''));
        });

        $points = [];
        $lastKey = null;

        foreach ($sorted as $event) {
            $sourceParts = [
                trim((string) ($event->localizacao ?? '')),
                trim((string) ($event->demora ?? '')),
                trim((string) ($event->status_texto ?? '')),
            ];

            $source = trim(implode(' ', array_filter($sourceParts, function ($item) {
                return $item !== '';
            })));

            $resolved = self::resolveLocationPoint($source);
            if (!$resolved) {
                continue;
            }

            $ts = strtotime((string) ($event->data_evento ?? ''));
            $dateLabel = $ts ? date('d/m/Y H:i', $ts) : '-';
            $key = $resolved['label'];

            if ($lastKey === $key) {
                continue;
            }

            $points[] = [
                'lat' => $resolved['lat'],
                'lon' => $resolved['lon'],
                'label' => $resolved['label'],
                'date' => $dateLabel,
            ];
            $lastKey = $key;
        }

        if (empty($points)) {
            $fallback = self::resolveLocationPoint((string) ($processo->local_coleta ?? '')) ?: [
                'label' => 'Uruguaiana/RS',
                'lat' => -29.759997,
                'lon' => -57.085609,
            ];

            $points[] = [
                'lat' => $fallback['lat'],
                'lon' => $fallback['lon'],
                'label' => $fallback['label'],
                'date' => '-',
            ];
        }

        return $points;
    }

    private static function resolveLocationPoint(string $text): ?array
    {
        $t = self::normalizeText($text);

        try {
            $conn = TTransaction::get();
            if ($conn) {
                $stmt = $conn->query("SELECT nome, cidade, estado, pais, latitude, longitude FROM localizacao");
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

                foreach ($rows as $row) {
                    $nome = self::normalizeText((string) ($row['nome'] ?? ''));
                    $cidade = self::normalizeText((string) ($row['cidade'] ?? ''));

                    if (($nome !== '' && strpos($t, $nome) !== false) || ($cidade !== '' && strpos($t, $cidade) !== false)) {
                        $label = trim((string) ($row['nome'] ?: $row['cidade']));
                        return [
                            'label' => $label !== '' ? $label : 'Localizacao',
                            'lat' => (float) $row['latitude'],
                            'lon' => (float) $row['longitude'],
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // fallback
        }

        $map = [
            'multilog uruguaiana' => ['label' => 'Multilog Uruguaiana/RS', 'lat' => -29.781797, 'lon' => -57.070356],
            'uruguaiana' => ['label' => 'Uruguaiana/RS', 'lat' => -29.759997, 'lon' => -57.085609],
            'cotecar paso de los libres' => ['label' => 'Cotecar Paso de los Libres', 'lat' => -29.711972, 'lon' => -57.086012],
            'paso de los libres' => ['label' => 'Paso de los Libres/AR', 'lat' => -29.712511, 'lon' => -57.090645],
            'santo tome' => ['label' => 'Santo Tome/AR', 'lat' => -28.549389, 'lon' => -56.045914],
            'sao borja' => ['label' => 'Sao Borja/RS', 'lat' => -28.661668, 'lon' => -56.004440],
            'mendoza' => ['label' => 'Mendoza/AR', 'lat' => -32.889458, 'lon' => -68.845839],
            'cordoba' => ['label' => 'Cordoba/AR', 'lat' => -31.416668, 'lon' => -64.183334],
            'los andes' => ['label' => 'Los Andes/CL', 'lat' => -32.833692, 'lon' => -70.598273],
            'libertadores' => ['label' => 'Los Libertadores/CL', 'lat' => -32.823122, 'lon' => -70.090314],
            'aduana argentina' => ['label' => 'Aduana Argentina', 'lat' => -29.712511, 'lon' => -57.090645],
            'aduana brasil' => ['label' => 'Aduana Brasil', 'lat' => -29.711972, 'lon' => -57.086012],
            'santiago' => ['label' => 'Santiago/CL', 'lat' => -33.448890, 'lon' => -70.669265],
            'porto alegre' => ['label' => 'Porto Alegre/RS', 'lat' => -30.034647, 'lon' => -51.217658],
        ];

        foreach ($map as $needle => $point) {
            if (strpos($t, $needle) !== false) {
                return $point;
            }
        }

        return null;
    }

    private static function normalizeText(string $text): string
    {
        $normalized = strtolower(trim($text));
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if ($converted !== false) {
            $normalized = $converted;
        }

        return preg_replace('/\s+/', ' ', $normalized);
    }

    private static function fmtDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '-';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy');
        }

        return $value;
    }
}





