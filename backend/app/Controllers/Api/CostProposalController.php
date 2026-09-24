<?php

namespace App\Controllers\Api;

use App\Models\BasicCostItemModel;
use App\Models\BasicCostModel;
use App\Models\CostProposalApprovalHistoryModel;
use App\Models\CostProposalItemModel;
use App\Models\CostProposalModel;
use App\Models\SignatoryModel;
use App\Models\UserModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;

class CostProposalController extends AdminApiController
{
    private const PROPOSAL_SIGNATURE_UPLOAD_DIR = 'uploads/cost-proposals/signatures/';

    private CostProposalModel $costProposalModel;
    private CostProposalApprovalHistoryModel $approvalHistoryModel;
    private CostProposalItemModel $costProposalItemModel;
    private BasicCostModel $basicCostModel;
    private BasicCostItemModel $basicCostItemModel;
    private SignatoryModel $signatoryModel;
    private UserModel $userModel;

    public function __construct()
    {
        $this->costProposalModel = new CostProposalModel();
        $this->approvalHistoryModel = new CostProposalApprovalHistoryModel();
        $this->costProposalItemModel = new CostProposalItemModel();
        $this->basicCostModel = new BasicCostModel();
        $this->basicCostItemModel = new BasicCostItemModel();
        $this->signatoryModel = new SignatoryModel();
        $this->userModel = new UserModel();
    }

    public function index()
    {
        helper('auth');
        $records = $this->costProposalModel->orderBy('id', 'DESC')->findAll();

        return $this->response->setJSON([
            'status' => 'ok',
            'data' => array_map(fn(array $record): array => $this->transformProposal($record), $records),
        ]);
    }

    public function show(int $id)
    {
        helper('auth');
        $proposal = $this->ensureProposalHasPublicToken($this->findProposalOrFail($id));
        $items = $this->costProposalItemModel->where('cost_proposal_id', $id)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();

        return $this->response->setJSON([
            'status' => 'ok',
            'data' => array_merge(
                $this->transformProposal($proposal),
                [
                    'items' => array_map(fn(array $item): array => $this->transformProposalItem($item), $items),
                    'approval_history' => $this->loadApprovalHistory($id),
                ],
            ),
        ]);
    }

