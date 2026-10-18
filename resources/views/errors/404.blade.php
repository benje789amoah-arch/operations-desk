@extends('errors.layout')

@section('code', '404')
@section('title', 'Not found')
@section('heading', 'We could not find that page')
@section('message')
    The record or page may have been moved, or the link may be incomplete. Activity records are never deleted, so check the reference and try searching the register.
@endsection
@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">Go to the overview</a>
    <a href="{{ url('/daily-register') }}" class="btn btn-secondary">Open the daily register</a>
@endsection
