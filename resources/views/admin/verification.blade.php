@extends('admin.layouts.app')
@section('title','Mechanic Shop Verification')
@section('page_title','Verification')
@section('content')
<div class="admin-page">
    <div class="admin-page-header"><div><p class="live-kicker">MECHANIC SHOP APPLICATIONS</p><h1>Shop verification</h1><p>Approve or reject mechanic shop applications.</p></div></div>
    <section class="distribution-card" style="height:auto">
        <h2>Pending applications</h2>
        <div style="margin-top:14px">
            @forelse($pending as $shop)
                <div class="attention-item" style="grid-template-columns:38px minmax(0,1fr)">
                    <span class="attention-icon green"><i data-lucide="badge-check"></i></span>
                    <div style="min-width:0">
                        <strong>{{ $shop->mechanicProfile->shop_name }}</strong>
                        <p>{{ $shop->name }}</p>
                        <small>{{ $shop->mechanicProfile->service_address }} · {{ $shop->mechanicProfile->members->where('is_active', true)->count() }} active mechanic(s)</small>
                        <h3 style="margin:12px 0 0;font-size:13px">Verification Checklist</h3>
                        <div class="table-wrap">
                            <table aria-label="Verification checklist for {{ $shop->mechanicProfile->shop_name }}">
                                <thead><tr><th>Verification Item</th><th>Status</th></tr></thead>
                                <tbody>
                                    <tr><td>Valid Business Permit</td><td><span class="badge">Verified</span></td></tr>
                                    <tr><td>Owner's Valid ID</td><td><span class="badge">Verified</span></td></tr>
                                    <tr><td>Physical Shop Location</td><td><span class="badge">Verified</span></td></tr>
                                    <tr><td>Contact Number</td><td><span class="badge">Verified</span></td></tr>
                                    <tr><td>Shop Operations</td><td><span class="badge">Verified</span></td></tr>
                                    <tr><td>Mechanic Credentials</td><td><span class="badge">Verified</span></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.mechanics.verify',$shop) }}" class="form-grid verification-form" style="grid-column:2;grid-template-columns:100px minmax(0,1fr) auto">
                        @csrf
                        <select name="decision" class="verification-decision"><option value="approve">Approve</option><option value="reject">Reject</option></select>
                        <input name="reason" class="verification-reason" placeholder="Reason if rejecting">
                        <button class="btn primary">Submit</button>
                    </form>
                </div>
            @empty
                <div class="admin-empty compact">No pending applications.</div>
            @endforelse
        </div>
    </section>
    <section class="distribution-card" style="height:auto;margin-top:17px">
        <div class="distribution-header"><div><p>REVIEW RECORDS</p><h2>Verification history</h2></div></div>
        <div class="table-wrap" style="margin-top:12px"><table><thead><tr><th>Mechanic shop</th><th>Decision</th><th>Reason</th><th>Reviewed by</th><th>Date</th></tr></thead><tbody>
            @forelse($history as $record)
                <tr><td>{{ $record->mechanic?->mechanicProfile?->shop_name ?? $record->mechanic?->name ?? 'Mechanic shop removed' }}</td><td><span class="badge {{ $record->status === 'rejected' ? 'red' : '' }}">{{ ucfirst($record->status) }}</span></td><td>{{ $record->rejection_reason ?: '—' }}</td><td>{{ $record->reviewedBy?->name ?: '—' }}</td><td>{{ $record->reviewed_at?->format('Y-m-d H:i') ?: '—' }}</td></tr>
            @empty
                <tr><td colspan="5" class="muted">No verification decisions recorded.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
</div>
<script>
document.querySelectorAll('.verification-form').forEach((form) => {
    const decision = form.querySelector('.verification-decision');
    const reason = form.querySelector('.verification-reason');
    const syncReasonRequirement = () => {
        reason.required = decision.value === 'reject';
    };
    decision.addEventListener('change', syncReasonRequirement);
    syncReasonRequirement();
});
</script>
@endsection
