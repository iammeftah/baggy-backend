<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebstoreInfoResource;
use App\Models\WebstoreInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class WebstoreInfoController extends Controller
{
    /**
     * Display the webstore information
     */
    public function index(): JsonResponse
    {
        $info = WebstoreInfo::first();

        if (!$info) {
            return response()->json([
                'success' => false,
                'message' => 'Webstore information not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new WebstoreInfoResource($info),
        ]);
    }

    /**
     * Update the webstore information
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'store_name' => 'required|string|max:255',
            'store_description' => 'nullable|string',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'instagram_url' => 'nullable|url|max:255',
            'tiktok_url' => 'nullable|url|max:255',
            'facebook_url' => 'nullable|url|max:255',
            'whatsapp_number' => 'nullable|string|max:20',
            'working_hours' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $info = WebstoreInfo::first();

        if (!$info) {
            return response()->json([
                'success' => false,
                'message' => 'Webstore information not found',
            ], 404);
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($info->logo_path && Storage::disk('public')->exists($info->logo_path)) {
                Storage::disk('public')->delete($info->logo_path);
            }

            // Store new logo
            $logoPath = $request->file('logo')->store('webstore', 'public');
            $info->logo_path = $logoPath;
        }

        // Update other fields
        $info->update($request->except('logo'));

        return response()->json([
            'success' => true,
            'message' => 'Webstore information updated successfully',
            'data' => new WebstoreInfoResource($info),
        ]);
    }

    /**
     * Upload or update logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $info = WebstoreInfo::first();

        if (!$info) {
            return response()->json([
                'success' => false,
                'message' => 'Webstore information not found',
            ], 404);
        }

        // Delete old logo
        if ($info->logo_path && Storage::disk('public')->exists($info->logo_path)) {
            Storage::disk('public')->delete($info->logo_path);
        }

        // Store new logo
        $logoPath = $request->file('logo')->store('webstore', 'public');
        $info->logo_path = $logoPath;
        $info->save();

        return response()->json([
            'success' => true,
            'message' => 'Logo uploaded successfully',
            'data' => [
                'logo_url' => $info->logo_url,
            ],
        ]);
    }

    /**
     * Delete logo
     */
    public function deleteLogo(): JsonResponse
    {
        $info = WebstoreInfo::first();

        if (!$info) {
            return response()->json([
                'success' => false,
                'message' => 'Webstore information not found',
            ], 404);
        }

        if (!$info->logo_path) {
            return response()->json([
                'success' => false,
                'message' => 'No logo to delete',
            ], 404);
        }

        // Delete logo file
        if (Storage::disk('public')->exists($info->logo_path)) {
            Storage::disk('public')->delete($info->logo_path);
        }

        $info->logo_path = null;
        $info->save();

        return response()->json([
            'success' => true,
            'message' => 'Logo deleted successfully',
        ]);
    }
}
