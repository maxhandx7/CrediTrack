@extends('layouts.portal')
@section('title', 'Gestión de préstamos')
@section('body')
<div class="hero">
    <div>
        <span class="logo" style="justify-content:center"><span class="logo-mark">C</span> CrediTrack</span>
        <h1>Tus préstamos, claros y al día.</h1>
        <p>Cuotas, pagos, recordatorios por WhatsApp y recibos en un solo lugar.</p>
        <div class="choices">
            <a class="btn" href="{{ route('portal.login') }}">Soy cliente: ver mi préstamo</a>
            <a class="btn btn-ghost" href="{{ url('/admin') }}">Soy prestamista</a>
        </div>
    </div>
</div>
@endsection
