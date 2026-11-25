@extends('admin.layouts.app')
@section('title','System Monitoring')
@section('page_title','System monitoring')
@section('content')
<div class="admin-page">
    <div class="admin-page-header"><div><p class="live-kicker">SYSTEM STATUS</p><h1>System monitoring</h1><p>Dispatch, service activity, uptime, and downtime.</p></div></div>

    @php
        $formatDuration = function (int $seconds): string {
            $days = intdiv($seconds, 86400);
            $hours = intdiv($seconds % 86400, 3600);
            $minutes = intdiv($seconds % 3600, 60);
            if ($days > 0) return $days.'d '.$hours.'h';
            if ($hours > 0) return $hours.'h '.$minutes.'m';
            return max(1, $minutes).'m';
        };
    @endphp

    <section class="admin-stats" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <article class="admin-stat"><div class="stat-copy"><span>Current system status</span><strong><span class="badge {{ $currentStatus === 'operational' ? '' : 'red' }}">{{ ucfirst($currentStatus) }}</span></strong><small>{{ $currentStatus === 'operational' ? 'All services available' : 'System unavailable' }}</small></div><div class="admin-stat-icon {{ $currentStatus === 'operational' ? 'green' : 'neutral' }}"><i data-lucide="{{ $currentStatus === 'operational' ? 'circle-check' : 'triangle-alert' }}"></i></div></article>
        <article class="admin-stat"><div class="stat-copy"><span>Recorded uptime</span><strong>{{ $formatDuration($uptimeSeconds) }}</strong><small>Operational time recorded</small></div><div class="admin-stat-icon green"><i data-lucide="activity"></i></div></article>
        <article class="admin-stat"><div class="stat-copy"><span>Recorded downtime</span><strong>{{ $formatDuration($downtimeSeconds) }}</strong><small>Downtime incidents recorded</small></div><div class="admin-stat-icon amber"><i data-lucide="clock-alert"></i></div></article>
    </section>

    <section class="distribution-card" style="height:auto;margin-top:17px">
        <div class="distribution-header"><div><p>STATUS CONTROL</p><h2>Update system status</h2></div></div>
        <form method="POST" action="{{ route('admin.system-status.update') }}" class="form-grid" style="margin-top:14px;grid-template-columns:180px 1fr auto">@csrf
            <label>Status<select name="status"><option value="operational">Operational</option><option value="downtime">Downtime</option></select></label>
            <label>Reason<input name="reason" placeholder="Optional status note"></label>
            <button class="btn primary" style="align-self:end">Update status</button>
        </form>
    </section>

    <section class="distribution-card" style="height:auto;margin-top:17px">
        <div class="distribution-header"><div><p>STATUS HISTORY</p><h2>Recent incidents</h2></div></div>
        <div class="table-wrap" style="margin-top:12px"><table><thead><tr><th>Status</th><th>Started</th><th>Ended</th><th>Duration</th><th>Reason</th></tr></thead><tbody>
            @forelse($statusHistory as $incident)<tr><td><span class="badge {{ $incident->status === 'operational' ? '' : 'red' }}">{{ ucfirst($incident->status) }}</span></td><td>{{ $incident->started_at->format('Y-m-d H:i') }}</td><td>{{ $incident->ended_at?->format('Y-m-d H:i') ?? 'Current' }}</td><td>{{ $formatDuration($incident->duration_seconds ?: ($incident->ended_at ? $incident->started_at->diffInSeconds($incident->ended_at) : $incident->started_at->diffInSeconds(now()))) }}</td><td>{{ $incident->reason ?: '—' }}</td></tr>@empty<tr><td colspan="5" class="muted">No status records.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section class="distribution-card" style="height:auto;margin-top:17px">
        <div class="distribution-header"><div><p>LIVE ACTIVITY</p><h2>Service requests</h2></div></div>
        <div class="table-wrap" style="margin-top:12px"><table><thead><tr><th>Reference</th><th>Rider</th><th>Mechanic shop</th><th>Dispatch</th><th>Service</th><th>Updated</th></tr></thead><tbody>@foreach($requests as $request)<tr><td>{{ $request->reference }}</td><td>{{ $request->rider?->name }}</td><td>{{ $request->mechanic?->mechanicProfile?->shop_name ?? $request->mechanic?->name ?? 'Unassigned shop' }}</td><td><span class="badge">{{ str_replace('_',' ',ucfirst($request->dispatch_status)) }}</span></td><td>{{ $request->serviceStatusLabel() }}</td><td>{{ $request->updated_at?->diffForHumans() }}</td></tr>@endforeach</tbody></table></div>
    </section>
</div>
@endsection
