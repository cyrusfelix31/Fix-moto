@extends('layouts.app')
@section('title', 'Mechanic Team')
@section('page_title', 'Mechanic team')
@section('content')
<div class="page mechanic-page">
    @if($errors->any())
        <div class="alert error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <section class="card">
        <div class="card-header">
            <div><h2>Shop roster</h2><p class="muted">{{ $profile->active_members_count }} active of {{ $teamMembers->count() }} registered mechanics</p></div>
        </div>
        <form method="POST" action="{{ route('mechanic.team.store') }}" class="form-grid" style="max-width:640px">
            @csrf
            <label>Mechanic name<input name="name" required maxlength="255"></label>
            <button class="btn primary" @disabled($teamMembers->count() >= 50)>Add mechanic</button>
        </form>
        <div class="table-wrap" style="margin-top:14px">
            <table>
                <thead><tr><th>Name</th><th>Status</th><th>Active requests</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($teamMembers as $member)
                        <tr>
                            <td>{{ $member->name }}</td>
                            <td>{{ $member->is_active ? 'Active' : 'Inactive' }}</td>
                            <td>{{ $member->active_request_count }}</td>
                            <td>
                                <form method="POST" action="{{ route('mechanic.team.toggle', $member) }}">
                                    @csrf
                                    <button class="btn outline" @disabled($member->is_active && $member->active_request_count > 0)>{{ $member->is_active ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">No mechanics added yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
