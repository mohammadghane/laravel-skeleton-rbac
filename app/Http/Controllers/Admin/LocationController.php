<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BulkDeletes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LocationRequest;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * موقعیت‌های جغرافیایی - ثبت نقطه روی نقشه‌ی OpenStreetMap.
 */
class LocationController extends Controller
{
    use BulkDeletes;

    public function index(Request $request): View
    {
        $locations = Location::with('creator')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($s) => $s->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term));
            })
            ->orderByDesc('id')
            ->get();

        if ($request->ajax() && $request->boolean('rows_only')) {
            return view('admin.locations.partials.table', compact('locations'));
        }

        return view('admin.locations.index', compact('locations'));
    }

    public function edit(Request $request, ?Location $location = null): View
    {
        $data = ['location' => $location];

        if ($request->ajax()) {
            return view('admin.locations.form', $data);
        }

        return view('admin.modal-page', [
            'title' => $location?->exists ? __('ویرایش موقعیت') : __('موقعیت جدید'),
            'view' => 'admin.locations.form',
            'data' => $data,
        ]);
    }

    public function store(LocationRequest $request): JsonResponse
    {
        $location = Location::create($request->validated() + [
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('موقعیت «:title» ثبت شد.', ['title' => $location->title]),
            'data' => $location,
        ]);
    }

    public function update(LocationRequest $request, Location $location): JsonResponse
    {
        $location->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => __('موقعیت «:title» ویرایش شد.', ['title' => $location->title]),
            'data' => $location,
        ]);
    }

    public function destroy(Location $location): JsonResponse
    {
        $location->delete();

        return response()->json(['success' => true, 'message' => __('موقعیت حذف شد.')]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->bulkDelete($request, Location::class, fn () => null, __('موقعیت'));
    }
}
