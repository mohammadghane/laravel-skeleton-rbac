@extends('layouts.admin')

@section('title', __('گزارش تغییرات'))

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-bold text-ink">{{ __('گزارش تغییرات') }}</h2>
        <p class="mt-1 text-sm text-ink/50">{{ __('تاریخچه کامل ایجاد، ویرایش و حذف رکوردها در سیستم') }}</p>
    </div>

    <form method="GET" class="mb-4 grid grid-cols-1 gap-3 rounded-2xl border border-line bg-white p-4 shadow-card sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label class="mb-1 block text-xs font-medium text-ink/60">{{ __('نوع مدل') }}</label>
            <select name="filters[auditable_type]" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                <option value="">{{ __('همه') }}</option>
                @foreach ($auditableTypes as $type)
                    <option value="{{ $type }}" @selected(request('filters.auditable_type') === $type)>
                        {{ __($modelLabels[$type] ?? class_basename($type)) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-ink/60">{{ __('نوع عملیات') }}</label>
            <select name="filters[action]" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                <option value="">{{ __('همه') }}</option>
                @foreach (['created' => __('ایجاد شد'), 'updated' => __('ویرایش شد'), 'deleted' => __('حذف شد'), 'restored' => __('بازیابی شد')] as $value => $label)
                    <option value="{{ $value }}" @selected(request('filters.action') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-ink/60">{{ __('از تاریخ') }}</label>
            <input type="date" name="filters[from]" value="{{ request('filters.from') }}" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-ink/60">{{ __('تا تاریخ') }}</label>
            <input type="date" name="filters[to]" value="{{ request('filters.to') }}" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="w-full rounded-lg bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-pine-600">{{ __('اعمال فیلتر') }}</button>
            @if (request()->hasAny(['filters']))
                <a href="{{ route('admin.audit-logs.index') }}" class="w-full rounded-lg border border-line px-4 py-2 text-center text-sm text-ink/60 hover:bg-surface">{{ __('پاک کردن') }}</a>
            @endif
        </div>
    </form>

    <div id="audit-logs-table" class="rounded-2xl border border-line bg-white shadow-card">
        @include('admin.audit-logs.partials.table', ['logs' => $logs, 'modelLabels' => $modelLabels])
    </div>
@endsection
