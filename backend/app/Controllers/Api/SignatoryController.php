<?php

namespace App\Controllers\Api;

use App\Models\SignatoryModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class SignatoryController extends AdminApiController
{
    private const SIGNATURE_UPLOAD_DIR = 'uploads/signatories/';

    private SignatoryModel $signatoryModel;

    public function __construct()
    {
        $this->signatoryModel = new SignatoryModel();
    }

    public function index()
    {
        helper('auth');

        $builder = $this->signatoryModel->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC');
        $keyword = trim((string) $this->request->getGet('q'));
        $isActive = $this->request->getGet('is_active');

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('name', $keyword)
                ->orLike('position_title', $keyword)
                ->groupEnd();
        }

        if ($isActive !== null && $isActive !== '') {
            $builder->where('is_active', (int) $isActive);
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'data' => array_map(fn (array $record): array => $this->transformRecord($record), $builder->findAll()),
        ]);
    }

    public function show(int $id)
    {
        helper('auth');

        return $this->response->setJSON([
            'status' => 'ok',
            'data' => $this->transformRecord($this->findRecordOrFail($id)),
        ]);
    }

    public function store()
    {
        helper('auth');
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();

        if (! $this->validatePayload($payload)) {
            return $this->validationErrorResponse();
        }

        $signatureImagePath = $this->prepareSignatureImagePath($payload);
        if ($signatureImagePath === false) {
            return $this->validationErrorResponse();
        }

        $this->signatoryModel->insert($this->preparePayload($payload, $signatureImagePath));

        return $this->response->setStatusCode(201)->setJSON([
            'status' => 'ok',
            'message' => 'Penandatangan berhasil ditambahkan.',
            'data' => ['id' => (int) $this->signatoryModel->getInsertID()],
        ]);
    }

    public function update(int $id)
    {
        helper('auth');
        $existingRecord = $this->findRecordOrFail($id);
        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if (! $this->validatePayload($payload, $id)) {
            return $this->validationErrorResponse();
        }

        $signatureImagePath = $this->prepareSignatureImagePath($payload, $existingRecord['signature_image_path'] ?? null);
        if ($signatureImagePath === false) {
            return $this->validationErrorResponse();
        }

        $this->signatoryModel->update($id, $this->preparePayload($payload, $signatureImagePath));

        if (($existingRecord['signature_image_path'] ?? null) !== $signatureImagePath) {
            $this->deleteSignatureImageFile($existingRecord['signature_image_path'] ?? null);
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'message' => 'Penandatangan berhasil diperbarui.',
            'data' => ['id' => $id],
        ]);
    }

    public function delete(int $id)
    {
        helper('auth');
        $record = $this->findRecordOrFail($id);
        $this->signatoryModel->delete($id);
        $this->deleteSignatureImageFile($record['signature_image_path'] ?? null);

        return $this->response->setJSON([
            'status' => 'ok',
            'message' => 'Penandatangan berhasil dihapus.',
        ]);
    }

    private function validatePayload(array $payload, ?int $id = null): bool
    {
        $nameRule = 'required|min_length[3]|max_length[180]|is_unique[signatories.name]';
        if ($id !== null) {
            $nameRule = "required|min_length[3]|max_length[180]|is_unique[signatories.name,id,{$id}]";
        }

        return $this->validateData($payload, [
            'name' => $nameRule,
            'position_title' => 'required|min_length[2]|max_length[180]',
            'is_active' => 'permit_empty|in_list[0,1]',
            'sort_order' => 'permit_empty|integer',
        ]) && $this->validateSignatureImagePayload($payload);
    }

    private function validationErrorResponse()
    {
        return $this->response->setStatusCode(422)->setJSON([
            'status' => 'error',
            'message' => 'Data penandatangan tidak valid.',
            'errors' => $this->validator->getErrors(),
        ]);
    }

    private function preparePayload(array $payload, ?string $signatureImagePath = null): array
    {
        return [
            'name' => trim((string) ($payload['name'] ?? '')),
            'position_title' => trim((string) ($payload['position_title'] ?? '')),
            'signature_image_path' => $signatureImagePath,
            'is_active' => (int) ($payload['is_active'] ?? 1),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
        ];
    }

    private function findRecordOrFail(int $id): array
    {
        $record = $this->signatoryModel->find($id);
        if ($record === null) {
            throw PageNotFoundException::forPageNotFound('Penandatangan tidak ditemukan.');
        }

        return $record;
    }

    private function transformRecord(array $record): array
    {
        return [
            'id' => (int) $record['id'],
            'name' => $record['name'],
            'position_title' => $record['position_title'],
            'signature_image_path' => $record['signature_image_path'] ?? null,
            'signature_image_url' => $this->buildPublicAssetUrl($record['signature_image_path'] ?? null),
            'signature_image_data_url' => $this->buildImageDataUrl($record['signature_image_path'] ?? null),
            'is_active' => (bool) ($record['is_active'] ?? false),
            'sort_order' => (int) ($record['sort_order'] ?? 0),
            'created_at' => $this->formatDateTime($record['created_at'] ?? null),
            'updated_at' => $this->formatDateTime($record['updated_at'] ?? null),
        ];
    }

    private function validateSignatureImagePayload(array $payload): bool
    {
        $base64 = trim((string) ($payload['signature_image_base64'] ?? ''));
        if ($base64 === '') {
            return true;
        }

        if (! preg_match('#^data:image/png;base64,#i', $base64)) {
            $this->validator->setError('signature_image_base64', 'File tanda tangan harus berupa gambar PNG.');
            return false;
        }

        $binary = base64_decode((string) preg_replace('#^data:image/png;base64,#i', '', $base64), true);
        if ($binary === false || $binary === '') {
            $this->validator->setError('signature_image_base64', 'File tanda tangan PNG tidak valid.');
            return false;
        }

        return true;
    }

    private function prepareSignatureImagePath(array $payload, ?string $existingPath = null)
    {
        $shouldRemove = filter_var($payload['remove_signature_image'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $base64 = trim((string) ($payload['signature_image_base64'] ?? ''));

        if ($base64 !== '') {
            return $this->saveSignatureImage($base64);
        }

        if ($shouldRemove) {
            return null;
        }

        return $existingPath;
    }

    private function saveSignatureImage(string $base64): string
    {
        $binary = base64_decode((string) preg_replace('#^data:image/png;base64,#i', '', $base64), true);
        $directory = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::SIGNATURE_UPLOAD_DIR);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $fileName = 'signature-' . bin2hex(random_bytes(8)) . '.png';
        file_put_contents($directory . $fileName, $binary);

        return self::SIGNATURE_UPLOAD_DIR . $fileName;
    }

    private function deleteSignatureImageFile(?string $path): void
    {
        $normalizedPath = trim((string) $path);
        if ($normalizedPath === '') {
            return;
        }

        $fullPath = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($normalizedPath, '/'));
        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }

    private function buildPublicAssetUrl(?string $path): ?string
    {
        $normalizedPath = trim((string) $path);
        if ($normalizedPath === '') {
            return null;
        }

        return rtrim(config('App')->baseURL, '/') . '/' . ltrim($normalizedPath, '/');
    }

    private function buildImageDataUrl(?string $path): ?string
    {
        $normalizedPath = trim((string) $path);
        if ($normalizedPath === '') {
            return null;
        }

        $fullPath = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($normalizedPath, '/'));
        if (! is_file($fullPath)) {
            return null;
        }

        $binary = file_get_contents($fullPath);
        if ($binary === false || $binary === '') {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($binary);
    }
}
