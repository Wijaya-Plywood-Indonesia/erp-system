<div style="height: 350px; border-radius: 10px; overflow: hidden" wire:ignore>
    <input
        id="pac-input"
        class="form-control mb-2"
        type="text"
        placeholder="Cari lokasi..."
    />
    <div id="map" style="height: 100%"></div>
</div>

<script
    src="https://maps.googleapis.com/maps/api/js?key={{
        env('GOOGLE_MAPS_API_KEY')
    }}&libraries=places"
    async
    defer
></script>

<script>
    document.addEventListener("livewire:load", function () {
        const latInput = document.querySelector(
            '[wire\\:model="data.latitude"]'
        );
        const lngInput = document.querySelector(
            '[wire\\:model="data.longitude"]'
        );

        let mapCenter = {
            lat: parseFloat(latInput?.value) || -6.2,
            lng: parseFloat(lngInput?.value) || 106.816666,
        };

        const map = new google.maps.Map(document.getElementById("map"), {
            center: mapCenter,
            zoom: 10,
        });

        const marker = new google.maps.Marker({
            position: mapCenter,
            map: map,
            draggable: true,
        });

        const input = document.getElementById("pac-input");
        const searchBox = new google.maps.places.SearchBox(input);

        map.addListener("bounds_changed", () => {
            searchBox.setBounds(map.getBounds());
        });

        searchBox.addListener("places_changed", () => {
            const places = searchBox.getPlaces();
            if (places.length == 0) return;
            const place = places[0];
            if (!place.geometry) return;

            map.panTo(place.geometry.location);
            marker.setPosition(place.geometry.location);

            latInput.value = place.geometry.location.lat();
            lngInput.value = place.geometry.location.lng();
            latInput.dispatchEvent(new Event("input"));
            lngInput.dispatchEvent(new Event("input"));
        });

        marker.addListener("dragend", (event) => {
            latInput.value = event.latLng.lat();
            lngInput.value = event.latLng.lng();
            latInput.dispatchEvent(new Event("input"));
            lngInput.dispatchEvent(new Event("input"));
        });
    });
</script>
