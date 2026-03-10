<?php

use Adianti\Control\TPage;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Container\TVBox;
// use Adianti\Widget\Util\TXMLBreadCrumb; // Descomente se usar

class PackingViewerPage extends TPage
{
    public function __construct($param)
    {
        parent::__construct($param);

        // **ATENÇÃO AOS COMENTÁRIOS ABAIXO**

        // CAMINHO PARA SEU SCRIPT pack_view.php
        // Este caminho é relativo à raiz do seu DOCUMENT_ROOT do servidor web,
        // ou relativo ao seu projeto se o servidor estiver configurado para isso.
        // Se o seu projeto Adianti está em http://localhost/sulconex81/
        // e você colocou pack_view.php em app/control/visualizacao/pack_view.php
        // então o src seria 'app/control/visualizacao/pack_view.php'
        // Isso PRESSUPÕE que seu servidor web permite executar diretamente este arquivo.
        $url_pack_view = 'app/control/visualizacao/pack_view.php'; // AJUSTE CONFORME SUA ESTRUTURA

        // Verifique se o arquivo realmente existe nesse caminho relativo à raiz do projeto.
        // O __DIR__ aqui se refere ao diretório de PackingViewerPage.php,
        // então precisamos de um caminho absoluto ou relativo à raiz do projeto.
        $base_path = dirname(__DIR__, 3); // Volta 3 níveis de app/control/visualizacao para a raiz do projeto
        
        // Este if é apenas para debug, para você verificar se o caminho está correto.
        // No ambiente de produção, remova ou use logs.
        if (!file_exists($base_path . '/' . $url_pack_view)) {
            parent::add(new TElement('div', "Arquivo pack_view.php não encontrado em: " . $base_path . '/' . $url_pack_view));
            return;
        }

        $iframe = new TElement('iframe');
        $iframe->src = $url_pack_view; // A URL que o navegador usará para carregar o script
        $iframe->id = 'packing_iframe';
        $iframe->style = "width: 100%; height: 85vh; border: none;";

        $vbox = new TVBox;
        $vbox->style = 'width: 100%;';
        // $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__)); // Descomente se usar
        $vbox->add($iframe);

        parent::add($vbox);
    }
}
?>