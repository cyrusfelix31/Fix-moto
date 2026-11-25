@extends('admin.layouts.app')
@section('title','Settings')
@section('page_title','Settings')
@section('content')
<div class="admin-page"><div class="admin-page-header"><div><p class="live-kicker">SYSTEM CONFIGURATION</p><h1>Settings</h1><p>Configure dispatch behavior and timeouts.</p></div></div><section class="distribution-card" style="height:auto;max-width:760px"><form method="POST" action="{{ route('admin.settings.update') }}" class="form-grid">@csrf<label>Maximum dispatch radius (km)<input type="number" name="dispatch_radius" value="{{ $settings->firstWhere('key','dispatch_radius')?->value ?? 25 }}" min="1" max="100"></label><label>Shop response timeout (seconds)<input type="number" name="response_timeout" value="{{ $settings->firstWhere('key','response_timeout')?->value ?? 90 }}" min="10" max="600"></label><label>Overall dispatch timeout (minutes)<input type="number" name="dispatch_timeout" value="{{ $settings->firstWhere('key','dispatch_timeout')?->value ?? 120 }}" min="10" max="1440"></label><button class="export-button" style="align-self:end;justify-content:center">Save settings</button></form></section></div>
@endsection
