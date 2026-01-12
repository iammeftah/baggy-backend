<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    /**
     * Get all categories.
     *
     * @return Collection
     */
    public function getAllCategories(): Collection
    {
        return Category::withCount('activeProducts')
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Get a category by ID.
     *
     * @param int $id
     * @return Category
     */
    public function getCategoryById(int $id): Category
    {
        return Category::findOrFail($id);
    }

    /**
     * Create a new category.
     *
     * @param array $data
     * @return Category
     */
    public function createCategory(array $data): Category
    {
        return Category::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * Update a category.
     *
     * @param Category $category
     * @param array $data
     * @return Category
     */
    public function updateCategory(Category $category, array $data): Category
    {
        $category->update([
            'name' => $data['name'] ?? $category->name,
            'slug' => $data['slug'] ?? $category->slug,
            'description' => $data['description'] ?? $category->description,
        ]);

        return $category;
    }

    /**
     * Delete a category.
     *
     * @param Category $category
     * @return bool
     * @throws \Exception
     */
    public function deleteCategory(Category $category): bool
    {
        // Check if category has products
        if ($category->products()->count() > 0) {
            throw new \Exception('Cannot delete category with existing products.');
        }

        return $category->delete();
    }
}
