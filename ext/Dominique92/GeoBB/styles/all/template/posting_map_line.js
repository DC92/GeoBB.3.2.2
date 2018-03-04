control.geocoder.addTo(map);
control.fileload.addTo(map);

L.Polyline.prototype.options.weight = L.Polygon.prototype.options.weight = 3;
L.Polyline.prototype.options.opacity = L.Polygon.prototype.options.opacity = 1;

var editor = new L.Control.Draw.Plus({
	draw: {
		polyline: true
	},
	edit: {
		remove: true
	},
	editType: 'Polyline',
	entry: 'edit-json',
	changed: 'edit-change'
}).addTo(map);

gis.addTo(editor.snapLayers);
control.fileload.loader.on('data:loaded', function(e) {
	e.layer.addTo(editor);
}, control.fileload);
