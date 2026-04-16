<?php

class PortalMotoristaDocumentoService
{
    private const IMAGE_ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg', 'tif', 'tiff', 'heic', 'heif', 'avif', 'ico'];

    public static function listForMotorista(Motorista $motorista, array $params): array
    {
        return PortalMotoristaSupportService::withSampleTransaction(function () use ($motorista, $params) {
            PortalMotoristaDocumento::ensureTables();

            $vehicles = PortalMotoristaSupportService::buildVehicleOptions((int) $motorista->id);
            $selectedVehicleId = max(0, (int) ($params['veiculo_id'] ?? 0));

            if ($selectedVehicleId > 0 && !self::vehicleOptionExists($vehicles, $selectedVehicleId)) {
                $selectedVehicleId = 0;
            }

            if ($selectedVehicleId === 0 && !empty($vehicles)) {
                $selectedVehicleId = (int) $vehicles[0]['id'];
            }

            $vehicleMeta = self::findVehicleMeta($vehicles, $selectedVehicleId);
            $documents = [];

            foreach (PortalMotoristaDocumento::getSlotDefinitions() as $tipoDocumento => $definition) {
                $veiculoId = !empty($definition['requires_vehicle']) && $selectedVehicleId > 0 ? $selectedVehicleId : null;
                $documents[] = self::mapDocumentSlot(
                    $tipoDocumento,
                    $definition,
                    PortalMotoristaDocumento::findByContext((int) $motorista->id, $tipoDocumento, $veiculoId),
                    !empty($definition['requires_vehicle']) ? $vehicleMeta : null
                );
            }

            return [
                'selected_vehicle_id' => $selectedVehicleId > 0 ? $selectedVehicleId : null,
                'vehicles' => $vehicles,
                'alerts' => PortalMotoristaDashboardService::buildDocumentAlerts((int) $motorista->id),
                'documents' => $documents,
            ];
        });
    }

