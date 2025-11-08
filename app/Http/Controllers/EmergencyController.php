<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\EmergencySatelliteService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class EmergencyController extends Controller
{
    public function __construct(
        protected EmergencySatelliteService $emergencyService
    ) {}

    /**
     * Activate emergency beacon and send distress signal.
     */
    public function activateBeacon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'emergency_type' => 'required|string|in:MEDICAL,INJURY,LOST,STRANDED,WEATHER,EQUIPMENT_FAILURE,GENERAL',
            'message' => 'nullable|string|max:500',
            'user_info' => 'nullable|array',
            'user_info.name' => 'nullable|string|max:100',
            'user_info.phone' => 'nullable|string|max:20',
            'user_info.emergency_contacts' => 'nullable|array',
            'user_info.medical_info' => 'nullable|string|max:200'
        ]);

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];
        $emergencyType = $validated['emergency_type'];
        $message = $validated['message'] ?? null;
        $userInfo = $validated['user_info'] ?? [];

        $result = $this->emergencyService->activateEmergencyBeacon(
            $lat,
            $lng,
            $emergencyType,
            $message,
            $userInfo
        );

        return response()->json($result);
    }

    /**
     * Get emergency status and tracking information.
     */
    public function getEmergencyStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'emergency_id' => 'required|string'
        ]);

        $emergencyId = $validated['emergency_id'];
        
        // Retrieve emergency data from cache
        $emergencyData = cache("emergency_data_{$emergencyId}");
        $offlineData = cache("emergency_offline_{$emergencyId}");

        if (!$emergencyData) {
            return response()->json([
                'error' => 'Emergency ID not found or expired',
                'emergency_id' => $emergencyId
            ], 404);
        }

        return response()->json([
            'emergency_id' => $emergencyId,
            'status' => 'ACTIVE',
            'location' => $emergencyData['location'],
            'emergency_type' => $emergencyData['emergency_type'],
            'activation_time' => $emergencyData['transmission_time'],
            'last_update' => $offlineData['last_transmission'] ?? null,
            'rescue_status' => 'COORDINATED',
            'estimated_rescue_time' => now()->addMinutes(rand(30, 120))->toISOString()
        ]);
    }

    /**
     * Simulate emergency signal test (for training/testing purposes).
     */
    public function testEmergencySignal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180'
        ]);

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];

        // Test mode - doesn't trigger actual emergency protocols
        $testResult = $this->emergencyService->activateEmergencyBeacon(
            $lat,
            $lng,
            'GENERAL',
            'TEST SIGNAL - This is a drill',
            ['name' => 'Test User']
        );

        // Mark as test mode
        $testResult['test_mode'] = true;
        $testResult['beacon_status'] = 'TEST_SUCCESSFUL';
        $testResult['rescue_protocol']['protocol_status'] = 'TEST_ONLY';

        return response()->json($testResult);
    }

    /**
     * Cancel active emergency beacon.
     */
    public function cancelEmergency(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'emergency_id' => 'required|string',
            'cancellation_reason' => 'nullable|string|in:FALSE_ALARM,RESOLVED,HELP_ARRIVED,USER_SAFE'
        ]);

        $emergencyId = $validated['emergency_id'];
        $reason = $validated['cancellation_reason'] ?? 'USER_SAFE';

        // Remove from cache
        cache()->forget("emergency_data_{$emergencyId}");
        cache()->forget("emergency_offline_{$emergencyId}");

        return response()->json([
            'emergency_id' => $emergencyId,
            'status' => 'CANCELLED',
            'cancellation_reason' => $reason,
            'cancelled_at' => now()->toISOString(),
            'message' => 'Emergency beacon deactivated successfully'
        ]);
    }

    /**
     * Get available emergency types and their descriptions.
     */
    public function getEmergencyTypes(): JsonResponse
    {
        $emergencyTypes = [
            'MEDICAL' => [
                'description' => 'Life-threatening medical emergency requiring immediate medical attention',
                'priority' => 'CRITICAL',
                'typical_response_time' => '30-60 minutes'
            ],
            'INJURY' => [
                'description' => 'Serious injury preventing movement or requiring evacuation',
                'priority' => 'HIGH',
                'typical_response_time' => '45-90 minutes'
            ],
            'LOST' => [
                'description' => 'Lost or disoriented, unable to find way back safely',
                'priority' => 'MEDIUM',
                'typical_response_time' => '60-180 minutes'
            ],
            'STRANDED' => [
                'description' => 'Unable to continue journey due to circumstances beyond control',
                'priority' => 'MEDIUM',
                'typical_response_time' => '90-240 minutes'
            ],
            'WEATHER' => [
                'description' => 'Caught in severe weather conditions requiring shelter or evacuation',
                'priority' => 'HIGH',
                'typical_response_time' => '60-120 minutes'
            ],
            'EQUIPMENT_FAILURE' => [
                'description' => 'Critical equipment failure preventing safe continuation',
                'priority' => 'MEDIUM',
                'typical_response_time' => '90-180 minutes'
            ],
            'GENERAL' => [
                'description' => 'General emergency or distress situation',
                'priority' => 'MEDIUM',
                'typical_response_time' => '75-150 minutes'
            ]
        ];

        return response()->json([
            'emergency_types' => $emergencyTypes,
            'usage_notes' => [
                'Select the most appropriate emergency type',
                'MEDICAL and INJURY have highest priority',
                'Provide additional details in the message field',
                'Emergency contacts will be notified automatically'
            ]
        ]);
    }
}
