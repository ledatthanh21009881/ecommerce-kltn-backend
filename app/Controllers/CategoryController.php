<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Validator, Container};
use App\Domain\Products\Category;
use App\Support\ResponseHelper;
use Exception;

class CategoryController extends Controller
{
    private Category $categoryModel;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->categoryModel = new Category($container->database());
    }

    /**
     * Get all categories with hierarchical structure
     * GET /api/v1/categories
     */
    public function index(Request $req, Response $res)
    {
        try {
            $type = $req->query('type', 'flat'); // flat or hierarchy
            
            if ($type === 'hierarchy') {
                $categories = $this->categoryModel->getAllHierarchical();
            } else {
                $categories = $this->categoryModel->getAll();
            }

            return $res->json(ResponseHelper::success($categories, 'Categories retrieved successfully'));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to retrieve categories: ' . $e->getMessage()));
        }
    }

    /**
     * Get main categories only
     * GET /api/v1/categories/main
     */
    public function getMainCategories(Request $req, Response $res)
    {
        try {
            $categories = $this->categoryModel->getMainCategories();
            return $res->json(ResponseHelper::success($categories, 'Main categories retrieved successfully'));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to retrieve main categories: ' . $e->getMessage()));
        }
    }

    /**
     * Get children categories by parent ID
     * GET /api/v1/categories/{id}/children
     */
    public function getChildren(Request $req, Response $res)
    {
        try {
            $parentId = (int) $req->getAttribute('id');
            
            if ($parentId <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid category ID']));
            }

            $categories = $this->categoryModel->getChildren($parentId);
            return $res->json(ResponseHelper::success($categories, 'Children categories retrieved successfully'));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to retrieve children categories: ' . $e->getMessage()));
        }
    }

    /**
     * Get single category by ID
     * GET /api/v1/categories/{id}
     */
    public function show(Request $req, Response $res)
    {
        try {
            $id = (int) $req->getAttribute('id');
            
            if ($id <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid category ID']));
            }

            $category = $this->categoryModel->find($id);
            
            if (!$category) {
                return $res->json(ResponseHelper::notFound('Category not found'));
            }

            // Get children if exists
            $category['children'] = $this->categoryModel->getChildren($id);

            return $res->json(ResponseHelper::success($category, 'Category retrieved successfully'));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to retrieve category: ' . $e->getMessage()));
        }
    }

    /**
     * Get category by slug
     * GET /api/v1/categories/slug/{slug}
     */
    public function getBySlug(Request $req, Response $res)
    {
        try {
            $slug = $req->getAttribute('slug');
            
            if (empty($slug)) {
                return $res->json(ResponseHelper::validationError(['slug' => 'Slug is required']));
            }

            $category = $this->categoryModel->findBySlug($slug);
            
            if (!$category) {
                return $res->json(ResponseHelper::notFound('Category not found'));
            }

            // Get children if exists
            $category['children'] = $this->categoryModel->getChildren($category['category_id']);

            return $res->json(ResponseHelper::success($category, 'Category retrieved successfully'));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to retrieve category: ' . $e->getMessage()));
        }
    }

    /**
     * Create new category
     * POST /api/v1/categories
     */
    public function store(Request $req, Response $res)
    {
        $data = $req->json();

        // Validation
        $validator = Validator::make($data, [
            'category_name' => 'required|min:2|max:100',
            'slug' => 'max:100',
            'parent_id' => 'integer',
            'position' => 'integer',
            'is_active' => 'boolean'
        ]);

        if (!$validator->validate()) {
            return $res->json(ResponseHelper::validationError($validator->getErrors()));
        }

        try {
            // Check if parent exists if parent_id provided
            if (!empty($data['parent_id'])) {
                $parent = $this->categoryModel->find($data['parent_id']);
                if (!$parent) {
                    return $res->json(ResponseHelper::validationError(['parent_id' => 'Parent category not found']));
                }
            }

            $categoryId = $this->categoryModel->create($data);
            $category = $this->categoryModel->find($categoryId);

            return $res->json(ResponseHelper::success($category, 'Category created successfully'), 201);
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to create category: ' . $e->getMessage()));
        }
    }

    /**
     * Update category
     * PUT /api/v1/categories/{id}
     */
    public function update(Request $req, Response $res)
    {
        try {
            $id = (int) $req->getAttribute('id');
            $data = $req->json();

            if ($id <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid category ID']));
            }

            // Check if category exists
            $existingCategory = $this->categoryModel->find($id);
            if (!$existingCategory) {
                return $res->json(ResponseHelper::notFound('Category not found'));
            }

            // Validation
            $validator = Validator::make($data, [
                'category_name' => 'min:2|max:100',
                'slug' => 'max:100',
                'parent_id' => 'integer',
                'position' => 'integer',
                'is_active' => 'boolean'
            ]);

            if (!$validator->validate()) {
                return $res->json(ResponseHelper::validationError($validator->getErrors()));
            }

            // Check if parent exists if parent_id provided
            if (!empty($data['parent_id'])) {
                // Cannot set self as parent
                if ($data['parent_id'] == $id) {
                    return $res->json(ResponseHelper::validationError(['parent_id' => 'Category cannot be its own parent']));
                }

                $parent = $this->categoryModel->find($data['parent_id']);
                if (!$parent) {
                    return $res->json(ResponseHelper::validationError(['parent_id' => 'Parent category not found']));
                }
            }

            $success = $this->categoryModel->update($id, $data);
            
            if (!$success) {
                return $res->json(ResponseHelper::error('No changes made to category'));
            }

            $category = $this->categoryModel->find($id);
            return $res->json(ResponseHelper::success($category, 'Category updated successfully'));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to update category: ' . $e->getMessage()));
        }
    }

    /**
     * Delete category (soft delete)
     * DELETE /api/v1/categories/{id}
     */
    public function destroy(Request $req, Response $res)
    {
        try {
            $id = (int) $req->getAttribute('id');

            if ($id <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid category ID']));
            }

            $category = $this->categoryModel->find($id);
            if (!$category) {
                return $res->json(ResponseHelper::notFound('Category not found'));
            }

            $success = $this->categoryModel->delete($id);
            
            if ($success) {
                return $res->json(ResponseHelper::success(null, 'Category deleted successfully'));
            } else {
                return $res->json(ResponseHelper::error('Failed to delete category'));
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Cannot delete category') !== false) {
                return $res->json(ResponseHelper::error($e->getMessage(), 400));
            }
            return $res->json(ResponseHelper::serverError('Failed to delete category: ' . $e->getMessage()));
        }
    }

    /**
     * Reorder categories
     * POST /api/v1/categories/reorder
     */
    public function reorder(Request $req, Response $res)
    {
        $data = $req->json();

        // Validation
        $validator = Validator::make($data, [
            'categories' => 'required|array'
        ]);

        if (!$validator->validate()) {
            return $res->json(ResponseHelper::validationError($validator->getErrors()));
        }

        try {
            // Validate each category order item
            foreach ($data['categories'] as $index => $item) {
                if (!isset($item['category_id']) || !isset($item['position'])) {
                    return $res->json(ResponseHelper::validationError([
                        "categories.{$index}" => 'Each item must have category_id and position'
                    ]));
                }
            }

            $success = $this->categoryModel->reorder($data['categories']);
            
            if ($success) {
                return $res->json(ResponseHelper::success(null, 'Categories reordered successfully'));
            } else {
                return $res->json(ResponseHelper::error('Failed to reorder categories'));
            }
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to reorder categories: ' . $e->getMessage()));
        }
    }

    /**
     * Toggle category status (active/inactive)
     * PATCH /api/v1/categories/{id}/toggle-status
     */
    public function toggleStatus(Request $req, Response $res)
    {
        try {
            $id = (int) $req->getAttribute('id');

            if ($id <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid category ID']));
            }

            $category = $this->categoryModel->find($id);
            if (!$category) {
                return $res->json(ResponseHelper::notFound('Category not found'));
            }

            $newStatus = !$category['is_active'];
            $success = $this->categoryModel->update($id, ['is_active' => $newStatus]);
            
            if ($success) {
                $updatedCategory = $this->categoryModel->find($id);
                return $res->json(ResponseHelper::success($updatedCategory, 'Category status updated successfully'));
            } else {
                return $res->json(ResponseHelper::error('Failed to update category status'));
            }
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to update category status: ' . $e->getMessage()));
        }
    }
}
