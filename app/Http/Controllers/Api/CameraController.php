<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Camera;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CameraController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cameras = Camera::query()
            ->when(
                ! $request->user()->isAdmin(),
                fn ($query) => $query->where(function ($query) use ($request) {
                    $query->where('created_by', $request->user()->id)
                        ->orWhereHas(
                            'schedules',
                            fn ($scheduleQuery) => $scheduleQuery
                                ->where('created_by', $request->user()->id)
                                ->where('is_active', true)
                        );
                })
            )
            ->orderBy('name')
            ->get()
            ->map(fn (Camera $camera) => $this->cameraData($camera));

        return response()->json(['data' => $cameras]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateCamera($request);

        $camera = Camera::create([
            'name' => $validated['name'],
            'ip_address' => $validated['ip_address'],
            'location' => $validated['location'] ?? $validated['name'],
            'stream_url' => null,
            'status' => $validated['status'] ?? 'online',
            'detection_sensitivity' => $validated['detection_sensitivity'] ?? 50,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Camera registered successfully.',
            'data' => $this->cameraData($camera),
        ], 201);
    }

    public function update(Request $request, Camera $camera): JsonResponse
    {
        $this->authorizeCamera($request, $camera);
        $validated = $this->validateCamera($request, $camera);

        $camera->update([
            'name' => $validated['name'],
            'ip_address' => $validated['ip_address'],
            'location' => $validated['location'] ?? $validated['name'],
            'status' => $validated['status'] ?? $camera->status,
            'detection_sensitivity' => $validated['detection_sensitivity'] ?? $camera->detection_sensitivity,
        ]);

        return response()->json([
            'message' => 'Camera updated successfully.',
            'data' => $this->cameraData($camera->fresh()),
        ]);
    }

    public function destroy(Request $request, Camera $camera): JsonResponse
    {
        $this->authorizeCamera($request, $camera);

        if ($camera->schedules()->exists() || $camera->autoSessions()->exists()) {
            return response()->json([
                'message' => 'This camera already has schedules or counting records and cannot be deleted.',
            ], 409);
        }

        $camera->delete();

        return response()->json([
            'message' => 'Camera deleted successfully.',
        ]);
    }

    private function validateCamera(Request $request, ?Camera $camera = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ip_address' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cameras', 'ip_address')->ignore($camera?->id),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['online', 'offline'])],
            'detection_sensitivity' => ['sometimes', 'integer', 'between:1,100'],
        ]);
    }

    private function authorizeCamera(Request $request, Camera $camera): void
    {
        abort_unless(
            $camera->created_by === $request->user()->id || $request->user()->isAdmin(),
            403,
            'You are not allowed to manage this camera.'
        );
    }

    private function cameraData(Camera $camera): array
    {
        return [
            'id' => $camera->id,
            'name' => $camera->name,
            'ip_address' => $camera->ip_address,
            'location' => $camera->location,
            'status' => $camera->status,
            'last_detection_at' => $camera->last_detection_at?->toIso8601String(),
            'detection_sensitivity' => $camera->detection_sensitivity,
        ];
    }
}
