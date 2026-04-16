<?php
/**
 * Creator Export Trait
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
trait AdiantiCreatorExportTrait
{
    private $exported;
    
    /**
     * Se the object to be exported
     */
    private function setExportedObject($object)
    {
        $this->exported = $object;
    }
    
    /**
     * Export to CSV
     * @param $output Output file
     */
    private function exportToXLS($param)
    {
        try
        {
            $output = 'app/output/'.uniqid().'.xls';
            
            $widths = [];
            $titles = [];
            
            $columns = $this->exported->getColumns();
            $configuration = TSession::getValue(__CLASS__.'_datagrid_columns');
            
            // reorder columns according to the user selection (sessionvar)
            if (!empty($configuration))
            {
                $order = array_keys($configuration);
                usort($columns, function($a, $b) use ($order) {
                    return strcmp(array_search($a->getProperty('data-key'), $order), array_search($b->getProperty('data-key'), $order));
                });
            }
            
            foreach ($columns as $column)
            {
                $title       = $column->getProperty('data-original-title');
                $is_visible  = $column->getProperty('visible') !== 'false';
                
                if ($column->isPrintable() && $is_visible)
                {
                    $titles[] = $title;
                    $width    = 100;
                    
                    if (is_null($column->getWidth()))
                    {
                        $width = 100;
                    }
                    else if (strpos($column->getWidth(), '%') !== false)
                    {
                        $width = ((int) $column->getWidth()) * 10;
                    }
                    else if (is_numeric($column->getWidth()))
                    {
                        $width = $column->getWidth();
                    }
                    
                    $widths[] = $width;
                }
            }
            
            $table = new \TTableWriterXLS($widths);
            $table->addStyle('title',  'Helvetica', '10', 'B', '#ffffff', '#617FC3');
            $table->addStyle('data',   'Helvetica', '10', '',  '#000000', '#FFFFFF', 'LR');
            
            $table->addRow();
            
            foreach ($titles as $title)
            {
                $table->addCell($title, 'center', 'title');
            }
            
            $this->limit = 0;
            
            if ( (!file_exists($output) && is_writable(dirname($output))) || is_writable($output))
            {
                TTransaction::openFake($this->database);
                $objects = $this->loadObjectsFromFilters($param);
                if ($objects)
                {
                    foreach ($objects as $object)
                    {
                        $table->addRow();
                        foreach ($columns as $column)
                        {
                            $is_visible  = $column->getProperty('visible') !== 'false';
                            
                            if ($column->isPrintable() && $is_visible)
                            {
                                $column_name = $column->getName();
                                $content = TDataGrid::getColumnContent($object, $column_name);
                                $table->addCell($content, 'center', 'data');
                            }
                        }
                    }
                }
                $table->save($output);
                TTransaction::close();
            }
            else
            {
                throw new Exception(AdiantiCoreTranslator::translate('Permission denied') . ': ' . $output);
            }
            
            return $output;
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
        }
    }
    
    /**
     * Export to PDF
     * @param $output Output file
     */
    private function exportToPDF($param)
    {
        try
        {
            $output = 'app/output/'.uniqid().'.pdf';
            
            if ( (!file_exists($output) && is_writable(dirname($output))) || is_writable($output))
            {
                $this->limit = 0;
                $this->exported->prepareForPrinting();
                $this->onReload([]);
                
                // string with HTML contents
                $html = clone $this->exported;
                $contents = '<meta charset="UTF-8">';
                $contents .= '<style>' . file_get_contents('app/resources/styles-print-bundle.css') . '</style>';
                $contents .= $html->getContents();
                $contents = str_replace('download.php?file=', '', $contents); // Remove TImage downloader

                $options = new \Dompdf\Options();
                $options-> setChroot (getcwd());
                $options->set('isRemoteEnabled', true);
                
                // converts the HTML template into PDF
                $dompdf = new \Dompdf\Dompdf($options);
                $dompdf-> loadHtml ($contents);
                $dompdf-> setPaper ($this->exported->getPageSize() ?? 'A4', $this->exported->getPageOrientation() ?? 'portrait');
                $dompdf-> render ();
                
                // write and open file
                file_put_contents($output, $dompdf->output());
            }
            else
            {
                throw new Exception(AdiantiCoreTranslator::translate('Permission denied') . ': ' . $output);
            }
            
            return $output;
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
        }
    }
}
