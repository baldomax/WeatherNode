@extends('errors.layout')

@section('title', __('404 — Page not found'))

@section('content')
    @include('errors.partials.hero', [
        'code' => 404,
        'scene' => 'wind',
        'headline' => __('Gusts took the page. Sorry.'),
        'message' => __('We scanned the skies and found… nothing. Either the URL is wrong, or a cheeky tailwind yeeted this page into the North Sea.'),
    ])
@endsection
