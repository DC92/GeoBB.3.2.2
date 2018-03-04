<?php
/**
 *
 * @package News
 * @copyright (c) 2016 Dominique Cavailhez
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace Dominique92\News\event;

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

		// Inclue les fichiers langages de cette extension
		$this->user->add_lang_ext($ns[0].'/'.$ns[1], 'common');
	}

	// Liste des hooks et des fonctions associées
	static public function getSubscribedEvents () {
		return [
			'core.display_forums_modify_row' => 'display_forums_modify_row',
			'core.index_modify_page_title' => 'index_modify_page_title',
		];
	}

	// Ajoute un bouton créer un point en face de la liste des forums
	function display_forums_modify_row ($vars) {
		global $auth;
		$row = $vars['row'];

		if ($auth->acl_get('f_post', $row['forum_id']) &&
			$row['forum_type'] == FORUM_POST)
			$row['forum_name'] .=
				' &nbsp; '.
				'<a href="posting.php?mode=post&f='.$row['forum_id'].'" title="Créer un nouveau sujet '.$row['forum_name'].'">'.
					'<img style="position:relative;top:4px" src="adm/images/file_new.gif" />'.
				'</a>';

		$vars['row'] = $row;
	}

	// Affiche les post les plus récents sur la page d'accueil
	function index_modify_page_title ($vars) {
		global $auth; // DCMM intégrer aux variables du listener ($this->auth)

		$nouvelles = request_var ('nouvelles', 20);
		$this->template->assign_var ('PLUS_NOUVELLES', $nouvelles * 2);

		$sql = "
			SELECT t.topic_id, topic_title,
				t.forum_id, forum_name, forum_image,
				topic_first_post_id, post_id, post_attachment, topic_posts_approved,
				username, poster_id, post_time, geo_massif
			FROM	 ".TOPICS_TABLE." AS t
				JOIN ".FORUMS_TABLE." AS f USING (forum_id)
				JOIN ".POSTS_TABLE ." AS p ON (p.post_id = t.topic_last_post_id)
				JOIN ".USERS_TABLE."  AS u ON (p.poster_id = u.user_id)
			WHERE post_visibility = ".ITEM_APPROVED."
			ORDER BY post_time DESC
			LIMIT ".$nouvelles;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
			if ($auth->acl_get('f_read', $row['forum_id'])) {
				$row ['topic_comments'] = $row['topic_posts_approved'] - 1;
				$row ['post_time'] = $this->user->format_date ($row['post_time']);
				$row ['geo_massif'] = str_replace ('~', '', $row ['geo_massif']);
				$this->template->assign_block_vars('nouvelles', array_change_key_case ($row, CASE_UPPER));
			}
		$this->db->sql_freeresult($result);

		// Affiche un message de bienvenu dépendant du style pour ceux qui ne sont pas connectés
		// Le texte de ces messages sont dans les posts dont le titre est !style
		$sql = "SELECT post_text,bbcode_uid,bbcode_bitfield FROM ".POSTS_TABLE." WHERE post_subject LIKE '!{$this->user->style['style_name']}'";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->template->assign_var ('GEO_PRESENTATION', generate_text_for_display($row['post_text'], $row['bbcode_uid'], $row['bbcode_bitfield'], OPTION_FLAG_BBCODE, true));
		$this->db->sql_freeresult($result);
	}
}