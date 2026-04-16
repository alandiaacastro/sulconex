<?php
require_once 'init.php';
new TSession;

function portalDriverCanDownloadFile(string $realFile): bool
{
    $portalMotoristaId = (int) TSession::getValue('portal_motorista_id');
    if ($portalMotoristaId <= 0)
    {
        return false;
    }

    $portalDirectory = realpath(__DIR__ . '/tmp/portal_motorista_documentos');
    if (!$portalDirectory)
    {
        return false;
    }

    $normalizedFile = str_replace('\\', '/', $realFile);
    $normalizedDirectory = rtrim(str_replace('\\', '/', $portalDirectory), '/');

    if (!str_starts_with($normalizedFile, $normalizedDirectory . '/'))
    {
        return false;
    }

    $opened = false;

    try
    {
        TTransaction::open('sample');
        $opened = true;
        PortalMotoristaDocumento::ensureTables();

        $stmt = TTransaction::get()->prepare('SELECT arquivo FROM portal_motorista_documento WHERE motorista_id = ?');
        $stmt->execute([$portalMotoristaId]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $storedFile = $row['arquivo'] ?? null;
            if ($storedFile && realpath($storedFile) === $realFile)
            {
                TTransaction::close();
                return true;
            }
        }

        TTransaction::close();
    }
    catch (Throwable $e)
    {
        if ($opened)
        {
            TTransaction::rollback();
        }
    }

    return false;
}

$systemLogged = (bool) TSession::getValue('logged');
$portalLogged = (bool) TSession::getValue('portal_motorista_logged');

if (isset($_GET['file']) && ($systemLogged || $portalLogged))
{
    $file = $_GET['file'];
    $realFile = realpath($file);

    if (!$realFile)
    {
        return;
    }

    $normalizedAppDir = rtrim(str_replace('\\', '/', realpath(__DIR__)), '/');
    $normalizedRealFile = str_replace('\\', '/', $realFile);

    // must be inside the application
    if (!str_starts_with($normalizedRealFile, $normalizedAppDir . '/'))
    {
        return;
    }

    if (!$systemLogged && !portalDriverCanDownloadFile($realFile))
    {
        return;
    }

    if (!file_exists($realFile))
    {
        return;
    }

    $relativeFile = ltrim(substr($normalizedRealFile, strlen($normalizedAppDir)), '/');

    // reserved path
    if (str_starts_with($relativeFile, 'files/system'))
    {
        return;
    }

    $info      = pathinfo($realFile);
    $extension = $info['extension'];
    
    $content_type_list = array();
    $content_type_list['txt']  = 'text/plain';
    $content_type_list['html'] = 'text/html';
    $content_type_list['csv']  = 'text/csv';
    $content_type_list['pdf']  = 'application/pdf';
    $content_type_list['rtf']  = 'application/rtf';
    $content_type_list['doc']  = 'application/msword';
    $content_type_list['docx'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    $content_type_list['xls']  = 'application/vnd.ms-excel';
    $content_type_list['xlsx'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    $content_type_list['ppt']  = 'application/vnd.ms-powerpoint';
    $content_type_list['pptx'] = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    $content_type_list['odt']  = 'application/vnd.oasis.opendocument.text';
    $content_type_list['ods']  = 'application/vnd.oasis.opendocument.spreadsheet';
    $content_type_list['jpeg'] = 'image/jpeg';
    $content_type_list['jpg']  = 'image/jpeg';
    $content_type_list['png']  = 'image/png';
    $content_type_list['gif']  = 'image/gif';
    $content_type_list['svg']  = 'image/svg+xml';
    $content_type_list['xml']  = 'application/xml';
    $content_type_list['zip']  = 'application/zip';
    $content_type_list['rar']  = 'application/x-rar-compressed';
    $content_type_list['bz']   = 'application/x-bzip';
    $content_type_list['bz2']  = 'application/x-bzip2';
    $content_type_list['tar']  = 'application/x-tar';
    
    if (file_exists($realFile) AND in_array(strtolower($extension), array_keys($content_type_list)))
    {
        $basename = !empty($_GET['basename']) ? basename((string) $_GET['basename']) : basename($realFile);
        $filesize = filesize($realFile); // get the filesize
        
        header("Pragma: public");
        header("Expires: 0"); // set expiration time
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-type: " . $content_type_list[strtolower($extension)] );
        header("Content-Length: {$filesize}");
        header("Content-disposition: inline; filename=\"{$basename}\"");
        header("Content-Transfer-Encoding: binary");
        
        // a readfile da problemas no internet explorer
        // melhor jogar direto o conteudo do arquivo na tela
        echo file_get_contents($realFile);
    }
}
