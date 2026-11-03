@extends('layouts.guest')
@section('title','Verify your email')
@section('content')<span class="eyebrow">ONE MORE STEP</span><h2>Check your inbox</h2><p class="muted">Open the verification link sent to {{ auth()->user()->email }}. Links expire after 60 minutes. Sign in before opening the link.</p><form action="{{ route('verification.send') }}" method="post">@csrf<button class="btn btn-primary full">Resend verification email</button></form><form action="{{ route('logout') }}" method="post">@csrf<button class="btn btn-outline full">Sign out</button></form>@endsection
