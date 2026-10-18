@extends('errors.layout')

@section('code', '429')
@section('title', 'Too many requests')
@section('heading', 'Please slow down')
@section('message')
    Too many requests were made in a short time. Wait a minute, then try again.
@endsection
@section('actions')
    <a href="javascript:history.back()" class="btn btn-secondary">Go back</a>
@endsection
