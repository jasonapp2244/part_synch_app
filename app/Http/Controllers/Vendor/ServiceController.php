<?php

namespace App\Http\Controllers\Vendor;


use Exception;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class ServiceController extends Controller
{
    public function addService(Request $request)
    {
        // dd($request->all());
        // dd($request->all());
        try {

            $validated = $request->validate([
                'product_id'       => 'nullable|exists:products,id',
                'service_types'    => 'nullable|string',
                'title'            => 'required|string',
                'description'      => 'nullable|string',
                'price'            => 'nullable|numeric|min:0',
                'start_day'        => 'nullable|date',
                'end_day'          => 'nullable|date|after_or_equal:start_day',
                'start_time'       => 'nullable|date_format:H:i',
                'end_time'         => 'nullable|date_format:H:i|after:start_time',
                'duration_type'    => ['nullable', Rule::in(['weekly', 'monthly', 'yearly'])],
                'is_recurring'     => 'boolean',
                'service_mode'     => ['nullable', Rule::in(['online', 'offline'])],
                'status'           => ['nullable', Rule::in(['active', 'inactive'])],
            ]);

            if ($request->product_id && !Product::where('id', $request->product_id)->exists()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product not found!',
                ], 400);
            }
            $service = Service::where('product_id', $request->product_id)->first();
            if ($service) {
                return response()->json([
                    'status' => false,
                    'message' => 'This Product service already exists!',
                ], 400);
            }

            $service = new Service();
            $service->user_id = Auth::id();
            $service->product_id = $request->product_id;
            $service->service_types = $request->service_types;
            $service->title = $request->title;
            $service->description = $request->description;
            $service->price = $request->price;
            $service->start_day = $request->start_day;
            $service->end_day = $request->end_day;
            $service->start_time = $request->start_time;
            $service->end_time = $request->end_time;
            $service->duration_type = $request->duration_type;
            $service->is_recurring = $request->is_recurring;
            $service->service_mode = $request->service_mode;
            $service->status = $request->status ?? 'active';

            if ($service->save()) {
                return response()->json([
                    'status' => true,
                    'message' => 'Service added successfully!',
                    'service_type' => Service::SERVICE_TYPES,
                    'duration_type' => Service::DURATION_TYPES,
                    'data' => $service,
                ], 201);
            }
        } catch (Exception $e) {
            Log::error('Service Insertion Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Service could not be inserted!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function showService($id = null)
    {
        try {
            if ($id) {
                $service = Service::find($id);
                if (!$service) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Service not found!',
                    ], 400);
                }
                return response()->json([
                    'status' => true,
                    'data' => $service,
                ], 200);
            }

            $services = Service::all();
            return response()->json([
                'status' => true,
                'data' => $services,
            ], 200);
        } catch (Exception $e) {
            Log::error('Show Service Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error fetching services!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateService(Request $request, $id)
    {
        try {
            $service = Service::find($id);

            if (!$service) {
                return response()->json([
                    'status' => false,
                    'message' => 'Service not found!',
                ], 400);
            }


            if ($request->has(key: 'service_types') && !array_key_exists($request->service_types, Service::SERVICE_TYPES)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid service type! Allowed: ' . implode(', ', array_keys(Service::SERVICE_TYPES)),
                ], 400);
            }

            if ($request->has('duration_type') && !array_key_exists($request->duration_type, Service::DURATION_TYPES)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid duration type! Allowed: ' . implode(', ', array_keys(Service::DURATION_TYPES)),
                ], 400);
            }


            $service->product_id = $request->product_id ?? $service->product_id;
            $service->service_types = $request->service_types ?? $service->service_types;
            $service->title = $request->title ?? $service->title;
            $service->description = $request->description ?? $service->description;
            $service->price = $request->price ?? $service->price;
            $service->start_day = $request->start_day ?? $service->start_day;
            $service->end_day = $request->end_day ?? $service->end_day;
            $service->start_time = $request->start_time ?? $service->start_time;
            $service->end_time = $request->end_time ?? $service->end_time;
            $service->duration_type = $request->duration_type ?? $service->duration_type;
            $service->is_recurring = $request->is_recurring ?? $service->is_recurring;
            $service->service_mode = $request->service_mode ?? $service->service_mode;
            $service->status = $request->status ?? $service->status;

            if ($service->save()) {
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No changes detected!',
                ], 200);
            }

            return response()->json([
                'status' => true,
                'service_types'=>SERVICE::SERVICE_TYPES,
                'duration_types'=>SERVICE::DURATION_TYPES,
                'message' => 'Service updated successfully!',
                'data' => $service,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Service Update Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Service update failed!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function deleteService($id)
    {
        try {
            $service = Service::find($id);
            if (!$service) {
                return response()->json([
                    'status' => false,
                    'message' => 'Service not found!',
                ], 400);
            }

            if ($service->delete()) {
                return response()->json([
                    'status' => true,
                    'message' => 'Service deleted successfully!',
                ], 200);
            }
        } catch (Exception $e) {
            Log::error('Service Deletion Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Service deletion failed!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
