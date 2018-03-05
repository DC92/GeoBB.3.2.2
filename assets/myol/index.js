// LA CARTE ------------------------------------------------
var map = new ol.Map({
	target: 'map',
	//	loadTilesWhileInteracting: true,
	controls: Object.values(controls),
	view: new ol.View({
		//center: ol.proj.fromLonLat([-3.5, 48.25]), // France
		center: ol.proj.fromLonLat([7, 47]), // Suisse
		//center: ol.proj.fromLonLat([9.2, 45.5]), // Milan
		//center: ol.proj.fromLonLat([7.07, 45.19]), // Rochemelon
		//center: ol.proj.fromLonLat([-.1, 51.5]), // Londres
		zoom: 8
	})
});

map.addControl(controlLayers(baseLayers, overLayers));

//TEST
var viseur = marqueur('images/viseur.png', [6.15, 46.2], 'edit-lonlat', [
	'Lon <input type="text" onchange="viseur.edit(this,0,4326)" size="12" maxlength="12" value="{0}"/>' +
	'<br/>Lat <input type="text" onchange="viseur.edit(this,1,4326)" size="12" maxlength="12" value="{1}"/>',
	'<br/>X <input type="text" onchange="viseur.edit(this,0,21781)" size="12" maxlength="12" value="{2}"/>' +
	'<br/>Y <input type="text" onchange="viseur.edit(this,1,21781)" size="12" maxlength="12" value="{3}"/>'
], 'edit');
map.addLayer(viseur);

map.addLayer(marqueur('images/cadre.png', [-.575, 44.845], 'lonlat', ['Lon {0}, Lat {1}', '<br/>X {2}, Y {3} (CH1903)']));

if(overLayers.Massifs)
	map.addLayer(lineEditor('geojson', [overLayers.Massifs]));
