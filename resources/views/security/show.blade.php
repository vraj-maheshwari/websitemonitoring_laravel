@extends('layouts.app')
@section('title', 'Security Detail')
@section('content')
@php($latestSeo = $site->seoLogs()->latest('checked_at')->first())
@include('sites.tabs.security', ['site' => $site])
@endsection
