<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KycStorageConfig;
use App\Utils\ResponseUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class KycStorageConfigController extends Controller
{
    public function index()
    {
        try {
            return ResponseUtils::success(KycStorageConfig::all(), 'Storage configurations retrieved successfully');
        } catch (\Exception $e) {
            return ResponseUtils::error('Failed to retrieve storage configurations', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|unique:kyc_storage_configs,name',
                'driver' => 'required|string|in:local,s3,sftp',
                'settings' => 'required|array',
                'settings.path' => 'required|string',
                'settings.disk' => 'required|string',
                'is_active' => 'boolean'
            ]);

            if ($validated['is_active'] ?? false) {
                KycStorageConfig::where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $config = KycStorageConfig::create($validated);

            if ($config->is_active) {
                Config::set('kyc.storage', [
                    'driver' => $config->driver,
                    'disk' => $config->settings['disk'],
                    'path' => $config->settings['path']
                ]);
            }

            return ResponseUtils::success($config, 'Storage configuration created successfully', 201);
        } catch (\Exception $e) {
            return ResponseUtils::error('Failed to create storage configuration', 500);
        }
    }

    public function show(KycStorageConfig $config)
    {
        try {
            return ResponseUtils::success($config, 'Storage configuration retrieved successfully');
        } catch (\Exception $e) {
            return ResponseUtils::error('Failed to retrieve storage configuration', 500);
        }
    }

    public function update(Request $request, KycStorageConfig $config)
    {
        try {
            $validated = $request->validate([
                'name' => 'string|unique:kyc_storage_configs,name,' . $config->id,
                'driver' => 'string|in:local,s3,sftp',
                'settings' => 'array',
                'settings.path' => 'required_with:settings|string',
                'settings.disk' => 'required_with:settings|string',
                'is_active' => 'boolean'
            ]);

            if ($validated['is_active'] ?? false) {
                KycStorageConfig::where('id', '!=', $config->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $config->update($validated);

            if ($config->is_active) {
                Config::set('kyc.storage', [
                    'driver' => $config->driver,
                    'disk' => $config->settings['disk'],
                    'path' => $config->settings['path']
                ]);
            }

            return ResponseUtils::success($config, 'Storage configuration updated successfully');
        } catch (\Exception $e) {
            return ResponseUtils::error('Failed to update storage configuration', 500);
        }
    }

    public function destroy(KycStorageConfig $config)
    {
        try {
            if ($config->is_active) {
                return ResponseUtils::error('Cannot delete active configuration', 422);
            }

            $config->delete();
            return ResponseUtils::success(null, 'Storage configuration deleted successfully');
        } catch (\Exception $e) {
            return ResponseUtils::error('Failed to delete storage configuration', 500);
        }
    }
}