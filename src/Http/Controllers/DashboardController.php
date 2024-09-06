<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Alerts;
use Illuminate\Http\Request;
use App\Models\DeviceApiData;
use App\Models\DeviceSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Helpers\PreviousEntryHelper;


class DashboardController extends Controller
{
    public function index()
    {
        $latestData = DeviceApiData::select('device_id', 'mode', 'fault_status', 'weight', 'output_injection_rate', DB::raw('MAX(created_at) as latest'))
            ->groupBy('device_id', 'mode', 'fault_status', 'weight', 'output_injection_rate')
            ->get();

        // Calculate the percentage of devices in manual and auto modes
        $totalDevices = $latestData->count();
        $manualModeCount = $latestData->where('mode', 'M')->count();
        $autoModeCount = $latestData->where('mode', 'A')->count();
        $noFaultCount = $latestData->where('fault_status', 0)->count();
        $faultCount1 = $latestData->where('fault_status', 1)->count();
        $faultCount2 = $latestData->where('fault_status', 2)->count();
        $faultCount3 = $latestData->where('fault_status', 3)->count();

        $manualModePercentage = round(($manualModeCount / $totalDevices) * 100, 1);
        $autoModePercentage = round(($autoModeCount / $totalDevices) * 100, 1);
        $noFaultCountPercentage = round(($noFaultCount / $totalDevices) * 100, 1);
        $faultCount1Percentage = round(($faultCount1 / $totalDevices) * 100, 1);
        $faultCount2Percentage = round(($faultCount2 / $totalDevices) * 100, 1);
        $faultCount3Percentage = round(($faultCount3 / $totalDevices) * 100, 1);

        // Fetch distinct devices
        $devices = DeviceSettings::select('device_id')->distinct()->get();
        $weights = $latestData->pluck('weight', 'device_id')->toArray();
        $outputInjectionRate = $latestData->pluck('output_injection_rate', 'device_id')->toArray();


        // Fetch the most recently updated device
        $latestDevice = DeviceApiData::orderBy('created_at', 'desc')->first();
        $latestDeviceId = $latestDevice ? $latestDevice->device_id : null;
        $latestDeviceSettings = DeviceSettings::where('device_id', $latestDeviceId)->orderBy('created_at', 'desc')->first();

        // Prepare data for the weight histogram


        return view('dashboard', compact(
            'devices',
            'latestDeviceId',
            'manualModePercentage',
            'autoModePercentage',
            'noFaultCountPercentage',
            'faultCount1Percentage',
            'faultCount2Percentage',
            'faultCount3Percentage',
            'weights',
            'outputInjectionRate',
            'latestDeviceSettings'
        ));
    }

    public function getDashboardData()
    {
        $latestDeviceSettings = DeviceSettings::orderBy('created_at', 'desc')->first();

        $maxWeight = env('MAXIMUM_CHLORINE_WEIGHT');

        $latestHourlyData = DeviceApiData::select(
            'device_id',
            'mode',
            'fault_status',
            DB::raw("ROUND((weight / $maxWeight) * 100, 2) as weight_percentage"),
            'output_injection_rate',
            'network_status',
            'created_at',
            DB::raw("ROUND(process_value / 100) as process_value"),
        )
            ->whereIn('created_at', function ($query) {
                $query->select(DB::raw('MAX(created_at)'))
                    ->from('device_api_data')
                    ->where('created_at', '>=', DB::raw('(SELECT MAX(created_at) - INTERVAL 25 HOUR FROM device_api_data)'))
                    ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H')"));
            })
            ->orderByDesc('created_at')
            ->limit(24)
            ->get();

        $latestApiHitTime = PreviousEntryHelper::getLatestApiHitTime();
        $offlineThreshold = now()->subMinutes(2);
        $status = $latestApiHitTime > $offlineThreshold ? 'online' : 'offline';
        $latestAlerts = Alerts::select('alert_type', 'alert_message', 'created_at')->orderBy('created_at', 'desc')->limit(8)->get();

        return response()->json([
            'graphdata' => $latestHourlyData,
            'latest_update' => $latestDeviceSettings,
            'previous_update' => $latestApiHitTime,
            'alerts' => $latestAlerts,
            'online_status' => $status,
        ]);
    }


