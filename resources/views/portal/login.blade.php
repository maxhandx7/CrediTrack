@extends('layouts.portal')
@section('title', 'Ingresar')
@section('body')
<div class="wrap auth">
    <a class="logo" href="{{ route('landing') }}" style="color:var(--ink);justify-content:center"><span class="logo-mark" style="color:#fff">C</span> CrediTrack</a>
    <h1 style="text-align:center">Consulta tu préstamo</h1>
    <p class="lead" style="text-align:center">Mira tus cuotas, tus pagos y descarga tus recibos.</p>
    <form class="card" method="post" action="{{ route('portal.code') }}">
        @csrf
        <label for="document">Número de cédula</label>
        <input id="document" name="document" inputmode="numeric" autocomplete="off" value="{{ old('document') }}" required autofocus>
        @error('document')<p class="error">{{ $message }}</p>@enderror
        <button class="btn" type="submit">Enviarme un código por WhatsApp</button>
        <p class="note">Te llega al número que tiene registrado tu prestamista.</p>
    </form>
</div>
@endsection
