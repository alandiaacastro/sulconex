<?php
/**
 * Creator Document traits
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
trait AdiantiCreatorDocumentTraits
{
    use AdiantiCreatorPresenterTrait;
    
    /**
     * Converts HTML to PDF
     */
    private static function convertHTMLToPDF($html, $page_size, $page_orientation)
    {
        $output = 'app/output/'.uniqid().'.pdf';
        
        if ( (!file_exists($output) && is_writable(dirname($output))) || is_writable($output))
        {
            $contents = '<style>' . file_get_contents('app/resources/styles-print-slim.css') . '</style>';
            $contents .= $html;
            $contents = str_replace('download.php?file=', '', $contents); // Remove TImage downloader
            $options = new \Dompdf\Options();
            $options->setChroot(getcwd());
            $options->set('isRemoteEnabled', true);
            
            // converts the HTML template into PDF
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($contents);
            $dompdf->setPaper($page_size, $page_orientation);
            $dompdf->render();
            
            // write and open file
            file_put_contents($output, $dompdf->output());
        }
        else
        {
            throw new Exception(AdiantiCoreTranslator::translate('Permission denied') . ': ' . $output);
        }
        
        return $output;
    }
}