    public function getFilteredData($interval)
    {
        $maxWeight = env('MAXIMUM_CHLORINE_WEIGHT');
        $currentYear = date('Y');

        $selectFields = [
            'device_id',
            'mode',
            'fault_status',
            DB::raw("ROUND((weight / $maxWeight) * 100, 2) as weight_percentage"),
            'output_injection_rate',
            'network_status',
            'created_at',
            DB::raw("ROUND(process_value / 100) as process_value"),
        ];

        try {
            switch ($interval) {
                case 1:
                    $graphData = DeviceApiData::select(array_merge($selectFields, [DB::raw("ROUND(process_value / 100) as process_value")]))
                        ->whereIn('created_at', function ($query) {
                            $query->select(DB::raw('MAX(created_at)'))
                                ->from('device_api_data')
                                ->where('created_at', '>=', DB::raw('(SELECT MAX(created_at) - INTERVAL 25 HOUR FROM device_api_data)'))
                                ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H')"));
                        })
                        ->orderByDesc('created_at')
                        ->limit(24)
                        ->get();
                    break;

                case 2:
                    $graphData = DeviceApiData::select($selectFields)
                        ->whereIn('created_at', function ($query) {
                            $query->select(DB::raw('MAX(created_at)'))
                                ->from('device_api_data')
                                ->where('created_at', '>=', DB::raw('(SELECT MAX(created_at) - INTERVAL 7 DAY FROM device_api_data)'))
                                ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d')"));
                        })
                        ->orderByDesc('created_at')
                        ->limit(7)
                        ->get();
                    break;

                case 3:
                    $graphData = DeviceApiData::select($selectFields)
                        ->whereIn('created_at', function ($query) {
                            $query->select(DB::raw('MAX(created_at)'))
                                ->from('device_api_data')
                                ->where('created_at', '>=', DB::raw('(SELECT MAX(created_at) - INTERVAL 30 DAY FROM device_api_data)'))
                                ->groupBy(DB::raw("FLOOR(DATEDIFF(created_at, (SELECT MAX(created_at) - INTERVAL 30 DAY FROM device_api_data)) / 4)"));
                        })
                        ->orderByDesc('created_at')
                        ->limit(8)
                        ->get();
                    break;

                case 4:
                    $graphData = DeviceApiData::select($selectFields)
                        ->whereYear('created_at', $currentYear)
                        ->whereIn('created_at', function ($query) use ($currentYear) {
                            $query->select(DB::raw('MIN(created_at)'))
                                ->from('device_api_data')
                                ->whereYear('created_at', $currentYear)
                                ->groupBy(DB::raw("YEAR(created_at), MONTH(created_at)"));
                        })
                        ->orderBy('created_at')
                        ->get();
                    break;

                default:
                    return response()->json(['error' => 'Invalid interval'], 400);
            }
            $latestApiHitTime = PreviousEntryHelper::getLatestApiHitTime();

            return response()->json([
                'graphdata' => $graphData,
                'previous_update' => $latestApiHitTime,
            ]);
        } catch (QueryException $e) {
            Log::error('Database query error: ' . $e->getMessage());
            return response()->json(['error' => 'Database query error'], 500);
        } catch (\Exception $e) {
            Log::error('General error: ' . $e->getMessage());
            return response()->json(['error' => 'An unexpected error occurred'], 500);
        }
    }

