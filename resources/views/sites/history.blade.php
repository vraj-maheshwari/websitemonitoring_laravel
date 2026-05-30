@extends('layouts.app')
@section('title', 'History')
@section('content')
<pre class="rounded-md bg-white border p-4 text-xs overflow-auto">{{ json_encode($data, JSON_PRETTY_PRINT) }}</pre>
@endsection
