<?php

use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TScript;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TDate;



class FaturaReport extends TPage
{
    public static function onGenerateReais($param)
    {
        self::generate($param, 'reais');
    }

    public static function onGenerateDolar($param)
    {
        self::generate($param, 'dolar');
    }

    private static function generate($param, $moeda)
    {
        try {
            TTransaction::open('sample');
            $fatura = new Fatura($param['key']);
            if (!$fatura) { throw new Exception('Fatura não encontrada!'); }
            
            // Lógica para escolher qual layout de PDF usar
            if ($moeda == 'reais') {
                self::gerarPDFReais($fatura);
            } else {
                self::gerarPDFDolar($fatura);
            }

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    private static function gerarPDFReais($object)
    {
        // ... (Cole aqui toda a sua lógica FPDF para o relatório em Reais)
        // Lembre-se de substituir utf8_decode por iconv e ajustar os campos
        // Ex: $pdf->Text(108, 42, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)($object->numero_fatura ?? '')));
        // Ex: $pdf->Text(12, 53, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)($object->cliente->nome ?? '')));
    }

    private static function gerarPDFDolar($object)
    {
        // ... (Cole aqui toda a sua lógica FPDF para o relatório em Dólar)
    }
}