    public function getFilteredAlerts($interval, $type = null)
    {
        $currentYear = Carbon::now()->year;
        $selectFields = ['alert_type', 'alert_message', 'created_at'];
        $alerts = null;

        // Start building the query
        $query = Alerts::select($selectFields);

        // Apply time-based filtering
        switch ($interval) {
            case 1:
            case 7:
            case 30:
                $query->where('created_at', '>=', Carbon::now()->subDays($interval));
                break;

            case 12:
                $query->whereYear('created_at', $currentYear);
                break;

            default:
                return response()->json(['error' => 'Invalid Time Period'], 400);
        }

        // Apply type filtering if type is provided
        if (!empty($type)) {
            if (!in_array($type, ['Error', 'Warning', 'Info'])) {
                return response()->json(['error' => 'Invalid Alert Type'], 400);
            }
            $query->where('alert_type', $type);
        }

        // Order by alert_id and limit the results
        $alerts = $query->orderBy('alert_id', 'desc')
            ->limit(10)
            ->get();

        $latestApiHitTime = PreviousEntryHelper::getLatestApiHitTime();

        return response()->json([
            'alerts' => $alerts,
            'previous_update' => $latestApiHitTime,
        ]);
    }



    public function checkDeviceOnlineStatus($device_id)
    {
        $deviceData = DeviceApiData::where('device_id', $device_id)->orderBy('id', 'desc')->first();

        $offlineThreshold = now()->subMinutes(2);
        $status = $deviceData->created_at > $offlineThreshold ? 'online' : 'offline';
        $latestApiHitTime = PreviousEntryHelper::getLatestApiHitTime();

        return response()->json([
            'online_status' => $status,
            'network_status' => $deviceData->network_status,
            'previous_update' => $latestApiHitTime,

        ]);
    }

