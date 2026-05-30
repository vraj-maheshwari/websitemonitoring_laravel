@extends('layouts.app')
@section('title', 'Login')
@section('content')
<div class="min-h-screen grid place-items-center bg-slate-50 p-6">
    <form method="POST" action="/login" class="w-full max-w-sm rounded-lg border bg-white p-6 space-y-4">
        @csrf
        <h1 class="text-xl font-semibold">Login</h1>
        @if ($errors->any()) <p class="text-sm text-red-700">{{ $errors->first() }}</p> @endif
        <input name="email" type="email" value="{{ old('email') }}" placeholder="Email" class="w-full rounded-md border p-2" required>
        <input name="password" type="password" placeholder="Password" class="w-full rounded-md border p-2" required>
        <button class="w-full rounded-md bg-slate-900 px-4 py-2 text-white">Login</button>
        <a href="/register" class="block text-sm text-slate-600">Create an account</a>
    </form>
</div>
@endsection
