@extends('admin.layouts.app')
@section('title', 'Audit logs')
@section('page_title', 'Audit logs')
@section('content')
<div class="admin-page">
    <div class="admin-page-header">
        <div>
            <p class="live-kicker">ADMIN &amp; USER ACTIVITY</p>
            <h1>Audit logs</h1>
            <p>Review recorded account and administrative activity.</p>
        </div>
    </div>

    <section class="distribution-card" style="height: auto">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Admin</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($auditLogs as $auditLog)
                        <tr>
                            <td>
                                <time datetime="{{ $auditLog->created_at->toIso8601String() }}">
                                    {{ $auditLog->created_at->format('Y-m-d H:i:s') }}
                                </time>
                            </td>
                            <td>{{ $auditLog->admin?->name ?? '—' }}</td>
                            <td>{{ $auditLog->user?->name ?? '—' }}</td>
                            <td>{{ ucfirst($auditLog->role) }}</td>
                            <td>{{ ucfirst(str_replace(['.', '_'], ' ', $auditLog->action)) }}</td>
                            <td>
                                @forelse($auditLog->details ?? [] as $key => $value)
                                    <div>
                                        <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong>
                                        {{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}
                                    </div>
                                @empty
                                    <span class="muted">—</span>
                                @endforelse
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="muted">No audit activity has been recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top: 14px">{{ $auditLogs->links() }}</div>
    </section>
</div>
@endsection
