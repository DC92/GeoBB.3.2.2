<?php
/**
 *
 * @package Reference
 * @copyright (c) 2016 Dominique Cavailhez
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace Dominique92\Reference\event;

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
		\phpbb\auth\auth $auth,
		\phpbb\extension\manager $extension_manager,
		$root_path
	) {
		$this->db = $db;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->auth = $auth;
		$this->extension_manager = $extension_manager;
		$this->root_path = $root_path;
	}

	// Liste des hooks et des fonctions associées
	static public function getSubscribedEvents() {
		return [
			'core.index_modify_page_title' => 'init_select',

			'core.viewtopic_modify_post_data' => 'viewtopic_modify_post_data',
			'core.viewtopic_assign_template_vars_before' => 'viewtopic_assign_template_vars_before',

			'core.modify_posting_parameters' => 'modify_posting_parameters', // ligne 104
			'core.modify_posting_auth' => 'modify_posting_auth', // ligne 394
			'core.submit_post_modify_sql_data' => 'submit_post_modify_sql_data',

			'geo.gis_after' => 'gis_after',
			'geo.gis_modify_sql' => 'gis_modify_sql',
			'geo.gis_modify_data' => 'gis_modify_data',
		];
	}

	// Sélections des fiches
	function init_select() {
		$this->template->assign_var ('IS_MODERATOR', $this->auth->acl_getf_global(['m_','a_'])); //TODO DCMM mettre dans un endroit plus centralisé

		// Popule le sélecteur de couches overlays
		$this->template->assign_block_vars('map_overlays', [
			'NAME' => 'Chemineur',
			'PAR' => 'site',
			'VALUE' => 'this',
		]);

		$sql = 'SELECT DISTINCT SUBSTRING_INDEX(url, "/", 3) AS domain FROM geo_reference';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
			$this->template->assign_block_vars('map_overlays', [
				'NAME' => ucfirst ($n = str_replace (['http://', 'https://', 'www.', '.org', '.com'], '', $row['domain'])),
				'PAR' => 'site',
				'VALUE' => $n,
			]);
		$this->db->sql_freeresult($result);

		$sql = "
			SELECT DISTINCT c.forum_name, c.forum_id
			FROM ".FORUMS_TABLE." AS c
			JOIN ".FORUMS_TABLE." AS f ON (f.parent_id = c.forum_id)
			WHERE f.forum_desc REGEXP '\[[all|first]=[a-z]+\]'
		";
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
			$this->template->assign_block_vars('map_overlays', [
				'NAME' => $row['forum_name'],
				'PAR' => 'poi',
				'VALUE' => $row['forum_id'],
			]);
		$this->db->sql_freeresult($result);
	}

	// Affichage des refs en tête de fiche
	function viewtopic_assign_template_vars_before($vars) {
		$this->init_select();

		$sql = 'SELECT * FROM geo_reference WHERE topic_id = '.$vars['topic_id'];
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
			$this->template->assign_block_vars('reference', [
				'URL' => $row['url'],
				'NAME' =>  $row['post_subject'],
				'DOMAIN' =>  str_replace('www.', '', parse_url ($row['url'],PHP_URL_HOST)),
			]);
		$this->db->sql_freeresult($result);
	}

	// Ajout des commentaires WRIC
	function viewtopic_modify_post_data($vars) {
		if (request_var('view','') == 'point') {
			$post_list = $vars['post_list'];
			$rowset = $vars['rowset'];
			$attachments = $vars['attachments'];
			$user_cache = $vars['user_cache'];
			$topic_data = $vars['topic_data'];

			$user_cache[ANONYMOUS] = array(
				'user_type'			=> USER_IGNORE,
				'username'			=> 'Anonymous',
				'user_colour'		=> '',
				'joined'			=> '',
				'posts'				=> '',
				'avatar'			=> '',
				'rank_image'		=> '',
				'rank_image_src'	=> '',
				'rank_title'		=> '',
				'sig'				=> '',
				'email'				=> '',
				'jabber'			=> '',
				'search'			=> '',
				'contact_user'		=> '',
				'age'				=> '',
				'warnings'			=> 0,
			);

			$sql = "SELECT *
				FROM geo_wric
				JOIN geo_reference USING (url)
				WHERE topic_id = ".$rowset[$post_list[0]]['topic_id'];
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result)) {
				$post_username =
					'"<a target="_blank"'
					.' href="'.$row['url'].'#C'.$row['id'].'"'
					.' title="Commentaire issu de http://www.refuges.info"'
					.'>'
					.$row['auteur'].'@refuges.info</a>"';
				$post_list[] = $pseudo_idc = 1000000 + $row['id'];
				$rowset [$pseudo_idc] = array (
					'hide_post' => false,
					'post_id' => $pseudo_idc,
					'post_time' => $row['date'],
					'user_id' => ANONYMOUS,
					'username' => '',
					'user_colour' => 'AA0000',
					'topic_id' => $topic_data['topic_id'],
					'post_subject' => '',
					'post_edit_count' => 0,
					'post_edit_time' => 0,
					'post_edit_reason' => '',
					'post_edit_user' => ANONYMOUS,
					'post_edit_locked' => 0,
					'post_delete_time' => 0,
					'post_delete_reason' => '',
					'post_visibility' => 1,
					'post_reported' => 0,
					'post_username' => $post_username,
					'post_text' =>$row['texte'],
					'bbcode_uid' => '',
					'bbcode_bitfield' => '',
					'enable_smilies' => 1,
					'enable_sig' => 1,
					'friend' => NULL,
					'foe' => NULL,
				);

				if ($row['photo'])
					$attachments [$pseudo_idc][0]= array (
						'attach_id' => $pseudo_idc,
						'post_msg_id' => $pseudo_idc,
						'topic_id' => $row['topic_id'],
						'in_message' => 0,
						'poster_id' => ANONYMOUS,
						'is_orphan' => 0,
						'physical_filename' => 'http://www.refuges.info'.$row['photo'],
						'real_filename' => 'http://www.refuges.info'.str_replace ('-originale', '', $row['photo']),
						'download_count' => 0,
						'attach_comment' => '',
						'extension' => 'jpg',
						'mimetype' => 'image/jpeg',
						'filesize' => 0,
						'filetime' => $row['date_photo'],
						'thumbnail' => 0,
						'exif' => $post_username,
					);
			}
			$this->db->sql_freeresult($result);

			$vars['post_list'] = $post_list;
			$vars['rowset'] = $rowset;
			$vars['attachments'] = $attachments;
			$vars['user_cache'] = $user_cache;
		}
	}

	/* Appelé avant tout */
	// Attache les fichiers files/attach/<POST_ID>/*.jpg
	function modify_posting_parameters($vars) {
		global $db;

		// Liste les attachments déjà présents
		$attachments = $sql_arri = [];
		$sql = 'SELECT * FROM '.ATTACHMENTS_TABLE.' WHERE post_msg_id = '.$vars['post_id'];
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result)) {
			$fs = explode('files/attach',$row['real_filename']);
			if (count ($fs) == 2)
				$attachments['files/attach'.$fs[1]] = $row;
		}
		$db->sql_freeresult($result);

		// Récupère le topic_id
		$sql = 'SELECT topic_id FROM '.POSTS_TABLE.' WHERE post_id = '.$vars['post_id'];
		$result = $db->sql_query_limit($sql, 1);
		$posts_table = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		// Insére les attachements
		foreach (glob ("files/attach/{$vars['post_id']}/*.{JPG,jpg}", GLOB_BRACE) AS $jpg)
			if (!isset ($attachments[$jpg]))
				$sql_arri [] = array (
					'post_msg_id' => $vars['post_id'],
					'topic_id' => $posts_table['topic_id'],
					'in_message' => 0,
					'poster_id' => $this->user->data['user_id'],
					'is_orphan' => 0,
					'physical_filename' => $jpg,
					'real_filename' => $jpg,
					'attach_comment' => '',
					'extension' => 'jpg',
					'mimetype' => 'image/jpeg',
				);

		// Ajoute, mais dans l'ordre de nommage des fichiers
		foreach (array_reverse ($sql_arri) AS $a)
			$db->sql_query('INSERT INTO '. ATTACHMENTS_TABLE.' '.$db->sql_build_array('INSERT', $a));

		// Marque ce post avec attachement
		$db->sql_query('UPDATE '.POSTS_TABLE.' SET post_attachment = 1 WHERE post_id = '.$vars['post_id']);
	}

	/* Appelé aprés vérifications autorisations à l'affichage de la page
	Crée une fiche: http://localhost/GeoBB/GeoBB319/posting.php?sid=...&mode=post&f=12&url=http://wri/...&nom=nnnn&lon=2&lat=45
	Lien à un topic: http://localhost/GeoBB/GeoBB319/posting.php?sid=...&mode=post&f=12&t=34&url=http://wri/...&nt=34
	Supprime un lien à un topic: http://localhost/GeoBB/GeoBB319/posting.php?sid=...&mode=post&f=12&t=34&url=http://wri/...
	*/
	function modify_posting_auth($vars) {
		global $db, $is_authed;

		$this->init_select();

		// Création d'une fiche
		if ($url = request_var('url', '')) {
			if (!$is_authed ||
				$this->user->session_id != request_var('sid', ''))
					trigger_error('NOT_AUTHORISED');

			$nt = request_var('nt', 0);
			if ($nom = utf8_normalize_nfc(ucfirst(urldecode (request_var('nom', '', true))))) {
				$data = [
					'forum_id' => $vars['forum_id'],
					'post_subject' => $nom,
					'geo_ref' => $url,
					'geo_lon' => request_var('lon', 0.0),
					'geo_lat' => request_var('lat', 0.0),
					'post_id' => 0, // Le créer
					'topic_id' => 0, // Le créer
					'message' => '',
					'message_md5' => md5(''),
					'bbcode_bitfield' => 0, //$message_parser->bbcode_bitfield, // TODO DCMM
					'bbcode_uid' => 0, //$message_parser->bbcode_uid,
					'icon_id' => 0,
					'enable_bbcode' => true,
					'enable_smilies' => true,
					'poster_id' => $this->user->data['user_id'],
					'enable_urls' => true,
					'enable_sig' => true,
					'topic_visibility' => true,
					'post_visibility' => true,
					'enable_indexing' => true,
					'post_edit_locked' => false,
					'notify_set' => false,
					'notify' => false,
				];
				$poll = [];
				\submit_post(
					'post',
					$nom,
					$this->user->data['username'],
					POST_NORMAL,
					$poll,
					$data
				);
				$nt = $data['topic_id'];
			}

			$sql = "UPDATE geo_reference SET topic_id = $nt WHERE url = '$url'";
			$db->sql_query($sql);

			// On arrête tout et on recharge
			header('Location: viewtopic.php?t='.($nt ?: $vars['topic_id']));
			exit;
		}
	}

	// Appelé à l'intérieur de submit_post
	function submit_post_modify_sql_data($vars) {
		if (isset ($vars['data']['geo_lon'])) {
			$sql_data = $vars['sql_data'];
			$sql_data[POSTS_TABLE]['sql']['geom'] =  'GeomFromText("POINT('.$vars['data']['geo_lon'].' '.$vars['data']['geo_lat'].')")';
			$vars['sql_data'] = $sql_data;
		}
	}

	function gis_modify_sql($vars) {
		// Insère l'extraction des données externes dans le flux géographique
		$sql_array = $vars['sql_array'];

		$sql_array ['SELECT'][] = 'url'; // 1 donnée supplèmentaire

		// Fusionne la table interne et externe
		$posts_it = 'post_subject,      post_id, topic_id, forum_id,                      post_visibility, geom, "this" AS url';
		$ref_it   = 'post_subject, 0 AS post_id, topic_id, forum_id, '.ITEM_APPROVED.' AS post_visibility, geom,           url';
		$sql_array['FROM'] = ["((SELECT $posts_it FROM ".POSTS_TABLE.") UNION (SELECT $ref_it FROM geo_reference))" => 'p'];

		$sql_array ['WHERE']['OR'][] = 't.topic_id IS NULL'; // Affiche un point externe s'il n'est pas associé à un topic ou si le topic associé n'existe plus
		$sql_array ['WHERE']['OR'][] = 't.topic_visibility != '.ITEM_APPROVED; // Ou si le topic est masqué

		$poi = request_var ('poi', '') ?: '9999';
		$site = str_replace ([',','-','.'], ['|','\\-','\\.'], request_var ('site', '') ?: 'NONE');
		$sql_array ['WHERE'][] = "f.parent_id IN ($poi)"; // Liste des types de points
		$sql_array ['WHERE'][] = "(url REGEXP '$site')"; // Liste des sites

		$vars['sql_array'] = $sql_array;
	}

	// Insère les données externes extraites dans les propriétés de chaque élément du flux géographique
	function gis_modify_data($vars) {
		global $bu; // Adresse racine du site
		$properties = $vars['properties'];
		$properties ['url'] =
			$vars['row']['url'] == 'this'
			? $bu.'viewtopic.php?t='.$vars['row']['topic_id']
			: $vars['row']['url'];
		$vars['properties'] = $properties;
	}

	// Traitement hors délais de l'importations de données des autres sites
	function gis_after($vars) {
		global $request;

		// Release flow & continue import
		ignore_user_abort (true);
//		ob_flush();
		flush();

		$log [] = str_repeat('*', 40);
		$log [] = date('r');
		$log [] = $request->server('REQUEST_SCHEME').'://'.$request->server('HTTP_HOST').$request->server('REQUEST_URI');

		get_sync_context ();
		geo_sync_wri ($vars['bbox']);
		geo_sync_prc (date_last_sync ('pyrenees'));
		geo_sync_c2c ($vars['bbox'], date_last_sync ('camptocamp'));

		$log [] = '';
		file_put_contents ('../../../GIS.log', implode (' ', $log) ."\n", FILE_APPEND);
	}
}
//-------------------------------------------------------------------------
// FONCTIONS
//-------------------------------------------------------------------------
function get_sync_context () {
	global $forums, $users, $db;

	// Liste des users
	$sql = "SELECT user_id, username, username_clean FROM phpbb_users";
	$result = $db->sql_query($sql);
	while ($row = $db->sql_fetchrow($result))
		$users [$row ['username_clean']] = $row;

	// Numéros des forum avec icones
	$sql = 'SELECT forum_id, forum_image FROM '.FORUMS_TABLE.' WHERE forum_image != ""';
	$result = $db->sql_query($sql);
	while ($row = $db->sql_fetchrow($result)) {
		preg_match('/([a-z_]+)\./i', $row['forum_image'], $m);
		if (count ($m))
			$forums[$m[1]] = $row['forum_id'];
	}
	$forums += [
		'gîte' => $forums['gite'],
		'gÃ®te' => $forums['gite'],
		'gite-d-etape' => $forums['gite'],
		'point culminant' => $forums['sommet'],
		'abri sommaire' => $forums['abri'],
		'emplacement de bivouac' => $forums['bivouac'],
		'camp de base' => $forums['bivouac'],
		'point' => $forums['point_eau'],
		'point-d-eau' => $forums['point_eau'],
		'ancien-point-d-eau' => $forums['point_eau'],
		'point-de-passage' => $forums['col'],
		'passage-delicat' => $forums['col'],
		'source' => $forums['point_eau'],
		'inutilisable' => $forums['ferme'],
		'cabane-non-gardee' => $forums['cabane'],
		'batiment' => $forums['inconnu'],
		'batiment-en-montagne' => $forums['inconnu'],
		'batiment-inutilisable' => $forums['ferme'],
		'refuge-garde' => $forums['refuge'],
		'' => $forums['inconnu'],
	];
}
//-------------------------------------------------------------------------
function geo_sync_wri ($bbox = 'world') {
	global $forums, $users, $log;

	$wri_upd = $wric_upd = [];
	$log [] = 'SYNC.PHP';
	$log [] = $urlWRI = "http://www.refuges.info/api/bbox?bbox=$bbox&nb_coms=100&detail=simple&format_texte=texte&format=xml";
	$xmlWRI = simplexml_load_file($urlWRI);
	foreach ($xmlWRI AS $x) {
		// Références à des points WRI
		preg_match('/([a-z\-]+)/i', $x->type->icone, $icones);
		if ($f = @$forums[$icones[1]])
			$wri_upd [] = [
				'post_subject' => '"'.str_replace ('"', '\\"', $x->nom).'"',
				'forum_id' => $f,
				'geom' => "GeomFromText('POINT({$x->coord->long} {$x->coord->lat})',0)",
				'url' => '"http://www.refuges.info/point/'.$x->id.'"',
				'last_update' => time(),
			];
		else if (!@$sans_icone[$icones[1]]++)
			echo"<pre>Icone WRI inconnue ({$icones[1]}) ".var_export($x->type,true).'</pre>';

		// Import des commentaires
		if (isset ($x->coms))
			foreach ($x->coms->node AS $c) {
				$noms = explode (' ', strtolower (@$c->createur->nom));
				if ($u = @$users[$noms[count($noms)-1]]) {
					$logWriId [(int)$c->id] = true;
					$wric_upd [] = [
						'id'               => (int) $c->id,
						'texte'            => '"'.str_replace ('"', '\\"', $c->texte).'"',
						'date'             => strtotime($c->date) ?: 0,
						'photo'            => '"'.@$c->photo->originale.'"',
						'date_photo'       => strtotime(@$c->photo->date) ?: 0,
						'auteur'           => '"'.str_replace ('"', '\\"', $c->createur->nom).'"',
						'url'              => '"http://www.refuges.info/point/'.$x->id.'"',
						'wric_last_update' => time(),
					];
				}
			}
	}
	sql_update_table ('geo_reference', $wri_upd);
	sql_update_table ('geo_wric', $wric_upd);
}
//-------------------------------------------------------------------------
function lon2x ($lon) {return $lon * 20037508.34 / 180;}
function lat2y ($lat) {return log (tan ((90 + $lat) * M_PI / 360)) / M_PI * 20037508.34;}
function x2lon ($x)   {return $x / 20037508.34 * 180;}
function y2lat ($y)   {return atan (exp ($y * M_PI / 20037508.34)) / M_PI * 360 -90;}

