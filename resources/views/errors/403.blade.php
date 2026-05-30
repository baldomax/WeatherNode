@extends('errors.layout')

@section('title', __('403 — Forbidden'))

@section('content')
    @include('errors.partials.hero', [
        'code' => 403,
        'scene' => 'lightning',
        'headline' => __('Storm warning: access denied'),
        'message' => __("This area is under a severe weather advisory. You don't have clearance to enter — maybe check with the station manager."),
    ])
@endsection
