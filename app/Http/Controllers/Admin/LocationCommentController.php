<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BulkDeletes;
use App\Http\Controllers\Controller;
use App\Models\LocationComment;
use App\Services\LocationProximityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ثبت نظر روی نقشه - فقط اگر نقطه‌ی انتخابی داخل شعاع مجاز یکی از موقعیت‌ها باشد.
 */
class LocationCommentController extends Controller
{
    use BulkDeletes;

    public function __construct(private readonly LocationProximityService $proximity)
    {
    }

    public function index(Request $request): View
    {
        $comments = LocationComment::with(['user', 'location'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where('body', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('family', 'like', $term))
                    ->orWhereHas('location', fn ($l) => $l->where('title', 'like', $term));
            })
            ->orderByDesc('id')
            ->get();

        if ($request->ajax() && $request->boolean('rows_only')) {
            return view('admin.location-comments.partials.table', compact('comments'));
        }

        return view('admin.location-comments.index', compact('comments'));
    }

    /**
     * بررسی زنده‌ی یک نقطه - با هر کلیک روی نقشه صدا زده می‌شود.
     *
     * همان سرویسی که store() استفاده می‌کند، تا پیامی که کاربر می‌بیند دقیقاً
     * همان چیزی باشد که هنگام ثبت اعمال می‌شود.
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $lat = (float) $validated['latitude'];
        $lng = (float) $validated['longitude'];

        $result = $this->proximity->check($lat, $lng);

        if ($result['allowed']) {
            return response()->json([
                'allowed' => true,
                'message' => __('شما مجاز هستید.'),
                'location' => $result['location']->title,
                'distance' => $result['distance'],
            ]);
        }

        // فاصله تا نزدیک‌ترین موقعیت را هم می‌دهیم: پیام «مجاز نیستید» بدون
        // اینکه بگوید چقدر دور است، کاربر را سردرگم می‌گذارد.
        $nearest = $this->proximity->nearest($lat, $lng);

        return response()->json([
            'allowed' => false,
            'message' => __('شما مجاز نیستید.'),
            'location' => $nearest['location']->title ?? null,
            'distance' => $nearest['distance'] ?? null,
        ]);
    }

    public function create(Request $request): View
    {
        // محدوده‌های مجاز فقط برای نمایش دایره‌ها روی نقشه فرستاده می‌شوند تا
        // کاربر پیش از کلیک بداند کجا مجاز است. تصمیم واقعی همچنان سمت سرور
        // گرفته می‌شود (check/store)، پس دستکاری این داده چیزی را باز نمی‌کند.
        $data = ['zones' => $this->allowedZones()];

        if ($request->ajax()) {
            return view('admin.location-comments.form', $data);
        }

        return view('admin.modal-page', [
            'title' => __('ثبت نظر'),
            'view' => 'admin.location-comments.form',
            'data' => $data,
        ]);
    }

    /** محدوده‌های فعال، برای رسم دایره روی نقشه. */
    private function allowedZones()
    {
        return \App\Models\Location::where('is_active', true)
            ->get()
            ->map(fn (\App\Models\Location $l) => [
                'title' => $l->title,
                'lat' => (float) $l->latitude,
                'lng' => (float) $l->longitude,
                'radius' => $l->allowed_radius,
            ])
            ->values();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'latitude.required' => __('نقطه‌ای روی نقشه انتخاب نشده است.'),
            'longitude.required' => __('نقطه‌ای روی نقشه انتخاب نشده است.'),
            'body.required' => __('متن نظر الزامی است.'),
        ]);

        $lat = (float) $validated['latitude'];
        $lng = (float) $validated['longitude'];

        // بررسی دوباره سمت سرور - پیام سمت کلاینت فقط راهنماست و قابل دور زدن
        // است؛ تصمیم واقعی اینجا گرفته می‌شود.
        $result = $this->proximity->check($lat, $lng);

        if (!$result['allowed']) {
            return response()->json([
                'success' => false,
                'message' => __('شما مجاز نیستید. نقطه‌ی انتخابی در محدوده‌ی هیچ موقعیت تعریف‌شده‌ای نیست.'),
            ], 422);
        }

        $comment = LocationComment::create([
            'user_id' => $request->user()->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'body' => $validated['body'],
            'was_allowed' => true,
            'location_id' => $result['location']->id,
            'distance_meters' => $result['distance'],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('شما مجاز هستید؛ نظر شما ثبت شد.'),
            'data' => $comment,
        ]);
    }

    public function destroy(LocationComment $locationComment): JsonResponse
    {
        $locationComment->delete();

        return response()->json(['success' => true, 'message' => __('نظر حذف شد.')]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->bulkDelete($request, LocationComment::class, fn () => null, __('نظر'));
    }
}
