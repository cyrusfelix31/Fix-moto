@extends('admin.layouts.app')
@section('title','Users and Shops')
@section('page_title','Users & shops')
@section('content')
<div class="admin-page">
    <div class="admin-page-header">
        <div>
            <p class="live-kicker">ACCOUNT DIRECTORY</p>
            <h1>Users & mechanic shops</h1>
            <p>Search and manage rider and shop accounts.</p>
        </div>
    </div>
    <section class="distribution-card" style="height:auto">
        <form method="GET" class="form-grid admin-search-grid" role="search">
            <label class="admin-search-field">
                <span class="muted">Search accounts</span>
                <input type="search" name="search" value="{{ $search }}" placeholder="Search name or email" aria-label="Search accounts by name or email">
            </label>
            <button type="submit" class="export-button admin-search-button">
                <i data-lucide="search"></i>
                <span>Search</span>
            </button>
        </form>
        <div class="table-wrap" style="margin-top:16px">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Shop mechanics</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->role === 'mechanic' ? 'Mechanic shop' : ucfirst($user->role) }}</td>
                            <td>{{ $user->role === 'mechanic' ? $user->mechanicProfile?->members->where('is_active', true)->count() ?? 0 : '—' }}</td>
                            <td>
                                <span class="badge {{ $user->is_suspended ? 'red' : 'active-status' }}">
                                    {{ $user->is_suspended ? 'Inactive' : 'Active' }}
                                </span>
                            </td>
                            <td>
                                @if($user->role !== 'admin')
                                    <form method="POST" action="{{ route('admin.users.suspend', $user) }}">
                                        @csrf
                                        <button class="btn {{ $user->is_suspended ? 'outline' : 'suspend-action' }}">
                                            {{ $user->is_suspended ? 'Activate' : 'Suspend' }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No accounts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:14px">{{ $users->links() }}</div>
    </section>
</div>
@endsection
