<?php
/**
 *
 * @package GeoBB
 * @copyright (c) 2016 Dominique Cavailhez
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace Dominique92\GeoBB\event;

if (!defined('IN_PHPBB'))
{
	exit;
}

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	public function __construct(
		\phpbb\db\driver\driver_interface $db,
		\phpbb\request\request_interface $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\extension\manager $extension_manager,
		$root_path
	) {
		$this->db = $db;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->extension_manager = $extension_manager;
		$this->root_path = $root_path;

		// Recherche du répertoire de l'extension
		$ns = explode ('\\', __NAMESPACE__);
		$this->ext_dir = 'ext/'.$ns[0].'/'.$ns[1].'/';

		// Inclue les fichiers langages de cette extension
		$this->user->add_lang_ext($ns[0].'/'.$ns[1], 'common');

		// On recherche les templates aussi dans l'extension
		$ext = $this->extension_manager->all_enabled();
		$ext[] = ''; // En dernier lieu, le style de base !
		foreach ($ext AS $k=>$v)
			$ext[$k] .= 'styles';
		$this->template->set_style($ext);
	}

	// Liste des hooks et des fonctions associées
	// On trouve le point d'appel en cherchant dans le logiciel de PhpBB 3.1: "event core.<XXX>"
	static public function getSubscribedEvents() {
		return [
			// Index
			'core.index_modify_page_title' => 'geobb_activate_map', //226

			// Viewtopic
			'core.viewtopic_get_post_data' => 'geobb_viewtopic_get_post_data', //1143
			'core.viewtopic_post_rowset_data' => 'viewtopic_post_rowset_data', //1240
			'core.viewtopic_modify_post_row' => 'geobb_viewtopic_modify_post_row', //1949
			'core.viewtopic_post_row_after' => 'viewtopic_post_row_after', //1949
			'geo.gis_modify_data' => 'gis_modify_data', //gis.php

			// Posting
			'core.submit_post_modify_sql_data' => 'submit_post_modify_sql_data', //21 -> functions_posting.php 1859
			'core.posting_modify_template_vars' => 'posting_modify_template_vars', //1834
			'core.posting_modify_submission_errors' => 'posting_modify_submission_errors', //1248
		];
	}

/* INTEGRER : TODO DCMM
BarMenu
	'core.page_footer' => 'page_footer',
News
	'core.display_forums_modify_row' => 'display_forums_modify_row',
	'core.index_modify_page_title' => 'index_modify_page_title',
ResizeImg
	'core.viewtopic_post_rowset_data' => 'viewtopic_post_rowset_data',
	'core.viewtopic_modify_post_data' => 'viewtopic_modify_post_data',
	'core.parse_attachments_modify_template_data' => 'parse_attachments_modify_template_data',
	'core.download_file_send_to_browser_before' => 'download_file_send_to_browser_before',
Reference
	'core.index_modify_page_title' => 'init_select',
	'core.viewtopic_modify_post_data' => 'viewtopic_modify_post_data',
	'core.viewtopic_assign_template_vars_before' => 'viewtopic_assign_template_vars_before',
	'core.modify_posting_auth' => 'modify_posting_auth',
	'core.submit_post_modify_sql_data' => 'submit_post_modify_sql_data',
	'geo.gis_after' => 'gis_after',
	'geo.gis_modify_sql' => 'gis_modify_sql',
	'geo.gis_modify_data' => 'gis_modify_data',
Organiser
			'core.viewtopic_before_f_read_check' => 'viewtopic_before_f_read_check',
			'core.viewtopic_post_rowset_data' => 'viewtopic_post_rowset_data',
			'core.viewtopic_modify_post_data' => 'viewtopic_modify_post_data',
			'core.viewtopic_modify_post_row' => 'viewtopic_modify_post_row',
			'core.modify_posting_auth' => 'modify_posting_auth',
*/

	function geobb_activate_map($vars, $forum_desc = '[all=accueil]', $first_post = true) {
		global $config_locale;

		preg_match ('/\[(first|all)=([a-z]+)\]/i', html_entity_decode ($forum_desc), $regle);
		switch (@$regle[1]) {
			case 'first': // Régle sur le premier post seulement
				if (!$first_post)
					break;

			case 'all': // Régle sur tous les posts
				$this->template->assign_vars([
//DCMM ?? utile ? DELETE					'EXT_DIR' => $this->ext_dir, //TODO DCMM: mettre un nom correspondant à cette extension !!
					'GEO_MAP_TYPE' => $regle[2],
					'MAP_KEYS' => @$config_locale['keys-js']
				]);
		}
	}

	/**
		VIEWTOPIC.PHP
	*/
	// Appelé avant la requette SQL qui récupère les données des posts
	function geobb_viewtopic_get_post_data($vars) {
		// Insère la conversion du champ geom en format WKT dans la requette SQL
		$sql_ary = $vars['sql_ary'];
		$sql_ary['SELECT'] .= ',AsText(geom) AS geomwkt';
		$vars['sql_ary'] = $sql_ary;
	}

	// Appelé lors de la première passe sur les données des posts qui lit les données SQL de phpbb-posts
	function viewtopic_post_rowset_data($vars) {
		// Mémorise les données SQL du post pour traitement plus loin (viewtopic procède en 2 fois)
		$this->post_data [$vars['row']['post_id']] = $vars['row'];
	}

	// Appelé lors de la deuxième passe sur les données des posts qui prépare dans $post_row les données à afficher sur le post du template
	function geobb_viewtopic_modify_post_row($vars) {
		if (isset ($this->post_data [$vars['row']['post_id']])) {
			$row = $this->post_data [$vars['row']['post_id']]; // Récupère les données SQL du post 
			$post_row = $vars['post_row'];

			// Convert the geom info in geoJson format
			preg_match ('/\[(first|all)=([a-z]+)\]/i', $vars['topic_data']['forum_desc'], $regle);
			if (count ($regle) == 3 &&
				(
					($regle[1] == 'all') ||
					($regle[1] == 'first' && ($row['post_id'] == $vars['topic_data']['topic_first_post_id']))
				) &&
				@$row['geomwkt']
			) {
				include_once('assets/GeoBB/geoPHP/geoPHP.inc'); // Librairie de conversion WKT <-> geoJson (needed before MySQL 5.7)
				$g = \geoPHP::load($row['geomwkt'],'wkt');
				$row['geomjson'] = $g->out('json');
				$this->get_bounds($g);
				$this->get_automatic_data($row);
				$this->geobb_activate_map($vars, $vars['topic_data']['forum_desc']);
			}

			foreach ($row AS $k=>$v)
				if (strpos ($k,'geo') === 0) {
					// Assign the phpbb-posts.geo* SQL data of to each template post area
					$post_row[strtoupper ($k)] = $v;

					// Assign the phpbb-posts.geo* SQL data of the first post to the template
					if ($vars['topic_data']['topic_first_post_id'] == $row['post_id'])
						$this->template->assign_var (strtoupper ($k), $v);
				}

			$vars['post_row'] = $post_row;
		}
	}

	// Appelé aprés l'assignation de postrow au template. Pour assigner les sous-blocks de postrow
	function viewtopic_post_row_after($vars) {
		global $limite;

		$row = $vars['row'];

		// Lecture des posts ayant un geom en contact
		$sql = "
			SELECT p.post_id, p.post_subject, p.topic_id,
				f.forum_id, f.forum_name, f.forum_image,
				AsText(p.geom) AS gp, AsText(l.geom) AS gl
			FROM ".POSTS_TABLE." AS l
				JOIN ".POSTS_TABLE." AS p ON (Intersects (l.geom, p.geom))
				JOIN ".FORUMS_TABLE." AS f ON (p.forum_id = f.forum_id)
			WHERE p.post_id != l.post_id
				AND l.post_id = ".$row['post_id']."
				GROUP BY (p.topic_id)";

		$result = $this->db->sql_query_limit($sql, $limite);
		while ($row = $this->db->sql_fetchrow($result)) {
			preg_match ('/([0-9\. ]+)/', $row['gp'], $pp);
			preg_match_all ('/([0-9\. ]+)/', $row['gl'], $pl);
			if (in_array ($pp[0], $pl[0])) // Intersects récolte tout les points qui sont dans le bbox des lignes. Il faut trier ceux qui en sont des sommets
				$this->template->assign_block_vars('postrow.jointif', array_change_key_case ($row, CASE_UPPER));
		}
	}

	function gis_modify_data($vars) {
		// Insère l'extraction des données externes dans le flux géographique
		$row = $vars['row'];

		if ($vars['diagBbox'])
			$this->optim ($row['geomphp'], $vars['diagBbox'] / 200); // La longueur min des segments de lignes & surfaces sera de 1/200 de la diagonale de la BBOX

		$vars['row'] = $row;
	}
	function optim (&$g, $granularity) { // Fonction récursive d'optimisation d'un objet PHP contenant des objets géographiques
		if (isset ($g->geometries)) // On recurse sur les Collection, ...
			foreach ($g->geometries AS &$gs)
				$this->optim ($gs, $granularity);

		if (isset ($g->features)) // On recurse sur les Feature, ...
			foreach ($g->features AS &$fs)
				$this->optim ($fs, $granularity);

		if (preg_match ('/multi/i', $g->type)) {
			foreach ($g->coordinates AS &$gs)
				$this->optim_coordinate_array ($gs, $granularity);
		} elseif (isset ($g->coordinates)) // On a trouvé une liste de coordonnées à optimiser
			$this->optim_coordinate_array ($g->coordinates, $granularity);
	}
	function optim_coordinate_array (&$cs, $granularity) { // Fonction d'optimisation d'un tableau de coordonnées
		if (count ($cs) > 2) { // Pour éviter les "Points" et "Poly" à 2 points
			$p = $cs[0]; // On positionne le point de référence de mesure de distance à une extrémité
			$r = []; // La liste de coordonnées optimisées
			foreach ($cs AS $k=>$v)
				if (!$k || // On garde la première extrémité
					$k == count ($cs) - 1) // Et la dernière
					$r[] = $v;
				elseif (hypot ($v[0] - $p[0], $v[1] - $p[1]) > $granularity)
					$r[] = // On copie ce point
					$p = // On repositionne le point de référence
						$v;
			$cs = $r; // On écrase l'ancienne
		}
	}

	// Calcul des données automatiques
	function get_automatic_data(&$row) {
		global $config_locale;
		preg_match_all('/([0-9\.\-]+)/', $row['geomwkt'], $ll);

		// Calcul de l'altitude avec mapquest
		if (array_key_exists ('geo_altitude', $row) && !$row['geo_altitude']) {
			$mapquest = 'http://open.mapquestapi.com/elevation/v1/profile?key='.$config_locale['keys-mapquest']
					   .'&callback=handleHelloWorldResponse&shapeFormat=raw&latLngCollection='.$ll[1][1].','.$ll[1][0];
			preg_match_all('/"height":([0-9]+)/', @file_get_contents ($mapquest), $retour);//TODO DCMM preg_match
			if ($r = @$retour[1][0])
				$row['geo_altitude'] = // Pour affichage
				$sql_update['geo_altitude'] = // Pour modification de la base
					$r.'~'; // ~ indique que la valeur & été déterminée par le serveur
		}

		// Détermination du massif par refuges.info
		if (array_key_exists ('geo_massif', $row) && !$row['geo_massif']) {
			$f_wri_export = 'http://www.refuges.info/api/polygones?type_polygon=1,10,11,17&bbox='.$ll[1][0].','.$ll[1][1].','.$ll[1][0].','.$ll[1][1];
			$wri_export = json_decode (@file_get_contents ($f_wri_export));
			if($wri_export->features)
				foreach ($wri_export->features AS $f)
					$ms [$f->properties->type->id] = $f->properties->nom;
			if (isset ($ms))
				ksort ($ms);
			$row['geo_massif'] = // Pour affichage
			$sql_update['geo_massif'] = // Pour modification de la base
				@$ms[array_keys($ms)[0]].'~'; // ~ indique que la valeur & été déterminée par le serveur
		}

		// Update de la base
		if (isset ($sql_update))
			$this->db->sql_query ('UPDATE '.POSTS_TABLE.' SET '.$this->db->sql_build_array('UPDATE',$sql_update)." WHERE post_id = ".$row['post_id']);

		// N'affiche pas le ~
		$row['geo_altitude'] = str_replace ('~', '', $row['geo_altitude']);
		$row['geo_massif'] = str_replace ('~', '', $row['geo_massif']);
	}

	// Calcul de la bbox englobante
	function get_bounds($g) {
		$b = $g->getBBox();
		$m = 0.005; // Marge autour d'un point simple (en °)
		foreach (['x','y'] AS $xy) {
			if ($b['min'.$xy] == $b['max'.$xy]) {
				$b['min'.$xy] -= $m;
				$b['max'.$xy] += $m;
			}
			foreach (['max','min'] AS $mm)
				$this->bbox['geo_bbox_'.$mm.$xy] =
					isset ($this->bbox['geo_bbox_'.$mm.$xy])
					? $mm ($this->bbox['geo_bbox_'.$mm.$xy], $b[$mm.$xy])
					: $b[$mm.$xy];
		}
		$this->template->assign_vars (array_change_key_case ($this->bbox, CASE_UPPER));
	}


	/**
		POSTING.PHP
	*/
	// Appelé lors de l'affichage de la page posting
	function posting_modify_template_vars($vars) {
		$post_data = $vars['post_data'];

		// Récupère la traduction des données spaciales SQL
		if (isset ($post_data['geom'])) {
			// Conversion WKT <-> geoJson
			$sql = 'SELECT AsText(geom) AS geomwkt
				FROM ' . POSTS_TABLE . '
				WHERE post_id = ' . $post_data['post_id'];
			$result = $this->db->sql_query($sql);
			$post_data['geomwkt'] = $this->db->sql_fetchfield('geomwkt');
			$this->db->sql_freeresult($result);

			// Traduction en geoJson
			include_once('assets/GeoBB/geoPHP/geoPHP.inc'); // Librairie de conversion WKT <-> geoJson (needed before MySQL 5.7)
			$g = \geoPHP::load($post_data['geomwkt'],'wkt');
			$this->get_bounds($g);
			$gp = json_decode ($g->out('json')); // On transforme le GeoJson en objet PHP
			$this->optim ($gp, 0.0001); // La longueur min des segments de lignes & surfaces sera de 0.0001 ° = 10 000 km / 90° * 0.0001 = 11m
			$post_data['geomjson'] = json_encode ($gp);
		}

		// Pour éviter qu'un titre vide invalide la page et toute la saisie graphique.
		// TODO : traiter au niveau du formulaire (avertissement de modif ?)
		if (!$post_data['post_subject'])
			$post_data['draft_subject'] = 'NEW';

		// Assign the phpbb-posts SQL data to the template
		foreach ($post_data AS $k=>$v)
			if (is_string ($v))
				$this->template->assign_var (
					strtoupper ($k),
					strstr($v, '~') == '~' ? null : $v // Efface les champs finissant par ~ pour les recalculer automatiquement
				);

		// Assign the forums geom type flags to the template
		$first_post =
			!isset ($post_data['topic_id']) || // Cas de la création d'un nouveau topic
			$post_data['topic_first_post_id'] == @$post_data['post_id'];

		$this->geobb_activate_map($vars, $post_data['forum_desc'], $first_post);

		// Patch phpbb to accept geom values
		// HORRIBLE hack mais comment faire autrement tant que les géométries ne sont pas prises en compte par PhpBB ???
		// DCMM TODO résolu en PhpBB 3.2
		$file_name = "phpbb/db/driver/driver.php";
		$file_tag = "\n\t\tif (is_null(\$var))";
		$file_patch = "\n\t\tif (strpos (\$var, 'GeomFromText') === 0) //GeoBB\n\t\t\treturn \$var;";
		$file_content = file_get_contents ($file_name);
		if (strpos($file_content, '{'.$file_tag))
			file_put_contents ($file_name, str_replace ('{'.$file_tag, '{'.$file_patch.$file_tag, $file_content));
	}

	// Appelé lors de la validation des données à enregistrer
	function submit_post_modify_sql_data($vars) {
		$sql_data = $vars['sql_data'];

		// Enregistre dans phpbb-posts les valeurs de $_POST correspondantes à des champs de phpbb-posts commençant par geo
		$sql = 'SHOW columns FROM '.POSTS_TABLE.' LIKE "geo%"';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result)) {
			$col_name = $row['Field'];

			// Corrige le type de colonne de geom si la table vient d'être crée
			// TODO DCMM : le mettre dans migration/...
			if ($col_name == 'geom' && $row['Type'] == 'text')
				$this->db->sql_query('ALTER TABLE '.POSTS_TABLE.' CHANGE geom geom GEOMETRYCOLLECTION NULL');

			$val = request_var ($col_name, 'UNDEFINED', true); // Look in $_POST
			if ($val != 'UNDEFINED')
				$sql_data[POSTS_TABLE]['sql'][$col_name] = utf8_normalize_nfc($val) ?: null; // null permet la supression du champ

			// Donnée spaciale
			$json = request_var ($col_name.'json', ''); // Look in $_POSTS[*json]
			if ($json) {
				include_once('assets/GeoBB/geoPHP/geoPHP.inc'); // Librairie de conversion WKT <-> geoJson (needed before MySQL 5.7)
				$g = \geoPHP::load (html_entity_decode($json), 'json');
				if ($g) // Pas de geom
					$sql_data[POSTS_TABLE]['sql'][$col_name] = 'GeomFromText("'.$g->out('wkt').'")';
			}
		}
		$this->db->sql_freeresult($result);

		$vars['sql_data'] = $sql_data;

		//-----------------------------------------------------------------
		// Save modif
		$data = $vars['data'];
		$save[] = date('r').' '.$this->user->data['username'];

		// Trace avant
		if ($data['post_id']) {
			$sql = 'SELECT *, AsText(geom) AS geomwkt FROM '.POSTS_TABLE.' WHERE post_id = '.$data['post_id'];
			$result = $this->db->sql_query($sql);
			$data_avant = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			$sql = 'SELECT attach_id FROM '.ATTACHMENTS_TABLE.' WHERE post_msg_id = '.$data['post_id'];
			$result = $this->db->sql_query($sql);
			while ($rowattchm = $this->db->sql_fetchrow($result))
				$attach_avant[] = $rowattchm['attach_id'];
			$this->db->sql_freeresult($result);

			if (isset ($attach_avant))
				$data_avant['attachments'] = implode ('|', $attach_avant);

			foreach (['forum_id','topic_id','post_id','poster_id','post_subject','post_text','geomwkt','attachments',] AS $k)
				if (@$data_avant[$k])
					$avant[] = $k.'='.str_replace("\n","\\n",$data_avant[$k]);

			$save[] = 'avant:'.implode(',',$avant);
		}

		// Trace aprés
		if (isset ($data['attachment_data'])) {
			foreach ($data['attachment_data'] AS $a)
				$attach_apres[] = $a['attach_id'];
			if (isset ($attach_apres))
				$data['attachments'] = implode ('|', $attach_apres);
		}
		if (isset ($data['geom']))
			$data['geom'] = str_replace (['GeomFromText("','")'], '', $sql_data[POSTS_TABLE]['sql']['geom']);

		foreach (['forum_id','topic_id','post_id','poster_id','topic_title','message','geom','attachments',] AS $k)
			if (@$data[$k])
				$apres[] = $k.'='.str_replace("\n","\\n",$data[$k]);

		$save[] = $vars['post_mode'].':'.implode(',',$apres);

		file_put_contents ('EDIT.log', implode ("\n", $save)."\n\n", FILE_APPEND);
	}

	// Permet la saisie d'un POST avec un texte vide
	function posting_modify_submission_errors($vars) {
		$error = $vars['error'];

		foreach ($error AS $k=>$v)	
			if ($v == $this->user->lang['TOO_FEW_CHARS'])
				unset ($error[$k]);

		$vars['error'] = $error;
	}
}