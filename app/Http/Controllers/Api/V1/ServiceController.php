<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Service::with(['category', 'pricing'])->where('is_active', true);

        // Filter by category if provided
        if ($request->has('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        // Filter by featured services
        if ($request->boolean('featured')) {
            $query->featured();
        }

        // Filter by popular services
        if ($request->boolean('popular')) {
            $query->popular();
        }

        // Filter by promoted services (featured OR popular)
        if ($request->boolean('promoted')) {
            $query->promoted();
        }

        $services = $query->get();

        return response()->json([
            'success' => true,
            'data' => ServiceResource::collection($services)
        ]);
    }

    public function show(Service $service): JsonResponse
    {
        $service->load(['category', 'pricing']);

        return response()->json([
            'success' => true,
            'data' => new ServiceResource($service)
        ]);
    }
    public function store(ServiceRequest $request): JsonResponse
    {
        $service = Service::create($request->validated());
        $service->load(['category', 'pricing']);

        return response()->json([
            'success' => true,
            'data' => new ServiceResource($service)
        ], 201);
    }

    public function update(ServiceRequest $request, Service $service): JsonResponse
    {
        $service->update($request->validated());
        $service->load(['category', 'pricing']);

        return response()->json([
            'success' => true,
            'data' => new ServiceResource($service)
        ]);
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service deleted successfully'
        ]);
    }
}
