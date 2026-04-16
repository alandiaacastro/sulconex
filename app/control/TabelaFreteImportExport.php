<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Control\TWindow;
use Adianti\Database\TCriteria;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TScript;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TToast;
use Adianti\Widget\Form\TFile;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapFormBuilder;

class TabelaFreteImportExport extends TPage
{
    private const DATABASE = 'sample';

    private static function parseImportRow(array $row): ?array
    {
        if (count($row) < 5) {
            return null;
        }

        [$tipoVeiculo, $tipo, $origem, $destino, $valorFrete] = array_map('trim', $row);

        if ($tipoVeiculo === '' || $origem === '' || $destino === '') {
            return null;
        }

        return [
            'tipo_veiculo' => TabelaFrete::normalizeUpper($tipoVeiculo),
            'tipo'         => ($tipo = TabelaFrete::normalizeUpper($tipo)) !== '' ? $tipo : null,
            'origem'       => TabelaFrete::normalizeUpper($origem),
            'destino'      => TabelaFrete::normalizeUpper($destino),
            'valor_frete'  => TabelaFrete::parseMoney($valorFrete),
        ];
    }

    private static function readImportRows(string $filePath, int &$errors): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new Exception('Nao foi possivel ler o arquivo.');
        }

        try {
            $header = fgetcsv($handle, 0, ';');
            if (!$header) {
                throw new Exception('Arquivo vazio ou formato invalido.');
            }

            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $parsedRow = self::parseImportRow($row);

                if ($parsedRow === null) {
                    $errors++;
                    continue;
                }

                $rows[] = $parsedRow;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    public static function onExport($param = null): void
    {
        try {
            TTransaction::open(self::DATABASE);

            $repository = new TRepository('TabelaFrete');
            $criteria   = new TCriteria;
            $criteria->setProperty('order', 'id');
            $fretes = $repository->load($criteria, false);

            TTransaction::close();

            $writer = new TTableWriterXLS([30, 60, 40, 120, 120, 80, 80]);
            $writer->addStyle('header', 'Arial', 10, 'B', '#FFFFFF', '#1e3a5f');
            $writer->addStyle('odd', 'Arial', 10, '', '#000000', '#FFFFFF');
            $writer->addStyle('even', 'Arial', 10, '', '#000000', '#EEF2F7');

            $writer->addRow('header');
            foreach (['ID', 'Tipo Veiculo', 'Tipo', 'Origem', 'Destino', 'Frete (R$)', 'Atualizacao'] as $column) {
                $writer->addCell($column, 'center', 'header');
            }

            foreach ($fretes ?? [] as $index => $frete) {
                $style = $index % 2 === 0 ? 'odd' : 'even';
                $writer->addRow($style);
                $writer->addCell($frete->id, 'center', $style);
                $writer->addCell($frete->tipo_veiculo, 'left', $style);
                $writer->addCell($frete->tipo ?? '', 'center', $style);
                $writer->addCell($frete->origem, 'left', $style);
                $writer->addCell($frete->destino, 'left', $style);
                $writer->addCell(number_format((float) $frete->valor_frete, 2, ',', '.'), 'right', $style);
                $writer->addCell(TabelaFrete::formatAtualizacao($frete->atualizacao), 'center', $style);
            }

            $fileName = 'tabela_fretes_' . date('Ymd_His') . '.xls';
            $filePath = 'tmp/' . $fileName;
            $writer->save($filePath);

            TScript::create("__adianti_download_file('{$filePath}')");
            TToast::show('success', 'Exportacao concluida!', 'bottom right', 'far:check-circle');
        } catch (Exception $e) {
            try { TTransaction::rollback(); } catch (Exception $rollbackException) {}
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onOpenImport($param = null): void
    {
        $window = TWindow::create('win_frete_import', 500, 300);
        $window->setTitle('Importar Tabela de Fretes (CSV)');
        $window->add(new self());
        $window->show();
    }

    public function __construct($param = null)
    {
        parent::__construct();

        $form = new BootstrapFormBuilder('form_frete_import');
        $form->setFormTitle('Importar CSV');

        $file = new TFile('csv_file');
        $file->setAllowedExtensions(['csv']);
        $file->setSize('100%');

        $row = $form->addFields([new TLabel('Arquivo CSV'), $file]);
        $row->layout = ['col-sm-12'];

        $form->addFields([new TLabel(
            '<small class="text-muted">Formato esperado (com cabecalho):<br>
            <code>tipo_veiculo;tipo;origem;destino;valor_frete</code><br>
            Exemplo: <code>CARRETA SIDER;NAC;Porto Alegre,RS;Sao Paulo,SP;3500.00</code></small>'
        )]);

        $importButton = $form->addAction('Importar', new TAction([$this, 'onImport']), 'fa:upload');
        $importButton->class = 'btn btn-sm btn-success';
        $form->addAction('Cancelar', new TAction([$this, 'onClose']), 'fa:times red');

        $container = new TVBox;
        $container->style = 'width:100%';
        $container->add($form);

        parent::add($container);
    }

    public static function onImport($param = null): void
    {
        try {
            $file = $_FILES['csv_file'] ?? null;

            if (empty($file['tmp_name'])) {
                throw new Exception('Nenhum arquivo enviado.');
            }

            $errors = 0;
            $rows = self::readImportRows($file['tmp_name'], $errors);

            if ($rows === []) {
                throw new Exception('Nenhuma linha valida encontrada no arquivo.');
            }

            $imported = 0;

            TTransaction::open(self::DATABASE);
            $connection = TTransaction::get();

            foreach ($rows as $row) {
                $existingId = TabelaFrete::findExistingRouteId(
                    $connection,
                    $row['origem'],
                    $row['destino'],
                    $row['tipo_veiculo']
                );

                $frete = $existingId !== null ? new TabelaFrete($existingId) : new TabelaFrete;
                $frete->tipo_veiculo = $row['tipo_veiculo'];
                $frete->tipo         = $row['tipo'];
                $frete->origem       = $row['origem'];
                $frete->destino      = $row['destino'];
                $frete->valor_frete  = $row['valor_frete'];
                $frete->atualizacao  = date('Y-m-d H:i:s');
                $frete->store();
                $imported++;
            }

            TTransaction::close();
            TWindow::closeWindow();

            $message = "{$imported} rota(s) importada(s) com sucesso.";
            if ($errors > 0) {
                $message .= " {$errors} linha(s) ignorada(s) por formato invalido.";
            }

            TToast::show('success', $message, 'bottom right', 'far:check-circle');
        } catch (Exception $e) {
            try { TTransaction::rollback(); } catch (Exception $rollbackException) {}
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onClose($param = null): void
    {
        TWindow::closeWindow();
    }
}
