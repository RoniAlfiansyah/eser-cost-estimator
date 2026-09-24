<?php

namespace App\Controllers\Api;

use App\Models\CategoryModel;
use App\Models\CostItemModel;
use App\Models\SubcategoryModel;
use App\Models\UnitModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class CostItemController extends AdminApiController
{
    private CostItemModel $costItemModel;
    private CategoryModel $categoryModel;
    private SubcategoryModel $subcategoryModel;
    private UnitModel $unitModel;

    public function __construct()
    {
        $this->costItemModel    = new CostItemModel();
        $this->categoryModel    = new CategoryModel();
        $this->subcategoryModel = new SubcategoryModel();
        $this->unitModel        = new UnitModel();
    }

    public function index()
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $builder = $this->costItemModel
            ->select('cost_items.*, categories.name as category_name, subcategories.name as subcategory_name, units.name as unit_name, units.symbol as unit_symbol')
            ->join('categories', 'categories.id = cost_items.category_id', 'left')
            ->join('subcategories', 'subcategories.id = cost_items.subcategory_id', 'left')
            ->join('units', 'units.id = cost_items.unit_id', 'left')
            ->orderBy('categories.name', 'ASC')
            ->orderBy('subcategories.name', 'ASC')
            ->orderBy('cost_items.item_name', 'ASC');

        $categoryId    = $this->request->getGet('category_id');
        $subcategoryId = $this->request->getGet('subcategory_id');
        $isActive      = $this->request->getGet('is_active');
        $keyword       = trim((string) $this->request->getGet('q'));

        if ($categoryId !== null && $categoryId !== '') {
            $builder->where('cost_items.category_id', (int) $categoryId);
        }

        if ($subcategoryId !== null && $subcategoryId !== '') {
            $builder->where('cost_items.subcategory_id', (int) $subcategoryId);
        }

        if ($isActive !== null && $isActive !== '') {
            $builder->where('cost_items.is_active', (int) $isActive);
        }

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('cost_items.item_name', $keyword)
                ->orLike('cost_items.description', $keyword)
                ->groupEnd();
        }

        $items = array_map(fn (array $item): array => $this->transformCostItem($item), $builder->findAll());

        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => $items,
        ]);
    }

    public function show(int $id)
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => $this->transformCostItem($this->findCostItemOrFail($id)),
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

        $this->costItemModel->insert($this->preparePayload($payload));
        $id = (int) $this->costItemModel->getInsertID();

        return $this->response->setStatusCode(201)->setJSON([
            'status'  => 'ok',
            'message' => 'Item biaya berhasil ditambahkan.',
            'data'    => $this->transformCostItem($this->findCostItemOrFail($id)),
        ]);
    }

    public function update(int $id)
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $this->findCostItemOrFail($id);
        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if (! $this->validateData($payload, $this->validationRules($id))) {
            return $this->validationErrorResponse();
        }

        $this->costItemModel->update($id, $this->preparePayload($payload));

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Item biaya berhasil diperbarui.',
            'data'    => $this->transformCostItem($this->findCostItemOrFail($id)),
        ]);
    }

    public function delete(int $id)
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $this->findCostItemOrFail($id);
        $this->costItemModel->delete($id);

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Item biaya berhasil dihapus.',
        ]);
    }

    private function findCostItemOrFail(int $id): array
    {
        $item = $this->costItemModel
            ->select('cost_items.*, categories.name as category_name, subcategories.name as subcategory_name, units.name as unit_name, units.symbol as unit_symbol')
            ->join('categories', 'categories.id = cost_items.category_id', 'left')
            ->join('subcategories', 'subcategories.id = cost_items.subcategory_id', 'left')
            ->join('units', 'units.id = cost_items.unit_id', 'left')
            ->find($id);

        if ($item === null) {
            throw PageNotFoundException::forPageNotFound('Item biaya tidak ditemukan.');
        }

        return $item;
    }

    private function transformCostItem(array $item): array
    {
        return [
            'id'               => (int) $item['id'],
            'category_id'      => (int) $item['category_id'],
            'category_name'    => $item['category_name'] ?? null,
            'subcategory_id'   => (int) $item['subcategory_id'],
            'subcategory_name' => $item['subcategory_name'] ?? null,
            'unit_id'          => (int) $item['unit_id'],
            'unit_name'        => $item['unit_name'] ?? null,
            'unit_symbol'      => $item['unit_symbol'] ?? null,
            'item_name'        => $item['item_name'],
            'default_duration' => (float) $item['default_duration'],
            'default_price'    => (float) $item['default_price'],
            'description'      => $item['description'],
            'is_active'        => (bool) $item['is_active'],
            'created_at'       => $this->formatDateTime($item['created_at'] ?? null),
            'updated_at'       => $this->formatDateTime($item['updated_at'] ?? null),
        ];
    }

    private function preparePayload(array $payload): array
    {
        $categoryId = (int) $payload['category_id'];
        $subcategoryId = (int) $payload['subcategory_id'];
        $unitId = (int) $payload['unit_id'];
        $subcategory = $this->ensureRelationsAreValid($categoryId, $subcategoryId, $unitId);

        return [
            'category_id'      => $categoryId,
            'subcategory_id'   => $subcategoryId,
            'unit_id'          => (int) $payload['unit_id'],
            'item_name'        => trim((string) $subcategory['name']),
            'default_duration' => (float) ($payload['default_duration'] ?? 0),
            'default_price'    => (float) ($payload['default_price'] ?? 0),
            'description'      => trim((string) ($payload['description'] ?? '')),
            'is_active'        => (int) ($payload['is_active'] ?? 1),
        ];
    }

    private function ensureRelationsAreValid(int $categoryId, int $subcategoryId, int $unitId): array
    {
        $category = $this->categoryModel->find($categoryId);
        if ($category === null) {
            throw PageNotFoundException::forPageNotFound('Kategori tidak ditemukan.');
        }

        $subcategory = $this->subcategoryModel->find($subcategoryId);
        if ($subcategory === null || (int) $subcategory['category_id'] !== $categoryId) {
            throw PageNotFoundException::forPageNotFound('Subkategori tidak valid untuk kategori yang dipilih.');
        }

        if ($this->unitModel->find($unitId) === null) {
            throw PageNotFoundException::forPageNotFound('Satuan tidak ditemukan.');
        }

        return $subcategory;
    }

    private function validationRules(?int $id = null): array
    {
        return [
            'category_id'      => 'required|integer',
            'subcategory_id'   => 'required|integer',
            'unit_id'          => 'required|integer',
            'default_duration' => 'permit_empty|decimal',
            'default_price'    => 'permit_empty|decimal',
            'description'      => 'permit_empty|max_length[65535]',
            'is_active'        => 'permit_empty|in_list[0,1]',
        ];
    }

    private function validationErrorResponse()
    {
        return $this->response->setStatusCode(422)->setJSON([
            'status'  => 'error',
            'message' => 'Data item biaya tidak valid.',
            'errors'  => $this->validator->getErrors(),
        ]);
    }
}
