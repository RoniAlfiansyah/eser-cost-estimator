<?php

namespace App\Controllers\Api;

use App\Models\BasicCostApprovalHistoryModel;
use App\Models\BasicCostItemModel;
use App\Models\BasicCostModel;
use App\Models\BasicCostWorkTypeModel;
use App\Models\CategoryModel;
use App\Models\CostItemModel;
use App\Models\UserModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;

class BasicCostController extends AdminApiController
{
    private BasicCostModel $basicCostModel;
    private BasicCostApprovalHistoryModel $approvalHistoryModel;
    private BasicCostItemModel $basicCostItemModel;
    private BasicCostWorkTypeModel $basicCostWorkTypeModel;
    private CostItemModel $costItemModel;
    private CategoryModel $categoryModel;
    private UserModel $userModel;

    public function __construct()
    {
        $this->basicCostModel = new BasicCostModel();
        $this->approvalHistoryModel = new BasicCostApprovalHistoryModel();
        $this->basicCostItemModel = new BasicCostItemModel();
        $this->basicCostWorkTypeModel = new BasicCostWorkTypeModel();
        $this->costItemModel = new CostItemModel();
        $this->categoryModel = new CategoryModel();
        $this->userModel = new UserModel();
    }

    public function index()
    {
        helper('auth');

        $records = $this->basicCostModel
            ->orderBy('basic_costs.id', 'DESC')
            ->findAll();

        $data = array_map(fn (array $record): array => $this->transformBasicCost($record), $records);

        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => $data,
        ]);
    }

    public function show(int $id)
    {
        helper('auth');

        $basicCost = $this->findBasicCostOrFail($id);
        $items = $this->basicCostItemModel
            ->where('basic_cost_id', $id)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
        $workTypes = $this->loadWorkTypes($id);

        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => array_merge(
                $this->transformBasicCost($basicCost),
                [
                    'work_type_category_ids' => array_map(fn (array $row): int => (int) $row['category_id'], $workTypes),
                    'work_types' => $workTypes,
                    'items' => array_map(fn (array $item): array => $this->transformBasicCostItem($item), $items),
                    'subtotals' => $this->buildSubtotals($items),
                    'approval_history' => $this->loadApprovalHistory($id),
                ],
            ),
        ]);
    }

    public function store()
    {
        helper('auth');

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();

        if (! $this->validatePayload($payload)) {
            return $this->validationErrorResponse();
        }

        $prepared = $this->prepareDocumentPayload($payload);
        $db = db_connect();
        $db->transStart();

        $this->basicCostModel->insert([
            'client_name'     => $prepared['header']['client_name'],
            'project_name'    => $prepared['header']['project_name'],
            'location'        => $prepared['header']['location'],
            'basic_cost_date' => $prepared['header']['basic_cost_date'],
            'notes'           => $prepared['header']['notes'],
            'grand_total'     => $prepared['grand_total'],
            'status'          => BasicCostModel::STATUS_DRAFT,
            'created_by'      => current_user('id'),
        ]);

        $basicCostId = (int) $this->basicCostModel->getInsertID();
        $this->insertWorkTypes($basicCostId, $prepared['work_types']);
        $this->insertItems($basicCostId, $prepared['items']);
        $this->logApprovalHistory(
            $basicCostId,
            'created',
            null,
            BasicCostModel::STATUS_DRAFT,
            'Dokumen basic cost dibuat.',
        );

        $db->transComplete();

        if (! $db->transStatus()) {
            throw new DatabaseException('Gagal menyimpan basic cost.');
        }

        return $this->response->setStatusCode(201)->setJSON([
            'status'  => 'ok',
            'message' => 'Basic cost berhasil dibuat.',
            'data'    => ['id' => $basicCostId],
        ]);
    }

    public function update(int $id)
    {
        helper('auth');

        $record = $this->findBasicCostOrFail($id);
        if ($response = $this->ensureCanEdit($id)) {
            return $response;
        }
        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if (! $this->validatePayload($payload)) {
            return $this->validationErrorResponse();
        }

        $prepared = $this->prepareDocumentPayload($payload);
        $db = db_connect();
        $db->transStart();

        $this->basicCostModel->update($id, [
            'client_name'     => $prepared['header']['client_name'],
            'project_name'    => $prepared['header']['project_name'],
            'location'        => $prepared['header']['location'],
            'basic_cost_date' => $prepared['header']['basic_cost_date'],
            'notes'           => $prepared['header']['notes'],
            'grand_total'     => $prepared['grand_total'],
        ]);

        $this->basicCostWorkTypeModel->where('basic_cost_id', $id)->delete();
        $this->basicCostItemModel->where('basic_cost_id', $id)->delete();
        $this->insertWorkTypes($id, $prepared['work_types']);
        $this->insertItems($id, $prepared['items']);
        $this->logApprovalHistory(
            $id,
            'updated',
            $record['status'],
            $record['status'],
            'Dokumen basic cost diperbarui.',
        );

        $db->transComplete();

        if (! $db->transStatus()) {
            throw new DatabaseException('Gagal memperbarui basic cost.');
        }

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Basic cost berhasil diperbarui.',
            'data'    => ['id' => $id],
        ]);
    }

    public function delete(int $id)
    {
        helper('auth');

        if ($response = $this->ensureCanEdit($id)) {
            return $response;
        }
        $this->basicCostModel->delete($id);

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Basic cost berhasil dihapus.',
        ]);
    }

    public function submit(int $id)
    {
        helper('auth');

        $record = $this->findBasicCostOrFail($id);
        if ($response = $this->ensureCanSubmit($record)) {
            return $response;
        }

        $db = db_connect();
        $db->transStart();

        $this->basicCostModel->update($id, [
            'status'       => BasicCostModel::STATUS_SUBMITTED,
            'submitted_at' => date('Y-m-d H:i:s'),
            'submitted_by' => current_user('id'),
        ]);
        $this->logApprovalHistory(
            $id,
            'submitted',
            $record['status'],
            BasicCostModel::STATUS_SUBMITTED,
            'Dokumen diajukan untuk review atasan.',
        );

        $db->transComplete();

        if (! $db->transStatus()) {
            throw new DatabaseException('Gagal mengirim basic cost untuk review.');
        }

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Basic cost berhasil dikirim untuk review.',
        ]);
    }

    public function requestRevision(int $id)
    {
        helper('auth');

        $record = $this->findBasicCostOrFail($id);
        if ($response = $this->ensureCanReview($record)) {
            return $response;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $note = trim((string) ($payload['note'] ?? ''));
        if ($note === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Catatan revisi wajib diisi.',
                'errors'  => ['note' => 'Catatan revisi wajib diisi.'],
            ]);
        }

        $db = db_connect();
        $db->transStart();

        $this->basicCostModel->update($id, [
            'status'       => BasicCostModel::STATUS_REVISION,
            'reviewed_at'  => date('Y-m-d H:i:s'),
            'reviewed_by'  => current_user('id'),
            'review_notes' => $note,
        ]);
        $this->logApprovalHistory(
            $id,
            'revision_requested',
            $record['status'],
            BasicCostModel::STATUS_REVISION,
            $note,
        );

        $db->transComplete();

        if (! $db->transStatus()) {
            throw new DatabaseException('Gagal meminta revisi basic cost.');
        }

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Basic cost berhasil dikembalikan untuk revisi.',
        ]);
    }

    public function approve(int $id)
    {
        helper('auth');

        $record = $this->findBasicCostOrFail($id);
        if ($response = $this->ensureCanReview($record)) {
            return $response;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $note = trim((string) ($payload['note'] ?? ''));

        $db = db_connect();
        $db->transStart();

        $this->basicCostModel->update($id, [
            'status'       => BasicCostModel::STATUS_APPROVED,
            'reviewed_at'  => date('Y-m-d H:i:s'),
            'reviewed_by'  => current_user('id'),
            'review_notes' => $note !== '' ? $note : 'Disetujui.',
        ]);
        $this->logApprovalHistory(
            $id,
            'approved',
            $record['status'],
            BasicCostModel::STATUS_APPROVED,
            $note !== '' ? $note : 'Dokumen disetujui.',
        );

        $db->transComplete();

        if (! $db->transStatus()) {
            throw new DatabaseException('Gagal menyetujui basic cost.');
        }

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Basic cost berhasil disetujui.',
        ]);
    }

    private function findBasicCostOrFail(int $id): array
    {
        $record = $this->basicCostModel
            ->find($id);

        if ($record === null) {
            throw PageNotFoundException::forPageNotFound('Basic cost tidak ditemukan.');
        }

        return $record;
    }

    private function validatePayload(array $payload): bool
    {
        $rules = [
            'client_name'           => 'required|min_length[3]|max_length[180]',
            'project_name'          => 'required|min_length[3]|max_length[220]',
            'location'              => 'permit_empty|max_length[220]',
            'basic_cost_date'       => 'required|valid_date[Y-m-d]',
            'notes'                 => 'permit_empty|max_length[65535]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return false;
        }

        $items = $payload['items'] ?? null;
        if (! is_array($items) || $items === []) {
            $this->validator->setError('items', 'Minimal harus ada satu item basic cost.');
            return false;
        }

        return true;
    }

    private function validationErrorResponse()
    {
        return $this->response->setStatusCode(422)->setJSON([
            'status'  => 'error',
            'message' => 'Data basic cost tidak valid.',
            'errors'  => $this->validator->getErrors(),
        ]);
    }

    private function prepareDocumentPayload(array $payload): array
    {
        $workTypeCategoryIds = array_values(array_filter(array_map('intval', $payload['work_type_category_ids'] ?? [])));
        $workTypes = $this->prepareWorkTypes($workTypeCategoryIds);

        $preparedItems = [];
        $grandTotal = 0.0;

        foreach (array_values($payload['items']) as $index => $itemPayload) {
            $preparedItem = $this->prepareItemPayload($itemPayload, $index);
            $preparedItems[] = $preparedItem;
            $grandTotal += $preparedItem['total_price'];
        }

        return [
            'header' => [
                'client_name'           => trim((string) $payload['client_name']),
                'project_name'          => trim((string) $payload['project_name']),
                'location'              => trim((string) ($payload['location'] ?? '')),
                'basic_cost_date'       => (string) $payload['basic_cost_date'],
                'notes'                 => trim((string) ($payload['notes'] ?? '')),
            ],
            'work_types' => $workTypes,
            'items' => $preparedItems,
            'grand_total' => round($grandTotal, 2),
        ];
    }

    private function prepareWorkTypes(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $workTypes = [];
        foreach ($categoryIds as $categoryId) {
            $category = $this->categoryModel->find($categoryId);
            if ($category === null) {
                throw PageNotFoundException::forPageNotFound('Jenis pekerjaan tidak ditemukan.');
            }

            $workTypes[] = [
                'category_id'   => (int) $category['id'],
                'category_name' => $category['name'],
            ];
        }

        return $workTypes;
    }

    private function prepareItemPayload(array $itemPayload, int $index): array
    {
        $costItemId = (int) ($itemPayload['cost_item_id'] ?? 0);
        if ($costItemId <= 0) {
            throw PageNotFoundException::forPageNotFound('Item master biaya tidak valid.');
        }

        $masterItem = $this->costItemModel
            ->select('cost_items.*, categories.name as category_name, subcategories.name as subcategory_name, units.name as unit_name, units.symbol as unit_symbol')
            ->join('categories', 'categories.id = cost_items.category_id', 'left')
            ->join('subcategories', 'subcategories.id = cost_items.subcategory_id', 'left')
            ->join('units', 'units.id = cost_items.unit_id', 'left')
            ->find($costItemId);

        if ($masterItem === null) {
            throw PageNotFoundException::forPageNotFound('Master item biaya tidak ditemukan.');
        }

        $quantity = (float) ($itemPayload['quantity'] ?? 0);
        $duration = (float) ($itemPayload['duration'] ?? 0);
        $unitPrice = (float) ($itemPayload['unit_price'] ?? 0);
        $totalPrice = round($quantity * $duration * $unitPrice, 2);

        return [
            'category_id'      => $masterItem['category_id'] !== null ? (int) $masterItem['category_id'] : null,
            'category_name'    => $masterItem['category_name'] ?? '-',
            'subcategory_id'   => $masterItem['subcategory_id'] !== null ? (int) $masterItem['subcategory_id'] : null,
            'subcategory_name' => $masterItem['subcategory_name'] ?? null,
            'cost_item_id'     => (int) $masterItem['id'],
            'item_name'        => $masterItem['item_name'],
            'unit_id'          => $masterItem['unit_id'] !== null ? (int) $masterItem['unit_id'] : null,
            'unit_name'        => $masterItem['unit_name'] ?? null,
            'unit_symbol'      => $masterItem['unit_symbol'] ?? null,
            'quantity'         => $quantity,
            'duration'         => $duration,
            'unit_price'       => $unitPrice,
            'total_price'      => $totalPrice,
            'sort_order'       => $index + 1,
            'source_type'      => ($itemPayload['source_type'] ?? null) === 'schedule' ? 'schedule' : null,
            'source_key'       => mb_substr(trim((string) ($itemPayload['source_key'] ?? '')), 0, 190) ?: null,
        ];
    }

    private function insertItems(int $basicCostId, array $items): void
    {
        foreach ($items as $item) {
            $item['basic_cost_id'] = $basicCostId;
            $this->basicCostItemModel->insert($item);
        }
    }

    private function insertWorkTypes(int $basicCostId, array $workTypes): void
    {
        foreach ($workTypes as $workType) {
            $this->basicCostWorkTypeModel->insert([
                'basic_cost_id' => $basicCostId,
                'category_id'   => $workType['category_id'],
                'category_name' => $workType['category_name'],
            ]);
        }
    }

    private function loadWorkTypes(int $basicCostId): array
    {
        return array_map(
            static fn (array $row): array => [
                'category_id'   => (int) $row['category_id'],
                'category_name' => $row['category_name'],
            ],
            $this->basicCostWorkTypeModel
                ->where('basic_cost_id', $basicCostId)
                ->orderBy('category_name', 'ASC')
                ->findAll()
        );
    }

    private function transformBasicCost(array $record): array
    {
        $workTypes = $this->loadWorkTypes((int) $record['id']);
        $submittedBy = $record['submitted_by'] ? $this->userModel->find((int) $record['submitted_by']) : null;
        $reviewedBy = $record['reviewed_by'] ? $this->userModel->find((int) $record['reviewed_by']) : null;
        $permissions = $this->buildPermissions($record);

        return [
            'id'                     => (int) $record['id'],
            'client_name'            => $record['client_name'],
            'project_name'           => $record['project_name'],
            'location'               => $record['location'],
            'basic_cost_date'        => $record['basic_cost_date'],
            'work_type_category_ids' => array_map(fn (array $row): int => (int) $row['category_id'], $workTypes),
            'work_types'             => $workTypes,
            'work_type_names'        => array_map(fn (array $row): string => $row['category_name'], $workTypes),
            'notes'                  => $record['notes'],
            'grand_total'            => (float) $record['grand_total'],
            'status'                 => $record['status'] ?? BasicCostModel::STATUS_DRAFT,
            'submitted_at'           => $this->formatDateTime($record['submitted_at'] ?? null),
            'submitted_by'           => $record['submitted_by'] !== null ? (int) $record['submitted_by'] : null,
            'submitted_by_name'      => $submittedBy['name'] ?? null,
            'reviewed_at'            => $this->formatDateTime($record['reviewed_at'] ?? null),
            'reviewed_by'            => $record['reviewed_by'] !== null ? (int) $record['reviewed_by'] : null,
            'reviewed_by_name'       => $reviewedBy['name'] ?? null,
            'review_notes'           => $record['review_notes'] ?? null,
            'created_by'             => $record['created_by'] !== null ? (int) $record['created_by'] : null,
            'created_at'             => $this->formatDateTime($record['created_at'] ?? null),
            'updated_at'             => $this->formatDateTime($record['updated_at'] ?? null),
            'can_edit'               => $permissions['can_edit'],
            'can_submit'             => $permissions['can_submit'],
            'can_request_revision'   => $permissions['can_request_revision'],
            'can_approve'            => $permissions['can_approve'],
        ];
    }

    private function transformBasicCostItem(array $item): array
    {
        return [
            'id'               => (int) $item['id'],
            'basic_cost_id'    => (int) $item['basic_cost_id'],
            'category_id'      => $item['category_id'] !== null ? (int) $item['category_id'] : null,
            'category_name'    => $item['category_name'],
            'subcategory_id'   => $item['subcategory_id'] !== null ? (int) $item['subcategory_id'] : null,
            'subcategory_name' => $item['subcategory_name'],
            'cost_item_id'     => $item['cost_item_id'] !== null ? (int) $item['cost_item_id'] : null,
            'item_name'        => $item['item_name'],
            'unit_id'          => $item['unit_id'] !== null ? (int) $item['unit_id'] : null,
            'unit_name'        => $item['unit_name'],
            'unit_symbol'      => $item['unit_symbol'],
            'quantity'         => (float) $item['quantity'],
            'duration'         => (float) $item['duration'],
            'unit_price'       => (float) $item['unit_price'],
            'total_price'      => (float) $item['total_price'],
            'sort_order'       => (int) $item['sort_order'],
            'source_type'      => $item['source_type'] ?? null,
            'source_key'       => $item['source_key'] ?? null,
        ];
    }

    private function buildSubtotals(array $items): array
    {
        $subtotals = [];

        foreach ($items as $item) {
            $key = $item['category_name'] ?? '-';
            if (! isset($subtotals[$key])) {
                $subtotals[$key] = 0.0;
            }

            $subtotals[$key] += (float) $item['total_price'];
        }

        $result = [];
        foreach ($subtotals as $categoryName => $total) {
            $result[] = [
                'category_name' => $categoryName,
                'total'         => round($total, 2),
            ];
        }

        return $result;
    }

    private function loadApprovalHistory(int $basicCostId): array
    {
        $rows = $this->approvalHistoryModel
            ->where('basic_cost_id', $basicCostId)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();

        return array_map(
            fn (array $row): array => [
                'id'          => (int) $row['id'],
                'action'      => $row['action'],
                'status_from' => $row['status_from'],
                'status_to'   => $row['status_to'],
                'note'        => $row['note'],
                'actor_id'    => $row['actor_id'] !== null ? (int) $row['actor_id'] : null,
                'actor_name'  => $row['actor_name'],
                'created_at'  => $this->formatDateTime($row['created_at'] ?? null),
            ],
            $rows,
        );
    }

    private function logApprovalHistory(int $basicCostId, string $action, ?string $from, string $to, ?string $note = null): void
    {
        $this->approvalHistoryModel->insert([
            'basic_cost_id' => $basicCostId,
            'action'        => $action,
            'status_from'   => $from,
            'status_to'     => $to,
            'note'          => $note,
            'actor_id'      => current_user('id'),
            'actor_name'    => current_user('name'),
        ]);
    }

    private function buildPermissions(array $record): array
    {
        helper('auth');

        $status = $record['status'] ?? BasicCostModel::STATUS_DRAFT;
        $userId = (int) current_user('id');
        $role = (string) current_user('role');
        $isAdmin = $role === UserModel::ROLE_ADMIN;
        $isReviewer = in_array($role, [UserModel::ROLE_ADMIN, UserModel::ROLE_MANAGER], true);
        $isOwner = $isAdmin || ((int) ($record['created_by'] ?? 0) === $userId);
        $canEdit = $isOwner && in_array($status, [BasicCostModel::STATUS_DRAFT, BasicCostModel::STATUS_REVISION], true);

        return [
            'can_edit'             => $canEdit,
            'can_submit'           => $isOwner && in_array($status, [BasicCostModel::STATUS_DRAFT, BasicCostModel::STATUS_REVISION], true),
            'can_request_revision' => $isReviewer && $status === BasicCostModel::STATUS_SUBMITTED,
            'can_approve'          => $isReviewer && $status === BasicCostModel::STATUS_SUBMITTED,
        ];
    }

    private function ensureCanEdit(int $id)
    {
        $record = $this->findBasicCostOrFail($id);
        $permissions = $this->buildPermissions($record);
        if (! $permissions['can_edit']) {
            return $this->response->setStatusCode(403)->setJSON([
                'status'  => 'error',
                'message' => 'Dokumen ini tidak bisa diedit pada status saat ini.',
            ]);
        }

        return null;
    }

    private function ensureCanSubmit(array $record)
    {
        $permissions = $this->buildPermissions($record);
        if (! $permissions['can_submit']) {
            return $this->response->setStatusCode(403)->setJSON([
                'status'  => 'error',
                'message' => 'Dokumen ini tidak bisa dikirim untuk review.',
            ]);
        }

        return null;
    }

    private function ensureCanReview(array $record)
    {
        $permissions = $this->buildPermissions($record);
        if (! ($permissions['can_request_revision'] || $permissions['can_approve'])) {
            return $this->response->setStatusCode(403)->setJSON([
                'status'  => 'error',
                'message' => 'Anda tidak bisa mereview dokumen ini pada status saat ini.',
            ]);
        }

        return null;
    }
}
