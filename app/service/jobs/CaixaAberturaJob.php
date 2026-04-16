<?php
/**
 * Job de abertura diária do caixa.
 * Pode ser executado pelo agendador:
 * php cmd.php "class=CaixaAberturaJob&method=run"
 */
class CaixaAberturaJob implements AdiantiJob
{
    public static function run($param = null)
    {
        Caixa::createTableIfNotExists();

        try
        {
            TTransaction::open('sample');
            $conn = TTransaction::get();

            $dataHoje  = date('Y-m-d');
            $descricao = 'ABERTURA DE CAIXA (AUTO)';

            $stmt = $conn->prepare('SELECT id FROM caixa WHERE data_lancamento = ? AND descricao = ? LIMIT 1');
            $stmt->execute([$dataHoje, $descricao]);
            $jaExiste = $stmt->fetchColumn();

            if (!$jaExiste)
            {
                $caixa = new Caixa;
                $caixa->data_lancamento = $dataHoje;
                $caixa->descricao       = $descricao;
                $caixa->tipo            = 'ENTRADA';
                $caixa->valor           = 0;
                $caixa->categoria       = 'MANUAL';
                $caixa->status          = 'CONCILIADO';
                $caixa->observacao      = 'Gerado automaticamente pelo agendamento diario.';
                $caixa->store();
            }

            TTransaction::close();
        }
        catch (Throwable $e)
        {
            TTransaction::rollback();
            throw new Exception('Falha no job de abertura do caixa: ' . $e->getMessage());
        }
    }
}
