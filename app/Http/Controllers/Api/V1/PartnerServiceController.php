<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class PartnerServiceController extends Controller
{
    public function index()
    {
        return auth()->user()->services()->withPivot('custom_price', 'is_active')->get();
    }

    public function attach(Request $request)
    {
        $request->validate([
            'service_id'   => 'required|exists:services,id',
            'custom_price' => 'nullable|numeric|min:0',
            'is_active'    => 'boolean',
        ]);
        auth()->user()->services()->syncWithoutDetaching([
            $request->service_id => [
                'custom_price' => $request->custom_price,
                'is_active'    => $request->is_active ?? true,
            ],
        ]);
        return response()->json(['message' => 'Service added']);
    }

    public function detach(Service $service)
    {
        auth()->user()->services()->detach($service->id);
        return response()->noContent();
    }
}
