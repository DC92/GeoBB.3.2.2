<?php
/**
 *
 * @package StylesViews
 * @copyright (c) 2016 Dominique Cavailhez
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace Dominique92\StylesViews\event;

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
		$root_path
	) {
		$this->db = $db;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->root_path = $root_path;

		// Recherche du répertoire de l'extension
		$ns = explode ('\\', __NAMESPACE__);
		$this->ext_dir = 'ext/'.$ns[0].'/'.$ns[1].'/';
	}

	// Liste des hooks et des fonctions associées
	static public function getSubscribedEvents() {
		return [
			'core.user_setup' => 'user_setup',
			'core.viewtopic_before_f_read_check' => 'force_view',
			'core.modify_posting_parameters' => 'force_view',
			'core.page_header' => 'page_header',
			'core.page_footer' => 'page_footer',

			// GIS.PHP
			'geo.sync_modify_sql' => 'sync_modify_sql',
		];
	}

/* Les styles applicables à un domaine sont définis dans /config.php
$styles = array (
	'domain.com' => 'style',
);
*/
	function user_setup($vars) {
		global $styles;
		$style_id = $vars['style_id'];

		// Force le style s'il est l'un des champs du domaine défini dans /config.php : EX: style.domaine.ext
		preg_match('/([a-z]+)\.[a-z]+/i', getenv('SCRIPT_NAME'), $scripts);
		$this->script = $scripts[1];
		$this->style_name = @$styles[getenv('SERVER_NAME')];
		$sql = 'SELECT * FROM '.STYLES_TABLE.' WHERE style_name = "'.$this->style_name.'"';
		$result = $this->db->sql_query ($sql);
		$row = $this->db->sql_fetchrow ($result);
		$this->db->sql_freeresult ($result);
		if ($row) {
			$style_id = $row ['style_id']; // Force le style PhpBB
			$this->template->assign_var ('GEO_STYLE', $this->style_name);
		}
		$vars['style_id'] = $style_id;
	}

/* Applique une vue particulière aux posts d'un forum si le descripteur contient:
	<page view="point" />
*/
	function force_view($vars) {
		if (!request_var('view', '')) {
			$sql = 'SELECT forum_desc FROM '.FORUMS_TABLE.' WHERE forum_id = '.$vars['forum_id'];
			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			preg_match('/\[view=([a-z]+)\]/i', html_entity_decode ($row['forum_desc']), $views);
			$this->request->overwrite ('view', @$views[1]);
		}

		// Assigne au body une classe dépendant de la vue
		$this->template->assign_var ('BODY_CLASS', 'view-'.request_var('view', ''));
	}

	// Récrit les infos du header
	function page_header($vars) {
		global $config, $config_locale;
		$config['sitename'] =
			@$config_locale['sitename']
			? $config_locale['sitename']
			: ucfirst (getenv('HTTP_HOST'));
		if (isset ($config_locale['site_desc']))
			$config['site_desc'] = $config_locale['site_desc'];
	}

	function page_footer($vars) {
//*DCMM*/{ob_start();var_dump($this->template);$d=ob_get_clean();echo'template = '.$d;} // VISUALISATION VARIABLES TEMPLATE

		// Liste les paramètres view potentiels
		$url = parse_url (getenv('REQUEST_URI'));
		parse_str (html_entity_decode (@$url['query']), $urlArgs);
		$request = str_replace ('body', 'forum', request_var('view', 'forum')); // Se ramène à forum

		foreach (glob ('styles/'.$this->user->style['style_name'].'/theme/view/*.gif') AS $icon) {
			$nom = pathinfo ($icon, PATHINFO_FILENAME); // Y compris forum
			$urlArgs['view'] = $nom; // Force l'argument
			$template = '/template/'.$this->script.'_'.str_replace ('forum', 'body', $nom).'.html';
			if ($request != $nom && (
					file_exists ('styles/'.$this->user->style['style_name'].$template) ||
					file_exists ('styles/'.$this->user->style['style_parent_tree'].$template))
			)
				$this->template->assign_block_vars('potviews', array(
					'NOM' => $nom,
					'URL'  => $url['path'].'?'.http_build_query ($urlArgs),
					'ICONE' => $icon,
					'TITRE' => str_replace ('print', 'imprimable', $nom)
				));
		}

		$nf = $this->script.'_'.request_var('view', 'body').'.'.request_var('format', 'html');
		foreach ([
			'styles/'.@$this->style_name,
			$this->ext_dir.'styles/all', // En priorité les templates de l'extension
		] AS $d)
			if (file_exists ($d.'/template/'.$nf))
				$this->template->set_filenames ([
					'body' => $nf
				]);
	}

	// Exclusion de certains forums pour certains domaines d'acccès
	function sync_modify_sql($vars) {
		if ($style_name = @$styles [getenv('SERVER_NAME')])
			$sql_array['WHERE'][] =  "forum_desc NOT LIKE '%[exclude=$style_name]%'";
	}
}