@extends('layouts.app')
@section('title', 'Service History')
@section('page_title', 'Service history')
@section('content')
<div class="page">
    <nav class="rider-breadcrumb" aria-label="Breadcrumb">
        <ol><li><a href="{{ route('rider.dashboard') }}">Dashboard</a></li><li aria-current="page">History</li></ol>
    </nav>

    <section class="card">
        <div class="card-header"><div><h2>All service requests</h2><p class="muted">Track current and previous requests.</p></div></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Reference</th><th>Vehicle</th><th>Services</th><th>Service status</th><th>Dispatch</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse($requests as $serviceRequest)
                        <tr>
                            <td><a href="{{ route('rider.requests.show', $serviceRequest) }}"><strong>{{ $serviceRequest->reference }}</strong></a></td>
                            <td>{{ $serviceRequest->vehicle->brand }} {{ $serviceRequest->vehicle->model }}</td>
                            <td>{{ implode(', ', $serviceRequest->services ?? [$serviceRequest->issue_category]) }}</td>
                            <td><span class="badge">{{ $serviceRequest->serviceStatusLabel() }}</span></td>
                            <td>{{ str_replace('_', ' ', ucfirst($serviceRequest->dispatch_status)) }}</td>
                            <td>{{ $serviceRequest->created_at->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No service request history yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
            <nav class="history-pagination" aria-label="History pagination">
                <span>Page {{ $requests->currentPage() }} of {{ $requests->lastPage() }}</span>
                <div>
                    @if($requests->onFirstPage())
                        <span class="pagination-disabled">Previous</span>
                    @else
                        <a href="{{ $requests->previousPageUrl() }}">Previous</a>
                    @endif
                    @if($requests->hasMorePages())
                        <a href="{{ $requests->nextPageUrl() }}">Next</a>
                    @else
                        <span class="pagination-disabled">Next</span>
                    @endif
                </div>
            </nav>
        @endif
    </section>
</div>
@endsection
