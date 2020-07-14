@extends('pdf::layouts.main')
@php
    /** @var \App\Services\Dto\Internal\OrderTicket\OrderInfoDto $order */
@endphp
@section('content')
    <h3>Заказ №{{ $order->number }}</h3>
@endsection