<?php

namespace App\Controllers\Api;

use App\Models\CategoryModel;
use App\Models\CostItemModel;
use App\Models\SubcategoryModel;
use App\Models\UnitModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class SubcategoryController extends AdminApiController
{
    private SubcategoryModel $subcategoryModel;
    private CategoryModel $categoryModel;
    private UnitModel $unitModel;
    private CostItemModel $costItemModel;

    public function __construct()
    {
        $this->subcategoryModel = new SubcategoryModel();
        $this->categoryModel    = new CategoryModel();
        $this->unitModel        = new UnitModel();
        $this->costItemModel    = new CostItemModel();
    }

    public function index()
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $builder = $this->subcategoryModel
            ->select('subcategories.*, categories.name as category_name, units.name as unit_name, units.symbol as unit_symbol')
            ->join('categories', 'categories.id = subcategories.category_id', 'left')
            ->join('units', 'units.id = subcategories.unit_id', 'left')
            ->orderBy('categories.name', 'ASC')
            ->orderBy('subcategories.name', 'ASC');

        $categoryId = $this->request->getGet('category_id');
        $isActive   = $this->request->getGet('is_active');
        $keyword    = trim((string) $this->request->getGet('q'));

        if ($categoryId !== null && $categoryId !== '') {
            $builder->where('subcategories.category_id', (int) $categoryId);
        }

        if ($isActive !== null && $isActive !== '') {
            $builder->where('subcategories.is_active', (int) $isActive);
        }

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('subcategories.name', $keyword)
                ->orLike('categories.name', $keyword)
                ->groupEnd();
        }

        $subcategories = array_map(fn (array $subcategory): array => $this->transformSubcategory($subcategory), $builder->findAll());

        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => $subcategories,
        ]);
    }

    public function show(int $id)
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => $this->transformSubcategory($this->findSubcategoryOrFail($id)),
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

        $categoryId = (int) $payload['category_id'];
        $unitId = (int) $payload['unit_id'];
        $this->ensureCategoryExists($categoryId);
        $this->ensureUnitExists($unitId);

        $subcategoryPayload = [
            'category_id'       => $categoryId,
            'unit_id'           => $unitId,
            'name'              => trim((string) $payload['name']),
            'default_duration'  => (float) ($payload['default_duration'] ?? 0),
            'default_price'     => (float) ($payload['default_price'] ?? 0),
            'description'       => trim((string) ($payload['description'] ?? '')),
            'is_active'         => (int) ($payload['is_active'] ?? 1),
        ];

        $this->subcategoryModel->db->transStart();
        $this->subcategoryModel->insert($subcategoryPayload);

        $id = (int) $this->subcategoryModel->getInsertID();
        $this->syncCostItem((int) $id, $subcategoryPayload);
        $this->subcategoryModel->db->transComplete();

        return $this->response->setStatusCode(201)->setJSON([
            'status'  => 'ok',
            'message' => 'Subkategori berhasil ditambahkan.',
            'data'    => $this->transformSubcategory($this->findSubcategoryOrFail($id)),
        ]);
    }

    public function update(int $id)
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $this->findSubcategoryOrFail($id);
        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if (! $this->validateData($payload, $this->validationRules($id))) {
            return $this->validationErrorResponse();
        }

        $categoryId = (int) $payload['category_id'];
        $unitId = (int) $payload['unit_id'];
        $this->ensureCategoryExists($categoryId);
        $this->ensureUnitExists($unitId);

        $subcategoryPayload = [
            'category_id'      => $categoryId,
            'unit_id'          => $unitId,
            'name'             => trim((string) $payload['name']),
            'default_duration' => (float) ($payload['default_duration'] ?? 0),
            'default_price'    => (float) ($payload['default_price'] ?? 0),
            'description'      => trim((string) ($payload['description'] ?? '')),
            'is_active'        => (int) ($payload['is_active'] ?? 1),
        ];

        $this->subcategoryModel->db->transStart();
        $this->subcategoryModel->update($id, $subcategoryPayload);
        $this->syncCostItem($id, $subcategoryPayload);
        $this->subcategoryModel->db->transComplete();

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Subkategori berhasil diperbarui.',
            'data'    => $this->transformSubcategory($this->findSubcategoryOrFail($id)),
        ]);
    }

    public function delete(int $id)
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $this->findSubcategoryOrFail($id);
        $this->subcategoryModel->delete($id);

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Subkategori berhasil dihapus.',
        ]);
    }

    public function import()
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        $rows = $payload['rows'] ?? null;

        if (! is_array($rows) || $rows === []) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'File import tidak berisi baris data yang valid.',
            ]);
        }

        $summary = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors'  => [],
        ];

        $this->subcategoryModel->db->transStart();

        foreach ($rows as $index => $row) {
            try {
                $prepared = $this->prepareImportRow(is_array($row) ? $row : []);
                if ($prepared === null) {
                    $summary['skipped'] += 1;
                    continue;
                }

                $existing = null;
                if (isset($prepared['id']) && $prepared['id'] > 0) {
                    $existing = $this->subcategoryModel->find((int) $prepared['id']);
                }

                if ($existing === null) {
                    $existing = $this->subcategoryModel
                        ->where('category_id', (int) $prepared['category_id'])
                        ->where('name', $prepared['name'])
                        ->first();
                }

                $subcategoryPayload = [
                    'category_id'      => (int) $prepared['category_id'],
                    'unit_id'          => (int) $prepared['unit_id'],
                    'name'             => $prepared['name'],
                    'default_duration' => (float) $prepared['default_duration'],
                    'default_price'    => (float) $prepared['default_price'],
                    'description'      => $prepared['description'],
                    'is_active'        => (int) $prepared['is_active'],
                ];

                if ($existing === null) {
                    $this->subcategoryModel->insert($subcategoryPayload);
                    $subcategoryId = (int) $this->subcategoryModel->getInsertID();
                    $summary['created'] += 1;
                } else {
                    $subcategoryId = (int) $existing['id'];
                    $this->subcategoryModel->update($subcategoryId, $subcategoryPayload);
                    $summary['updated'] += 1;
                }

                $this->syncCostItem($subcategoryId, $subcategoryPayload);
            } catch (\Throwable $exception) {
                $summary['errors'][] = [
                    'row'     => $index + 2,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        if ($summary['errors'] !== []) {
            $this->subcategoryModel->db->transRollback();

            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Import subkategori gagal. Periksa baris yang bermasalah.',
                'summary' => $summary,
            ]);
        }

        $this->subcategoryModel->db->transComplete();

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Import subkategori berhasil diproses.',
            'summary' => $summary,
        ]);
    }

    private function findSubcategoryOrFail(int $id): array
    {
        $subcategory = $this->subcategoryModel
            ->select('subcategories.*, categories.name as category_name, units.name as unit_name, units.symbol as unit_symbol')
            ->join('categories', 'categories.id = subcategories.category_id', 'left')
            ->join('units', 'units.id = subcategories.unit_id', 'left')
            ->find($id);

        if ($subcategory === null) {
            throw PageNotFoundException::forPageNotFound('Subkategori tidak ditemukan.');
        }

        return $subcategory;
    }

    private function transformSubcategory(array $subcategory): array
    {
        return [
            'id'            => (int) $subcategory['id'],
            'category_id'   => (int) $subcategory['category_id'],
            'category_name' => $subcategory['category_name'] ?? null,
            'unit_id'       => isset($subcategory['unit_id']) ? (int) $subcategory['unit_id'] : null,
            'unit_name'     => $subcategory['unit_name'] ?? null,
            'unit_symbol'   => $subcategory['unit_symbol'] ?? null,
            'name'          => $subcategory['name'],
            'default_duration' => (float) ($subcategory['default_duration'] ?? 0),
            'default_price'    => (float) ($subcategory['default_price'] ?? 0),
            'description'      => $subcategory['description'] ?? null,
            'is_active'     => (bool) $subcategory['is_active'],
            'created_at'    => $this->formatDateTime($subcategory['created_at'] ?? null),
            'updated_at'    => $this->formatDateTime($subcategory['updated_at'] ?? null),
        ];
    }

    private function ensureCategoryExists(int $categoryId): void
    {
        if ($this->categoryModel->find($categoryId) === null) {
            throw PageNotFoundException::forPageNotFound('Kategori tidak ditemukan.');
        }
    }

    private function ensureUnitExists(int $unitId): void
    {
        if ($this->unitModel->find($unitId) === null) {
            throw PageNotFoundException::forPageNotFound('Satuan tidak ditemukan.');
        }
    }

    private function validationRules(?int $id = null): array
    {
        return [
            'category_id'      => 'required|integer',
            'unit_id'          => 'required|integer',
            'name'             => 'required|min_length[3]|max_length[150]',
            'default_duration' => 'permit_empty|decimal',
            'default_price'    => 'permit_empty|decimal',
            'description'      => 'permit_empty|max_length[65535]',
            'is_active'        => 'permit_empty|in_list[0,1]',
        ];
    }

    private function syncCostItem(int $subcategoryId, array $payload): void
    {
        $costItemPayload = [
            'category_id'      => (int) $payload['category_id'],
            'subcategory_id'   => $subcategoryId,
            'unit_id'          => (int) $payload['unit_id'],
            'item_name'        => trim((string) $payload['name']),
            'default_duration' => (float) ($payload['default_duration'] ?? 0),
            'default_price'    => (float) ($payload['default_price'] ?? 0),
            'description'      => trim((string) ($payload['description'] ?? '')),
            'is_active'        => (int) ($payload['is_active'] ?? 1),
        ];

        $existingItem = $this->costItemModel->where('subcategory_id', $subcategoryId)->first();

        if ($existingItem === null) {
            $this->costItemModel->insert($costItemPayload);
            return;
        }

        $this->costItemModel->update((int) $existingItem['id'], $costItemPayload);
    }

    private function prepareImportRow(array $row): ?array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[$this->normalizeImportKey((string) $key)] = is_string($value) ? trim($value) : $value;
        }

        $name = trim((string) ($normalized['nama_subkategori'] ?? $normalized['subkategori'] ?? $normalized['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $categoryId = $this->resolveCategoryId(
            $normalized['category_id'] ?? null,
            $normalized['nama_kategori'] ?? $normalized['category_name'] ?? null,
        );
        $unitId = $this->resolveUnitId(
            $normalized['unit_id'] ?? null,
            $normalized['nama_satuan'] ?? $normalized['unit_name'] ?? null,
            $normalized['simbol_satuan'] ?? $normalized['unit_symbol'] ?? null,
        );

        return [
            'id'               => isset($normalized['id']) && $normalized['id'] !== '' ? (int) $normalized['id'] : null,
            'category_id'      => $categoryId,
            'unit_id'          => $unitId,
            'name'             => $name,
            'default_duration' => $this->normalizeDecimalValue($normalized['durasi_default'] ?? $normalized['default_duration'] ?? 0),
            'default_price'    => $this->normalizeDecimalValue($normalized['harga_dasar'] ?? $normalized['default_price'] ?? 0),
            'description'      => trim((string) ($normalized['deskripsi'] ?? $normalized['description'] ?? '')),
            'is_active'        => $this->normalizeStatusValue($normalized['status'] ?? $normalized['is_active'] ?? 1),
        ];
    }

    private function normalizeImportKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = str_replace([' ', '-', '/', '(', ')'], '_', $key);
        return preg_replace('/_+/', '_', $key) ?? $key;
    }

    private function resolveCategoryId($categoryIdValue, $categoryNameValue): int
    {
        if ($categoryIdValue !== null && $categoryIdValue !== '') {
            $categoryId = (int) $categoryIdValue;
            $this->ensureCategoryExists($categoryId);
            return $categoryId;
        }

        $categoryName = trim((string) $categoryNameValue);
        if ($categoryName === '') {
            throw new \RuntimeException('Nama kategori wajib diisi.');
        }

        $category = $this->categoryModel->where('name', $categoryName)->first();
        if ($category === null) {
            throw new \RuntimeException(sprintf('Kategori "%s" tidak ditemukan.', $categoryName));
        }

        return (int) $category['id'];
    }

    private function resolveUnitId($unitIdValue, $unitNameValue, $unitSymbolValue): int
    {
        if ($unitIdValue !== null && $unitIdValue !== '') {
            $unitId = (int) $unitIdValue;
            $this->ensureUnitExists($unitId);
            return $unitId;
        }

        $unitName = trim((string) $unitNameValue);
        $unitSymbol = trim((string) $unitSymbolValue);

        if ($unitName !== '') {
            $unit = $this->unitModel->where('name', $unitName)->first();
            if ($unit !== null) {
                return (int) $unit['id'];
            }
        }

        if ($unitSymbol !== '') {
            $unit = $this->unitModel->where('symbol', $unitSymbol)->first();
            if ($unit !== null) {
                return (int) $unit['id'];
            }
        }

        throw new \RuntimeException('Satuan tidak ditemukan untuk baris import.');
    }

    private function normalizeDecimalValue($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace(['Rp', 'rp', '.', ' '], '', (string) $value);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function normalizeStatusValue($value): int
    {
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['0', 'inactive', 'nonaktif', 'false', 'no'], true)) {
            return 0;
        }

        return 1;
    }

    private function validationErrorResponse()
    {
        return $this->response->setStatusCode(422)->setJSON([
            'status'  => 'error',
            'message' => 'Data subkategori tidak valid.',
            'errors'  => $this->validator->getErrors(),
        ]);
    }
}
