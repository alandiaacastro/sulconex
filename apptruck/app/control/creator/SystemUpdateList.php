<?php
use Jfcherng\Diff\Differ;
use Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Factory\RendererFactory;
use Jfcherng\Diff\Renderer\RendererConstant;

/**
 * SystemUpdateList
 *
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
class SystemUpdateList extends TPage
{
    protected $list;
    protected $form;
    
    private static $block_folders = ['app/lib/creator/', 'app/templates/adminbs5/', 'lib/', 'vendor/']; // update in block
    private static $prechecks     = ['app/lib/creator/', 'app/templates/adminbs5/', 'lib/', 'app/control/creator/SystemUpdateList.php', 'app/lib/include/admin.css', 'app/lib/include/admin.js', 'app/lib/include/application.js', 'app/lib/util/AdiantiRouteTranslator.php', 'app/lib/util/AdiantiTemplateTranslator.php', 'app/lib/menu/AdiantiMenuBuilder.php', 'app/lib/menu/AdiantiNavBarParser.php', 'app/service/auth/ApplicationAuthenticationRestService.php', 'app/service/auth/ApplicationAuthenticationService.php', 'app/service/auth/RecaptchaServices.php', 'app/service/system/AdiantiFileHashGeneratorService.php', 'app/service/system/SystemDatabaseInformationService.php', 'app/service/system/SystemDocumentDownloaderService.php', 'app/service/system/SystemDocumentUploaderService.php', 'app/service/system/SystemPermissionService.php', 'app/service/system/SystemProgramService.php', 'app/service/system/SystemScheduleService.php', 'app/config/framework_hashes.php']; // precheck if any update
    private static $folder_map    = ['app/lib/creator/'        => 'app/lib/creator/VERSION',
                                     'app/templates/adminbs5/' => 'app/templates/adminbs5/VERSION',
                                     'lib/'                    => 'lib/VERSION',
                                     'vendor/'                 => 'composer.lock'];
    /**
     * Page constructor
     */
    public function __construct($param)
    {
        parent::__construct();
        
        $this->form = new BootstrapFormBuilder;
        $this->form->setFormTitle(_t('File tree'));
        
        // creates a DataGrid
        $this->list = new TCheckList('order_list');
        $this->list->setProperty('class', 'table table-striped table-hover tchecklist vertical-middle');
        
        $this->form->addFields([$this->list]);
        $btn = $this->form->addAction(_t('Apply updates'), new TAction([$this, 'onSave'], ['file' => $param['file'] ?? '']), 'fa:check');
        $btn->{'class'} = 'btn btn-primary';
        
        $col_path = $this->list->addColumn('path', 'Path', 'left',  '99%');
        $col_diff = $this->list->addColumn('path', 'Diff', 'center', null);
        
        $col_path->setTransformer( function($value, $object, $row, $cell) {
            $is_dir  = (substr($value,-1) == '/');
            $slashes = substr_count($value, '/');
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', ($is_dir ? $slashes -1 : $slashes));
            
            if ($is_dir)
            {
                $pure_value = $value;
                $hint = _t('If you select a directory, all its files will automatically be updated, even if they are not selected');
                $value = "<i class=\"fa-regular fa-folder-open\"></i> <b>{$value}</b> &nbsp;&nbsp; <i title=\"{$hint}\" class=\"fa-solid fa-circle-info blue\"></i>";
                
                if ($pure_value == 'app/lib/creator/')
                {
                    $value .= '&nbsp;&nbsp;[Creator Libs] <b style="color:var(--bs-primary)">IMPORTANT UPDATE</b> <i class="fa-solid fa-certificate orange"></i>';
                }
                else if ($pure_value == 'app/templates/adminbs5/')
                {
                    $value .= '&nbsp;&nbsp;[Template] <b style="color:var(--bs-primary)">IMPORTANT UPDATE</b> <i class="fa-solid fa-certificate orange"></i>';
                }
                else if ($pure_value == 'lib/')
                {
                    $value .= '&nbsp;&nbsp;[Adianti Framework] <b style="color:var(--bs-primary)">IMPORTANT UPDATE</b> <i class="fa-solid fa-certificate orange"></i>';
                }
                else if ($pure_value == 'vendor/')
                {
                    $value .= '&nbsp;&nbsp;['._t('Third party packages').'] <i class="fa-solid fa-certificate orange"></i>';
                }
            }
            else if (is_file($value))
            {
                $value = basename($value);
                $value = "<i class=\"fa-regular fa-file\"></i> {$value} ";
            }
            else
            {
                $value = "<i class=\"fa-regular fa-file\"></i> {$value} ";
            }
            return $indent . $value;
        });
        
        $col_diff->setTransformer( function($value, $object, $row, $cell) use ($param) {
            if ( (substr($value,-1) !== '/' && substr($value,-3) !== '.db') || in_array($value, self::$block_folders) )
            {
                $cell->href = '#';
                $button = new TActionLink('Diff', new TAction([$this, 'showDiff'], ['path'=>$value, 'patch' => $param['file'] ?? '']), null, null, null, 'fa:code-compare green');
                $button->class = 'btn btn-sm btn-default';
                $button->style = 'padding-top:1px;padding-bottom:1px';
                return $button;
            }
        });
        
        $input_search = new TEntry('search');
        $input_search->placeholder = _t('Search');
        $input_search->setSize('100%');
        
        $this->list->enableSearch($input_search, 'path');
        
        $hbox = new THBox;
        $hbox->add( $input_search );//->style = 'float:right;width:30%;';
        
        $alert = new TAlert('info', _t('Only apply updates to a development server, never update directly to production. Always make backups before updates and test them thoroughly before promoting them to production') . '. ' . 
                                       _t('If the application does not load after a Framework update, it will be necessary to download it completely again'));
        $alert->{'style'} = 'margin-bottom: 10px';
        
        $panel = new TPanelGroup($hbox);
        $panel->addHeaderActionLink(_t('Reload'), new TAction([$this, 'fetchProject'], ['static' => '1']), 'fa:refresh');
        $panel->{'class'} = 'card expand-title';
        $panel->add($alert);
        
        $panel2 = new TPanelGroup(_t('Problems found'));
        $panel2->add($this->checkDuplicates());
        
        $panel3 = new TPanelGroup(_t('Last backups from applied patches'));
        $panel3->add($this->listLastBackups());
        
        $vbox = TVBox::pack($panel2, $panel3);
        $vbox->style = 'width:100%';
        
        $internal_hbox = new THBox;
        $internal_hbox->{'style'} = 'width:100%';
        $internal_hbox->add($this->form)->{'style'} .= ';width:60%;vertical-align:top';
        $internal_hbox->add($vbox)->{'style'} .= ';width:calc(40% - 5px);vertical-align:top';
        $panel->add($internal_hbox);
        
        // vertical box container
        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($panel);
        
        parent::add($container);
    }
    
    /**
     * Fetch project from Creator Studio server
     */
    public static function fetchProject()
    {
        try
        {
            if (TSession::getValue('login') !== 'admin')
            {
                throw new Exception(_t('Permission denied'));
            }
            
            if (!file_exists('patches') || !is_writable('patches'))
            {
                throw new Exception(_t('Permission denied') . ': patches/');
            }
            
            $ini = AdiantiApplicationConfig::get();
            $location = $ini['general']['creator_url'] . '/fetch-project';
            
            // payload
            $body = ['token' => $ini['general']['token'],
                     'language' => ApplicationTranslator::getLanguage(),
                     'version'  => trim(file_get_contents('lib/VERSION')),
                     'vendor'   => base64_encode(trim(file_get_contents('composer.lock')))];
            
            // service key
            $key = 'ad882df215cf8a7f5297c009ffb2eb41e18749524017c7eade289fbcbe2b585e';
            
            // make request
            $ret = AdiantiHttpClient::request($location, 'POST', $body, 'Basic ' . $key, [], true);
            
            if (!empty($ret['name']) && !empty($ret['file']))
            {
                $put = file_put_contents('patches/'.$ret['name'], base64_decode($ret['file']));
                if ($put === false)
                {
                    throw new Exception(_t('Permission denied') . ': patches/');
                }
                
                AdiantiCoreApplication::loadPage('SystemUpdateList', 'onLoad', ['file' => $ret['name']]);
            }
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
        }
    }
    
    /**
     * Check any duplicated class in the file tree
     */
    private function checkDuplicates()
    {
        $folders = array();
        $folders[] = 'app/model';
        $folders[] = 'app/service';
        $folders[] = 'app/control';
        $folders[] = 'app/helpers';
        $folders[] = 'app/view';
        $folders[] = 'app/lib';
        
        $list_group = new TElement('div');
        $list_group->{'class'} = "list-group";
        
        try
        {
            $list = [];
            foreach ($folders as $folder)
            {
                if (file_exists($folder))
                {
                    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder),
                                                           RecursiveIteratorIterator::SELF_FIRST) as $entry)
                    {
                        if (is_file($entry))
                        {
                            if (substr($entry, -4) == '.php')
                            {
                                if (!isset($list[ basename($entry) ]))
                                {
                                    $list[ basename($entry) ] = [ (string) $entry ];
                                }
                                else
                                {
                                    $list[ basename($entry) ][] = (string) $entry;
                                }
                            }
                        }
                    }
                }
            }
            
            if ($list)
            {
                foreach ($list as $key => $values)
                {
                    if (count($values) > 1)
                    {
                        $group_item = new TElement('span');
                        $group_item->href="#";
                        $group_item->class="list-group-item";
                        
                        $wrapper = new TElement('div');
                        $wrapper->class = 'd-flex w-100 justify-content-between';
                        
                        $title = new TElement('h5');
                        $title->class='mb-1';
                        $title->add($key);
                        
                        $small = new TElement('small');
                        $small->class = 'badge bg-danger';
                        $small->style = 'height: fit-content';
                        $small->add(_t('Duplicated'));
                        
                        $group_item->add($wrapper);
                        
                        $wrapper->add($title);
                        $wrapper->add($small);
                        
                        $list_group->add($group_item);
                        
                        foreach ($values as $value)
                        {
                            $content = new TElement('p');
                            $content->class = 'mb-1';
                            $content->add('<i class="fa-regular fa-circle-right orange"></i> ' . $value);
                            $group_item->add($content);
                        }
                    }
                }
                
                $list_group->add(new TAlert('warning', _t('Duplicate files cause conflicts in the class loader. You should not repeat the file name and class name')));
            }
            else
            {
                $small = new TElement('small');
                $small->class = 'badge bg-info';
                $small->style = 'height: fit-content';
                $small->add(_t('No duplicates found'));
                $list_group->add($small);
            }
        }
        catch(Exception $e)
        {
            new TMessage('error', $e->getMessage());
        }
        
        return $list_group;
    }
    
    /**
     * List last local backups
     */
    private function listLastBackups()
    {
        if (!file_exists('backups') || !is_readable('backups'))
        {
            return;
        }
        $list_group = new TElement('div');
        $list_group->{'class'} = "list-group";
        
        $list = [];
        foreach (new DirectoryIterator('backups') as $file)
        {
            if($file->isDot() || substr($file,0,1) == '.')
            {
                continue;
            }
            $list[] = (string) $file->getFilename();
        }
        
        arsort($list);
        if ($list)
        {
            $list = array_slice($list, 0, 10);
            
            foreach ($list as $file)
            {
                $group_item = new TElement('span');
                $group_item->href="#";
                $group_item->class="list-group-item";
                
                $wrapper = new TElement('div');
                $wrapper->class = 'd-flex w-100 justify-content-between';
                
                $title = new TElement('h5');
                $title->class='mb-1';
                $title->add('<i class="fa-regular fa-file-zipper blue" style="font-size:1rem"></i> ' . $file);
                
                $small = new TElement('a');
                $small->class = 'badge bg-primary';
                $small->style = 'height: fit-content;cursor:pointer';
                $small->href  = "download.php?file=backups/{$file}";
                $small->add(_t('Download'));
                
                $group_item->add($wrapper);
                
                $wrapper->add($title);
                $wrapper->add($small);
                
                $list_group->add($group_item);
            }
        }
        
        return $list_group;
    }
    
    
    /**
     * Load the list from remote Zip
     */
    public function onLoad($param)
    {
        try
        {
            $path = 'patches/'.$param['file'];
            
            if (file_exists($path))
            {
                $zip = new ZipArchive($path);
                
                $opened = $zip->open($path);
                if ($opened === true)
                {
                    $list = [];
                    $diffs = [];
                    
                    // clear from Zip folders from unchanged libs
                    self::clearLibs($zip);
                    
                    for ( $i = 0; $i < $zip->numFiles; $i++ )
                    {
                        $stat = $zip->statIndex( $i ); 
                        if ($stat)
                        {
                            $content = $zip->getFromIndex( $i );
                            
                            $relative = str_replace('project/', '', $stat['name']);
                            
                            $is_block_folder = false;
                            
                            if (!empty($relative))
                            {
                                foreach (self::$block_folders as $block_folder)
                                {
                                    if (substr($relative, 0, strlen($block_folder)) == $block_folder)
                                    {
                                        if (!in_array($block_folder, $list))
                                        {
                                            $list[] = $block_folder;
                                        }
                                        
                                        $is_block_folder = true;
                                    }
                                }
                                
                                if (!$is_block_folder)
                                {
                                    if (is_dir($relative))
                                    {
                                        $list[] = $relative;
                                    }
                                    else if (!file_exists($relative) || ( file_exists($relative) && md5($content) !== md5(@file_get_contents($relative))))
                                    {
                                        $list[] = $relative;
                                        $list[] = dirname($relative) . '/';
                                        $diffs[] = $relative;
                                    }
                                }
                            }
                        }
                    }
                    
                    $list = array_unique($list);
                    asort($list);
                    
                    if ($list)
                    {
                        foreach ($list as $entry)
                        {
                            $obj = new stdClass;
                            $obj->path = $entry;
                            
                            if (is_dir($entry))
                            {
                                // if directory is within the file diff list
                                if (self::listContains($diffs, $entry))
                                {
                                    $this->list->addItem($obj);
                                }
                                else if ( in_array($entry, self::$block_folders))
                                {
                                    $this->list->addItem($obj);
                                }
                            }
                            else
                            {
                                $this->list->addItem($obj);
                            }
                        }
                    }
                    
                    if (!empty(array_intersect(self::$prechecks, $list)))
                    {
                        $precheck = array_merge(['buildid'], array_intersect(self::$prechecks, $list));
                        $this->list->setValue($precheck);
                        new TMessage('warning', _t('There are important updates in the following folders that have been pre-selected for update') . ': <br><b>'. implode('<br>', array_intersect(self::$prechecks, $list)) .'</b>');
                    }
                    else
                    {
                        $this->list->setValue(['buildid']);
                    }
                    $zip->close();
                }
                else
                {
                    throw new Exception(_t('Permission denied') . ': '. $path);
                }
            }
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
        }
    }
    
    /**
     * Check if the list contains the string in partial
     */
    private static function listContains($list, $string)
    {
        foreach ($list as $element)
        {
            if (substr($element,0,strlen($string)) == $string)
            {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Show file diff
     */
    public static function showDiff($param)
    {
        try
        {
            foreach (self::$folder_map as $folder => $diff_file)
            {
                if ($param['path'] == $folder)
                {
                    $param['path'] = $diff_file;
                }
            }
            
            $path = $param['path'];
            $patch = 'patches/'.$param['patch'];
            $old = '';
            if (file_exists($path))
            {
                $old = file_get_contents($path);
            }
            
            $zip = new ZipArchive;
            $opened = $zip->open($patch);
            if ($opened !== true)
            {
                new TMessage('error', _t('Permission denied'));
                return;
            }
            
            $new = $zip->getFromName('project/'.$path);
            $zip->close();
            
            $rendererName = 'SideBySide';
            
            // the Diff class options
            $differOptions = [
                'context' => Differ::CONTEXT_ALL,
                'ignoreCase' => false,
                'ignoreLineEnding' => false,
                'ignoreWhitespace' => false,
                'lengthLimit' => 2000,
            ];
            
            // the renderer class options
            $rendererOptions = [
                'detailLevel' => 'word',
                'language' => ['eng',
                    [
                        'old_version' => _t('Local version (in use)'),
                        'new_version' => _t('Updated version (new)'),
                    ]],
                'lineNumbers' => true,
                'separateBlock' => true,
                'showHeader' => true,
                'spacesToNbsp' => true,
                'tabSize' => 4,
                'mergeThreshold' => 0.8,
                'cliColorization' => RendererConstant::CLI_COLOR_AUTO,
                'outputTagAsString' => false,
                'jsonEncodeFlags' => \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
                'wordGlues' => [' ', '-'],
                'resultForIdenticals' => null,
                'wrapperClasses' => ['diff-wrapper'],
            ];

            // custom usage
            $differ = new Differ(explode("\n", $old), explode("\n", $new), $differOptions);
            $renderer = RendererFactory::make($rendererName, $rendererOptions); // or your own renderer object
            $result = $renderer->render($differ);
            
            $window = TWindow::create($path, 0.9, 0.9);
            $window->add($result);
            $window->show();
            
            TStyle::importFromFile('app/resources/diff.html');
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
        }
    }
    
    /**
     * Create a backup from the file list
     */
    private function backup($list, $include_backup)
    {
        $ini = AdiantiApplicationConfig::get();
        if (!file_exists('backups') || !is_writable('backups'))
        {
            throw new Exception(_t('Permission denied') . ': backups/');
        }
        
        $date = DateTime::createFromFormat('0.u00 U', microtime());
        $date->setTimeZone(new DateTimeZone($ini['general']['timezone']));
        
        $backup = 'backups/backup-'.$date->format('Y-m-d_His_u').'.zip';
        $zip = new ZipArchive;
        $res = $zip->open($backup, ZipArchive::CREATE);
        $rootPath = realpath('.');
        
        // include additional directories (lib, vendor, etc..)
        if ($include_backup)
        {
            foreach ($include_backup as $include_folder)
            {
                $files = new RecursiveIteratorIterator (new RecursiveDirectoryIterator($include_folder), RecursiveIteratorIterator::LEAVES_ONLY);
                foreach ($files as $name => $file)
                {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($rootPath) + 1);
                    
                    if (is_file($filePath))
                    {
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            }
        }
        
        if ($res === TRUE)
        {
            foreach ($list as $entry)
            {
                if (file_exists($entry) && is_file($entry))
                {
                    $zip->addFromString($entry, @file_get_contents($entry));
                }
            }
            $zip->close();
        }
        else
        {
            throw new Exception(_t('Permission denied') . ': backups/');
        }
    }
    
    /**
     * Apply patch from selected items
     */
    public function onSave($param)
    {
        try
        {
            // reload list
            $this->onLoad($param);
            
            if (!file_exists('backups') || !is_writable('backups'))
            {
                throw new Exception(_t('There must be a /backups folder with write permissions to apply the patch'));
            }
            
            $data = $this->form->getData(); // optional parameter: active record class
            $path = 'patches/'.$param['file'];
            $errors = [];
            
            if (file_exists($path))
            {
                $zip = new ZipArchive;
                $opened = $zip->open($path);
                
                if ($opened === TRUE)
                {
                    $final_list = [];
                    for ( $i = 0; $i < $zip->numFiles; $i++ )
                    {
                        $stat = $zip->statIndex( $i ); 
                        $content = $zip->getFromIndex( $i );
                        
                        $zip_entry = str_replace('project/', '', $stat['name']);
                        
                        // está na lista de seleção, extrai
                        if (in_array($zip_entry, $data->order_list))
                        {
                            if (substr($zip_entry,-1) !== '/')
                            {
                                $final_list[] = $zip_entry;
                            }
                        }
                        else // not in selected list, but test if the directory is
                        {
                            // iterate the selected list
                            foreach ($data->order_list as $selected)
                            {
                                // test if the path is within the selected list.
                                if (substr($zip_entry, 0, strlen($selected)) == $selected)
                                {
                                    // in these cases, include all, because the folders will be deleted firstly
                                    if (in_array($selected, self::$block_folders))
                                    {
                                        $final_list[] = $zip_entry;
                                    }
                                    // just add the diff ones
                                    else if (substr($zip_entry,-1) !== '/' && (!file_exists($zip_entry) || ( file_exists($zip_entry) && md5($content) !== md5(file_get_contents($zip_entry)))))
                                    {
                                        $final_list[] = $zip_entry;
                                    }
                                }
                            }
                        }
                    }
                    
                    // extra files
                    if (in_array('vendor/', $data->order_list))
                    {
                        $final_list[] = 'composer.json';
                        $final_list[] = 'composer.lock';
                    }
                    $final_list = array_unique($final_list);
                    
                    if (!empty($final_list))
                    {
                        $include_backup = [];
                        
                        // include block folders in backup
                        foreach (self::$block_folders as $block_folder)
                        {
                            if (in_array($block_folder, $data->order_list))
                            {
                                $include_backup[] = $block_folder;
                            }
                        }
                        
                        // backup
                        $this->backup($final_list, $include_backup);
                        
                        // remove block folders
                        foreach (self::$block_folders as $block_folder)
                        {
                            if (in_array($block_folder, $data->order_list))
                            {
                                if (!is_writable($block_folder))
                                {
                                    throw new Exception(_t('Permission denied') . ': ' . $block_folder);
                                }
                                self::recursiveDelete($block_folder);
                            }
                        }
                        
                        foreach ($final_list as $file)
                        {
                            if (!file_exists(dirname($file)))
                            {
                                if (!@mkdir(dirname($file), 0755, true))
                                {
                                    $errors[] = '<b>'._t('Permission denied') . '</b>:  '. dirname($file);
                                }
                            }
                            
                            if (substr($file,-1) !== '/') // vendor and framework subdirectory entries
                            {
                                if ( (file_exists($file) && !is_writable($file)) || (!is_writable(dirname($file))) )
                                {
                                    $errors[] = '<b>'._t('Permission denied') . '</b>:  '. $file;
                                }
                                else
                                {
                                    file_put_contents($file, $zip->getFromName('project/'.$file));
                                }
                            }
                        }
                        
                        if ($errors)
                        {
                            new TMessage('error', implode('<br>', $errors));
                        }
                        else
                        {
                            SystemPermissionService::reloadPermissions(false);
                            AdiantiCoreApplication::gotoPage('SystemUpdateList', 'fetchProject', ['static' => '1', 'register_state' => 'false']);
                        }
                    }
                    else
                    {
                        throw new Exception(_t('Select files and folders to be updated'));
                    }
                }
                else
                {
                    throw new Exception(_t('Permission denied') . ': '. $path);
                }
                
                $zip->close();
            }
            
            // put the data back to the form
            $this->form->setData($data);
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
        }
    }
    
    /**
     * Recursive delete folder from local file tree
     */
    private static function recursiveDelete($folder)
    {
        // apaga recursivamente o $folder
        $dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder), RecursiveIteratorIterator::CHILD_FIRST);
        for ($dir-> rewind (); $dir-> valid (); $dir-> next ())
        {
            if ($dir-> isDir ())
            {
                if (! $dir-> isDot () )
                {
                    rmdir($dir-> getPathname ());
                }
            }
            else
            {
                unlink($dir-> getPathname ());
            }
        }
        rmdir($folder);
    }
    
    /**
     * Clear from Zip folders from unchanged libs (app/lib/creator and app/templates/adminbs5) b/c they always come from remote
     * This way they can be treated the same way as lib/ and vendor/
     */
    private static function clearLibs($zip)
    {
        foreach (self::$folder_map as $folder => $file)
        {
            $local_index  = @file_get_contents($file);
            $remote_index = $zip->getFromName('project/'.$file);
            
            if (!empty($local_index) && $local_index == $remote_index)
            {
                for ($i = $zip->numFiles - 1; $i >= 0; $i--)
                {
                    $entry = $zip->getNameIndex($i);
                    if (strpos($entry, 'project/'.$folder) === 0) // inside folder
                    {
                        $zip->deleteName($entry);
                    }
                }
            }
        }
    }
}
