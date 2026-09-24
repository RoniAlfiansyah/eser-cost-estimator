<?php

namespace App\Controllers\Api;

use App\Models\UnitModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class UnitController extends AdminApiController
{
    private UnitModel $unitModel;

    public function __construct()
    {
        $this->unitModel = new UnitModel();
    }

    public function index()
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $builder = $this->unitModel->orderBy('name', 'ASC');
        $isActive = $this->request->getGet('is_active');
        $keyword = trim((string) $this->request->getGet('q'));

        if ($isActive !== null && $isActive !== '') {
            $builder->where('is_active', (int) $isActive);
        }

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('name', $keyword)
                ->orLike('symbol', $keyword)
                ->groupEnd();
        }

        $units = array_map(fn (array $unit): array => $this->transformUnit($unit), $builder->findAll());

        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => $units,
        ]);
    }

    public function show(int $id)
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => $this->transformUnit($this->findUnitOrFail($id)),
        ]);
    }

    public function store()
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();

        if (! $this->validateData($payload, $this->validationRules())) {
            return $this->validationErrorResponse();
        }

        $this->unitModel->insert([
            'name'      => trim((string) $payload['name']),
            'symbol'    => trim((string) $payload['symbol']),
            'is_active' => (int) ($payload['is_active'] ?? 1),
        ]);

        $id = (int) $this->unitModel->getInsertID();

        return $this->response->setStatusCode(201)->setJSON([
            'status'  => 'ok',
            'message' => 'Satuan berhasil ditambahkan.',
            'data'    => $this->transformUnit($this->findUnitOrFail($id)),
        ]);
    }

    public function update(int $id)
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $this->findUnitOrFail($id);
        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if (! $this->validateData($payload, $this->validationRules($id))) {
            return $this->validationErrorResponse();
        }

        $this->unitModel->update($id, [
            'name'      => trim((string) $payload['name']),
            'symbol'    => trim((string) $payload['symbol']),
            'is_active' => (int) ($payload['is_active'] ?? 1),
        ]);

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Satuan berhasil diperbarui.',
            'data'    => $this->transformUnit($this->findUnitOrFail($id)),
        ]);
    }

    public function delete(int $id)
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $this->findUnitOrFail($id);
        $this->unitModel->delete($id);

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Satuan berhasil dihapus.',
        ]);
    }

    private function findUnitOrFail(int $id): array
    {
        $unit = $this->unitModel->find($id);

        if ($unit === null) {
            throw PageNotFoundException::forPageNotFound('Satuan tidak ditemukan.');
        }

        return $unit;
    }

    private function transformUnit(array $unit): array
    {
        return [
            'id'         => (int) $unit['id'],
            'name'       => $unit['name'],
            'symbol'     => $unit['symbol'],
            'is_active'  => (bool) $unit['is_active'],
            'created_at' => $this->formatDateTime($unit['created_at'] ?? null),
            'updated_at' => $this->formatDateTime($unit['updated_at'] ?? null),
        ];
    }

    private function validationRules(?int $id = null): array
    {
        $nameRule = 'required|min_length[2]|max_length[100]|is_unique[units.name]';
        $symbolRule = 'required|min_length[1]|max_length[30]|is_unique[units.symbol]';

        if ($id !== null) {
            $nameRule = "required|min_length[2]|max_length[100]|is_unique[units.name,id,{$id}]";
            $symbolRule = "required|min_length[1]|max_length[30]|is_unique[units.symbol,id,{$id}]";
        }

        return [
            'name'      => $nameRule,
            'symbol'    => $symbolRule,
            'is_active' => 'permit_empty|in_list[0,1]',
        ];
    }

    private function validationErrorResponse()
    {
        return $this->response->setStatusCode(422)->setJSON([
            'status'  => 'error',
            'message' => 'Data satuan tidak valid.',
            'errors'  => $this->validator->getErrors(),
        ]);
    }
}
