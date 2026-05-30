@extends('errors.layout')

@section('title', __('419 — Session expired'))

@section('content')
    @include('errors.partials.hero', [
        'code' => 419,
        'scene' => 'wind',
        'headline' => __('Your session drifted away'),
        'message' => __('Like morning fog, your session evaporated. Please refresh the page and try again — we'll keep the data warm.'),
    ])
@endsection
