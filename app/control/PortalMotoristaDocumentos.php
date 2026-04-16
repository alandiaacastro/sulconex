<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\THidden;
use Adianti\Widget\Form\TImageCropper;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapFormBuilder;

class PortalMotoristaDocumentos extends TPage
{
    private const IMAGE_ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg', 'tif', 'tiff', 'heic', 'heif', 'avif', 'ico',
    ];

    private $selectorForm;
    private $uploadForm;
    private $cardsContainer;
    private $loaded;

    public function __construct()
    {
        parent::__construct();


        if (!TSession::getValue('portal_motorista_logged') && !TSession::getValue('logged')) {
            AdiantiCoreApplication::gotoPage('PortalMotoristaLogin');
            return;
        }

        try {
            TTransaction::open('sample');
            Motorista::ensureTables();
            PortalMotoristaDocumento::ensureTables();
            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
        }

        // ---- Formulário seletor de veículo ----
        $this->selectorForm = new BootstrapFormBuilder('form_portal_documentos_filtro');
        $this->selectorForm->setFormTitle('Documentos do Motorista');

        $veiculoId = new TCombo('veiculo_id');
        $veiculoId->setSize('100%');

        $this->selectorForm->addFields(
            [new TLabel('<i class="fas fa-car-side me-1"></i> Veículo (Cavalo / Semi-Reboque)')],
            [$veiculoId]
        );
        $this->selectorForm->addAction('Carregar Veículo', new TAction([$this, 'onSelectVehicle']), 'fa:truck blue');

        // ---- Cards de documentos ----
        $this->cardsContainer = new TElement('div');

        // ---- Formulário de upload ----
        $this->uploadForm = new BootstrapFormBuilder('form_portal_documentos_upload');
        $this->uploadForm->setFormTitle('<i class="fas fa-cloud-upload-alt me-2"></i> Enviar ou Atualizar Documentos');

        $selectedVehicle = new THidden('veiculo_id');
        $currentCnhFile = new THidden('cnh_file_current');
        $currentCavaloFile = new THidden('cavalo_file_current');
        $currentSemiFile = new THidden('semi_file_current');

        $cnhFile = new TImageCropper('cnh_file');
        $this->configureDocumentCropperField($cnhFile, 'CNH');

        $cavaloFile = new TImageCropper('cavalo_file');
        $this->configureDocumentCropperField($cavaloFile, 'Documento do Cavalo');

        $semiFile = new TImageCropper('semi_file');
        $this->configureDocumentCropperField($semiFile, 'Documento do Semi-Reboque');

        // Hidden vehicle id — hidden wrapper added to form body
        $hiddenWrap = new TElement('div');
        $hiddenWrap->style = 'display:none';
        $hiddenWrap->add($selectedVehicle);
        $hiddenWrap->add($currentCnhFile);
        $hiddenWrap->add($currentCavaloFile);
        $hiddenWrap->add($currentSemiFile);
        $this->uploadForm->add($hiddenWrap);

        $this->uploadForm->addContent([
            '<div class="text-muted mb-2" style="font-size:.82rem">Envie uma foto do documento e ajuste o recorte antes de salvar. Arquivos antigos continuam disponíveis nos cards acima.</div>',
        ]);

        $this->uploadForm->addFields(
            [new TLabel('<i class="fas fa-id-card me-1" style="color:#4F46E5"></i> CNH <small class="text-muted">(Habilitação)</small>')],
            [$cnhFile]
        );
        $this->uploadForm->addFields(
            [new TLabel('<i class="fas fa-truck me-1" style="color:#059669"></i> Documento do Cavalo <small class="text-muted">(CRLV)</small>')],
            [$cavaloFile]
        );
        $this->uploadForm->addFields(
            [new TLabel('<i class="fas fa-trailer me-1" style="color:#D97706"></i> Documento do Semi-Reboque <small class="text-muted">(CRLV)</small>')],
            [$semiFile]
        );

        // Register hidden field for serialization too
        $this->uploadForm->setFields([$selectedVehicle, $currentCnhFile, $currentCavaloFile, $currentSemiFile, $cnhFile, $cavaloFile, $semiFile]);
        $this->uploadForm->addAction('Salvar Documentos', new TAction([$this, 'onSave']), 'fa:upload green');

        $contentWrap = new TElement('div');
        $contentWrap->class = 'portal-page-content';
        $contentWrap->add($this->selectorForm);
        $contentWrap->add($this->cardsContainer);
        $contentWrap->add($this->uploadForm);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(PortalMotoristaHelper::buildNav('documentos'));
        $container->add($contentWrap);
        parent::add($container);
    }

    public function onSelectVehicle($param = null)
    {
        $vehicleId = !empty($param['veiculo_id']) ? (int) $param['veiculo_id'] : null;
        TSession::setValue('PortalMotoristaDocumentos_selected_vehicle_id', $vehicleId);
        $this->onReload();
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');
            PortalMotoristaDocumento::ensureTables();

            $motorista = Motorista::getPortalMotorista();
            if (!$motorista) {
                TTransaction::close();
                new TMessage('error', 'Motorista nao encontrado para a sessao atual.');
                return;
            }

            $veiculos = $this->loadVehicleOptions($motorista->id);
            $selectedVehicleId = (int) (TSession::getValue('PortalMotoristaDocumentos_selected_vehicle_id') ?: 0);
            if ($selectedVehicleId && !isset($veiculos[$selectedVehicleId])) {
                $selectedVehicleId = 0;
            }
            if (!$selectedVehicleId && !empty($veiculos)) {
                $selectedVehicleId = (int) array_key_first($veiculos);
                TSession::setValue('PortalMotoristaDocumentos_selected_vehicle_id', $selectedVehicleId);
            }

            $selectorData = new stdClass;
            $selectorData->veiculo_id = $selectedVehicleId ?: '';
            $this->selectorForm->setData($selectorData);

            $documents = $this->loadCurrentDocuments((int) $motorista->id, $selectedVehicleId);
            $uploadData = new stdClass;
            $uploadData->veiculo_id = $selectedVehicleId ?: '';
            $uploadData->cnh_file_current = $documents['cnh'] ? $documents['cnh']->arquivo : '';
            $uploadData->cavalo_file_current = $documents['cavalo'] ? $documents['cavalo']->arquivo : '';
            $uploadData->semi_file_current = $documents['semi'] ? $documents['semi']->arquivo : '';
            $uploadData->cnh_file = $this->getCropperInitialValue($documents['cnh'] ?? null);
            $uploadData->cavalo_file = $this->getCropperInitialValue($documents['cavalo'] ?? null);
            $uploadData->semi_file = $this->getCropperInitialValue($documents['semi'] ?? null);
            $this->uploadForm->setData($uploadData);

            $this->setVehicleFieldItems($veiculos);
            $this->renderCards($motorista, $selectedVehicleId, $documents);

            TTransaction::close();
            $this->loaded = true;
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('sample');
            PortalMotoristaDocumento::ensureTables();

            $motorista = Motorista::getPortalMotorista();
            if (!$motorista) {
                throw new Exception('Motorista nao encontrado para a sessao atual.');
            }

            $veiculoId = !empty($param['veiculo_id']) ? (int) $param['veiculo_id'] : null;
            $saved = 0;

            $saved += $this->saveUploadedDocument($motorista->id, PortalMotoristaDocumento::TIPO_CNH, null, $param['cnh_file'] ?? null, 'CNH', $param['cnh_file_current'] ?? null);
            $saved += $this->saveUploadedDocument($motorista->id, PortalMotoristaDocumento::TIPO_CAVALO, $veiculoId, $param['cavalo_file'] ?? null, 'Documento do Cavalo', $param['cavalo_file_current'] ?? null);
            $saved += $this->saveUploadedDocument($motorista->id, PortalMotoristaDocumento::TIPO_SEMI_REBOQUE, $veiculoId, $param['semi_file'] ?? null, 'Documento do Semi-Reboque', $param['semi_file_current'] ?? null);

            if ($saved === 0) {
                throw new Exception('Ajuste ou remova pelo menos uma imagem para salvar.');
            }

            TSession::setValue('PortalMotoristaDocumentos_selected_vehicle_id', $veiculoId);
            TTransaction::close();
            new TMessage('info', 'Documentos enviados com sucesso.');
            $this->onReload();
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onView($param)
    {
        try {
            TTransaction::open('sample');

            $motorista = Motorista::getPortalMotorista();
            if (!$motorista) {
                throw new Exception('Motorista nao encontrado para a sessao atual.');
            }

            $doc = new PortalMotoristaDocumento($param['key']);
            if ((int) $doc->motorista_id !== (int) $motorista->id) {
                throw new Exception('Voce nao tem permissao para acessar este arquivo.');
            }

            $file = $doc->arquivo;
            $basename = $doc->arquivo_original ?: basename((string) $file);

            TTransaction::close();
            TPage::openFile($file, $basename);
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload();
        }
        parent::show();
    }

    private function renderCards(Motorista $motorista, int $selectedVehicleId, array $documents = []): void
    {
        $this->cardsContainer->clearChildren();

        $grid = new TElement('div');
        $grid->class = 'd-flex flex-column gap-2 mb-3';

        $cnh = $documents['cnh'] ?? PortalMotoristaDocumento::findByContext((int) $motorista->id, PortalMotoristaDocumento::TIPO_CNH);
        $grid->add($this->buildDocumentCard('CNH', 'fa-id-card', '#4F46E5', '#EEF2FF', $cnh, 'Envie a foto ou PDF da sua habilitação.'));

        if ($selectedVehicleId > 0) {
            $veiculo = new Veiculo($selectedVehicleId);
            $placaTrator = htmlspecialchars((string) ($veiculo->placa_trator ?: 'Sem placa'));
            $placaSemi = htmlspecialchars((string) ($veiculo->antt_consulta_semi_reboque->placa ?? 'Não informado'));

            $cavalo = $documents['cavalo'] ?? PortalMotoristaDocumento::findByContext((int) $motorista->id, PortalMotoristaDocumento::TIPO_CAVALO, $selectedVehicleId);
            $semi = $documents['semi'] ?? PortalMotoristaDocumento::findByContext((int) $motorista->id, PortalMotoristaDocumento::TIPO_SEMI_REBOQUE, $selectedVehicleId);

            $grid->add($this->buildDocumentCard("Cavalo <small class='fw-normal text-muted'>{$placaTrator}</small>", 'fa-truck', '#059669', '#ECFDF5', $cavalo, 'Envie CRLV ou documento equivalente do cavalo.'));
            $grid->add($this->buildDocumentCard("Semi-Reboque <small class='fw-normal text-muted'>{$placaSemi}</small>", 'fa-trailer', '#D97706', '#FFFBEB', $semi, 'Envie CRLV ou documento equivalente do semi-reboque.'));
        } else {
            $grid->add($this->buildEmptyVehicleCard());
        }

        $this->cardsContainer->add($grid);
    }

    private function buildDocumentCard(string $title, string $icon, string $color, string $bg, ?PortalMotoristaDocumento $doc, string $hint): TElement
    {
        $card = new TElement('div');
        $card->class = 'card border-0 rounded-3';
        $card->style = 'box-shadow:0 1px 6px rgba(0,0,0,.07)';

        // Header
        $header = new TElement('div');
        $header->class = 'd-flex align-items-center gap-3 p-3';
        $header->style = "border-bottom:1px solid rgba(0,0,0,.06)";
        $header->add("
            <div class='d-flex align-items-center justify-content-center rounded-3 flex-shrink-0'
                 style='width:38px;height:38px;background:{$bg}'>
                <i class='fas {$icon}' style='font-size:.9rem;color:{$color}'></i>
            </div>
            <div>
                <div class='fw-bold' style='font-size:.88rem;color:#0F172A'>{$title}</div>
                <div class='text-muted' style='font-size:.75rem'>{$hint}</div>
            </div>
        ");
        $card->add($header);

        // Body
        $body = new TElement('div');
        $body->class = 'p-3';

        if ($doc) {
            $arquivoOriginal = htmlspecialchars((string) ($doc->arquivo_original ?: basename((string) $doc->arquivo)));
            $updatedAt = $doc->updated_at ? date('d/m/Y H:i', strtotime((string) $doc->updated_at)) : '-';
            $viewUrl = htmlspecialchars($this->buildDownloadUrl($doc), ENT_QUOTES, 'UTF-8');

            $body->add("
                <div class='d-flex align-items-center justify-content-between mb-2'>
                    <span class='badge rounded-pill' style='background:#D1FAE5;color:#065F46;font-size:.75rem;padding:4px 10px'>
                        <i class='fas fa-check-circle me-1'></i>Enviado
                    </span>
                    <a href='{$viewUrl}' target='_blank' rel='noopener noreferrer'
                       class='btn btn-sm rounded-3 fw-semibold'
                       style='background:#EEF2FF;color:#4F46E5;font-size:.78rem;border:none;padding:4px 12px'>
                        <i class='fas fa-eye me-1'></i>Ver
                    </a>
                </div>
                <div style='font-size:.78rem;color:#64748B'><strong>Arquivo:</strong> {$arquivoOriginal}</div>
                <div style='font-size:.78rem;color:#64748B'><strong>Atualizado:</strong> {$updatedAt}</div>
            ");
        } else {
            $body->add("
                <span class='badge rounded-pill' style='background:#FEF3C7;color:#92400E;font-size:.75rem;padding:4px 10px'>
                    <i class='fas fa-clock me-1'></i>Pendente
                </span>
                <div class='text-muted mt-2' style='font-size:.78rem'>Nenhum arquivo enviado ainda.</div>
            ");
        }

        $card->add($body);
        return $card;
    }

    private function buildEmptyVehicleCard(): TElement
    {
        $card = new TElement('div');
        $card->class = 'card border-0 rounded-3 text-center p-4';
        $card->style = 'box-shadow:0 1px 6px rgba(0,0,0,.07)';
        $card->add("
            <div style='color:#94A3B8;font-size:2rem;margin-bottom:.5rem'>
                <i class='fas fa-truck-loading'></i>
            </div>
            <div class='fw-bold' style='font-size:.9rem;color:#475569'>Nenhum veículo selecionado</div>
            <div class='text-muted' style='font-size:.78rem;margin-top:4px'>Selecione um veículo acima para ver os documentos do cavalo e do semi-reboque.</div>
        ");
        return $card;
    }

    private function setVehicleFieldItems(array $veiculos): void
    {
        $field = $this->selectorForm->getField('veiculo_id');
        if ($field) {
            $field->addItems(['' => 'Selecione'] + $veiculos);
        }
    }

    private function loadVehicleOptions(int $motoristaId): array
    {
        $repo = new TRepository('Veiculo');
        $criteria = new TCriteria;
        $criteria->add(new TFilter('motorista_id', '=', $motoristaId));
        $criteria->setProperty('order', 'id desc');
        $items = $repo->load($criteria, false);

        $options = [];
        if ($items) {
            foreach ($items as $veiculo) {
                $semi = $veiculo->antt_consulta_semi_reboque->placa ?? null;
                $label = trim((string) ($veiculo->placa_trator ?: 'Sem placa'));
                if ($semi) {
                    $label .= ' / ' . $semi;
                }
                $options[(int) $veiculo->id] = $label;
            }
        }

        return $options;
    }

    private function loadCurrentDocuments(int $motoristaId, int $selectedVehicleId): array
    {
        return [
            'cnh' => PortalMotoristaDocumento::findByContext($motoristaId, PortalMotoristaDocumento::TIPO_CNH),
            'cavalo' => $selectedVehicleId > 0 ? PortalMotoristaDocumento::findByContext($motoristaId, PortalMotoristaDocumento::TIPO_CAVALO, $selectedVehicleId) : null,
            'semi' => $selectedVehicleId > 0 ? PortalMotoristaDocumento::findByContext($motoristaId, PortalMotoristaDocumento::TIPO_SEMI_REBOQUE, $selectedVehicleId) : null,
        ];
    }

    private function saveUploadedDocument(int $motoristaId, string $tipoDocumento, ?int $veiculoId, ?string $uploadedName, string $titulo, ?string $currentStoredFile = null): int
    {
        $uploadState = $this->parseUploadFieldValue($uploadedName, $currentStoredFile);

        if (!empty($uploadState['delete_only'])) {
            return $this->deleteStoredDocument($motoristaId, $tipoDocumento, $veiculoId, $titulo);
        }

        $uploadedFileName = $uploadState['uploaded_name'] ?? null;
        if (empty($uploadedFileName)) {
            return 0;
        }

        if ($tipoDocumento !== PortalMotoristaDocumento::TIPO_CNH && empty($veiculoId)) {
            throw new Exception("Selecione um veiculo para enviar {$titulo}.");
        }

        $source = $this->resolveUploadedPath($uploadedFileName);
        if (!$source || !file_exists($source)) {
            throw new Exception("Arquivo enviado para {$titulo} nao encontrado.");
        }

        $extension = strtolower((string) pathinfo($uploadedFileName, PATHINFO_EXTENSION));
        $mimeType = function_exists('mime_content_type') ? mime_content_type($source) : null;
        $allowedImageExtensions = self::IMAGE_ALLOWED_EXTENSIONS;
        $isPdf = ($extension === 'pdf') || ($mimeType === 'application/pdf');
        $isImage = (!empty($mimeType) && str_starts_with($mimeType, 'image/')) || in_array($extension, $allowedImageExtensions, true);

        if (!$isPdf && !$isImage) {
            throw new Exception("Formato invalido para {$titulo}. Use PDF/pdf ou imagem (JPG/jpg, JPEG/jpeg, PNG/png, WEBP/webp, GIF/gif, BMP/bmp, SVG/svg, TIFF/tiff, HEIC/heic, HEIF/heif, AVIF/avif, ICO/ico).");
        }

        $directory = PortalMotoristaDocumento::ensureStorageDirectory();
        $safeName = $this->buildStoredFilename($motoristaId, $veiculoId, $tipoDocumento, $extension);
        $destination = $directory . '/' . $safeName;

        if (!@copy($source, $destination)) {
            throw new Exception("Nao foi possivel salvar {$titulo}.");
        }

        $record = PortalMotoristaDocumento::findByContext($motoristaId, $tipoDocumento, $veiculoId) ?: new PortalMotoristaDocumento;
        $oldFile = $record->arquivo ?? null;

        $record->motorista_id = $motoristaId;
        $record->veiculo_id = $veiculoId;
        $record->tipo_documento = $tipoDocumento;
        $record->titulo = $titulo;
        $record->arquivo = $destination;
        $record->arquivo_original = basename((string) $uploadedFileName);
        $record->mime_type = function_exists('mime_content_type') ? mime_content_type($destination) : null;
        $record->store();

        if ($oldFile && $oldFile !== $destination && str_starts_with((string) $oldFile, $directory . '/') && file_exists($oldFile)) {
            @unlink($oldFile);
        }

        return 1;
    }

    private function deleteStoredDocument(int $motoristaId, string $tipoDocumento, ?int $veiculoId, string $titulo): int
    {
        if ($tipoDocumento !== PortalMotoristaDocumento::TIPO_CNH && empty($veiculoId)) {
            throw new Exception("Selecione um veiculo para remover {$titulo}.");
        }

        $record = PortalMotoristaDocumento::findByContext($motoristaId, $tipoDocumento, $veiculoId);
        if (!$record) {
            return 0;
        }

        $directory = PortalMotoristaDocumento::ensureStorageDirectory();
        $storedFile = $record->arquivo ?? null;
        $record->delete();

        if ($storedFile && str_starts_with((string) $storedFile, $directory . '/') && file_exists($storedFile)) {
            @unlink($storedFile);
        }

        return 1;
    }

    private function parseUploadFieldValue(?string $value, ?string $currentStoredFile = null): array
    {
        if (empty($value)) {
            return [
                'uploaded_name' => null,
                'delete_only' => false,
            ];
        }

        $decoded = json_decode(urldecode($value), true);
        if (!is_array($decoded)) {
            return [
                'uploaded_name' => ($currentStoredFile && $value === $currentStoredFile) ? null : $value,
                'delete_only' => false,
            ];
        }

        $fileName = trim((string) ($decoded['fileName'] ?? ''));
        $newFile = trim((string) ($decoded['newFile'] ?? ''));
        $delFile = trim((string) ($decoded['delFile'] ?? ''));

        if ($newFile !== '' || $this->isTemporaryUpload($fileName)) {
            return [
                'uploaded_name' => $newFile !== '' ? $newFile : $fileName,
                'delete_only' => false,
            ];
        }

        if ($delFile !== '') {
            return [
                'uploaded_name' => null,
                'delete_only' => !empty($currentStoredFile) && $delFile === $currentStoredFile,
            ];
        }

        return [
            'uploaded_name' => null,
            'delete_only' => false,
        ];
    }

    private function isTemporaryUpload(string $fileName): bool
    {
        if ($fileName === '') {
            return false;
        }

        $normalized = str_replace('\\', '/', $fileName);
        if (!str_starts_with($normalized, 'tmp/')) {
            return false;
        }

        return !str_starts_with($normalized, 'tmp/portal_motorista_documentos/');
    }

    private function resolveUploadedPath(string $uploadedName): ?string
    {
        $basename = basename($uploadedName);
        $candidates = [
            'tmp/' . $basename,
            $uploadedName,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate && file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function configureDocumentCropperField(TImageCropper $field, string $title): void
    {
        $field->setSize('100%', 170);
        $field->setAllowedExtensions(self::IMAGE_ALLOWED_EXTENSIONS);
        $field->enableFileHandling();
        $field->setWindowTitle("Ajustar imagem de {$title}");
        $field->setButtonLabel('Usar imagem');
        $field->setCropSize(1600, 1200);
    }

    private function getCropperInitialValue(?PortalMotoristaDocumento $doc): string
    {
        if (!$doc || !$this->isImageDocument($doc)) {
            return '';
        }

        return (string) $doc->arquivo;
    }

    private function isImageDocument(PortalMotoristaDocumento $doc): bool
    {
        $mimeType = (string) ($doc->mime_type ?? '');
        if ($mimeType !== '' && str_starts_with($mimeType, 'image/')) {
            return true;
        }

        $extension = strtolower((string) pathinfo((string) $doc->arquivo, PATHINFO_EXTENSION));
        return in_array($extension, self::IMAGE_ALLOWED_EXTENSIONS, true);
    }

    private function buildDownloadUrl(PortalMotoristaDocumento $doc): string
    {
        return 'download.php?' . http_build_query([
            'file' => (string) $doc->arquivo,
            'basename' => (string) ($doc->arquivo_original ?: basename((string) $doc->arquivo)),
        ]);
    }

    private function buildStoredFilename(int $motoristaId, ?int $veiculoId, string $tipoDocumento, string $extension): string
    {
        $chunks = [
            'motorista',
            $motoristaId,
            $tipoDocumento,
        ];

        if ($veiculoId) {
            $chunks[] = 'veiculo';
            $chunks[] = $veiculoId;
        }

        $chunks[] = date('YmdHis');

        return implode('_', $chunks) . '.' . $extension;
    }
}
