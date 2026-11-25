@extends('layouts.app')
@section('title', 'My Vehicles')
@section('page_title', 'My vehicles')
@section('content')
<div class="page">
    <nav class="rider-breadcrumb" aria-label="Breadcrumb">
        <ol><li><a href="{{ route('rider.dashboard') }}">Dashboard</a></li><li aria-current="page">Vehicles</li></ol>
    </nav>

    <section class="card rider-vehicle-form">
        <div class="card-header"><div><h2>Add a vehicle</h2><p class="muted">Register a two-wheeled or four-wheeled vehicle.</p></div></div>
        @if($errors->any())
            <div class="alert error rider-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif
        <form method="POST" action="{{ route('rider.vehicles.store') }}" class="form-grid">
            @csrf
            <label for="vehicle-category">Vehicle category
                <select id="vehicle-category" name="vehicle_category" required>
                    @foreach($vehicleCategories as $value => $label)
                        <option value="{{ $value }}" @selected(old('vehicle_category') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label for="vehicle-type">Vehicle type<select id="vehicle-type" name="vehicle_type" required></select></label>
            <label>Make / brand<input name="brand" value="{{ old('brand') }}" required maxlength="100"></label>
            <label>Model<input name="model" value="{{ old('model') }}" required maxlength="100"></label>
            <label>Year<input type="number" name="year" value="{{ old('year', date('Y')) }}" required></label>
            <label>Plate number<input name="plate_number" value="{{ old('plate_number') }}" maxlength="30"></label>
            <label class="rider-form-wide">Notes<input name="notes" value="{{ old('notes') }}" maxlength="1000"></label>
            <button class="btn primary rider-form-submit">Add vehicle</button>
        </form>
    </section>

    <section class="card rider-vehicle-list">
        <div class="card-header"><div><h2>Registered vehicles</h2><p class="muted">{{ $vehicles->count() }} vehicle(s)</p></div></div>
        @forelse($vehicles as $vehicle)
            <article class="rider-vehicle-item">
                <span class="stat-icon"><i data-lucide="{{ $vehicle->vehicle_category === 'four_wheeled' ? 'car-front' : 'bike' }}"></i></span>
                <div><strong>{{ $vehicle->brand }} {{ $vehicle->model }}</strong><p>{{ $vehicleCategories[$vehicle->vehicle_category] ?? 'Vehicle' }} · {{ $vehicle->vehicle_type }} · {{ $vehicle->year }}</p></div>
                <span class="rider-vehicle-plate">{{ $vehicle->plate_number ?: 'No plate number' }}</span>
            </article>
        @empty
            <p class="rider-empty-state">You haven’t added a vehicle yet.</p>
        @endforelse
    </section>
</div>
<script>
const vehicleCategory = document.getElementById('vehicle-category');
const vehicleType = document.getElementById('vehicle-type');
const vehicleTypes = {
    two_wheeled: ['Scooter', 'Underbone', 'Sport', 'Dual sport', 'Classic'],
    four_wheeled: ['Car', 'SUV', 'Van', 'Pickup truck'],
};
const savedVehicleType = @json(old('vehicle_type'));

function updateVehicleTypes() {
    const types = vehicleTypes[vehicleCategory.value] ?? [];
    vehicleType.replaceChildren(...types.map(type => new Option(type, type)));
    if (types.includes(savedVehicleType)) {
        vehicleType.value = savedVehicleType;
    }
}

vehicleCategory.addEventListener('change', updateVehicleTypes);
updateVehicleTypes();
</script>
@endsection