function geo_sync_c2c ($bbox = '', $last_date = 0) {
	global $forums, $template, $db;
	$forums += [
		'hut' => $forums['cabane'],
		'shelter' => $forums['abri'],
		'summit' => $forums['sommet'],
		'pass' => $forums['col'],
		'lake' => $forums['lac'],
		'locality' => $forums['village'],
		// Non importés
		'climbing_outdoor' => 0,
		'bisse' => 0,
		'access' => 0,
		'waterfall' => 0,
		'local_product' => 0,
		'virtual' => 0,
		'misc' => 0
	];
	$bbll = explode(',',$bbox);
	$bbxy = [
		lon2x ($bbll[0]),
		lat2y ($bbll[1]),
		lon2x ($bbll[2]),
		lat2y ($bbll[3]),
	];
	$js = json_decode (@file_get_contents ('https://api.camptocamp.org/waypoints?pl=fr&bbox='.implode('%2C', $bbxy)));

	if ($js)
		foreach ($js->documents AS $d) {
			if (!isset ($forums[$d->waypoint_type])) { // Type de point non déterminé
				file_put_contents ('C2CUNKN.log', $d->waypoint_type."\n", FILE_APPEND );
				continue;
			}

		foreach ($d->locales AS $k=>$v)
			if (!$k || $v->lang == 'fr')
				$locale = $v;

		$g = json_decode ($d->geometry->geom);

		if ($forum_id = $forums[$d->waypoint_type])
			$sql_values[] = $sv = [
				'post_subject' => '"'.str_replace('"', '\\"', $locale->title).'"',
				'forum_id' => $forums[$d->waypoint_type],
				'geom' => implode (' ', [
					'GeomFromText("POINT(',
					x2lon($g->coordinates[0]),
					y2lat($g->coordinates[1]),
					')",0)'
				]),
				'url' => implode ('/', [
					'"https://www.camptocamp.org/waypoints',
					$d->document_id,
					$locale->lang,
					'-"'
				]),
				'last_update'  => time(),
			];
	}
	sql_update_table ('geo_reference', $sql_values);
}
//-------------------------------------------------------------------------
function geo_sync_prc ($last_date = 0) {
	global $forums;

	if (time() - $last_date > 24 * 3600) { // Une fois par jour
		eval (str_replace ('var ', '$', @file_get_contents ('http://www.pyrenees-refuges.com/lib/refuges.js')));
		$sql_values = [];
		if($addressPoints)
			foreach ($addressPoints AS $k=>$v)
				$sql_values[] = [
					'post_subject' => '"'.str_replace('"', '\\"', $v[2]).'"',
					'forum_id'     => $forums['cabane'],
					'geom'         => "GeomFromText('POINT({$v[0]} {$v[1]})',0)",
					'url'          => '"http://www.pyrenees-refuges.com/fr/affiche.php?numenr='.$v[3].'"',
					'last_update'  => time(),
				];
		sql_update_table ('geo_reference', $sql_values);
	}
}
//-------------------------------------------------------------------------
function sql_update_table ($table, $lignes) {
	global $db, $log;

	$logid = [];
	if (count ($lignes)) {
		foreach ($lignes AS $ligne) {
			$sql_values[] = implode (',', $ligne);
			if (isset ($ligne['id']))
				$logid[] = $ligne['id'];
		}
		foreach ($ligne AS $k=>$v)
			$sql_eq[] = "$k=VALUES($k)";

		$sql = "INSERT INTO $table (".implode (',', array_keys ($ligne)).")
			VALUES \n(".implode ("),\n(", $sql_values).")
			ON DUPLICATE KEY UPDATE ".implode (',', $sql_eq);
		$db->sql_query($sql);

		$log [] = $table.' = '.count($lignes);
		$log [] = implode ('|', array_keys ($logid));
	}
}
//-------------------------------------------------------------------------
// Calcul du délai depuis le dernier sync PRC
function date_last_sync ($site) {
	global $db;

	$sql = "SELECT last_update FROM geo_reference WHERE url LIKE '%$site%' ORDER BY last_update DESC";
	$result = $db->sql_query($sql);
	$r =
		$result
		? $db->sql_fetchrow($result) ['last_update']
		: 0;
	$db->sql_freeresult($result);

//*DCMM*/echo"<pre style='background-color:white;color:black;font-size:14px;'>Dernier update : ".var_export(date('r',$r),true).'</pre>';
	return $r;
}
