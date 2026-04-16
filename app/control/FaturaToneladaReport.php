<?php

use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Dialog\TMessage;

class FaturaToneladaReport extends TPage
{
    public static function onGenerateReais($param)
    {
        self::generate($param);
    }

    public static function onGenerateDolar($param)
    {
        new TMessage('error', 'O relatorio de fatura por tonelada esta disponivel apenas em reais.');
    }

    private static function generate($param)
    {
        try {
            TTransaction::open('sample');
            $fatura = new FaturaTonelada($param['key']);
            if (!$fatura) {
                throw new Exception('Fatura nao encontrada!');
            }

            FaturaReport::generateFromObject($fatura, 'reais', ['file_prefix' => 'fatura_tonelada_', 'hide_peso_bruto' => true]);

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }
}
