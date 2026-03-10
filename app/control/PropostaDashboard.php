<?php
use Adianti\Control\TPage;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Util\TBreadCrumb;
use Adianti\Database\TTransaction;

class PropostaDashboard extends TPage
{
    private static $database = 'sample';

    public function __construct($param)
    {
        parent::__construct();

        $box = new TVBox;
        $box->style = 'width: 100%';
        $box->add(TBreadCrumb::create(["Comercial", "Dashboard Propostas"]));

        // Pega últimos 10 resultados
        TTransaction::open(self::$database);
        $items = Proposta::where('id', '>', 0)->orderBy('id desc')->limit(10)->load();
        TTransaction::close();

        $labels = [];
        $margens = [];

        foreach (array_reverse($items) as $p) {
            $labels[] = $p->Cotacao_ID ?: $p->id;
            $margens[] = (float) $p->margem_percentual;
        }

        $labels_js = json_encode($labels);
        $margens_js = json_encode($margens);

        // Chart.js via CDN (sem TScript)
        $html = "
        <div class='card' style='padding:15px;border-radius:10px'>
            <h3 style='margin-top:0'>Margem % (últimas 10 propostas)</h3>
            <canvas id='chartMargem' height='100'></canvas>
        </div>

        <script src='https://cdn.jsdelivr.net/npm/chart.js'></script>
        <script>
            (function(){
                var ctx = document.getElementById('chartMargem');
                if(!ctx) return;
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: {$labels_js},
                        datasets: [{
                            label: 'Margem (%)',
                            data: {$margens_js},
                            borderWidth: 2,
                            fill: false,
                            tension: 0.2
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            })();
        </script>";

        $box->add($html);
        parent::add($box);
    }
}