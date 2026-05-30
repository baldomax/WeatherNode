@extends('errors.layout')

@section('title', __('503 — Maintenance mode'))

@section('content')
    @include('errors.partials.hero', [
        'code' => 503,
        'scene' => 'flood',
        'headline' => __('Station temporarily offline'),
        'message' => __('We're recalibrating the instruments. Check back shortly — clear skies ahead.'),
    ])
@endsection
