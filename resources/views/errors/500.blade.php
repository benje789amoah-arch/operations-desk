@extends('errors.layout')

@section('code', '500')
@section('title', 'Something went wrong')
@section('heading', 'Something went wrong on our side')
@section('message')
    The error has been logged for the support team. Your previously saved records are safe. Try again in a moment; if it keeps happening, report the time it occurred.
@endsection
@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">Go to the overview</a>
@endsection
