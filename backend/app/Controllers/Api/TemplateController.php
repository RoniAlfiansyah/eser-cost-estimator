<?php

namespace App\Controllers\Api;

use App\Models\CategoryModel;
use App\Models\CostItemModel;
use App\Models\TemplateItemModel;
use App\Models\TemplateModel;
use App\Models\UserModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;

class TemplateController extends AdminApiController
{
    private TemplateModel $templateModel;
    private TemplateItemModel $templateItemModel;
    private CategoryModel $categoryModel;
    private CostItemModel $costItemModel;

    public function __construct()
    {
        $this->templateModel = new TemplateModel();
        $this->templateItemModel = new TemplateItemModel();
        $this->categoryModel = new CategoryModel();
        $this->costItemModel = new CostItemModel();
    }

    public function index()
    {
        helper('auth');

        $builder = $this->templateModel
            ->select('templates.*, categories.name as work_type_name, users.name as created_by_name, COUNT(template_items.id) as item_count')
            ->join('categories', 'categories.id = templates.work_type_id', 'left')
            ->join('users', 'users.id = templates.created_by', 'left')
            ->join('template_items', 'template_items.template_id = templates.id', 'left')
            ->groupBy('templates.id')
            ->orderBy('categories.name', 'ASC')
            ->orderBy('templates.template_name', 'ASC');

        $keyword = trim((string) $this->request->getGet('q'));
        $workTypeId = $this->request->getGet('work_type_id');
        $workTypeIds = trim((string) $this->request->getGet('work_type_ids'));
        $isActive = $this->request->getGet('is_active');

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('templates.template_name', $keyword)
                ->orLike('templates.description', $keyword)
                ->groupEnd();
        }

        if ($workTypeId !== null && $workTypeId !== '') {
            $builder->where('templates.work_type_id', (int) $workTypeId);
        } elseif ($workTypeIds !== '') {
            $ids = array_values(array_filter(array_map('intval', explode(',', $workTypeIds))));
            if ($ids !== []) {
                $builder->whereIn('templates.work_type_id', $ids);
            }
        }

        if ($isActive !== null && $isActive !== '') {
            $builder->where('templates.is_active', (int) $isActive);
        } elseif (current_user('role') !== UserModel::ROLE_ADMIN) {
            $builder->where('templates.is_active', 1);
        }

