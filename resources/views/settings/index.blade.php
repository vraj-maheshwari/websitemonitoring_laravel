@extends('layouts.app')
@section('title', 'Settings')
@section('content')
<div class="grid gap-6 md:grid-cols-2">
    <section class="bg-white border rounded-lg p-5">
        <h2 class="font-semibold">Account</h2>
        <p class="mt-2 text-sm text-slate-600">{{ auth()->user()->email }}</p>
    </section>
    <form method="POST" action="{{ route('settings.update') }}" class="bg-white border rounded-lg p-5 space-y-4">
        @csrf @method('PUT')
        <h2 class="font-semibold">Change Password</h2>
        <input name="current_password" type="password" placeholder="Current password" class="w-full rounded-md border p-2" required>
        <input name="password" type="password" placeholder="New password" class="w-full rounded-md border p-2" required>
        <input name="password_confirmation" type="password" placeholder="Confirm password" class="w-full rounded-md border p-2" required>
        <button class="rounded-md bg-slate-900 px-4 py-2 text-white">Update Password</button>
    </form>
</div>
@endsection
