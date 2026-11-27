const ringRelation = (ring, longitude, latitude) => {
    let inside = false;

    for (let index = 0, previous = ring.length - 1; index < ring.length; previous = index++) {
        const [currentLongitude, currentLatitude] = ring[index];
        const [previousLongitude, previousLatitude] = ring[previous];
        const crossProduct = (longitude - currentLongitude) * (previousLatitude - currentLatitude)
            - (latitude - currentLatitude) * (previousLongitude - currentLongitude);

        if (Math.abs(crossProduct) < 1e-10
            && longitude >= Math.min(currentLongitude, previousLongitude) - 1e-10
            && longitude <= Math.max(currentLongitude, previousLongitude) + 1e-10
            && latitude >= Math.min(currentLatitude, previousLatitude) - 1e-10
            && latitude <= Math.max(currentLatitude, previousLatitude) + 1e-10) {
            return 2;
        }

        if ((currentLatitude > latitude) !== (previousLatitude > latitude)
            && longitude < (previousLongitude - currentLongitude) * (latitude - currentLatitude)
                / (previousLatitude - currentLatitude) + currentLongitude) {
            inside = !inside;
        }
    }

    return inside ? 1 : 0;
};

const isInsideGeometry = (geometry, longitude, latitude) => {
    const polygons = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;

    return polygons.some(rings => {
        const outerRelation = ringRelation(rings[0], longitude, latitude);
        if (outerRelation === 0) {
            return false;
        }
        if (outerRelation === 2) {
            return true;
        }

        return !rings.slice(1).some(hole => ringRelation(hole, longitude, latitude) === 1);
    });
};

window.FixMotoGeography = Object.freeze({
    isInside(geometry, latitude, longitude) {
        return isInsideGeometry(geometry, longitude, latitude);
    },
});

document.addEventListener('DOMContentLoaded', () => {
    const trackers = [...document.querySelectorAll('[data-progress-url][data-progress-status]')];
    if (!trackers.length) return;

    let reloading = false;
    const refreshProgress = async () => {
        if (document.hidden || reloading) return;
        for (const tracker of trackers) {
            try {
                const response = await fetch(tracker.dataset.progressUrl, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) continue;
                const progress = await response.json();
                if (progress.service_status !== tracker.dataset.progressStatus
                    || progress.dispatch_status !== tracker.dataset.progressDispatch) {
                    reloading = true;
                    window.location.reload();
                    return;
                }
            } catch (error) {
                console.warn('Unable to sync service progress.', error);
            }
        }
    };

    refreshProgress();
    window.setInterval(refreshProgress, 12000);
});