    public function getDeviceData(Request $request)
    {
        $deviceId = $request->get('device_id');
        $data = DeviceApiData::where('device_id', $deviceId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->reverse();

        $labels = $data->pluck('created_at')->map(function ($date) {
            return $date->format('Y-m-d H:i:s');
        });

        $values = $data->pluck('output_injection_rate');

        return response()->json([
            'labels' => $labels,
            'values' => $values
        ]);
    }

    public function getDeviceModeData(Request $request)
    {
        $deviceId = $request->get('device_id');
        $data = DeviceApiData::where('device_id', $deviceId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->reverse();

        $labels = $data->pluck('created_at')->map(function ($date) {
            return $date->format('Y-m-d H:i:s');
        });

        $values = $data->pluck('flow_rate');
        $hello = 1;

        return response()->json([
            'labels' => $labels,
            'values' => $values
        ]);
    }

    public function updateDeviceSettings(Request $request)
    {

        $deviceId = $request->get('device_id');
        $deviceSettings = DeviceSettings::where('device_id', $deviceId)->orderBy('created_at', 'desc')->first();
        if ($deviceSettings->key != $request->key || $deviceSettings->updation_rate != $request->updation_rate) {
            $settings_flag = 2;
        } else {
            $settings_flag = 1;
        }
        if ($deviceSettings->mode == $request->mode && $deviceSettings->device_status == $request->device_status && $deviceSettings->setpoint == $request->setpoint && $deviceSettings->key == $request->key && $deviceSettings->updation_rate == $request->updation_rate) {
            return response()->json([
                'success' => true,
                'message' => 'No changes has been detected.',
                'flag' => 1,
            ]);
        } else {

            $setpoint = round($request->setpoint, 2);

            $deviceSettingsArray = [
                'device_id' => $deviceId,
                'mode' => $request->mode,
                'device_status' => $request->device_status,
                'setpoint' => $setpoint,
                'key'   => $request->key,
                'updation_rate' => $request->updation_rate,
                'settings_flag' => $settings_flag,
            ];

            $deviceSettingsEntry = new DeviceSettings($deviceSettingsArray);
            $deviceSettingsEntry->save();

            return response()->json([
                'success' => true,
                'message' => 'Settings for device ' . $deviceId . ' successfully.',
                'flag'    => 2,
            ]);
        }
    }

    public function fetchDeviceSettings(Request $request)
    {
        $deviceId = $request->get('device_id');
        $deviceSettings = DeviceSettings::where('device_id', $deviceId)->orderBy('created_at', 'desc')->first();
        return response()->json([
            'success' => true,
            'data' => $deviceSettings,
        ]);
    }

    public function toggleDeviceStatus(Request $request)
    {
        try {
            $deviceId = $request->device_id;
            $deviceSettings = DeviceSettings::where('device_id', $deviceId)->orderBy('created_at', 'desc')->first();

            if (!$deviceSettings) {
                throw new \Exception('Device settings not found for device ID: ' . $deviceId);
            }

            $mode = $deviceSettings->mode == 'A' ? 'M' : 'A';

            $newDeviceSettings = [
                'device_id'        => $deviceId,
                'mode'             => $mode,
                'device_status'    => $deviceSettings->device_status,
                'setpoint'         => $deviceSettings->setpoint,
                'key'              => $deviceSettings->key,
                'updation_rate'    => $deviceSettings->updation_rate,
                'settings_flag'    => $deviceSettings->settings_flag,
            ];

            DeviceSettings::create($newDeviceSettings);
            $latestApiHitTime = PreviousEntryHelper::getLatestApiHitTime();

            return response()->json([
                'status' => 'success',
                'previous_update' => $latestApiHitTime,
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling device status: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['status' => 'error', 'message' => 'An error occurred while toggling the device status.'], 500);
        }
    }
    public function toggleDeviceOnOffStatus(Request $request)
    {
        try {
            $deviceId = $request->device_id;
            $deviceSettings = DeviceSettings::where('device_id', $deviceId)->orderBy('created_at', 'desc')->first();

            if (!$deviceSettings) {
                throw new \Exception('Device settings not found for device ID: ' . $deviceId);
            }

            $device_status = $deviceSettings->device_status == 'ON' ? 'OFF' : 'ON';

            $newDeviceSettings = [
                'device_id'        => $deviceId,
                'mode'             => $deviceSettings->mode,
                'device_status'    => $device_status,
                'setpoint'         => $deviceSettings->setpoint,
                'key'              => $deviceSettings->key,
                'updation_rate'    => $deviceSettings->updation_rate,
                'settings_flag'    => $deviceSettings->settings_flag,
            ];

            DeviceSettings::create($newDeviceSettings);
            $latestApiHitTime = PreviousEntryHelper::getLatestApiHitTime();

            return response()->json([
                'status' => 'success',
                'previous_update' => $latestApiHitTime,
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling device status: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['status' => 'error', 'message' => 'An error occurred while toggling the device status.'], 500);
        }
    }

    public function updateSetpoint(Request $request, $Device_ID)
    {
        try {
            $validatedData = $request->validate([
                'setpoint' => 'required|numeric|min:0|max:5',
            ]);

            $newSetpoint = round($validatedData['setpoint'], 2);

            $deviceSettings = DeviceSettings::where('device_id', $Device_ID)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$deviceSettings) {
                throw new \Exception('Device settings not found for device ID: ' . $Device_ID);
            }

            if ($deviceSettings->setpoint != $newSetpoint) {
                $newDeviceSettings = [
                    'device_id' => $Device_ID,
                    'mode' => $deviceSettings->mode,
                    'device_status' => $deviceSettings->device_status,
                    'setpoint' => $newSetpoint,
                    'key' => $deviceSettings->key,
                    'updation_rate' => $deviceSettings->updation_rate,
                    'settings_flag' => $deviceSettings->settings_flag,
                ];
                DeviceSettings::create($newDeviceSettings);

                return response()->json(['status' => 'success', 'message' => 'Updated setpoint']);
            } else {
                return response()->json(['status' => 'fail', 'message' => 'No change in the given setpoint']);
            }
        } catch (\Exception $e) {
            Log::error('Error updating setpoint: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['status' => 'error', 'message' => 'An error occurred while updating the setpoint.'], 500);
        }
    }
}
