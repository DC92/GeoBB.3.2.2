<?php
/**
 *
 * @package ResizeImg
 * @copyright (c) 2016 Dominique Cavailhez
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 * Addon to download/file.php?id=<ID>
 * Import external images where the initial URL is in SQL phpbb-attachments.real_filename
 * Resize to param s=<MAX PIXELS>
 */

namespace Dominique92\ResizeImg\event;

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
	}

	// Liste des hooks et des fonctions associées
	static public function getSubscribedEvents() {
		return [
			'core.viewtopic_post_rowset_data' => 'viewtopic_post_rowset_data',
			'core.viewtopic_modify_post_data' => 'viewtopic_modify_post_data',
			'core.viewtopic_modify_post_row' => 'viewtopic_modify_post_row',
			'core.parse_attachments_modify_template_data' => 'parse_attachments_modify_template_data',
			'core.download_file_send_to_browser_before' => 'download_file_send_to_browser_before',
		];
	}

	// Appelé lors de la première passe sur les données des posts qui lit les données SQL de phpbb-posts
	function viewtopic_post_rowset_data($vars) {
		// Mémorise les données SQL du post pour traitement plus loin
		$this->post_data [$vars['row']['post_id']] = $vars['row'];
	}

	function viewtopic_modify_post_data($vars) { // ligne 1576
		$this->attachments = $vars['attachments'];
	}

	// Insère des miniatures des liens.jpg insérés dans les messages
	function viewtopic_modify_post_row($vars) { // ligne 2006
		global $db;
		$post_row = $vars['post_row'];
		preg_match_all('/href="(http[^"]*\.(jpe?g|png))"[^>]*>([^<]*\.(jpe?g|png))<\/a>/i', $post_row['MESSAGE'], $imgs); // Récupère les urls d'images

		foreach ($imgs[1] AS $k=>$href) {
			$sql_rch = "SELECT * FROM ".ATTACHMENTS_TABLE." WHERE real_filename = '".addslashes($href)."'";
			$result = $this->db->sql_query_limit($sql_rch, 1);
			$r = $this->db->sql_fetchrow($result);
			if(!$r) { // L'image n'est pas dans la base
				$sql_ary = array(
					'physical_filename'	=> $href,
					'attach_comment'	=> $href,
					'real_filename'		=> $href,
					'extension'			=> 'jpg',
					'mimetype'			=> 'image/jpeg',
					'filesize'			=> 0,
					'filetime'			=> time(),
					'thumbnail'			=> 0,
					'is_orphan'			=> 0,
					'in_message'		=> 0,
					'post_msg_id'		=> $vars['row']['post_id'],
					'topic_id'			=> $vars['row']['topic_id'],
					'poster_id'			=> $vars['poster_id'],
				);
				$db->sql_query('INSERT INTO ' . ATTACHMENTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary));
				$result = $this->db->sql_query_limit($sql_rch, 1);
				$r = $this->db->sql_fetchrow($result);
			}
			
			$post_row['MESSAGE'] = str_replace (
				$href.'">'.$imgs[3][$k].'<',
				$href.'"><img title="'.$href.'" alt="'.$href.'" style="border:5px solid #F3E358" src="download/file.php?id='.$r['attach_id'].'&s=200&'.time().'"><',
				$post_row['MESSAGE']
			);
		}
		$vars['post_row'] = $post_row;
	}

	function parse_attachments_modify_template_data($vars) {
		if (@$this->attachments) {
			$post_id = $vars['attachment']['post_msg_id'];

			// Assigne les valeurs au template
			$this->block_array = $vars['block_array'];
			$this->block_array['TEXT_SIZE'] = strlen (@$this->post_data[$post_id]['post_text']) * count($this->attachments[$post_id]);
			$this->block_array['DATE'] = str_replace (' 00:00', '', $this->user->format_date($vars['attachment']['filetime']));
			$this->block_array['AUTEUR'] = $vars['row']['user_sig']; // GEO-TODO DCMM Retrouver le nom du "poster_id" : $vars['attachment']['poster_id'] ??
			$this->block_array['EXIF'] = $vars['attachment']['exif'];
			foreach ($vars['attachment'] AS $k=>$v)
				$this->block_array[strtoupper($k)] = $v;
			$vars['block_array'] = $this->block_array;

			// Ceci va assigner un template à {postrow.attachment.DISPLAY_ATTACHMENT}
			$nf = 'viewtopic_'.request_var('view', 'body').'_photo.html';
			if (file_exists ($this->root_path.'styles/'.$this->user->style['style_name'].'/template/'.$nf))
				$this->template->set_filenames ([
					'attachment_tpl' => $nf
				]);
		}
	}

	function download_file_send_to_browser_before($vars) {
		$attachment = $vars['attachment'];
		if (!is_dir ('../cache/geo/'))
			mkdir ('../cache/geo/');

		// Images externes
		$purl = parse_url ($attachment ['real_filename']);
		if (isset ($purl['host'])) { // le fichier est distant
			$local = '../cache/geo/'.str_replace ('/', '-', $purl['path']);
			if (!file_exists ($local) || !filesize ($local)) {
				// Recuperation du contenu
				$url_cache = file_get_contents ($attachment['real_filename']);

				if (ord ($url_cache) == 0xFF) // Si c'est une image jpeg
					file_put_contents ($local, $url_cache); // Ecrit le fichier
				else { // Message d'erreur sinon
					$nbcligne = 40;
					$cs = [];
					if (!$url_cache)
						$err_msg = $user->lang('FILE_GET_CONTENTS_ERROR', $attachment['real_filename']);
					foreach (explode ("\n", strip_tags ($err_msg)) AS $v)
						if ($v)
							$cs = array_merge ($cs, str_split (strip_tags ($v), $nbcligne));
					$im = imagecreate  ($nbcligne * 7 + 10, 12 * count ($cs) + 8);
					ImageColorAllocate ($im, 0, 0, 200);
					foreach ($cs AS $k => $v)
						ImageString ($im, 3, 5, 3 + 12 * $k, $v, ImageColorAllocate ($im, 255, 255, 255)); 
					imagejpeg ($im, $local);
					ImageDestroy ($im); 
				}
			}
			$attachment ['physical_filename'] = $local;
		}
		else if (is_file('../'.$attachment['real_filename'])) // Fichier relatif à la racine du site
			$attachment ['physical_filename'] = '../'.$attachment ['real_filename']; // script = download/file.php

		if ($exif = @exif_read_data ('../files/'.$attachment['physical_filename'])) {
			$fls = explode ('/', @$exif ['FocalLength']);
			if (count ($fls) == 2)
				$info[] = round($fls[0]/$fls[1]).'mm';

			$aps = explode ('/', @$exif ['FNumber']);
			if (count ($aps) == 2)
				$info[] = 'f/'.round($aps[0]/$aps[1], 1).'';

			$exs = explode ('/', @$exif ['ExposureTime']);
			if (count ($exs) == 2)
				$info[] = '1/'.round($exs[1]/$exs[0]).'s';

			if (@$exif['ISOSpeedRatings'])
				$info[] = $exif['ISOSpeedRatings'].'ASA';

			if (@$exif ['Model']) {
				if (@$exif ['Make'] &&
					strpos ($exif ['Model'], $exif ['Make']) === false)
					$info[] = $exif ['Make'];
				$info[] = $exif ['Model'];
			}

			$this->db->sql_query (implode (' ', [
				'UPDATE '.ATTACHMENTS_TABLE,
				'SET exif = "'.implode (' ', $info ?: ['~']).'",',
					'filetime = '.(strtotime(@$exif['DateTimeOriginal']) ?: @$exif['FileDateTime'] ?: @$attachment['filetime']),
				'WHERE attach_id = '.$attachment['attach_id']
			]));
		}

		// Reduction de la taille de l'image
		if ($max_size = request_var('s', 0)) {
			$img_size = @getimagesize ('../files/'.$attachment['physical_filename']);
			$isx = $img_size [0]; $isy = $img_size [1]; 
			$reduction = max ($isx / $max_size, $isy / $max_size);
			if ($reduction > 1) { // Il faut reduire l'image
				$pn = pathinfo ($attachment['physical_filename']);
				$temporaire = '../cache/geo/'.$pn['basename'].'.'.$max_size.@$pn['extension'];

				// Si le fichier temporaire n'existe pas, il faut le creer
				if (!is_file ($temporaire)); {
					$mimetype = explode('/',$attachment['mimetype']);

					// Get source image
					$imgcreate = 'imagecreatefrom'.$mimetype[1]; // imagecreatefromjpeg / imagecreatefrompng / imagecreatefromgif
					$image_src = $imgcreate ('../files/'.$attachment['physical_filename']);

					// Detect orientation
					$angle = [
						3 => 180,
						6 => -90,
						8 =>  90,
					];
					$a = @$angle [$exif ['Orientation']];
					if ($a)
						$image_src = imagerotate ($image_src, $a, 0);
					if (abs ($a) == 90) {
						$tmp = $isx;
						$isx = $isy;
						$isy = $tmp;
					}

					// Build destination image
					$image_dest = imagecreatetruecolor ($isx / $reduction, $isy / $reduction); 
					imagecopyresampled ($image_dest, $image_src, 0,0, 0,0, $isx / $reduction, $isy / $reduction, $isx, $isy);

					// Convert image
					$imgconv = 'image'.$mimetype[1]; // imagejpeg / imagepng / imagegif
					$imgconv ($image_dest, $temporaire); 

					// Cleanup
					imagedestroy ($image_dest); 
					imagedestroy ($image_src);
				}
				$attachment['physical_filename'] = $temporaire;
			}
		}

		$vars['attachment'] = $attachment;
	}
}