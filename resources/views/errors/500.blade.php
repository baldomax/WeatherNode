@extends('errors.layout')

@section('title', __('500 — Server error'))

@section('content')
    @include('errors.partials.hero', [
        'code' => 500,
        'scene' => 'lightning',
        'headline' => __('Thunderstorm detected in the server room'),
        'message' => __("A sudden pressure drop hit our backend. We're drying cables, rebooting clouds, and politely asking the lightning to leave."),
    ])
@endsection
