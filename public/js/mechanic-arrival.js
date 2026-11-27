document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-mechanic-arrival]').forEach(form => {
        const targetLatitude = Number(form.dataset.targetLatitude);
        const targetLongitude = Number(form.dataset.targetLongitude);
        const radius = Number(form.dataset.radius || 50);
        const maxAccuracy = Number(form.dataset.maxAccuracy || 35);
        const maxAgeMs = Number(form.dataset.maxAge || 120) * 1000;
        const message = form.querySelector('[data-gps-message]');
        const distanceLabel = form.querySelector('[data-gps-distance]');
        const accuracyLabel = form.querySelector('[data-gps-accuracy-label]');
        const submitButton = form.querySelector('[data-arrival-submit]');
        const hiddenLatitude = form.querySelector('[data-gps-latitude]');
        const hiddenLongitude = form.querySelector('[data-gps-longitude]');
        const hiddenAccuracy = form.querySelector('[data-gps-accuracy]');
        const hiddenCapturedAt = form.querySelector('[data-gps-captured-at]');
        let watchId = null;
        let submitting = false;

        const distanceInMeters = (lat1, lon1, lat2, lon2) => {
            const radians = degrees => degrees * Math.PI / 180;
            const dLat = radians(lat2 - lat1);
            const dLon = radians(lon2 - lon1);
            const a = Math.sin(dLat / 2) ** 2
                + Math.cos(radians(lat1)) * Math.cos(radians(lat2)) * Math.sin(dLon / 2) ** 2;
            return 6371000 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        };

        const renderPosition = position => {
            const age = Date.now() - position.timestamp;
            const accuracy = Number(position.coords.accuracy);
            const distance = distanceInMeters(
                Number(position.coords.latitude),
                Number(position.coords.longitude),
                targetLatitude,
                targetLongitude,
            );
            const fresh = age >= -10000 && age <= maxAgeMs;
            const accurate = Number.isFinite(accuracy) && accuracy <= maxAccuracy;
            const nearby = Number.isFinite(distance) && distance <= radius;

            distanceLabel.textContent = `${Math.round(distance)} m`;
            accuracyLabel.textContent = `${Math.round(accuracy)} m${fresh ? '' : ' · stale'}`;
            submitButton.disabled = !(fresh && accurate && nearby) || submitting;

            if (!fresh) {
                message.textContent = 'GPS reading is too old. Request a fresh location.';
            } else if (!accurate) {
                message.textContent = `GPS accuracy is ±${Math.round(accuracy)} m. Move to an open area; the limit is ${Math.round(maxAccuracy)} m.`;
            } else if (!nearby) {
                message.textContent = `You are ${Math.round(distance)} m away. Move within ${Math.round(radius)} m of the rider.`;
            } else {
                message.textContent = 'You are close enough. Confirm arrival to take a fresh GPS reading.';
            }

            return { age, accuracy, distance, fresh, accurate, nearby };
        };

        const gpsError = (error, onFailure = null) => {
            if (onFailure) {
                onFailure(error);
                return;
            }

            submitButton.disabled = true;
            if (error?.code === 1) {
                message.textContent = 'Location permission was denied. Allow location access in your browser settings, then retry.';
            } else if (error?.code === 2) {
                message.textContent = 'Your location is unavailable. Turn on location services and try again.';
            } else if (error?.code === 3) {
                message.textContent = 'GPS took too long. Move to an open area and try again.';
            } else {
                message.textContent = 'This browser cannot provide GPS. Use a device with location enabled.';
            }
        };

        const requestFreshPosition = (onSuccess, onFailure = null) => {
            if (!navigator.geolocation) {
                gpsError(null, onFailure);
                return;
            }

            navigator.geolocation.getCurrentPosition(
                onSuccess,
                error => gpsError(error, onFailure),
                { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 },
            );
        };

        if (!navigator.geolocation) {
            gpsError();
        } else {
            watchId = navigator.geolocation.watchPosition(renderPosition, gpsError, {
                enableHighAccuracy: true,
                maximumAge: 0,
                timeout: 15000,
            });
        }

        form.querySelector('[data-gps-retry]')?.addEventListener('click', () => {
            message.textContent = 'Checking your current location…';
            requestFreshPosition(position => renderPosition(position));
        });

        form.addEventListener('submit', event => {
            event.preventDefault();
            if (submitting || submitButton.disabled) return;

            submitting = true;
            submitButton.disabled = true;
            submitButton.textContent = 'Checking fresh GPS…';

            requestFreshPosition(position => {
                const result = renderPosition(position);
                if (!result.fresh || !result.accurate || !result.nearby) {
                    submitting = false;
                    submitButton.textContent = 'Mark as Arrived';
                    submitButton.disabled = true;
                    return;
                }

                hiddenLatitude.value = String(position.coords.latitude);
                hiddenLongitude.value = String(position.coords.longitude);
                hiddenAccuracy.value = String(result.accuracy);
                hiddenCapturedAt.value = String(position.timestamp || Date.now());
                form.submit();
            }, error => {
                submitting = false;
                submitButton.textContent = 'Mark as Arrived';
                gpsError(error);
            });
        });

        form.addEventListener('reset', () => {
            if (watchId !== null) navigator.geolocation?.clearWatch(watchId);
        });
    });
});