    public function publicShow(string $token)
    {
        $proposal = $this->costProposalModel->where('public_token', $token)->first();
        if ($proposal === null) {
            throw PageNotFoundException::forPageNotFound('Dokumen proposal tidak ditemukan.');
        }

        $status = (string) ($proposal['status'] ?? CostProposalModel::STATUS_DRAFT);
        if (! in_array($status, [CostProposalModel::STATUS_APPROVED, CostProposalModel::STATUS_ISSUED], true)) {
            throw PageNotFoundException::forPageNotFound('Dokumen proposal belum tersedia untuk publik.');
        }

        $items = $this->costProposalItemModel
            ->where('cost_proposal_id', (int) $proposal['id'])
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        return $this->response->setJSON([
            'status' => 'ok',
            'data' => array_merge(
                $this->transformProposal($proposal),
                [
                    'items' => array_map(fn(array $item): array => $this->transformProposalItem($item), $items),
                    'public_url' => $this->buildPublicUrl((string) ($proposal['public_token'] ?? '')),
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

        $prepared = $this->preparePayload($payload, $this->findApprovedBasicCost((int) $payload['basic_cost_id']));
        $db = db_connect();
        $db->transStart();

        $this->costProposalModel->insert(array_merge($prepared['header'], $prepared['totals'], [
            'status' => CostProposalModel::STATUS_DRAFT,
            'created_by' => current_user('id'),
            'public_token' => $this->generatePublicToken(),
        ]));
        $proposalId = (int) $this->costProposalModel->getInsertID();
        $this->insertItems($proposalId, $prepared['items']);
        $this->logApprovalHistory($proposalId, 'created', null, CostProposalModel::STATUS_DRAFT, 'Dokumen cost proposal dibuat.');

        $db->transComplete();
        if (! $db->transStatus()) {
            throw new DatabaseException('Gagal menyimpan cost proposal.');
        }

        return $this->response->setStatusCode(201)->setJSON([
            'status' => 'ok',
            'message' => 'Cost proposal berhasil dibuat.',
            'data' => ['id' => $proposalId],
        ]);
    }

    public function update(int $id)
    {
        helper('auth');
        $proposal = $this->ensureProposalHasPublicToken($this->findProposalOrFail($id));
        if ($response = $this->ensureCanEdit($proposal)) {
            return $response;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        if (! $this->validatePayload($payload, $id)) {
            return $this->validationErrorResponse();
        }

        $prepared = $this->preparePayload($payload, $this->findApprovedBasicCost((int) $payload['basic_cost_id']));
        $db = db_connect();
        $db->transStart();

        $this->costProposalModel->update($id, array_merge($prepared['header'], $prepared['totals']));
        $this->costProposalItemModel->where('cost_proposal_id', $id)->delete();
        $this->insertItems($id, $prepared['items']);
        $this->logApprovalHistory($id, 'updated', $proposal['status'] ?? CostProposalModel::STATUS_DRAFT, $proposal['status'] ?? CostProposalModel::STATUS_DRAFT, 'Dokumen cost proposal diperbarui.');

        $db->transComplete();
        if (! $db->transStatus()) {
            throw new DatabaseException('Gagal memperbarui cost proposal.');
        }

        if (($proposal['signatory_signature_path'] ?? null) !== ($prepared['header']['signatory_signature_path'] ?? null)) {
            $this->deletePublicAssetFile($proposal['signatory_signature_path'] ?? null);
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'message' => 'Cost proposal berhasil diperbarui.',
            'data' => ['id' => $id],
        ]);
    }

    public function delete(int $id)
    {
        helper('auth');
        $proposal = $this->findProposalOrFail($id);
        if ($response = $this->ensureCanEdit($proposal)) {
            return $response;
        }

        $this->deletePublicAssetFile($proposal['signatory_signature_path'] ?? null);
        $this->costProposalModel->delete($id);

        return $this->response->setJSON(['status' => 'ok', 'message' => 'Cost proposal berhasil dihapus.']);
    }

    public function submit(int $id)
    {
        helper('auth');
        $record = $this->findProposalOrFail($id);
        if ($response = $this->ensureCanSubmit($record)) {
            return $response;
        }

        $db = db_connect();
        $db->transStart();
        $this->costProposalModel->update($id, [
            'status' => CostProposalModel::STATUS_SUBMITTED,
            'submitted_at' => date('Y-m-d H:i:s'),
            'submitted_by' => current_user('id'),
        ]);
        $this->logApprovalHistory($id, 'submitted', $record['status'] ?? CostProposalModel::STATUS_DRAFT, CostProposalModel::STATUS_SUBMITTED, 'Dokumen diajukan untuk review atasan.');
        $db->transComplete();

        if (! $db->transStatus()) {
            throw new DatabaseException('Gagal mengirim cost proposal untuk review.');
        }

        return $this->response->setJSON(['status' => 'ok', 'message' => 'Cost proposal berhasil dikirim untuk review.']);
    }

    public function requestRevision(int $id)
    {
        helper('auth');
        $record = $this->findProposalOrFail($id);
        if ($response = $this->ensureCanReview($record)) {
            return $response;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $note = trim((string) ($payload['note'] ?? ''));
        if ($note === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Catatan revisi wajib diisi.',
                'errors' => ['note' => 'Catatan revisi wajib diisi.'],
            ]);
        }

        $db = db_connect();
        $db->transStart();
        $this->costProposalModel->update($id, [
            'status' => CostProposalModel::STATUS_REVISION,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'reviewed_by' => current_user('id'),
            'review_notes' => $note,
        ]);
        $this->logApprovalHistory($id, 'revision_requested', $record['status'] ?? CostProposalModel::STATUS_DRAFT, CostProposalModel::STATUS_REVISION, $note);
        $db->transComplete();

        if (! $db->transStatus()) {
            throw new DatabaseException('Gagal meminta revisi cost proposal.');
        }

        return $this->response->setJSON(['status' => 'ok', 'message' => 'Cost proposal berhasil dikembalikan untuk revisi.']);
    }

    public function approve(int $id)
    {
        helper('auth');
        $record = $this->findProposalOrFail($id);
        if ($response = $this->ensureCanReview($record)) {
            return $response;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $note = trim((string) ($payload['note'] ?? ''));

        $db = db_connect();
        $db->transStart();
        $this->costProposalModel->update($id, [
            'status' => CostProposalModel::STATUS_APPROVED,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'reviewed_by' => current_user('id'),
            'review_notes' => $note !== '' ? $note : 'Disetujui.',
        ]);
        $this->logApprovalHistory($id, 'approved', $record['status'] ?? CostProposalModel::STATUS_DRAFT, CostProposalModel::STATUS_APPROVED, $note !== '' ? $note : 'Dokumen disetujui.');
        $db->transComplete();

        if (! $db->transStatus()) {
            throw new DatabaseException('Gagal menyetujui cost proposal.');
        }

        return $this->response->setJSON(['status' => 'ok', 'message' => 'Cost proposal berhasil disetujui.']);
    }

    public function issue(int $id)
    {
        helper('auth');
        $record = $this->findProposalOrFail($id);
        if ($response = $this->ensureCanIssue($record)) {
            return $response;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $note = trim((string) ($payload['note'] ?? ''));

        $db = db_connect();
        $db->transStart();
        $this->costProposalModel->update($id, ['status' => CostProposalModel::STATUS_ISSUED]);
        $this->logApprovalHistory($id, 'issued', $record['status'] ?? CostProposalModel::STATUS_APPROVED, CostProposalModel::STATUS_ISSUED, $note !== '' ? $note : 'Dokumen diterbitkan untuk client.');
        $db->transComplete();

        if (! $db->transStatus()) {
            throw new DatabaseException('Gagal menandai cost proposal sebagai issued.');
        }

        return $this->response->setJSON(['status' => 'ok', 'message' => 'Cost proposal berhasil ditandai sebagai issued.']);
    }

    private function findProposalOrFail(int $id): array
    {
        $record = $this->costProposalModel->find($id);
        if ($record === null) {
            throw PageNotFoundException::forPageNotFound('Cost proposal tidak ditemukan.');
        }

        return $record;
    }

    private function findApprovedBasicCost(int $basicCostId): array
    {
        $record = $this->basicCostModel->find($basicCostId);
        if ($record === null) {
            throw PageNotFoundException::forPageNotFound('Basic cost tidak ditemukan.');
        }
        if (($record['status'] ?? null) !== BasicCostModel::STATUS_APPROVED) {
            throw new DatabaseException('Cost proposal hanya bisa dibuat dari basic cost yang sudah approved.');
        }

        return $record;
    }

    private function validatePayload(array $payload, ?int $id = null): bool
    {
        $rules = [
            'basic_cost_id' => 'required|integer',
            'proposal_number' => 'required|min_length[3]|max_length[60]',
            'proposal_date' => 'required|valid_date[Y-m-d]',
            'valid_until' => 'permit_empty|valid_date[Y-m-d]',
            'client_name' => 'required|min_length[3]|max_length[180]',
            'project_name' => 'required|min_length[3]|max_length[220]',
            'location' => 'permit_empty|max_length[220]',
            'notes' => 'permit_empty|max_length[65535]',
            'assumptions' => 'permit_empty|max_length[65535]',
            'exclusions' => 'permit_empty|max_length[65535]',
            'payment_terms' => 'permit_empty|max_length[65535]',
            'signatory_id' => 'required|integer',
            'attention_name' => 'permit_empty|max_length[180]',
            'proposal_subject' => 'permit_empty|max_length[255]',
            'attachment_label' => 'permit_empty|max_length[255]',
        ];
        if (! $this->validateData($payload, $rules)) {
            return false;
        }

        $items = $payload['items'] ?? null;
        if (! is_array($items) || $items === []) {
            $this->validator->setError('items', 'Item proposal wajib diisi.');
            return false;
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                $this->validator->setError('items', 'Format item proposal tidak valid.');
                return false;
            }
            if (trim((string) ($item['item_name'] ?? '')) === '') {
                $this->validator->setError("items.$index.item_name", 'Nama item proposal wajib diisi.');
                return false;
            }
            $markupType = (string) ($item['markup_type'] ?? 'percent');
            if (! in_array($markupType, ['percent', 'fixed'], true)) {
                $this->validator->setError("items.$index.markup_type", 'Tipe markup harus percent atau fixed.');
                return false;
            }
        }

        $duplicate = $this->costProposalModel->where('proposal_number', trim((string) $payload['proposal_number']));
        if ($id !== null) {
            $duplicate->where('id !=', $id);
        }
        if ($duplicate->first() !== null) {
            $this->validator->setError('proposal_number', 'Nomor proposal sudah digunakan.');
            return false;
        }

        return true;
    }

    private function validationErrorResponse()
    {
        return $this->response->setStatusCode(422)->setJSON([
            'status' => 'error',
            'message' => 'Data cost proposal tidak valid.',
            'errors' => $this->validator->getErrors(),
        ]);
    }

    private function preparePayload(array $payload, array $basicCost): array
    {
        $signatory = $this->findSignatoryOrFail((int) ($payload['signatory_id'] ?? 0));
        $basicCostItems = $this->basicCostItemModel->where('basic_cost_id', (int) $basicCost['id'])->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();
        $basicCostItemsById = [];
        foreach ($basicCostItems as $basicCostItem) {
            $basicCostItemsById[(int) $basicCostItem['id']] = $basicCostItem;
        }

        $preparedItems = [];
        $basicCostTotal = 0.0;
        $grandTotal = 0.0;
        foreach (($payload['items'] ?? []) as $index => $item) {
            $basicCostItemId = isset($item['basic_cost_item_id']) && $item['basic_cost_item_id'] !== null ? (int) $item['basic_cost_item_id'] : null;
            $sourceItem = $basicCostItemId !== null && isset($basicCostItemsById[$basicCostItemId]) ? $basicCostItemsById[$basicCostItemId] : null;
            $quantity = round((float) ($item['quantity'] ?? ($sourceItem['quantity'] ?? 0)), 2);
            $duration = round((float) ($item['duration'] ?? ($sourceItem['duration'] ?? 0)), 2);
            $multiplier = max(0, $quantity) * max(0, $duration);
            $basicUnitPrice = round((float) ($sourceItem['unit_price'] ?? $item['basic_unit_price'] ?? 0), 2);
            $basicTotalPrice = round((float) ($sourceItem['total_price'] ?? ($multiplier * $basicUnitPrice)), 2);
            $markupType = in_array(($item['markup_type'] ?? 'percent'), ['percent', 'fixed'], true) ? (string) $item['markup_type'] : 'percent';
            $markupValue = round((float) ($item['markup_value'] ?? 0), 2);
            $markupAmount = $markupType === 'percent' ? round($basicUnitPrice * ($markupValue / 100), 2) : $markupValue;
            $proposalUnitPrice = round($basicUnitPrice + $markupAmount, 2);
            $proposalTotalPrice = round($multiplier * $proposalUnitPrice, 2);
            $basicCostTotal += $basicTotalPrice;
            $grandTotal += $proposalTotalPrice;
            $preparedItems[] = [
                'basic_cost_item_id' => $basicCostItemId,
                'category_name' => trim((string) ($sourceItem['category_name'] ?? $item['category_name'] ?? '-')),
                'subcategory_name' => trim((string) ($sourceItem['subcategory_name'] ?? $item['subcategory_name'] ?? '')) ?: null,
                'item_name' => trim((string) ($sourceItem['item_name'] ?? $item['item_name'] ?? '')),
                'unit_symbol' => trim((string) ($sourceItem['unit_symbol'] ?? $item['unit_symbol'] ?? '')) ?: null,
                'quantity' => $quantity,
                'duration' => $duration,
                'basic_unit_price' => $basicUnitPrice,
                'basic_total_price' => $basicTotalPrice,
                'markup_type' => $markupType,
                'markup_value' => $markupValue,
                'markup_amount' => $markupAmount,
                'proposal_unit_price' => $proposalUnitPrice,
                'proposal_total_price' => $proposalTotalPrice,
                'unit_price' => $proposalUnitPrice,
                'total_price' => $proposalTotalPrice,
                'sort_order' => isset($item['sort_order']) ? (int) $item['sort_order'] : $index,
            ];
        }

        return [
            'header' => [
                'basic_cost_id' => (int) $basicCost['id'],
                'proposal_number' => trim((string) $payload['proposal_number']),
                'proposal_date' => (string) $payload['proposal_date'],
                'valid_until' => ($payload['valid_until'] ?? '') !== '' ? (string) $payload['valid_until'] : null,
                'client_name' => trim((string) $payload['client_name']),
                'project_name' => trim((string) $payload['project_name']),
                'location' => trim((string) ($payload['location'] ?? '')),
                'work_type_names' => trim((string) ($payload['work_type_names'] ?? '')),
                'notes' => trim((string) ($payload['notes'] ?? '')),
                'assumptions' => trim((string) ($payload['assumptions'] ?? '')),
                'exclusions' => trim((string) ($payload['exclusions'] ?? '')),
                'payment_terms' => trim((string) ($payload['payment_terms'] ?? '')),
                'signatory_id' => (int) $signatory['id'],
                'signatory_name' => $signatory['name'],
                'signatory_title' => $signatory['position_title'],
                'signatory_signature_path' => $this->createProposalSignatureSnapshot($signatory['signature_image_path'] ?? null),
                'attention_name' => trim((string) ($payload['attention_name'] ?? '')),
                'proposal_subject' => trim((string) ($payload['proposal_subject'] ?? '')),
                'attachment_label' => trim((string) ($payload['attachment_label'] ?? '')),
            ],
            'totals' => [
                'basic_cost_total' => round($basicCostTotal, 2),
                'overhead_type' => 'fixed',
                'overhead_value' => 0,
                'overhead_amount' => 0,
                'contingency_type' => 'fixed',
                'contingency_value' => 0,
                'contingency_amount' => 0,
                'margin_type' => 'fixed',
                'margin_value' => 0,
                'margin_amount' => 0,
                'discount_type' => 'fixed',
                'discount_value' => 0,
                'discount_amount' => 0,
                'tax_type' => 'fixed',
                'tax_value' => 0,
                'tax_amount' => 0,
                'grand_total' => round($grandTotal, 2),
            ],
            'items' => $preparedItems,
        ];
    }

    private function insertItems(int $proposalId, array $items): void
    {
        foreach ($items as $item) {
            $item['cost_proposal_id'] = $proposalId;
            $this->costProposalItemModel->insert($item);
        }
    }

    private function transformProposal(array $record): array
    {
        $basicCost = $this->basicCostModel->find((int) $record['basic_cost_id']);
        $signatory = $record['signatory_id'] ? $this->signatoryModel->find((int) $record['signatory_id']) : null;
        $createdBy = $record['created_by'] ? $this->userModel->find((int) $record['created_by']) : null;
        $submittedBy = $record['submitted_by'] ? $this->userModel->find((int) $record['submitted_by']) : null;
        $reviewedBy = $record['reviewed_by'] ? $this->userModel->find((int) $record['reviewed_by']) : null;
        $permissions = $this->buildPermissions($record);
        $signaturePath = $record['signatory_signature_path'] ?? ($signatory['signature_image_path'] ?? null);

        return [
            'id' => (int) $record['id'],
            'basic_cost_id' => (int) $record['basic_cost_id'],
            'basic_cost_status' => $basicCost['status'] ?? null,
            'proposal_number' => $record['proposal_number'],
            'proposal_date' => $record['proposal_date'],
            'valid_until' => $record['valid_until'],
            'client_name' => $record['client_name'],
            'project_name' => $record['project_name'],
            'location' => $record['location'],
            'work_type_names' => $record['work_type_names'],
            'notes' => $record['notes'],
            'assumptions' => $record['assumptions'],
            'exclusions' => $record['exclusions'],
            'payment_terms' => $record['payment_terms'],
            'signatory_id' => $record['signatory_id'] !== null ? (int) $record['signatory_id'] : null,
            'signatory_name' => $record['signatory_name'] ?? null,
            'signatory_title' => $record['signatory_title'] ?? null,
            'signatory_signature_path' => $signaturePath,
            'signatory_signature_url' => $this->buildPublicAssetUrl($signaturePath),
            'signatory_signature_data_url' => $this->buildImageDataUrl($signaturePath),
            'attention_name' => $record['attention_name'] ?? null,
            'proposal_subject' => $record['proposal_subject'] ?? null,
            'attachment_label' => $record['attachment_label'] ?? null,
            'public_token' => $record['public_token'] ?? null,
            'public_url' => $this->buildPublicUrl((string) ($record['public_token'] ?? '')),
            'basic_cost_total' => (float) $record['basic_cost_total'],
            'overhead_type' => $record['overhead_type'],
            'overhead_value' => (float) $record['overhead_value'],
            'overhead_amount' => (float) $record['overhead_amount'],
            'contingency_type' => $record['contingency_type'],
            'contingency_value' => (float) $record['contingency_value'],
            'contingency_amount' => (float) $record['contingency_amount'],
            'margin_type' => $record['margin_type'],
            'margin_value' => (float) $record['margin_value'],
            'margin_amount' => (float) $record['margin_amount'],
            'discount_type' => $record['discount_type'],
            'discount_value' => (float) $record['discount_value'],
            'discount_amount' => (float) $record['discount_amount'],
            'tax_type' => $record['tax_type'],
            'tax_value' => (float) $record['tax_value'],
            'tax_amount' => (float) $record['tax_amount'],
            'grand_total' => (float) $record['grand_total'],
            'status' => $record['status'] ?? CostProposalModel::STATUS_DRAFT,
            'submitted_at' => $this->formatDateTime($record['submitted_at'] ?? null),
            'submitted_by' => $record['submitted_by'] !== null ? (int) $record['submitted_by'] : null,
            'submitted_by_name' => $submittedBy['name'] ?? null,
            'reviewed_at' => $this->formatDateTime($record['reviewed_at'] ?? null),
            'reviewed_by' => $record['reviewed_by'] !== null ? (int) $record['reviewed_by'] : null,
            'reviewed_by_name' => $reviewedBy['name'] ?? null,
            'review_notes' => $record['review_notes'] ?? null,
            'created_by' => $record['created_by'] !== null ? (int) $record['created_by'] : null,
            'created_by_name' => $createdBy['name'] ?? null,
            'created_at' => $this->formatDateTime($record['created_at'] ?? null),
            'updated_at' => $this->formatDateTime($record['updated_at'] ?? null),
            'can_edit' => $permissions['can_edit'],
            'can_submit' => $permissions['can_submit'],
            'can_request_revision' => $permissions['can_request_revision'],
            'can_approve' => $permissions['can_approve'],
            'can_issue' => $permissions['can_issue'],
        ];
    }

    private function transformProposalItem(array $item): array
    {
        return [
            'id' => (int) $item['id'],
            'cost_proposal_id' => (int) $item['cost_proposal_id'],
            'basic_cost_item_id' => $item['basic_cost_item_id'] !== null ? (int) $item['basic_cost_item_id'] : null,
            'category_name' => $item['category_name'],
            'subcategory_name' => $item['subcategory_name'],
            'item_name' => $item['item_name'],
            'unit_symbol' => $item['unit_symbol'],
            'quantity' => (float) $item['quantity'],
            'duration' => (float) $item['duration'],
            'basic_unit_price' => (float) ($item['basic_unit_price'] ?? 0),
            'basic_total_price' => (float) ($item['basic_total_price'] ?? 0),
            'markup_type' => $item['markup_type'] ?? 'percent',
            'markup_value' => (float) ($item['markup_value'] ?? 0),
            'markup_amount' => (float) ($item['markup_amount'] ?? 0),
            'proposal_unit_price' => (float) ($item['proposal_unit_price'] ?? $item['unit_price'] ?? 0),
            'proposal_total_price' => (float) ($item['proposal_total_price'] ?? $item['total_price'] ?? 0),
            'unit_price' => (float) $item['unit_price'],
            'total_price' => (float) $item['total_price'],
            'sort_order' => (int) $item['sort_order'],
        ];
    }

    private function loadApprovalHistory(int $proposalId): array
    {
        $rows = $this->approvalHistoryModel->where('cost_proposal_id', $proposalId)->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->findAll();

        return array_map(fn(array $row): array => [
            'id' => (int) $row['id'],
            'action' => $row['action'],
            'status_from' => $row['status_from'],
            'status_to' => $row['status_to'],
            'note' => $row['note'],
            'actor_id' => $row['actor_id'] !== null ? (int) $row['actor_id'] : null,
            'actor_name' => $row['actor_name'],
            'created_at' => $this->formatDateTime($row['created_at'] ?? null),
        ], $rows);
    }

    private function logApprovalHistory(int $proposalId, string $action, ?string $from, string $to, ?string $note = null): void
    {
        $this->approvalHistoryModel->insert([
            'cost_proposal_id' => $proposalId,
            'action' => $action,
            'status_from' => $from,
            'status_to' => $to,
            'note' => $note,
            'actor_id' => current_user('id'),
            'actor_name' => current_user('name'),
        ]);
    }

    private function buildPermissions(array $record): array
    {
        helper('auth');
        $role = (string) current_user('role');
        $userId = (int) current_user('id');
        $status = (string) ($record['status'] ?? CostProposalModel::STATUS_DRAFT);
        $isAdmin = $role === UserModel::ROLE_ADMIN;
        $isReviewer = in_array($role, [UserModel::ROLE_ADMIN, UserModel::ROLE_MANAGER], true);
        $isOwner = $isAdmin || ((int) ($record['created_by'] ?? 0) === $userId);
        $canEdit = $isOwner && in_array($status, [CostProposalModel::STATUS_DRAFT, CostProposalModel::STATUS_REVISION], true);

        return [
            'can_edit' => $canEdit,
            'can_submit' => $isOwner && in_array($status, [CostProposalModel::STATUS_DRAFT, CostProposalModel::STATUS_REVISION], true),
            'can_request_revision' => $isReviewer && $status === CostProposalModel::STATUS_SUBMITTED,
            'can_approve' => $isReviewer && $status === CostProposalModel::STATUS_SUBMITTED,
            'can_issue' => $isOwner && $status === CostProposalModel::STATUS_APPROVED,
        ];
    }

    private function ensureCanEdit(array $record)
    {
        if (! $this->buildPermissions($record)['can_edit']) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Cost proposal ini tidak bisa diedit pada status saat ini.']);
        }
        return null;
    }

    private function ensureCanSubmit(array $record)
    {
        if (! $this->buildPermissions($record)['can_submit']) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Dokumen ini tidak bisa dikirim untuk review.']);
        }
        return null;
    }

    private function ensureCanReview(array $record)
    {
        $permissions = $this->buildPermissions($record);
        if (! ($permissions['can_request_revision'] || $permissions['can_approve'])) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Anda tidak bisa mereview dokumen ini pada status saat ini.']);
        }
        return null;
    }

    private function ensureCanIssue(array $record)
    {
        if (! $this->buildPermissions($record)['can_issue']) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Dokumen ini belum bisa ditandai sebagai issued.']);
        }
        return null;
    }

