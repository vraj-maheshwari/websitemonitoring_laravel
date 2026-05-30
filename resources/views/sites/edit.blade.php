@extends('layouts.app')
@section('title', 'Edit Site')
@section('content')
<form method="POST" action="{{ route('sites.update', $site) }}" class="bg-white border rounded-lg p-5">
    @method('PUT')
    @include('sites._form')
</form>
@endsection
