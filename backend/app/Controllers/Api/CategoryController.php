<?php

namespace App\Controllers\Api;

use App\Models\CategoryModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class CategoryController extends AdminApiController
{
    private CategoryModel $categoryModel;

    public function __construct()
    {
        $this->categoryModel = new CategoryModel();
    }

    public function index()
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $builder = $this->categoryModel->orderBy('name', 'ASC');
        $isActive = $this->request->getGet('is_active');
        $keyword = trim((string) $this->request->getGet('q'));

        if ($isActive !== null && $isActive !== '') {
            $builder->where('is_active', (int) $isActive);
        }

        if ($keyword !== '') {
            $builder->like('name', $keyword);
        }

        $categories = array_map(fn (array $category): array => $this->transformCategory($category), $builder->findAll());

        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => $categories,
        ]);
    }

    public function show(int $id)
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => $this->transformCategory($this->findCategoryOrFail($id)),
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

        $this->categoryModel->insert([
            'name'      => trim((string) $payload['name']),
            'is_active' => (int) ($payload['is_active'] ?? 1),
        ]);

        $id = (int) $this->categoryModel->getInsertID();

        return $this->response->setStatusCode(201)->setJSON([
            'status'  => 'ok',
            'message' => 'Kategori berhasil ditambahkan.',
            'data'    => $this->transformCategory($this->findCategoryOrFail($id)),
        ]);
    }

    public function update(int $id)
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $this->findCategoryOrFail($id);
        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if (! $this->validateData($payload, $this->validationRules($id))) {
            return $this->validationErrorResponse();
        }

        $this->categoryModel->update($id, [
            'name'      => trim((string) $payload['name']),
            'is_active' => (int) ($payload['is_active'] ?? 1),
        ]);

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Kategori berhasil diperbarui.',
            'data'    => $this->transformCategory($this->findCategoryOrFail($id)),
        ]);
    }

    public function delete(int $id)
    {
        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $this->findCategoryOrFail($id);
        $this->categoryModel->delete($id);

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Kategori berhasil dihapus.',
        ]);
    }

    private function findCategoryOrFail(int $id): array
    {
        $category = $this->categoryModel->find($id);

        if ($category === null) {
            throw PageNotFoundException::forPageNotFound('Kategori tidak ditemukan.');
        }

        return $category;
    }

    private function transformCategory(array $category): array
    {
        return [
            'id'         => (int) $category['id'],
            'name'       => $category['name'],
            'is_active'  => (bool) $category['is_active'],
            'created_at' => $this->formatDateTime($category['created_at'] ?? null),
            'updated_at' => $this->formatDateTime($category['updated_at'] ?? null),
        ];
    }

    private function validationRules(?int $id = null): array
    {
        $nameRule = 'required|min_length[3]|max_length[150]|is_unique[categories.name]';

        if ($id !== null) {
            $nameRule = "required|min_length[3]|max_length[150]|is_unique[categories.name,id,{$id}]";
        }

        return [
            'name'      => $nameRule,
            'is_active' => 'permit_empty|in_list[0,1]',
        ];
    }

    private function validationErrorResponse()
    {
        return $this->response->setStatusCode(422)->setJSON([
            'status'  => 'error',
            'message' => 'Data kategori tidak valid.',
            'errors'  => $this->validator->getErrors(),
        ]);
    }
}
