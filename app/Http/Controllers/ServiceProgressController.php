<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceProgressController extends Controller
{
    public function show(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $viewer = $request->user();
        $isParticipant = $viewer->role === 'admin'
            || (int) $serviceRequest->rider_id === (int) $viewer->id
            || (int) $serviceRequest->mechanic_id === (int) $viewer->id;

        abort_unless($isParticipant, 403);

        return response()->json([
            'service_status' => $serviceRequest->service_status,
            'dispatch_status' => $serviceRequest->dispatch_status,
            'updated_at' => $serviceRequest->updated_at?->toIso8601String(),
        ])->header('Cache-Control', 'no-store, private');
    }
}
