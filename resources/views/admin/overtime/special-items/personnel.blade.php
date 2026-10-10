@extends('layouts.admin')

@section('title', __('پرسنل مشمول') . ' — ' . $specialItem->title)

@section('content')
    @include('admin.overtime.partials.page-header', [
        'title' => __('پرسنل مشمول') . ' — ' . $specialItem->title,
        'subtitle' => __('مقدار ثابت این آیتم: :value ساعت برای هر پرسنل مشمول.', ['value' => $specialItem->default_value]),
        'action' => new \Illuminate\Support\HtmlString('<a href="' . route('admin.overtime.special-items.index') . '" class="rounded-lg border border-line px-4 py-2.5 text-sm font-medium text-ink/70">' . __('بازگشت') . '</a>'),
    ])

    <form method="GET" class="mb-4 flex gap-2">
        <input name="q" value="{{ request('q') }}" class="w-full max-w-md rounded-lg border border-line px-3 py-2 text-sm" placeholder="{{ __('نام یا کد پرسنلی…') }}">
        <button class="rounded-lg border border-line px-4 py-2 text-sm">{{ __('جست‌وجو') }}</button>
    </form>

    <form method="POST" action="{{ route('admin.overtime.special-items.personnel.sync', $specialItem) }}" class="rounded-2xl border border-line bg-white shadow-card">
        @csrf
        @method('PUT')
        @php($visibleIds = $personnel->getCollection()->pluck('id')->all())
        @foreach (array_diff($eligibleIds, $visibleIds) as $retainedId)
            <input type="hidden" name="retained_ids[]" value="{{ $retainedId }}">
        @endforeach
        <div class="overflow-x-auto">
            <table class="w-full text-start text-sm">
                <thead><tr class="border-b border-line text-xs text-ink/50"><th class="px-5 py-3">{{ __('مشمول') }}</th><th class="px-5 py-3">{{ __('پرسنل') }}</th><th class="px-5 py-3">{{ __('واحد / معاونت') }}</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($personnel as $person)
                        <tr>
                            <td class="px-5 py-3"><input type="checkbox" name="personnel_ids[]" value="{{ $person->id }}" @checked(in_array($person->id, $eligibleIds)) class="rounded border-line text-pine"></td>
                            <td class="px-5 py-3 font-medium">{{ trim($person->name . ' ' . $person->family) }} <span class="font-mono text-xs text-ink/40">{{ $person->personnel_code ?: $person->username }}</span></td>
                            <td class="px-5 py-3 text-xs text-ink/60">{{ $person->unit?->title ?? $person->deputy?->title ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-8 text-center text-ink/40">{{ __('پرسنلی یافت نشد.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line p-4">
            {{ $personnel->links() }}
            <button type="submit" class="rounded-lg bg-pine px-4 py-2.5 text-sm font-medium text-white">{{ __('ذخیره‌ی پرسنل مشمول') }}</button>
        </div>
    </form>
@endsection
