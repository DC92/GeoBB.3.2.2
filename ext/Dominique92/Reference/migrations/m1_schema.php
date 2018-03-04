<?php
/**
*
* External im^ported database management extension for the phpBB Forum Software package.
*
* @copyright (c) 2016 Dominique Cavailhez
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace Dominique92\Reference\migrations;

/**
 * Migration stage 1: Schema changes
 */
class m1_schema extends \phpbb\db\migration\migration
{
	/**
	 * Check if this migration is effectively installed
	 *
	 * @return bool True if this migration is installed, False if this migration is not installed
	 * @access public
	 */
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists('geo_reference');
	}

	/**
	 * Add the geo_reference table
	 *
	 * @return array Array of table schema
	 * @access public
	 */
	public function update_schema()
	{
		return array(
			'add_tables' => array(
				'geo_reference' => array(
					'COLUMNS' => array(
						'post_subject'	=> array('XSTEXT_UNI', '', 'true_sort'),
						'forum_id'	    => array('UINT', 0),
						'topic_id'	    => array('UINT', 0),
						'geom'          => array('TEXT', null),
						'url'           => array('VCHAR:255', ''),
						'last_update'   => array('TIMESTAMP', 0),
					),
					'PRIMARY_KEY' => 'url',
				),
				'geo_wric' => array(
					'COLUMNS' => array(
						'id'               => array('UINT', 0),
						'texte'            => array('MTEXT_UNI', ''),
						'date'             => array('TIMESTAMP', 0),
						'photo'            => array('VCHAR:255', null),
						'date_photo'       => array('TIMESTAMP', 0),
						'auteur'           => array('VCHAR:255', null),
						'url'              => array('VCHAR:255', null),
						'wric_last_update' => array('TIMESTAMP', 0),
					),
					'PRIMARY_KEY' => 'id',
				),
			),
		);
	}

	/**
	 * Drop the geo_reference table
	 *
	 * @return array Array of table schema
	 * @access public
	 */
	public function revert_schema()
	{
		return array(
			'drop_tables' => array(
				'geo_reference',
				'geo_wric',
			),
		);
	}
}
