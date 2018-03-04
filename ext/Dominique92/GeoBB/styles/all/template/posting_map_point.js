var curseur = new L.Marker(
	map.getCenter(), // Valeur par défaut
	{
		draggable: true,
		riseOnHover: true,
		icon: L.icon({
			iconUrl: '{EXT_DIR}styles/all/theme/images/curseur.png',
			className: 'leaflet-move',
			iconAnchor: [15, 15]
		})
	}
);
curseur.coordinates('edit'); // Affiche / saisi les coordonnées
curseur.addTo(map);
map.setView(curseur._latlng, 15); // Centre la carte sur ce point

control.geocoder.addTo(map);

function gotogps () {
	control.gps.deactivate()
	control.gps.on('gps:located', function(e) {
		e.target._map.setView(e.latlng, 15, {
			reset: true
		});
		curseur.setLatLng(e.latlng);
	});
	control.gps.activate()
}

// Snap sur les autres objects vectoriels
curseur.snapediting = new L.Handler.MarkerSnap(map, curseur);
curseur.snapediting.addGuideLayer(gis);
curseur.snapediting.enable();
