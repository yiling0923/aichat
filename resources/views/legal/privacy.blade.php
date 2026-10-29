@extends('legal.layout')
@section('title', __('Privacy notice'))
@section('also'){!! __('Our :link cover the rest.', ['link' => '<a href="'.route('terms').'">'.e(__('terms of service')).'</a>']) !!}@endsection
