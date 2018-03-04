control.geocoder.addTo(map);
control.fileload.addTo(map);
control.fileget.addTo(map);
control.print.addTo(map);

function hover(post_id, e) {
	var found = false;
	gis.eachLayerRecursive(function(l) {
		if (l.options.post_id == post_id) {
			if (!l.options.baseColor) // Mémorise 1 fois
				l.options.baseColor = l.options.color;

			l.setStyle({
				color: e == 'mouseout' ? l.options.baseColor : 'red'
			});
			found = true;
		}
	});

	var pel = document.getElementById('p' + post_id);
	if (pel && found)
		pel.className = pel.className
			.replace(/\shovered/g, '') +
			(e == 'mouseout' ? '' : ' hovered');
}

gis.on('mouseover mouseout', function(e) {
	hover(e.layer.options.post_id, e.type);
});

var pel;
<!-- BEGIN postrow -->
	pel = document.getElementById('p{postrow.POST_ID}');
	pel.onmouseover = pel.onmouseout =
		function(e) {
			hover(
				{postrow.POST_ID}, 
				e.type
			);
		};
<!-- END postrow -->