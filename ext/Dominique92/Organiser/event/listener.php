<?php
/**
 *
 * @package Organiser
 * @copyright (c) 2016 Dominique Cavailhez
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace Dominique92\Organiser\event;

if (!defined('IN_PHPBB'))
{
	exit;
}

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\request\request_interface $request,
		\phpbb\template\template $template,
		$root_path
	) {
		$this->config = $config;
		$this->db = $db;
		$this->request = $request;
		$this->template = $template;
		$this->root_path = $root_path;
	}

	// Liste des hooks et des fonctions associées
	static public function getSubscribedEvents() {
		return [
			'core.viewtopic_before_f_read_check' => 'viewtopic_before_f_read_check',
			'core.viewtopic_post_rowset_data' => 'viewtopic_post_rowset_data',
			'core.viewtopic_modify_post_data' => 'viewtopic_modify_post_data',
			'core.viewtopic_modify_post_row' => 'viewtopic_modify_post_row',
			'core.modify_posting_auth' => 'modify_posting_auth',
			'core.posting_modify_submit_post_after' => 'posting_modify_submit_post_after',
		];
	}

	public function viewtopic_before_f_read_check($vars) { // ligne 370
		// Supprime la pagination
		$this->config['posts_per_page'] = 1000;
	}

	// Appelé lors de la première passe sur les données des posts qui lit les données SQL de phpbb-posts
	function viewtopic_post_rowset_data($vars) {
		// Mémorise les données SQL du post pour traitement plus loin (viewtopic procède en 2 fois)
		$this->post_data [$vars['row']['post_id']] = $vars['row'];
	}

	function viewtopic_modify_post_data($vars) {
		$this->template->assign_vars ([
			'TOPIC_FIRST_POST_ID' => $vars['topic_data']['topic_first_post_id'] // Assigne le n° du premier post au template
		]);

		// Trie les posts par leur n°
		$post_list = $vars['post_list'];
		foreach ($post_list AS $k=>$p)
			if (isset ($this->post_data [$p]))
				$new_post_list [$p + $this->post_data [$p]['sort']] = $p;
		ksort($new_post_list);
		$vars['post_list'] = array_values ($new_post_list);

		// Permutation des posts
		$order_sorted = $order = @$this->request->get_super_global()['o'];
		if ($order) {
			sort ($order_sorted);
			foreach ($order AS $k=>$p)
				$this->db->sql_query('UPDATE '.POSTS_TABLE.' SET sort = '.($order_sorted[$k] - $p).' WHERE post_id = '.$p);

			// On enlève les paramètres de l'URL et on recharge
			header('Location: viewtopic.php?t='.request_var('t', 0));
			exit;
		}
	}

	function viewtopic_modify_post_row($vars) {
		$row = $vars['row'];
		$post_row = $vars['post_row'];

		// Détermine si le titre du post est une réponse
		if (!strncasecmp ($row['post_subject'], 're:', 3))
			$post_row['RE'] = substr ($row['post_subject'], 3);

		$vars['post_row'] = $post_row;
	}

	function modify_posting_auth($vars) {
		require_once($this->root_path . 'includes/functions_admin.php');

		// Popule le sélecteur de forum
		// TODO DCMM ça n'a rien à faire là. A déplacer dans un autre plugin ?
		if ($view = request_var('view', '')) {
			$sql = "SELECT forum_id, forum_name, parent_id, forum_type, forum_flags, forum_options, left_id, right_id, forum_desc
				FROM ".FORUMS_TABLE."
				WHERE forum_desc LIKE '%[view=$view]%'
				ORDER BY left_id ASC";
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
				$forum_list [] = '<option value="' . $row['forum_id'] . '"' .($row['forum_id'] == $vars['forum_id'] ? ' selected="selected"' : ''). '>' . $row['forum_name'] . '</option>';
			$this->db->sql_freeresult($result);

			if (isset ($forum_list))
				$this->template->assign_var ('S_FORUM_SELECT', implode ('', $forum_list));
		}

		// Assigne le nouveau forum pour la création
		$vars['forum_id'] = request_var('to_forum_id', $vars['forum_id']);

		// Le bouge
		if ($vars['mode'] == 'edit' && // S'il existe déjà !
			$vars['forum_id'] != $vars['forum_id'])
			move_topics([$vars['post_id']], $vars['forum_id']);
	}

	// Tri des attachements
	function posting_modify_submit_post_after($vars) {
		if ($vars['post_data']['post_id']) {
			// Get SQL attachment data
			$sql = 'SELECT * FROM '.ATTACHMENTS_TABLE.' WHERE post_msg_id = '.$vars['post_data']['post_id'].' ORDER BY attach_id DESC';
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result)) {
				$sqln[] = $r = $row['attach_id'];
				unset($row['attach_id']);
				$sqla[$r] = $row;
			}
			$this->db->sql_freeresult($result);

			// POST attachment data
			$ads = $this->request->variable('attachment_data', array(0 => array('' => '')), true, \phpbb\request\request_interface::POST);
			foreach (array_values($ads) AS $k=>$v) {
				$sql =
					'UPDATE '.ATTACHMENTS_TABLE.
					' SET ' .$this->db->sql_build_array('UPDATE', $sqla[$v['attach_id']]).
					' WHERE attach_id = '.$sqln[$k];
				$this->db->sql_query($sql);
			}
		}
	}
}