        $records = $builder->findAll();

        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => array_map(fn (array $record): array => $this->transformTemplate($record), $records),
        ]);
    }

    public function show(int $id)
    {
        helper('auth');

        $template = $this->findTemplateOrFail($id);

        if ((bool) $template['is_active'] === false && current_user('role') !== UserModel::ROLE_ADMIN) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Template tidak ditemukan.',
            ]);
        }

        $items = $this->templateItemModel
            ->select('template_items.*, cost_items.category_id, cost_items.subcategory_id, cost_items.item_name, cost_items.default_price, cost_items.description, categories.name as category_name, subcategories.name as subcategory_name, units.id as unit_id, units.name as unit_name, units.symbol as unit_symbol')
            ->join('cost_items', 'cost_items.id = template_items.cost_item_id')
            ->join('categories', 'categories.id = cost_items.category_id', 'left')
            ->join('subcategories', 'subcategories.id = cost_items.subcategory_id', 'left')
            ->join('units', 'units.id = cost_items.unit_id', 'left')
            ->where('template_items.template_id', $id)
            ->orderBy('template_items.sort_order', 'ASC')
            ->orderBy('template_items.id', 'ASC')
            ->findAll();

        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => array_merge(
                $this->transformTemplate($template),
                [
                    'items' => array_map(fn (array $item): array => $this->transformTemplateItem($item), $items),
                ],
            ),
        ]);
    }

    public function store()
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        helper('auth');
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();

        if (! $this->validatePayload($payload)) {
            return $this->validationErrorResponse();
        }

        $prepared = $this->preparePayload($payload);
        $db = db_connect();
        $db->transStart();

        $this->templateModel->insert([
            'work_type_id'  => $prepared['header']['work_type_id'],
            'template_name' => $prepared['header']['template_name'],
            'description'   => $prepared['header']['description'],
            'is_active'     => $prepared['header']['is_active'],
            'created_by'    => current_user('id'),
        ]);

        $templateId = (int) $this->templateModel->getInsertID();
        $this->insertItems($templateId, $prepared['items']);

        $db->transComplete();

        if (! $db->transStatus()) {
            throw new DatabaseException('Gagal menyimpan template.');
        }

        return $this->response->setStatusCode(201)->setJSON([
            'status'  => 'ok',
            'message' => 'Template berhasil dibuat.',
            'data'    => ['id' => $templateId],
        ]);
    }

    public function update(int $id)
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $this->findTemplateOrFail($id);
        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if (! $this->validatePayload($payload, $id)) {
            return $this->validationErrorResponse();
        }

        $prepared = $this->preparePayload($payload);
        $db = db_connect();
        $db->transStart();

        $this->templateModel->update($id, [
            'work_type_id'  => $prepared['header']['work_type_id'],
            'template_name' => $prepared['header']['template_name'],
            'description'   => $prepared['header']['description'],
            'is_active'     => $prepared['header']['is_active'],
        ]);

        $this->templateItemModel->where('template_id', $id)->delete();
        $this->insertItems($id, $prepared['items']);

        $db->transComplete();

        if (! $db->transStatus()) {
            throw new DatabaseException('Gagal memperbarui template.');
        }

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Template berhasil diperbarui.',
            'data'    => ['id' => $id],
        ]);
    }

    public function delete(int $id)
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $this->findTemplateOrFail($id);
        $this->templateModel->delete($id);

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Template berhasil dihapus.',
        ]);
    }

    private function findTemplateOrFail(int $id): array
    {
        $record = $this->templateModel
            ->select('templates.*, categories.name as work_type_name, users.name as created_by_name')
            ->join('categories', 'categories.id = templates.work_type_id', 'left')
            ->join('users', 'users.id = templates.created_by', 'left')
            ->find($id);

        if ($record === null) {
            throw PageNotFoundException::forPageNotFound('Template tidak ditemukan.');
        }

        return $record;
    }

    private function validatePayload(array $payload, ?int $id = null): bool
    {
        $rules = [
            'work_type_id'  => 'required|integer',
            'template_name' => 'required|min_length[3]|max_length[180]',
            'description'   => 'permit_empty|max_length[65535]',
            'is_active'     => 'permit_empty|in_list[0,1]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return false;
        }

        $workType = $this->categoryModel->find((int) $payload['work_type_id']);
        if ($workType === null) {
            $this->validator->setError('work_type_id', 'Jenis pekerjaan tidak ditemukan.');
            return false;
        }

        $duplicate = $this->templateModel
            ->where('work_type_id', (int) $payload['work_type_id'])
            ->where('template_name', trim((string) $payload['template_name']));

        if ($id !== null) {
            $duplicate->where('id !=', $id);
        }

        if ($duplicate->first() !== null) {
            $this->validator->setError('template_name', 'Nama template sudah digunakan untuk jenis pekerjaan ini.');
            return false;
        }

        $items = $payload['items'] ?? null;
        if (! is_array($items) || $items === []) {
            $this->validator->setError('items', 'Minimal harus ada satu item template.');
            return false;
        }

        $seenCostItemIds = [];
        foreach ($items as $index => $item) {
            $costItemId = (int) ($item['cost_item_id'] ?? 0);
            if ($costItemId <= 0 || $this->costItemModel->find($costItemId) === null) {
                $this->validator->setError("items.$index.cost_item_id", 'Item master biaya tidak valid.');
                return false;
            }

            if (in_array($costItemId, $seenCostItemIds, true)) {
                $this->validator->setError("items.$index.cost_item_id", 'Item master biaya duplikat dalam template.');
                return false;
            }

            $seenCostItemIds[] = $costItemId;
        }

        return true;
    }

    private function validationErrorResponse()
    {
        return $this->response->setStatusCode(422)->setJSON([
            'status'  => 'error',
            'message' => 'Data template tidak valid.',
            'errors'  => $this->validator->getErrors(),
        ]);
    }

    private function preparePayload(array $payload): array
    {
        $items = [];

        foreach (array_values($payload['items']) as $index => $item) {
            $items[] = [
                'cost_item_id'       => (int) $item['cost_item_id'],
                'default_quantity'   => $this->normalizeNullableNumber($item['default_quantity'] ?? null),
                'default_duration'   => $this->normalizeNullableNumber($item['default_duration'] ?? null),
                'default_unit_price' => $this->normalizeNullableNumber($item['default_unit_price'] ?? null),
                'sort_order'         => (int) ($item['sort_order'] ?? ($index + 1)),
            ];
        }

        return [
            'header' => [
                'work_type_id'  => (int) $payload['work_type_id'],
                'template_name' => trim((string) $payload['template_name']),
                'description'   => trim((string) ($payload['description'] ?? '')),
                'is_active'     => (int) ($payload['is_active'] ?? 1),
            ],
            'items' => $items,
        ];
    }

    private function normalizeNullableNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function insertItems(int $templateId, array $items): void
    {
        foreach ($items as $item) {
            $item['template_id'] = $templateId;
            $this->templateItemModel->insert($item);
        }
    }

    private function transformTemplate(array $record): array
    {
        return [
            'id'            => (int) $record['id'],
            'work_type_id'  => (int) $record['work_type_id'],
            'work_type_name'=> $record['work_type_name'] ?? null,
            'template_name' => $record['template_name'],
            'description'   => $record['description'],
            'is_active'     => (bool) $record['is_active'],
            'created_by'    => $record['created_by'] !== null ? (int) $record['created_by'] : null,
            'created_by_name' => $record['created_by_name'] ?? null,
            'item_count'    => isset($record['item_count']) ? (int) $record['item_count'] : 0,
            'created_at'    => $this->formatDateTime($record['created_at'] ?? null),
            'updated_at'    => $this->formatDateTime($record['updated_at'] ?? null),
        ];
    }

    private function transformTemplateItem(array $item): array
    {
        return [
            'id'                 => (int) $item['id'],
            'template_id'        => (int) $item['template_id'],
            'cost_item_id'       => (int) $item['cost_item_id'],
            'category_id'        => $item['category_id'] !== null ? (int) $item['category_id'] : null,
            'category_name'      => $item['category_name'] ?? null,
            'subcategory_id'     => $item['subcategory_id'] !== null ? (int) $item['subcategory_id'] : null,
            'subcategory_name'   => $item['subcategory_name'] ?? null,
            'item_name'          => $item['item_name'],
            'unit_id'            => $item['unit_id'] !== null ? (int) $item['unit_id'] : null,
            'unit_name'          => $item['unit_name'] ?? null,
            'unit_symbol'        => $item['unit_symbol'] ?? null,
            'default_quantity'   => $item['default_quantity'] !== null ? (float) $item['default_quantity'] : null,
            'default_duration'   => $item['default_duration'] !== null ? (float) $item['default_duration'] : null,
            'default_unit_price' => $item['default_unit_price'] !== null ? (float) $item['default_unit_price'] : null,
            'master_default_price' => isset($item['default_price']) ? (float) $item['default_price'] : 0.0,
            'description'        => $item['description'] ?? null,
            'sort_order'         => (int) $item['sort_order'],
        ];
    }
}
