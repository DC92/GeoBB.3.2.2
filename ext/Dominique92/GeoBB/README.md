Dominique92.GeoBB
=================
- GeoBB est une extension cartographique pour forums PhpBB 3.1 associant des points, lignes ou surfaces sur une carte à un sujet dans un forum.
- Chaque forum regroupe les éléments de même catégorie (même icône sur la carte).
- Chaque sujet ou commentaire dans le forum peut être représenté par une icône, des lignes ou des surfaces sur la carte.

DEMO
====
[Voir une DEMO](http://geobb.cavailhez.fr)

INSTALLATION
============
* Pré-requis:
	- Hébergement PHP 5.3.3 min & MySQL 5.0 min (pour les fonctions géographiques)

* Installer un forum PhpBB 3.1:
	- Télécharger le [pack complet 3.1.x](http://www.phpbb-fr.com/telechargements/)
	- Déziper et transférer sur le serveur.
	Pour en savoir plus: [doc install PhpBB](https://www.phpbb.com/community/docs/INSTALL.html)
	- Créer une base vide sur MySQL ou utiliser une base existante.
	- Aller à la racine du forum depuis un explorateur, suivre les instructions.
	- Pour en savoir plus: [Documentation sur le forum](https://www.phpbb.com/support/docs/en/3.1/ug/)

* Installer GeoBB:
	- Télécharger cette extension (Bouton "Download ZIP" ci dessus)
	- Déziper et transférer le répertoire Dominique92 dans le répertoire /ext du forum.
	- Aller dans l'administration du forum (Lien en bas de page du forum) => PERSONALISER => Gérer les extensions => GeoBB => Activer
	- FORUMS => Gérer les forums => Créer un nouveau forum
	- Copier les permissions depuis: => Votre premier forum
	- Nom du forum: Le nom du type des points qui seront dans ce forum.
	- Image du forum: L'URL de icone qui représentera les points de ce forum (facultatif).
	Ces icones (fichiers .png 16x16) sont à tansférer dans un répertoire quelconque du serveur.
		- Exemple: ```ext/Dominique92/GeoBB/types_points/site.png```
	- Description: Insérer l'une les chaines de caractères suivantes:
		- ```<map first="point" />``` si vous voulez associer une position à chaque sujet du forum (en fait au premier commentaire de chaque sujet).
		- ```<map all="point" />``` si vous voulez associer une position à chaque commentaire de chaque sujet.
		- ```<map first="line" />``` si vous voulez associer des lignes au premier commentaire de chaque sujet.
		- ```<map all="line" />``` si vous voulez associer des lignes à chaque commentaire de chaque sujet.
		- ```<map first="surface" />``` si vous voulez associer des surfaces au premier commentaire de chaque sujet.
		- ```<map all="surface" />``` si vous voulez associer des surfaces à chaque commentaire de chaque sujet.
	- Envoyer

* Créer un point:
	- Aller sur le site => Dans le nouveau forum => Nouveau sujet
	- Entrer le nom, un commentaire, faire glisser le curseur jaune sur la carte pour définir la position.
	- Envoyer

* Customisation:
(facultatif, pour développeur)
	- Style:
		- [Les bases des styles](https://www.phpbb.com/styles/installing/)
		- [Styles de PhpBB 3.1 téléchargeables](https://www.phpbb.com/customise/db/styles/board_styles-12/3.1?sk=r&sd=d)
		- [Edition et création de styles](https://www.phpbb.com/styles/create/)
	- Fonctionnalités:
		- [Les extensions](https://www.phpbb.com/extensions/)
		- [Extensions de PhpBB 3.1 téléchargeables](https://www.phpbb.com/customise/db/extensions-36/3.1?sk=r&sd=d)
		- [Développer une extension](https://www.phpbb.com/extensions/writing/)
	- Affichage des cartes:
		- L'affichage des cartes est réalisé par une librairie basée sur openlayers, un certain nombre de plugins et d'optimisations dont les sources sont disponibles [ICI](https://github.com/Dominique92/MyOl)
