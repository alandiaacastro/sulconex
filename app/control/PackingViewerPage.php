<?php
use Adianti\Control\TPage;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage; // Adicionado para exibir mensagens de erro Adianti

class PackingViewerPage extends TPage
{
    public function __construct($param)
    {
        parent::__construct($param);
        error_log("PackingViewerPage: Construtor iniciado.");

        try
        {
            // URL para o iframe (relativo à raiz do seu site no navegador)
            // Ex: se seu projeto é localhost/sulconex81/ e o script está em sulconex81/scripts_publicos/pack_view.php
            $url_pack_view = 'scripts_publicos/pack_view.php';

            // Caminho do servidor para a verificação file_exists
            $server_file_path = '';
            if (defined('PATH')) { // PATH é definido no init.php do Adianti e aponta para a raiz do projeto
                $server_file_path = PATH . DIRECTORY_SEPARATOR . $url_pack_view;
            } else {
                $project_folder = 'sulconex81'; // Ou o nome correto da pasta do seu projeto
                $server_file_path = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $project_folder . DIRECTORY_SEPARATOR . $url_pack_view;
            }
            
            //error_log("PackingViewerPage: Verificando arquivo no caminho do servidor: " . $server_file_path);

            if (!file_exists($server_file_path)) {
                $error_msg = "Arquivo pack_view.php NÃƒO encontrado no caminho do servidor: " . htmlspecialchars($server_file_path);
                error_log("PackingViewerPage ERROR: " . strip_tags($error_msg));
                new TMessage('error', $error_msg);
                return; 
            }
            //error_log("PackingViewerPage: Arquivo pack_view.php ENCONTRADO.");

            $iframe = new TElement('iframe');
            // CORREÃ‡ÃƒO AQUI: Simplesmente use $url_pack_view.
            // O navegador interpretará isso como relativo ao domínio atual se não começar com http://,
            // ou relativo ao caminho da página atual se for apenas um nome de arquivo.
            // Para garantir que seja relativo à raiz do seu site, idealmente você teria a URL base.
            // Se sua aplicação Adianti está em http://localhost/sulconex81/,
            // e $url_pack_view é 'scripts_publicos/pack_view.php',
            // o navegador resolverá para http://localhost/sulconex81/scripts_publicos/pack_view.php.
            $iframe->src = $url_pack_view; 
            $iframe->id = 'packing_iframe';
            $iframe->style = "width: 100%; height: 85vh; border: none;";
            //error_log("PackingViewerPage: Iframe objeto criado com src: " . $iframe->src);

            $vbox = new TVBox;
            $vbox->style = 'width: 100%;';
            $vbox->add($iframe);
            //error_log("PackingViewerPage: VBox criada e iframe adicionado.");

            parent::add($vbox);
            //error_log("PackingViewerPage: VBox adicionada à página. Construtor quase finalizado.");
        }
        catch (Exception $e)
        {
            error_log("EXCEÃ‡ÃƒO em PackingViewerPage __construct: " . $e->getMessage() . ' - Stack: ' . $e->getTraceAsString());
            new TMessage('error', 'Exceção no construtor: ' . $e->getMessage());
            echo "Exceção capturada: " . $e->getMessage();
        }
        //error_log("PackingViewerPage: Construtor finalizado.");
    }

    public function onShow($param)
    {
        // Lógica de onShow se necessário
    }
}
?>