@extends('layouts.guest')
@section('title',ucfirst($mode))
@section('content')
<span class="eyebrow">SECUREVAULT</span>
<h2>{{ ['login'=>'Welcome back','register'=>'Create your private vault','forgot'=>'Forgot your password?','reset'=>'Set a new password'][$mode] }}</h2>
<p class="muted">{{ ['login'=>'Sign in to your private workspace.','register'=>'Keep everything important in one secure place.','forgot'=>'We’ll email you a link to reset your password.','reset'=>'Choose a strong password you haven’t used before.'][$mode] }}</p>
<form method="post" action="{{ ['login'=>url('/login'),'register'=>url('/register'),'forgot'=>route('password.email'),'reset'=>route('password.update')][$mode] }}">@csrf
@if($mode==='register')<label>Full name<input name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name"></label>@endif
@if($mode==='reset')<input type="hidden" name="token" value="{{ $token }}">@endif
<label>Email address<input name="email" type="email" required maxlength="255" value="{{ old('email',$email??'') }}" autocomplete="email"></label>
@if($mode!=='forgot')<label>Password<input name="password" type="password" required autocomplete="{{ $mode==='login'?'current-password':'new-password' }}" @if($mode!=='login') minlength="12" @endif maxlength="1024"></label>@endif
@if(in_array($mode,['register','reset']))<small class="muted">At least 12 characters, with uppercase, lowercase, a number, and a symbol.</small><label>Confirm password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>@endif
@if($mode==='register')<label class="check"><input name="terms" value="1" type="checkbox" required> I accept the <a href="{{ route('terms') }}" target="_blank" rel="noopener">terms and privacy notice</a>.</label>@endif
@if($mode==='login')<div class="text-end"><a href="{{ route('password.request') }}">Forgot password?</a></div>@endif
<button class="btn btn-primary full" type="submit">{{ ['login'=>'Sign in to SecureVault →','register'=>'Create account →','forgot'=>'Send reset link →','reset'=>'Update password →'][$mode] }}</button></form>
<div class="auth-switch">@if($mode==='login')New here? <a href="{{ route('register') }}">Create an account</a>@else<a href="{{ route('login') }}">← Back to sign in</a>@endif</div><p class="auth-note">◇ &nbsp; Email-based access. No phone number required.</p>
@endsection
