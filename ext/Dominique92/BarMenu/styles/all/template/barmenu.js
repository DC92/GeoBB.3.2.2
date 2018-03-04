menu(); // On exécute une fois dès qu'on affiche
window.addEventListener('load', menu); // Pour le cas ou la barre de scroll vient changer la donne
window.onresize = menu; // Si on change la largeur de la fenetre

function menu(flip) {
	var menu = document.getElementById('menu');
	if (typeof flip == 'boolean') // On plie / déplie le menu
		menu.className =
			menu.className == 'horizontal'
				? 'vertical'
				: 'horizontal';

	// Met en colonne les <li> qui ne rentrent pas dans la ligne du menu
	var lis = menu.getElementsByTagName('li');
	for (i in lis)
		if (typeof lis[i] == 'object' && lis[i].parentNode == menu) { // Les <li> du menu
			lis[i].className = ''; // Doit être réinitialisé avant de mesurer la position
			lis[i].className =
				i > 1 && // Sauf le fil d'ariane et le bouton
				lis[i].offsetTop > lis[0].offsetTop // Les <li> qui dépassent
					? 'colonne' // Sont mis en colonnes
					: 'ligne';
		}
	document.getElementById('bouton').className = // Le bouton
		document.getElementsByClassName('colonne').length // Si des <li> ont été mis en colonnes
			? '' // Rend le bouton visible
			: 'cache';
}

function deplie(el) {
	if (el.className != 'ligne')
		el.className =
			el.className == 'deplie'
				? 'colonne'
				: 'deplie';
}
