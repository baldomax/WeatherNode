@extends('errors.layout')

@section('title', __('429 — Too many requests'))

@section('content')
    @include('errors.partials.hero', [
        'code' => 429,
        'scene' => 'flood',
        'headline' => __("Whoa, that's a lot of requests!"),
        'message' => __('Our sensors are flooded. Take a breath, wait a minute, and try again — the barometer needs time to stabilize.'),
    ])
@endsection
