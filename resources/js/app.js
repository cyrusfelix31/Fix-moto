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
