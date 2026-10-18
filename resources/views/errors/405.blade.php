@extends('errors.layout')

@section('code', '405')
@section('title', 'Not available this way')
@section('heading', 'That page cannot be opened directly')
@section('message')
    This address only accepts form submissions, for example signing out from the account menu. Use the buttons in the application instead of typing the address.
@endsection
@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">Go to the overview</a>
@endsection
