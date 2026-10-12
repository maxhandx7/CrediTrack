@extends('layouts.portal')
@section('title', 'Código')
@section('body')
<div class="wrap auth">
    <a class="logo" href="{{ route('landing') }}" style="color:var(--ink);justify-content:center"><span class="logo-mark" style="color:#fff">C</span> CrediTrack</a>
    <h1 style="text-align:center">Revisa tu WhatsApp</h1>
    <p class="lead" style="text-align:center">Si tu cédula está registrada, te enviamos un código de 6 dígitos. Vence en 10 minutos.</p>
    <form class="card" method="post" action="{{ route('portal.verify.submit') }}">
        @csrf
        <input type="hidden" name="document" value="{{ $document }}">
        <label for="code">Código</label>
        <input id="code" name="code" class="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" required autofocus>
        @error('code')<p class="error">{{ $message }}</p>@enderror
        <button class="btn" type="submit">Entrar</button>
        <a class="btn btn-ghost" href="{{ route('portal.login') }}">Pedir otro código</a>
    </form>
</div>
@endsection
