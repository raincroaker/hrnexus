<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventCategoryRequest;
use App\Http\Requests\UpdateEventCategoryRequest;
use App\Models\EventCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class EventCategoryController extends Controller
{
    /**
     * Store a newly created event category.
     */
    public function store(StoreEventCategoryRequest $request): JsonResponse
    {
        $category = EventCategory::create([
            'user_id' => Auth::id(),
            'name' => $request->validated()['name'],
            'color' => $request->validated()['color'],
        ]);

        return response()->json([
            'message' => 'Category created successfully.',
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
            ],
        ], 201);
    }

    /**
     * Update the specified event category.
     */
    public function update(UpdateEventCategoryRequest $request, EventCategory $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json([
            'message' => 'Category updated successfully.',
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
            ],
        ]);
    }

    /**
     * Remove the specified event category.
     */
    public function destroy(EventCategory $category): JsonResponse
    {
        // Check if category is used by any events
        if ($category->events()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category. It is currently being used by one or more events.',
            ], 422);
        }

        $category->forceDelete();

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }
}
