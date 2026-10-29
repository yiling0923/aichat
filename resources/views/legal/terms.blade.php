@extends('legal.layout')
@section('title', __('Terms of service'))
@section('also'){!! __('Our :link explains how data is handled.', ['link' => '<a href="'.route('privacy').'">'.e(__('privacy notice')).'</a>']) !!}@endsection
