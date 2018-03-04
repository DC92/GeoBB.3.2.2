<?php
/**
 *
 * @package BarMenu
 * @copyright (c) 2016 Dominique Cavailhez
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace Dominique92\BarMenu\event;

if (!defined('IN_PHPBB'))
{
	exit;
}

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	public function __construct(
		\phpbb\db\driver\driver_interface $db,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\extension\manager $extension_manager
	) {
		$this->db = $db;
		$this->template = $template;
		$this->user = $user;
		$this->extension_manager = $extension_manager;

		// Recherche du répertoire de l'extension
		$ns = explode ('\\', __NAMESPACE__);

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
	static public function getSubscribedEvents() {
		return [
			'core.page_footer' => 'page_footer',
		];
	}

	function page_footer($vars) {
		$sql = "
			SELECT p.forum_id AS category_id,
				p.forum_name AS category_name,
				f.forum_id AS first_forum_id,
				f.forum_desc
			FROM ".FORUMS_TABLE." AS f
			JOIN ".FORUMS_TABLE." AS p ON p.forum_id = f.parent_id
			WHERE f.forum_type = ".FORUM_POST."
				AND p.forum_type = ".FORUM_CAT."
				AND p.parent_id = 0
			ORDER BY f.left_id
		";
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
			if (!@$cat[$row['category_id']]++) // Seulement une fois par catégorie
				$this->template->assign_block_vars (
					'geo_categories',
					array_change_key_case (
						preg_replace ('/s( |$)/i', '$1', $row), // Enlève les s en fin de mot
						CASE_UPPER
					)
				);
		$this->db->sql_freeresult($result);
	}
}