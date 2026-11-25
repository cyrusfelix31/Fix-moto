@extends('admin.layouts.app')
@section('title','Live Dispatch')
@section('page_title','Live dispatch')
@section('content')
<div class="admin-page"><div class="admin-page-header"><div><p class="live-kicker">GPS OPERATIONS · CALAPAN CITY</p><h1>Live dispatch</h1><p>Monitor available mechanic shops and active roadside requests.</p></div></div><div class="map-card"><div class="panel-header"><h2>Dispatch map</h2><div class="map-legend"><span><i class="legend-dot green"></i>Available shops</span><span><i class="legend-dot amber"></i>Offline shops</span><span><i class="legend-dot red"></i>Unassigned</span></div></div><div id="liveDispatchMap" style="height:500px;border-radius:8px"></div></div></div>
<script>
const map = L.map('liveDispatchMap').setView([13.4112, 121.1803], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
@foreach($mechanics as $profile)
    @php($shopName = $profile->shop_name ?: ($profile->mechanic?->name ?? 'Mechanic shop'))
    @php($shopStatus = $profile->is_online && $profile->active_members_count > 0 ? 'Available' : 'Offline')
    @php($shopDetails = $shopName.' — '.$shopStatus.' — '.$profile->service_address.' — '.$profile->active_members_count.' mechanic(s)')
    L.marker([{{ $profile->latitude }}, {{ $profile->longitude }}]).addTo(map)
        .bindPopup(document.createTextNode({{ Js::from($shopDetails) }}));
@endforeach
@foreach($requests as $request)
    @php($requestDetails = $request->reference.' — '.$request->issue_category)
    L.marker([{{ $request->latitude }}, {{ $request->longitude }}]).addTo(map)
        .bindPopup(document.createTextNode({{ Js::from($requestDetails) }}));
@endforeach
</script>
@endsection