    public static function upload(Motorista $motorista, array $data, array $files): array
    {
        return PortalMotoristaSupportService::withSampleTransaction(function (PDO $connection) use ($motorista, $data, $files) {
            PortalMotoristaDocumento::ensureTables();

            $tipoDocumento = trim((string) ($data['tipo_documento'] ?? ''));
            $veiculoId = !empty($data['veiculo_id']) ? (int) $data['veiculo_id'] : null;
            $definition = PortalMotoristaDocumento::getTypeDefinition($tipoDocumento);

            if ($definition === null) {
                throw new PortalMotoristaApiException('Tipo de documento invalido.', 422, 'validation_error');
            }

            if (!empty($definition['requires_vehicle'])) {
                if (empty($veiculoId)) {
                    throw new PortalMotoristaApiException('Selecione um veiculo antes de enviar este arquivo.', 422, 'validation_error');
                }

                PortalMotoristaSupportService::assertVehicleBelongsToMotorista($connection, $veiculoId, (int) $motorista->id);
            } else {
                $veiculoId = null;
            }

            $uploadedFile = $files['arquivo'] ?? null;
            if (!is_array($uploadedFile) || (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new PortalMotoristaApiException('Selecione um arquivo valido para envio.', 422, 'validation_error');
            }

            $extension = strtolower((string) pathinfo((string) ($uploadedFile['name'] ?? ''), PATHINFO_EXTENSION));
            $tmpName = (string) ($uploadedFile['tmp_name'] ?? '');

            if ($tmpName === '' || !is_file($tmpName)) {
                throw new PortalMotoristaApiException('Arquivo temporario nao encontrado.', 422, 'upload_not_found');
            }

            $mimeType = function_exists('mime_content_type') ? (mime_content_type($tmpName) ?: '') : '';
            $isPdf = $extension === 'pdf' || $mimeType === 'application/pdf';
            $isImage = in_array($extension, self::IMAGE_ALLOWED_EXTENSIONS, true) || ($mimeType !== '' && str_starts_with($mimeType, 'image/'));

            if (!$isImage && !(PortalMotoristaDocumento::allowsPdf($tipoDocumento) && $isPdf)) {
                $message = PortalMotoristaDocumento::allowsPdf($tipoDocumento)
                    ? 'Formato invalido. Use PDF ou imagem.'
                    : 'Formato invalido. Use apenas imagem para esta foto.';
                throw new PortalMotoristaApiException($message, 422, 'invalid_file_type');
            }

            $directory = PortalMotoristaDocumento::ensureStorageDirectory();
            $filename = self::buildStoredFilename((int) $motorista->id, $veiculoId, $tipoDocumento, $extension);
            $destination = $directory . '/' . $filename;

            if (!@copy($tmpName, $destination)) {
                throw new PortalMotoristaApiException('Nao foi possivel salvar o documento.', 500, 'upload_failed');
            }

            $record = PortalMotoristaDocumento::findByContext((int) $motorista->id, $tipoDocumento, $veiculoId) ?: new PortalMotoristaDocumento;
            $oldFile = (string) ($record->arquivo ?? '');

            $record->motorista_id = (int) $motorista->id;
            $record->veiculo_id = $veiculoId;
            $record->tipo_documento = $tipoDocumento;
            $record->titulo = (string) ($definition['title'] ?? self::resolveTitle($tipoDocumento));
            $record->arquivo = $destination;
            $record->arquivo_original = basename((string) $uploadedFile['name']);
            $record->mime_type = function_exists('mime_content_type') ? mime_content_type($destination) : null;
            $record->store();

            if ($oldFile !== '' && $oldFile !== $destination && file_exists($oldFile)) {
                @unlink($oldFile);
            }

            return [
                'message' => 'Documento enviado com sucesso.',
                'document' => self::mapDocumentRecord($record),
            ];
        });
    }

    public static function delete(Motorista $motorista, int $documentId): array
    {
        if ($documentId <= 0) {
            throw new PortalMotoristaApiException('Documento invalido.', 422, 'validation_error');
        }

        return PortalMotoristaSupportService::withSampleTransaction(function () use ($motorista, $documentId) {
            PortalMotoristaDocumento::ensureTables();

            $record = new PortalMotoristaDocumento($documentId);
            if (empty($record->id) || (int) $record->motorista_id !== (int) $motorista->id) {
                throw new PortalMotoristaApiException('Documento nao localizado.', 404, 'document_not_found');
            }

            $storedFile = (string) ($record->arquivo ?? '');
            $record->delete();

            if ($storedFile !== '' && file_exists($storedFile)) {
                @unlink($storedFile);
            }

            return [
                'id' => $documentId,
                'message' => 'Documento removido com sucesso.',
            ];
        });
    }

    private static function mapDocumentSlot(string $type, array $definition, ?PortalMotoristaDocumento $document, ?array $vehicleMeta): array
    {
        return [
            'slot' => (string) ($definition['slot'] ?? $type),
            'title' => (string) ($definition['title'] ?? 'Documento'),
            'type' => $type,
            'hint' => (string) ($definition['hint'] ?? ''),
            'requires_vehicle' => !empty($definition['requires_vehicle']),
            'vehicle' => $vehicleMeta,
            'record' => $document ? self::mapDocumentRecord($document) : null,
            'required' => empty($definition['requires_vehicle']) || !empty($vehicleMeta),
            'accept' => (string) ($definition['accept'] ?? '.jpg,.jpeg,.png,.webp,.gif,.bmp,.svg,.pdf'),
            'capture' => !empty($definition['capture']) ? (string) $definition['capture'] : null,
            'kind' => (string) ($definition['kind'] ?? 'document'),
        ];
    }

    private static function mapDocumentRecord(PortalMotoristaDocumento $document): array
    {
        return [
            'id' => (int) $document->id,
            'title' => (string) ($document->titulo ?? ''),
            'type' => (string) ($document->tipo_documento ?? ''),
            'arquivo' => (string) ($document->arquivo ?? ''),
            'arquivo_original' => (string) ($document->arquivo_original ?: basename((string) $document->arquivo)),
            'mime_type' => (string) ($document->mime_type ?? ''),
            'updated_at' => (string) ($document->updated_at ?? ''),
            'updated_at_label' => PortalMotoristaSupportService::formatDate((string) ($document->updated_at ?? ''), 'd/m/Y H:i') ?: '-',
            'download_url' => PortalMotoristaSupportService::buildDocumentDownloadUrl($document),
        ];
    }

    private static function buildStoredFilename(int $motoristaId, ?int $veiculoId, string $tipoDocumento, string $extension): string
    {
        $parts = ['motorista', $motoristaId, $tipoDocumento];

        if ($veiculoId) {
            $parts[] = 'veiculo';
            $parts[] = $veiculoId;
        }

        $parts[] = date('YmdHis');
        return implode('_', $parts) . '.' . $extension;
    }

    private static function resolveTitle(string $tipoDocumento): string
    {
        return PortalMotoristaDocumento::getTipoLabels()[$tipoDocumento] ?? 'Documento';
    }

    private static function vehicleOptionExists(array $vehicles, int $selectedVehicleId): bool
    {
        foreach ($vehicles as $vehicle) {
            if ((int) ($vehicle['id'] ?? 0) === $selectedVehicleId) {
                return true;
            }
        }

        return false;
    }

    private static function findVehicleMeta(array $vehicles, int $selectedVehicleId): ?array
    {
        foreach ($vehicles as $vehicle) {
            if ((int) ($vehicle['id'] ?? 0) === $selectedVehicleId) {
                return $vehicle;
            }
        }

        return null;
    }
}
