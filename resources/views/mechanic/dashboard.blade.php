@extends('layouts.app')
@section('title','Mechanic Shop Dashboard')
@section('page_title','Mechanic shop dashboard')
@section('content')
<div class="page mechanic-page">
    <div class="grid stats">
        <article class="card stat-card"><div><span class="muted">Verification</span><strong>{{ $profile->is_verified ? 'Verified' : 'Pending' }}</strong></div><span class="stat-icon"><i data-lucide="badge-check"></i></span></article>
        <article class="card stat-card"><div><span class="muted">Incoming shop requests</span><strong>{{ $offerCount }}</strong></div><span class="stat-icon"><i data-lucide="radio"></i></span></article>
        <article class="card stat-card"><div><span class="muted">Active requests</span><strong>{{ $activeJobCount }}</strong></div><span class="stat-icon"><i data-lucide="briefcase"></i></span></article>
        <article class="card stat-card"><div><span class="muted">Active mechanics</span><strong>{{ $profile->active_members_count }}</strong><small class="muted">{{ $teamMemberCount }} registered</small></div><span class="stat-icon"><i data-lucide="users"></i></span></article>
    </div>

    <section class="card" style="margin-top:18px">
        <div class="card-header"><div><h2>{{ $profile->shop_name }}</h2><p class="muted">{{ $profile->service_address }}</p></div><span class="badge {{ $profile->is_online ? '' : 'amber' }}">{{ $profile->is_online ? 'Available' : 'Offline' }}</span></div>
        <p class="muted">Manage shop requests, dispatch mechanics, and keep your shop information up to date from the separate pages.</p>
        <div class="grid content-grid">
            <a class="card mechanic-link-card" href="{{ route('mechanic.shop-requests') }}"><h2>Shop requests</h2><p class="muted">Review incoming offers and manage active requests.</p><span class="btn primary">Open requests</span></a>
            <a class="card mechanic-link-card" href="{{ route('mechanic.team') }}"><h2>Mechanic team</h2><p class="muted">Add mechanics and manage roster availability.</p><span class="btn primary">Manage team</span></a>
            <a class="card mechanic-link-card" href="{{ route('mechanic.shop-profile') }}"><h2>Shop profile</h2><p class="muted">Edit shop details and move the location pin.</p><span class="btn primary">Edit profile</span></a>
        </div>
    </section>
</div>
@endsection
