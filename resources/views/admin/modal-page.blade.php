{{--
    Fallback wrapper for any "modal form" partial (roles/users create-edit, role permissions)
    when it's requested as a direct page load instead of pulled into #admin-modal via fetch.
    Controllers decide which to return based on $request->ajax().
--}}
@extends('layouts.admin')

@section('title', $title ?? '')

@section('content')
    <div class="mx-auto max-w-2xl rounded-2xl border border-line bg-white p-6 shadow-card">
        @include($view, $data ?? [])
    </div>
@endsection
