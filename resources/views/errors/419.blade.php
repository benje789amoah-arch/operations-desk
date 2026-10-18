@extends('errors.layout')

@section('code', '419')
@section('title', 'Session expired')
@section('heading', 'Your session has expired')
@section('message')
    For security, the page could not be submitted because your session timed out. Sign in again, then re-enter anything you were typing.
@endsection
@section('actions')
    <a href="{{ url('/login') }}" class="btn btn-primary">Sign in again</a>
@endsection
