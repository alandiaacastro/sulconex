<?php

class PortalMotoristaApiKernel
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

    public function handle(): void
    {
        $this->sendDefaultHeaders();

        try {
            if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
                http_response_code(204);
                return;
            }

            $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            $route = trim((string) ($_GET['route'] ?? ''), '/');

            if ($method === 'POST' && $route === 'auth/login') {
                $payload = $this->getInputData();
                $this->respondSuccess(PortalMotoristaAuthService::login(
                    (string) ($payload['telefone'] ?? ''),
                    (string) ($payload['senha_portal'] ?? $payload['senha'] ?? '')
                ));
                return;
            }

            if ($method === 'POST' && $route === 'auth/logout') {
                PortalMotoristaAuthService::logout();
                $this->respondSuccess(['message' => 'Sessao encerrada com sucesso.']);
                return;
            }

            if ($method === 'GET' && $route === 'auth/me') {
                $this->respondSuccess(PortalMotoristaAuthService::me());
                return;
            }

            if ($method === 'POST' && $route === 'auth/change-password') {
                $motorista = PortalMotoristaAuthService::getAuthenticatedMotorista();
                $payload = $this->getInputData();
                $this->respondSuccess(PortalMotoristaAuthService::changePassword(
                    $motorista,
                    (string) ($payload['nova_senha'] ?? $payload['senha'] ?? ''),
                    (string) ($payload['confirmacao_senha'] ?? $payload['confirmar_senha'] ?? $payload['senha_confirmacao'] ?? '')
                ));
                return;
            }

            $motorista = PortalMotoristaAuthService::getAuthenticatedMotorista();

            if (PortalMotoristaAuthService::requiresPasswordChange($motorista)) {
                throw new PortalMotoristaApiException(
                    'Atualize sua senha antes de continuar.',
                    403,
                    'password_change_required',
                    ['must_change_password' => true]
                );
            }

            if ($method === 'GET' && $route === 'dashboard') {
                $this->respondSuccess(PortalMotoristaDashboardService::fetch($motorista));
                return;
            }

            if ($method === 'GET' && $route === 'cargas') {
                $this->respondSuccess(PortalMotoristaCargaService::listAvailable($motorista, $_GET));
                return;
            }

            if ($method === 'POST' && $route === 'solicitacoes') {
                $this->respondSuccess(PortalMotoristaCargaService::createSolicitacao($motorista, $this->getInputData()), [], 201);
                return;
            }

            if ($method === 'GET' && $route === 'solicitacoes') {
                $this->respondSuccess(PortalMotoristaSolicitacaoService::listForMotorista($motorista, $_GET));
                return;
            }

            if ($method === 'POST' && preg_match('#^solicitacoes/(\d+)/cancelar$#', $route, $matches)) {
                $this->respondSuccess(PortalMotoristaSolicitacaoService::cancel($motorista, (int) $matches[1]));
                return;
            }

            if ($method === 'GET' && $route === 'andamento') {
                $this->respondSuccess(PortalMotoristaAndamentoService::listForMotorista($motorista));
                return;
            }

            if ($method === 'POST' && $route === 'andamento/localizacao') {
                $this->respondSuccess(PortalMotoristaAndamentoService::saveLocation($motorista, $this->getInputData()));
                return;
            }

            if ($method === 'POST' && $route === 'andamento/comprovante') {
                $this->respondSuccess(PortalMotoristaAndamentoService::uploadComprovante($motorista, $_POST, $_FILES), [], 201);
                return;
            }

            if ($method === 'GET' && $route === 'documentos') {
                $this->respondSuccess(PortalMotoristaDocumentoService::listForMotorista($motorista, $_GET));
                return;
            }

            if ($method === 'POST' && $route === 'documentos') {
                $this->respondSuccess(PortalMotoristaDocumentoService::upload($motorista, $_POST, $_FILES), [], 201);
                return;
            }

            if ($method === 'DELETE' && preg_match('#^documentos/(\d+)$#', $route, $matches)) {
                $this->respondSuccess(PortalMotoristaDocumentoService::delete($motorista, (int) $matches[1]));
                return;
            }

            if ($method === 'GET' && $route === 'contratos') {
                $this->respondSuccess(PortalMotoristaContratoService::listForMotorista($motorista));
                return;
            }

            throw new PortalMotoristaApiException('Endpoint nao encontrado.', 404, 'not_found');
        } catch (PortalMotoristaApiException $e) {
            $this->respondError($e->getHttpStatus(), $e->getErrorCode(), $e->getMessage(), $e->getDetails());
        } catch (Throwable $e) {
            $this->respondError(500, 'server_error', $e->getMessage());
        }
    }

    private function getInputData(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }

        $rawBody = file_get_contents('php://input');
        if (!$rawBody) {
            return [];
        }

        $decoded = json_decode($rawBody, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function sendDefaultHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    private function respondSuccess($data, array $meta = [], int $status = 200): void
    {
        http_response_code($status);
        echo json_encode([
            'ok' => true,
            'data' => $data,
            'meta' => (object) $meta,
        ], self::JSON_FLAGS);
    }

    private function respondError(int $status, string $code, string $message, array $details = []): void
    {
        http_response_code($status);
        echo json_encode([
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
            ],
        ], self::JSON_FLAGS);
    }
}
