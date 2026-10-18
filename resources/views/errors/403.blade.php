@extends('errors.layout')

@section('code', '403')
@section('title', 'Access denied')
@section('heading', 'You do not have access to this page')
@section('message')
    @php($reason = isset($exception) ? $exception->getMessage() : '')
    {{ $reason && $reason !== 'This action is unauthorized.' && $reason !== 'Forbidden' ? $reason : 'Your role does not include this area of the desk. If you need access for your work, ask an administrator to review your role.' }}
@endsection
@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">Go to the overview</a>
    <a href="javascript:history.back()" class="btn btn-secondary">Go back</a>
@endsection
