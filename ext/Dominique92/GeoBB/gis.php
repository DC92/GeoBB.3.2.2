<?php
/**
* Extractions de données géographiques
*
* @copyright (c) Dominique Cavailhez 2015
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : '../../../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);

include_once('../../../assets/geoPHP/geoPHP.inc'); // Librairie de conversion WKT <-> geoJson (needed before MySQL 5.7)

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup();

$bboxs = explode (',', $bbox = request_var ('bbox', '-180,-90,180,90'));
$bbox_sql =
	$bboxs[0].' '.$bboxs[1].','.
	$bboxs[2].' '.$bboxs[1].','.
	$bboxs[2].' '.$bboxs[3].','.
	$bboxs[0].' '.$bboxs[3].','.
	$bboxs[0].' '.$bboxs[1];

$diagBbox = hypot ($bboxs[2] - $bboxs[0], $bboxs[3] - $bboxs[1]); // Hypothènuse de la bbox

$priority = request_var ('priority', 0); // topic_id à affichage prioritaire
$limite = request_var ('limite', 150); // Nombre de points maximum
$format = $format_app = request_var ('format', 'json');
if ($format == 'gpx') {
	$limite = 10000;
	$diagBbox = 0; // Pas d'optimisation
}

// Recherche des points dans la bbox
$sql_array = [
	'SELECT' => [
		'post_subject',
		'f.forum_id',
		't.topic_id',
		'post_id',
		'forum_image',
		'forum_desc',
		'AsText(geom) AS geomwkt',
	],
	'FROM' => [POSTS_TABLE => 'p'],
	'LEFT_JOIN' => [[
		'FROM' => [TOPICS_TABLE => 't'],
		'ON' => 't.topic_id = p.topic_id',
	],[
		'FROM' => [FORUMS_TABLE => 'f'],
		'ON' => 'f.forum_id = p.forum_id',
	]],
	'WHERE' => [
		'geom IS NOT NULL',
		"Intersects (GeomFromText ('POLYGON (($bbox_sql))'),geom)",
		'post_visibility = '.ITEM_APPROVED,
		'OR' => [
			't.topic_first_post_id = p.post_id',
			'forum_desc LIKE "%[all=%"',
		],
	],
	'ORDER_BY' => "CASE WHEN f.forum_id = $priority THEN 0 ELSE left_id END",
];

/**
 * Change SQL query for fetching geographic data
 *
 * @event geo.gis_modify_sql
 * @var array     sql_array    Fully assembled SQL query with keys SELECT, FROM, LEFT_JOIN, WHERE
 */
$vars = array(
	'sql_array',
);
extract($phpbb_dispatcher->trigger_event('geo.gis_modify_sql', compact($vars)));

// Build query
if (is_array ($sql_array ['SELECT']))
	$sql_array ['SELECT'] = implode (',', $sql_array ['SELECT']);

if (is_array ($sql_array ['WHERE'])) {
	foreach ($sql_array ['WHERE'] AS $k=>&$w)
		if (is_array ($w))
			$w = '('.implode (" $k ", $w).')';
	$sql_array ['WHERE'] = implode (' AND ', $sql_array ['WHERE']);
}
$sql = $db->sql_build_query('SELECT', $sql_array);
$result = $db->sql_query_limit($sql, $limite);

// Ajoute l'adresse complète aux images d'icones
$sp = explode ('/', getenv('REQUEST_SCHEME'));
$ri = explode ('/ext/', getenv('REQUEST_URI'));
$bu = $sp[0].'://'.getenv('SERVER_NAME').$ri[0].'/';

$gjs = [];
while ($row = $db->sql_fetchrow($result)) {

	// Filtre d'une cabane privée
	if (strstr($row['post_subject'],'Pekoa'))
		continue;

	$properties = [
		'nom' => $row['post_subject'],
		'id' => $row['topic_id'],
		'type_id' => $row['forum_id'],
		'post_id' => $row['post_id'],
		'icone' => $bu.$row['forum_image'],
	];

	preg_match('/\[color=([a-z]+)\]/i', html_entity_decode ($row['forum_desc']), $colors);
	if (count ($colors))
		$properties['color'] = $colors[1];

	$g = geoPHP::load ($row['geomwkt'], 'wkt'); // On lit le geom en format WKT fourni par MySql
	$row['geomjson'] = $g->out('json'); // On le transforme en format GeoJson
	$row['geomphp'] = json_decode ($row['geomjson']); // On transforme le GeoJson en objet PHP

	/**
	 * Change properties before sending
	 *
	 * @event geo.gis_modify_data
	 * @var array row
	 * @var array properties
	 */
	$vars = array(
		'row',
		'properties',
		'diagBbox', // Line or surface min segment length
	);
	extract($phpbb_dispatcher->trigger_event('geo.gis_modify_data', compact($vars)));

	$gjs[] = [
		'type' => 'Feature',
		'geometry' => $row['geomphp'], // On ajoute le tout à la liste à afficher sous la forme d'un "Feature" (Sous forme d'objet PHP)
		'properties' => $properties,
	];
}
$db->sql_freeresult($result);

// Formatage du header
$secondes_de_cache = 60;
$ts = gmdate("D, d M Y H:i:s", time() + $secondes_de_cache) . " GMT";
header("Content-disposition: filename=chemineur.$format");
header("Content-Type: application/$format_app; UTF-8"); // rajout du charset
header("Content-Transfer-Encoding: binary");
header("Pragma: cache");
header("Expires: $ts");
header("Access-Control-Allow-Origin: *");
header("Cache-Control: max-age=$secondes_de_cache");

// On transforme l'objet PHP en code geoJson
$json = json_encode ([
	'type' => 'FeatureCollection',
	'features' => $gjs
]) . PHP_EOL;

if ($format == 'gpx') {
	$mmav = ["><", '"geoPHP" version="1.0"'];
	$mmap = [">\n<", '"http://chemineur.fr" xmlns="http://www.topografix.com/GPX/1/0"'];
	// Récupère les noms des points
	foreach ($gjs AS $gjsv)
		if ($gjsv['geometry']->type == 'Point') {
			$ll = 'lat="'.$gjsv['geometry']->coordinates[1].'" lon="'.$gjsv['geometry']->coordinates[0].'"';
			$mmav [] = "<wpt $ll />";
			$mmap [] = "<wpt $ll><name>".$gjsv['properties']['nom']."</name></wpt>";
	}

	// On transforme l'objet PHP en code gpx
	$gp = geoPHP::load ($json, 'json');
	$gpx = str_replace ($mmav, $mmap, $gp->out('gpx'));
}

echo $$format;

/**
 * Execute something after actions
 *
 * @event geo.gis_after
 */
$vars = array(
	'bbox',
);
extract($phpbb_dispatcher->trigger_event('geo.gis_after', compact($vars)));
