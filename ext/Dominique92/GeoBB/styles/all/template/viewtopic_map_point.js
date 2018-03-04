control.geocoder.addTo(map);
control.fileload.addTo(map);
control.fileget.addTo(map);
control.print.addTo(map);

var cadre = new L.Marker([0,0], {
		icon: L.icon({
			iconUrl: '{EXT_DIR}styles/all/theme/images/cadre.png',
			className: 'leaflet-grab',
			iconAnchor: [15, 21]
		})
	})
	.coordinates('position'); // Affiche les coordonnées.

if (cadre._latlng)
	cadre.addTo(map);
