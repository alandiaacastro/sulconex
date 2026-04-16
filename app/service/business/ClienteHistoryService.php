<?php

use Adianti\Database\TTransaction;

class ClienteHistoryService
{
    private static $database = 'sample';

    /**
     * Retorna os KPIs do cliente (Cotaes, CRTs, Faturas)
     */
    public static function getCustomerKPIs(int $clientId): array
    {
        $kpis = [
            'propostas' => 0,
            'crts' => 0,
            'faturas' => 0
        ];

        try {
            TTransaction::open(self::$database);
            $conn = TTransaction::get();

            // Cotaes
            $stmt = $conn->prepare("SELECT COUNT(*) FROM Proposta WHERE cliente_id = :cliente");
            $stmt->execute([':cliente' => $clientId]);
            $kpis['propostas'] = (int) $stmt->fetchColumn();

            // CRTs
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM conhecimento 
                WHERE remetente_id = :cliente OR destinatario_id = :cliente 
                   OR consignatario_id = :cliente OR notificar_id = :cliente 
                   OR pagador_id = :cliente
            ");
            $stmt->execute([':cliente' => $clientId]);
            $kpis['crts'] = (int) $stmt->fetchColumn();

            // Faturas
            $stmt = $conn->prepare("SELECT COUNT(*) FROM fatura WHERE pessoa_id = :cliente");
            $stmt->execute([':cliente' => $clientId]);
            $kpis['faturas'] = (int) $stmt->fetchColumn();

            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            throw $e;
        }

        return $kpis;
    }

    /**
     * Retorna as ltimas propostas/cotacoes do cliente
     */
    public static function getCustomerProposals(int $clientId): array
    {
        try {
            TTransaction::open(self::$database);
            $conn = TTransaction::get();

            $stmt = $conn->prepare("
                SELECT id, Cotacao_ID, Data_Cotacao, Data_Validade_Cotacao, Situacao, Faturamento_Valor_1
                FROM Proposta
                WHERE cliente_id = :cliente
                ORDER BY id DESC
            ");
            $stmt->execute([':cliente' => $clientId]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            TTransaction::close();
            return $result;
        } catch (Exception $e) {
            TTransaction::rollback();
            throw $e;
        }
    }

    /**
     * Retorna os ltimos CRTs do cliente
     */
    public static function getCustomerCRTs(int $clientId): array
    {
        try {
            TTransaction::open(self::$database);
            $conn = TTransaction::get();

            $stmt = $conn->prepare("
                SELECT c.id, c.numero, c.data_transportador_assinatura, s.nome AS status_nome
                FROM conhecimento c
                LEFT JOIN status_crt s ON s.id = c.status_crt_id
                WHERE c.remetente_id = :cliente
                   OR c.destinatario_id = :cliente
                   OR c.consignatario_id = :cliente
                   OR c.notificar_id = :cliente
                   OR c.pagador_id = :cliente
                ORDER BY c.id DESC
            ");
            $stmt->execute([':cliente' => $clientId]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            TTransaction::close();
            return $result;
        } catch (Exception $e) {
            TTransaction::rollback();
            throw $e;
        }
    }

    /**
     * Retorna as ltimas faturas do cliente
     */
    public static function getCustomerInvoices(int $clientId): array
    {
        try {
            TTransaction::open(self::$database);
            $conn = TTransaction::get();

            $stmt = $conn->prepare("
                SELECT id, numero_fatura, numero_crt, emissao, vencimento, valor_fatura, pagamento
                FROM fatura
                WHERE pessoa_id = :cliente
                ORDER BY id DESC
            ");
            $stmt->execute([':cliente' => $clientId]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            TTransaction::close();
            return $result;
        } catch (Exception $e) {
            TTransaction::rollback();
            throw $e;
        }
    }
}
