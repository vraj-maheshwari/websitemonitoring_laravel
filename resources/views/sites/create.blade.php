@extends('layouts.app')
@section('title', 'Add Site')
@section('content')
<form method="POST" action="{{ route('sites.store') }}" class="bg-white border rounded-lg p-5">
    @include('sites._form')
</form>
@endsection
