<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ProductStoreRequest;
use App\Http\Requests\V1\ProductUpdateRequest;
use App\Http\Requests\V1\ProductChargeAttachRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Utils\ResponseUtils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::with('charges')->get();
        return ResponseUtils::success(
            ProductResource::collection($products),
            'Products retrieved successfully'
        );
    }

    public function store(ProductStoreRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());
        return ResponseUtils::success(
            new ProductResource($product),
            'Product created successfully',
            Response::HTTP_CREATED
        );
    }

    public function show(Product $product): JsonResponse
    {
        return ResponseUtils::success(
            new ProductResource($product->load('charges')),
            'Product retrieved successfully'
        );
    }

    public function update(ProductUpdateRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());
        return ResponseUtils::success(
            new ProductResource($product),
            'Product updated successfully'
        );
    }

    public function deactivate(Product $product): JsonResponse
    {
        $product->update(['is_active' => false]);
        return ResponseUtils::success(
            new ProductResource($product),
            'Product deactivated successfully'
        );
    }

    public function attachCharges(ProductChargeAttachRequest $request, Product $product): JsonResponse
    {
        $product->charges()->sync($request->validated()['charge_ids']);
        return ResponseUtils::success(
            new ProductResource($product->load('charges')),
            'Charges attached successfully'
        );
    }
}