    private function ensureProposalHasPublicToken(array $proposal): array
    {
        $token = trim((string) ($proposal['public_token'] ?? ''));
        if ($token !== '') {
            return $proposal;
        }

        $token = $this->generatePublicToken();
        $this->costProposalModel->update((int) $proposal['id'], ['public_token' => $token]);
        $proposal['public_token'] = $token;
        return $proposal;
    }

    private function findSignatoryOrFail(int $signatoryId): array
    {
        $record = $this->signatoryModel->find($signatoryId);
        if ($record === null) {
            throw PageNotFoundException::forPageNotFound('Penandatangan tidak ditemukan.');
        }

        return $record;
    }

    private function generatePublicToken(): string
    {
        do {
            $token = bin2hex(random_bytes(16));
        } while ($this->costProposalModel->where('public_token', $token)->first() !== null);

        return $token;
    }

    private function buildPublicUrl(string $token): ?string
    {
        return $token === '' ? null : '/quotation/' . $token;
    }

    private function createProposalSignatureSnapshot(?string $sourcePath): ?string
    {
        $normalizedSourcePath = trim((string) $sourcePath);
        if ($normalizedSourcePath === '') {
            return null;
        }

        $sourceFullPath = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($normalizedSourcePath, '/'));
        if (! is_file($sourceFullPath)) {
            return null;
        }

        $targetDirectory = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::PROPOSAL_SIGNATURE_UPLOAD_DIR);
        if (! is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        $targetFileName = 'proposal-signature-' . bin2hex(random_bytes(8)) . '.png';
        $targetRelativePath = self::PROPOSAL_SIGNATURE_UPLOAD_DIR . $targetFileName;
        copy($sourceFullPath, $targetDirectory . $targetFileName);

        return $targetRelativePath;
    }

    private function deletePublicAssetFile(?string $path): void
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
