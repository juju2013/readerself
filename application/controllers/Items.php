<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Items extends CI_Controller {
	function __construct() {
		parent::__construct();
	}
	public function get($mode, $id = FALSE) {
		if(!$this->axipi_session->userdata('mbr_id') && $mode != 'public_profile') {
			redirect(base_url());
		}

		$modes = array('all', 'priority', 'geolocation', 'audio', 'video', 'starred', 'shared', 'nofolder', 'folder', 'feed', 'category', 'author', 'search', 'cloud', 'public_profile');
		$clouds = array('tags', 'authors');

		$content = array();
		$introduction_direction = false;
		$introduction_title = false;

		$is_member = FALSE;
		if($mode == 'public_profile') {
			$query = $this->db->query('SELECT mbr.* FROM '.$this->db->dbprefix('members').' AS mbr WHERE mbr.mbr_nickname = ? GROUP BY mbr.mbr_id', array($id));
			if($query->num_rows() > 0) {
				$is_member = $query->row();
			}
		}

		$is_folder = FALSE;
		if($mode == 'folder') {
			$query = $this->db->query('SELECT flr.* FROM '.$this->db->dbprefix('folders').' AS flr WHERE flr.mbr_id = ? AND flr.flr_id = ? GROUP BY flr.flr_id', array($this->member->mbr_id, $id));
			if($query->num_rows() > 0) {
				$is_folder = $query->row();
				$is_folder->subscriptions = $this->db->query('SELECT COUNT(DISTINCT(sub.sub_id)) AS count FROM '.$this->db->dbprefix('subscriptions').' AS sub WHERE sub.mbr_id = ? AND sub.flr_id = ?', array($this->member->mbr_id, $id))->row()->count;

				$is_folder->shared_items = $this->db->query('SELECT COUNT(DISTINCT(shr.shr_id)) AS count FROM '.$this->db->dbprefix('share').' AS shr LEFT JOIN '.$this->db->dbprefix('items').' AS itm ON itm.itm_id = shr.itm_id WHERE shr.mbr_id = ? AND itm.fed_id IN ( SELECT sub.fed_id FROM '.$this->db->dbprefix('subscriptions').' AS sub WHERE sub.fed_id = itm.fed_id AND sub.mbr_id = ? AND sub.flr_id = ? )', array($this->member->mbr_id, $this->member->mbr_id, $id))->row()->count;

				$is_folder->starred_items = $this->db->query('SELECT COUNT(DISTINCT(fav.fav_id)) AS count FROM '.$this->db->dbprefix('favorites').' AS fav LEFT JOIN '.$this->db->dbprefix('items').' AS itm ON itm.itm_id = fav.itm_id WHERE fav.mbr_id = ? AND itm.fed_id IN ( SELECT sub.fed_id FROM '.$this->db->dbprefix('subscriptions').' AS sub WHERE sub.fed_id = itm.fed_id AND sub.mbr_id = ? AND sub.flr_id = ? )', array($this->member->mbr_id, $this->member->mbr_id, $id))->row()->count;
			}
		}

		$is_feed = FALSE;
		if($mode == 'feed') {
			$query = $this->db->query('SELECT sub.*, fed.fed_host, flr.flr_title, fed.fed_url, fed.fed_direction, fed.fed_title, fed.fed_description FROM '.$this->db->dbprefix('subscriptions').' AS sub LEFT JOIN '.$this->db->dbprefix('feeds').' AS fed ON fed.fed_id = sub.fed_id LEFT JOIN '.$this->db->dbprefix('folders').' AS flr ON flr.flr_id = sub.flr_id WHERE sub.mbr_id = ? AND sub.fed_id = ? GROUP BY sub.sub_id', array($this->member->mbr_id, $id));
			if($query->num_rows() > 0) {
				$is_feed = $query->row();
				$is_feed->subscribe = 1;
			} else {
				$query = $this->db->query('SELECT fed.*, fed.fed_direction, fed.fed_direction AS sub_direction FROM '.$this->db->dbprefix('feeds').' AS fed WHERE fed.fed_id = ? GROUP BY fed.fed_id', array($id));
				if($query->num_rows() > 0) {
					$is_feed = $query->row();
					$is_feed->subscribe = 0;
				}
			}
		}

		$is_author = FALSE;
		if($mode == 'author') {
			$query = $this->db->query('SELECT auh.* FROM '.$this->db->dbprefix('authors').' AS auh WHERE auh.auh_id = ? GROUP BY auh.auh_id', array($id));
			if($query->num_rows() > 0) {
				$is_author = $query->row();
				$this->readerself_library->clean_authors('title', $is_author->auh_title);
			}
		}

		$is_category = FALSE;
		if($mode == 'category') {
			$query = $this->db->query('SELECT tag.* FROM '.$this->db->dbprefix('tags').' AS tag WHERE tag.tag_id = ? GROUP BY tag.tag_id', array($id));
			if($query->num_rows() > 0) {
				$is_category = $query->row();
				$this->readerself_library->clean_categories('title', $is_category->tag_title);
			}
		}

		$this->readerself_library->set_template('_json');
		$this->readerself_library->set_content_type('application/json');

		if($this->input->is_ajax_request() && in_array($mode, $modes)) {

			$this->axipi_session->set_userdata('items-mode', $mode);
			$this->axipi_session->set_userdata('items-id', $id);

			$content['nav'] = array();
			$content['nav']['items_refresh'] = true;
			$content['nav']['items_mode'] = true;
			$content['nav']['items_display'] = true;
			$content['nav']['items_read'] = true;
			$content['nav']['item_up'] = true;
			$content['nav']['item_down'] = true;

			if($mode == 'cloud' && in_array($id, $clouds)) {
				$content['result_type'] = 'cloud';

				$content['nav']['items_mode'] = false;
				$content['nav']['items_display'] = false;
				$content['nav']['items_read'] = false;
				$content['nav']['item_up'] = false;
				$content['nav']['item_down'] = false;

				$date_ref = date('Y-m-d H:i:s', time() - 3600 * 24 * 30);

				if($id == 'tags') {
					$introduction_title = '<i class="material-icons md-18">label</i>'.$this->lang->line('tags').'*';
				}
				if($id == 'authors') {
					$introduction_title = '<i class="material-icons md-18">people</i>'.$this->lang->line('authors').'*';
				}

				$items = array();

				$legend = array();
				$values = array();
				if($id == 'tags') {
					$this->readerself_library->clean_categories('date', $date_ref);

					$query = $this->db->query('SELECT LOWER(tag.tag_title) AS ref, tag.tag_id AS id, COUNT(DISTINCT(tag_itm.itm_id)) AS count FROM '.$this->db->dbprefix('items').' AS itm LEFT JOIN '.$this->db->dbprefix('subscriptions').' AS sub ON sub.fed_id = itm.fed_id LEFT JOIN '.$this->db->dbprefix('tags_items').' AS tag_itm ON tag_itm.itm_id = itm.itm_id LEFT JOIN '.$this->db->dbprefix('tags').' AS tag ON tag.tag_id = tag_itm.tag_id WHERE itm.itm_date >= ? AND sub.mbr_id = ? GROUP BY ref ORDER BY count DESC LIMIT 0,100', array($date_ref, $this->member->mbr_id));
				}
				if($id == 'authors') {
					$this->readerself_library->clean_authors('date', $date_ref);

					$query = $this->db->query('SELECT LOWER(auh.auh_title) AS ref, itm.auh_id AS id, COUNT(DISTINCT(itm.itm_id)) AS count FROM '.$this->db->dbprefix('items').' AS itm LEFT JOIN '.$this->db->dbprefix('subscriptions').' AS sub ON sub.fed_id = itm.fed_id LEFT JOIN '.$this->db->dbprefix('authors').' AS auh ON auh.auh_id = itm.auh_id WHERE itm.auh_id IS NOT NULL AND itm.itm_datecreated >= ? AND sub.mbr_id = ? GROUP BY ref ORDER BY count DESC LIMIT 0,100', array($date_ref, $this->member->mbr_id));
				}
				if($query->num_rows() > 0) {
					if($id == 'tags') {
						$exclude = array('non classé', 'uncategorized', 'actualités : informatique', 'actualités : internet', 'actualités : télécoms', 'actualités : it management');
					}
					if($id == 'authors') {
						$exclude = array('webmaster');
					}
					$max = false;
					foreach($query->result() as $row) {
						if(!in_array($row->ref, $exclude)) {
							if(!$max) {
								$max = $row->count;
							}
							$items[$row->ref] = array('count'=>$row->count, 'id'=>$row->id);
						}
					}
					ksort($items);
					$content['cloud'] = '<div class="mdl-card mdl-shadow--2dp mdl-color--'.$this->config->item('material-design/colors/background/card').' mdl-cell mdl-cell--12-col" id="cloud">';
					$content['cloud'] .= '<div class="mdl-card__supporting-text mdl-color-text--'.$this->config->item('material-design/colors/text/content').'"><p>';
					foreach($items as $k => $v) {
						$percent = ($v['count'] * 100) / $max;
						$percent = $percent - ($percent % 10);
						$percent = intval($percent) + 100;
						if($id == 'tags') {
							$content['cloud'] .= '<a class="category mdl-color-text--'.$this->config->item('material-design/colors/text/link').'" data-cat_id="'.$v['id'].'" href="'.base_url().'items/get/category/'.$v['id'].'" style="font-size:'.$percent.'%;">'.$k.'</a> ';
						}
						if($id == 'authors') {
							$content['cloud'] .= '<a class="author mdl-color-text--'.$this->config->item('material-design/colors/text/link').'" data-auh_id="'.$v['id'].'" href="'.base_url().'items/get/author/'.$v['id'].'" style="font-size:'.$percent.'%;">'.$k.'</a> ';
						}
					}
					$content['cloud'] .= '</p><p>*'.$this->lang->line('last_30_days').'</p></div></div>';
				} else {
					$content['cloud'] = '';
				}

			} else {
				$content['result_type'] = 'items';

				$content['items'] = array();

				$where = array();
				$bindings = array();

				if($is_member) {
					$content['begin'] = $this->load->view('items_begin', array('is_member'=>$is_member, 'mode'=>$mode), TRUE);

					$where[] = 'itm.itm_id IN ( SELECT shr.itm_id FROM '.$this->db->dbprefix('share').' AS shr WHERE shr.itm_id = itm.itm_id AND shr.mbr_id = ? )';
					$bindings[] = $is_member->mbr_id;

					$where[] = 'itm.fed_id IN ( SELECT sub.fed_id FROM '.$this->db->dbprefix('subscriptions').' AS sub WHERE sub.fed_id = itm.fed_id AND sub.mbr_id = ? )';
					$bindings[] = $is_member->mbr_id;

				} else if($mode == 'priority') {
					$introduction_title = '<i class="material-icons md-18">announcement</i>'.$this->lang->line('priority_items').' (<span id="intro-load-priority-items"></span>)';
					$where[] = 'itm.fed_id IN ( SELECT sub.fed_id FROM '.$this->db->dbprefix('subscriptions').' AS sub WHERE sub.fed_id = itm.fed_id AND sub.mbr_id = ? AND sub.sub_priority = ? )';
					$bindings[] = $this->member->mbr_id;
					$bindings[] = 1;

				} else {
					$introduction_title = '<i class="material-icons md-18">public</i>'.$this->lang->line('all_items').' (<span id="intro-load-all-items"></span>)';
					if($is_feed) {
					} else {
						$where[] = 'itm.fed_id IN ( SELECT sub.fed_id FROM '.$this->db->dbprefix('subscriptions').' AS sub WHERE sub.fed_id = itm.fed_id AND sub.mbr_id = ? )';
						$bindings[] = $this->member->mbr_id;
					}
				}

				if($mode == 'geolocation') {
					$content['begin'] = $this->load->view('items_begin', array('mode'=>$mode), TRUE);

					$where[] = 'itm.itm_latitude IS NOT NULL';
					$where[] = 'itm.itm_longitude IS NOT NULL';
				}

				if($mode == 'audio') {
					$introduction_title = '<i class="icon icon-volume-up"></i>'.$this->lang->line('audio_items').' (<span id="intro-load-audio-items"></span>)';
					$where[] = 'enr.enr_type LIKE ?';
					$bindings[] = 'audio/%';
				}

				if($mode == 'video') {
					$introduction_title = '<i class="icon icon-youtube-play"></i>'.$this->lang->line('video_items').' (<span id="intro-load-video-items"></span>)';
					$where[] = '(enr.enr_type LIKE ? OR enr.enr_link LIKE ?)';
					$bindings[] = 'video/%';
					$bindings[] = 'https://www.youtube.com/%';
				}

				if($mode == 'starred') {
					$content['begin'] = $this->load->view('items_begin', array('mode'=>$mode), TRUE);

					$where[] = 'itm.itm_id IN ( SELECT fav.itm_id FROM '.$this->db->dbprefix('favorites').' AS fav WHERE fav.itm_id = itm.itm_id AND fav.mbr_id = ? )';
					$bindings[] = $this->member->mbr_id;

					$content['nav']['items_mode'] = false;
					$content['nav']['items_read'] = false;

				} else if($mode == 'shared') {
					$content['begin'] = $this->load->view('items_begin', array('mode'=>$mode), TRUE);

					$where[] = 'itm.itm_id IN ( SELECT shr.itm_id FROM '.$this->db->dbprefix('share').' AS shr WHERE shr.itm_id = itm.itm_id AND shr.mbr_id = ? )';
					$bindings[] = $this->member->mbr_id;

					$content['nav']['items_mode'] = false;
					$content['nav']['items_read'] = false;

				} else {
					if($mode == 'search') {
						$search = trim(urldecode($id));
						$words = explode(' ', $search);
						$where_or = array();
						$bindings_or = array();
						foreach($words as $word) {
							if(substr($word, 0, 1) == '@') {
								$where[] = 'itm.itm_date LIKE ?';
								$bindings[] = substr($word, 1).'%';
							} else {
								$where_or[] = 'itm.itm_title LIKE ?';
								$bindings_or[] = '%'.$word.'%';

								//$where_or[] = 'itm.itm_author LIKE ?';
								//$bindings_or[] = '%'.$word.'%';

								//$where_or[] = 'itm.itm_id IN ( SELECT cat.itm_id FROM '.$this->db->dbprefix('categories').' AS cat WHERE cat.cat_title LIKE ? )';
								//$bindings_or[] = '%'.$word.'%';
							}
						}
						if(count($where_or) > 0) {
							$where[] = '('.implode(' AND ', $where_or).')';
							$bindings = array_merge($bindings, $bindings_or);
						}
						$content['nav']['items_refresh'] = false;
						$content['nav']['items_mode'] = false;
						$content['nav']['items_read'] = false;

					} else if($this->input->get('items_mode') == 'unread_only') {
						$where[] = 'itm.itm_id NOT IN ( SELECT hst.itm_id FROM '.$this->db->dbprefix('history').' AS hst WHERE hst.itm_id = itm.itm_id AND hst.mbr_id = ? )';
						$bindings[] = $this->member->mbr_id;
					}
				}

				if($is_folder) {
					$content['begin'] = $this->load->view('items_begin', array('is_folder'=>$is_folder, 'mode'=>$mode), TRUE);

					$where[] = 'itm.fed_id IN ( SELECT sub.fed_id FROM '.$this->db->dbprefix('subscriptions').' AS sub WHERE sub.fed_id = itm.fed_id AND sub.flr_id = ? )';
					$bindings[] = $is_folder->flr_id;
				}

				if($is_feed) {
					$is_feed->categories = false;
					if($this->config->item('tags')) {
						$date_ref = date('Y-m-d H:i:s', time() - 3600 * 24 * 30);
						$categories = $this->db->query('SELECT cat.cat_title AS ref, cat.cat_id AS id, COUNT(DISTINCT(itm.itm_id)) AS nb FROM '.$this->db->dbprefix('items').' AS itm LEFT JOIN '.$this->db->dbprefix('feeds').' AS fed ON fed.fed_id = itm.fed_id LEFT JOIN '.$this->db->dbprefix('categories').' AS cat ON cat.itm_id = itm.itm_id WHERE cat.cat_id IS NOT NULL AND itm.itm_date >= ? AND fed.fed_id = ? GROUP BY ref ORDER BY nb DESC LIMIT 0,10', array($date_ref, $is_feed->fed_id))->result();
						if($categories) {
							$this->readerself_library->clean_categories('feed', $is_feed->fed_id);
						}

						$categories = $this->db->query('SELECT tag.tag_title AS ref, tag.tag_id AS id, COUNT(DISTINCT(tag_itm.itm_id)) AS nb FROM '.$this->db->dbprefix('tags').' AS tag LEFT JOIN '.$this->db->dbprefix('tags_items').' AS tag_itm ON tag_itm.tag_id = tag.tag_id LEFT JOIN '.$this->db->dbprefix('items').' AS itm ON itm.itm_id = tag_itm.itm_id WHERE itm.itm_date >= ? AND itm.fed_id = ? GROUP BY ref ORDER BY nb DESC LIMIT 0,10', array($date_ref, $is_feed->fed_id))->result();
						if($categories) {
							$is_feed->categories = array();
							foreach($categories as $cat) {
								if(substr($cat->ref, 0, 17) == 'foursquare:venue=') {
								} else {
									$is_feed->categories[] = '<a class="mdl-navigation__link mdl-color-text--'.$this->config->item('material-design/colors/text/card-title').' category" data-cat_id="'.$cat->id.'" href="'.base_url().'items/get/category/'.$cat->id.'"><i class="material-icons md-16">label</i>'.$cat->ref.'</a>';
								}
							}
						}
					}
					$content['begin'] = $this->load->view('items_begin', array('is_feed'=>$is_feed, 'mode'=>$mode), TRUE);

					$where[] = 'itm.fed_id = ?';
					$bindings[] = $is_feed->fed_id;
				}

				if($is_author) {
					$introduction_title = '<i class="material-icons md-18">person</i>'.$is_author->auh_title.' (<span id="intro-load-author-items">0</span>)';
					$where[] = 'itm.auh_id = ?';
					$bindings[] = $is_author->auh_id;
				}

				if($is_category) {
					$introduction_title = '<i class="material-icons md-18">label</i>'.$is_category->tag_title.' (<span id="intro-load-category-items">0</span>)';
					$where[] = 'itm.itm_id IN ( SELECT tag_itm.itm_id FROM '.$this->db->dbprefix('tags_items').' AS tag_itm WHERE tag_itm.tag_id = ? )';
					$bindings[] = $is_category->tag_id;
				}

				if($mode == 'nofolder') {
					$introduction_title = '<i class="icon icon-folder-close"></i><em>'.$this->lang->line('no_folder').'</em> (<span id="intro-load-nofolder-items">0</span>)';
					$where[] = 'itm.fed_id IN ( SELECT sub.fed_id FROM '.$this->db->dbprefix('subscriptions').' AS sub WHERE sub.fed_id = itm.fed_id AND sub.flr_id IS NULL )';
				}

				if($mode == 'search') {
					$sql = 'SELECT COUNT(DISTINCT(itm.itm_id)) AS global_total
					FROM '.$this->db->dbprefix('items').' AS itm
					WHERE '.implode(' AND ', $where);
					$query = $this->db->query($sql, $bindings);
					$content['total_global'] = intval($query->row()->global_total);
					$introduction_title = '<i class="icon icon-file-text-alt"></i>'.$search.' {<span id="intro-load-search-items">'.$content['total_global'].'</span>}';
				}

				if($this->input->get('items_display') == 'collapse') {
					$pagination_items = 30;
				} else {
					$pagination_items = 10;
				}

				$sql = 'SELECT itm.itm_id FROM '.$this->db->dbprefix('items').' AS itm ';
				if($mode == 'audio' || $mode == 'video') {
					$sql .= 'LEFT JOIN '.$this->db->dbprefix('enclosures').' AS enr ON enr.itm_id = itm.itm_id ';
				}
				$sql .= 'WHERE '.implode(' AND ', $where).'
				GROUP BY itm.itm_id
				ORDER BY itm.itm_date DESC';
				if($mode == 'starred') {
					$sql .= ' LIMIT '.intval($this->input->post('pagination')).','.$pagination_items;
				} else if($mode == 'shared') {
					$sql .= ' LIMIT '.intval($this->input->post('pagination')).','.$pagination_items;
				} else {
					if($mode == 'search') {
						$sql .= ' LIMIT '.intval($this->input->post('pagination')).','.$pagination_items;
					} else if($this->input->get('items_mode') == 'unread_only' && $this->input->get('items_display') == 'expand') {
						$sql .= ' LIMIT 0,'.$pagination_items;
					} else {
						$sql .= ' LIMIT '.intval($this->input->post('pagination')).','.$pagination_items;
					}
				}
				$query = $this->db->query($sql, $bindings);
				$content['total'] = $query->num_rows();

				if($query->num_rows() > 0) {
					$u = 0;
					foreach($query->result() as $itm) {
						$sql = 'SELECT itm.* FROM '.$this->db->dbprefix('items').' AS itm WHERE itm.itm_id = ? GROUP BY itm.itm_id';
						$itm = $this->db->query($sql, array($itm->itm_id))->row();

						if($itm->itm_author && !$itm->auh_id) {
							$itm->auh_id = $this->readerself_library->convert_author_title($itm->itm_author);
							$this->db->set('auh_id', $itm->auh_id);
							$this->db->set('itm_author', '');
							$this->db->where('itm_id', $itm->itm_id);
							$this->db->update('items');
						}
						if($itm->auh_id) {
							$sql = 'SELECT auh.* FROM '.$this->db->dbprefix('authors').' AS auh WHERE auh.auh_id = ? GROUP BY auh.auh_id';
							$itm->auh = $this->db->query($sql, array($itm->auh_id))->row();
						} else {
							$itm->auh = false;
						}

						$sql = 'SELECT fed.fed_link, fed.fed_host, sub.sub_id, sub.sub_priority AS priority, sub.sub_title, fed.fed_title, sub.sub_direction, fed.fed_direction, flr.flr_id, flr.flr_title, flr.flr_direction FROM '.$this->db->dbprefix('subscriptions').' AS sub LEFT JOIN '.$this->db->dbprefix('feeds').' AS fed ON fed.fed_id = sub.fed_id LEFT JOIN '.$this->db->dbprefix('folders').' AS flr ON flr.flr_id = sub.flr_id WHERE sub.fed_id = ? AND sub.mbr_id = ? GROUP BY sub.sub_id';
						$itm->case_member = 'you';
						if($is_member) {
							$itm->sub = $this->db->query($sql, array($itm->fed_id, $is_member->mbr_id))->row();
							$itm->case_member = 'public_profile';
						} else {
							$itm->sub = $this->db->query($sql, array($itm->fed_id, $this->member->mbr_id))->row();
						}

						$itm->shared_by = false;
						if($this->axipi_session->userdata('mbr_id') && $mode != 'public_profile') {
							$sql = 'SELECT mbr.mbr_nickname FROM '.$this->db->dbprefix('share').' AS shr LEFT JOIN '.$this->db->dbprefix('followers').' AS fws ON fws.fws_following = shr.mbr_id LEFT JOIN '.$this->db->dbprefix('members').' AS mbr ON mbr.mbr_id = fws.fws_following WHERE mbr.mbr_nickname IS NOT NULL AND fws.mbr_id = ? AND shr.itm_id = ? GROUP BY mbr.mbr_id ORDER BY mbr_nickname ASC';
							$shared_by = $this->db->query($sql, array($this->member->mbr_id, $itm->itm_id))->result();
							if($shared_by) {
								$itm->shared_by = array();
								foreach($shared_by as $shr) {
									$itm->shared_by[] = '<a href="'.base_url().'member/'.$shr->mbr_nickname.'">'.$shr->mbr_nickname.'</a>';
								}
							}
						}

						$itm->categories = false;
						if($this->config->item('tags')) {
							$categories = $this->db->query('SELECT cat.* FROM '.$this->db->dbprefix('categories').' AS cat WHERE cat.itm_id = ? GROUP BY cat.cat_id', array($itm->itm_id))->result();
							if($categories) {
								$this->readerself_library->clean_categories('item', $itm->itm_id);
							}

							$categories = $this->db->query('SELECT tag.* FROM '.$this->db->dbprefix('tags').' AS tag LEFT JOIN '.$this->db->dbprefix('tags_items').' AS tag_itm ON tag_itm.tag_id = tag.tag_id WHERE tag_itm.itm_id = ? GROUP BY tag.tag_id', array($itm->itm_id))->result();
							if($categories) {
								$itm->categories = array();
								foreach($categories as $cat) {
									if(substr($cat->tag_title, 0, 17) == 'foursquare:venue=') {
									} else {
										if($is_member) {
											$itm->categories[] = $cat->tag_title;
										} else {
											$itm->categories[] = '<a class="mdl-navigation__link mdl-color-text--'.$this->config->item('material-design/colors/text/card-title').' category" data-cat_id="'.$cat->tag_id.'" href="'.base_url().'items/get/category/'.$cat->tag_id.'"><i class="material-icons md-16">label</i>'.$cat->tag_title.'</a>';
										}
									}
								}
							}
						}

						$sql = 'SELECT enr.* FROM '.$this->db->dbprefix('enclosures').' AS enr WHERE enr.itm_id = ? GROUP BY enr.enr_id ORDER BY enr.enr_type ASC';
						$itm->enclosures = $this->db->query($sql, array($itm->itm_id))->result();

						if(!$is_member) {
							$sql = 'SELECT hst.* FROM '.$this->db->dbprefix('history').' AS hst WHERE hst.itm_id = ? AND hst.mbr_id = ? GROUP BY hst.hst_id';
							$query = $this->db->query($sql, array($itm->itm_id, $this->member->mbr_id));
							if($query->num_rows() > 0) {
								$itm->history = 'read';
							} else {
								$itm->history = 'unread';
							}

							if($this->config->item('starred_items')) {
								$sql = 'SELECT fav.* FROM '.$this->db->dbprefix('favorites').' AS fav WHERE fav.itm_id = ? AND fav.mbr_id = ? GROUP BY fav.fav_id';
								$query = $this->db->query($sql, array($itm->itm_id, $this->member->mbr_id));
								if($query->num_rows() > 0) {
									$itm->star = 1;
								} else {
									$itm->star = 0;
								}
							}

							if($this->config->item('shared_items')) {
								$sql = 'SELECT shr.* FROM '.$this->db->dbprefix('share').' AS shr WHERE shr.itm_id = ? AND shr.mbr_id = ? GROUP BY shr.shr_id';
								$query = $this->db->query($sql, array($itm->itm_id, $this->member->mbr_id));
								if($query->num_rows() > 0) {
									$itm->share = 1;
								} else {
									$itm->share = 0;
								}
							}
						} else {
							$itm->history = 'unread';
							$itm->star = 0;
							$itm->share = 0;
						}

						$itm->itm_date = $this->readerself_library->timezone_datetime($itm->itm_date);
						list($itm->explode_date, $itm->explode_time) = explode(' ', $itm->itm_date);

						$itm->itm_content = $this->readerself_library->prepare_content($itm->itm_content);

						$content['items'][$u] = array('itm_id' => $itm->itm_id, 'itm_content' => $this->load->view('item', array('itm'=>$itm, 'mode'=>$mode), TRUE));
						$u++;
					}
				} else {
					$lastcrawl = $this->db->query('SELECT crr.crr_datecreated FROM '.$this->db->dbprefix('crawler').' AS crr GROUP BY crr.crr_id ORDER BY crr.crr_id DESC LIMIT 0,1')->row();
					if($lastcrawl && $mode != 'public_profile') {
						$content['end'] = '<div class="mdl-card mdl-shadow--2dp mdl-color--'.$this->config->item('material-design/colors/background/card').' mdl-cell mdl-cell--12-col" id="last_crawl">';
						$content['end'] .= '</div>';
					} else if($mode == 'public_profile') {
						$content['end'] = '<div class="mdl-card mdl-shadow--2dp mdl-color--'.$this->config->item('material-design/colors/background/card').' mdl-cell mdl-cell--12-col"><div class="mdl-card__title mdl-color-text--'.$this->config->item('material-design/colors/text/card-title').'">';
						$content['end'] .= '<h1 class="mdl-card__title-text">'.$this->lang->line('no_more_items').'</h1>';
						$content['end'] .= '</div></div>';
					}
				}
			}

			if($introduction_title && isset($content['begin']) == 0) {
				if($introduction_direction) {
					$content['begin'] = '<article dir="'.$introduction_direction.'" id="introduction" class="title">';
				} else {
					$content['begin'] = '<div class="mdl-card mdl-shadow--2dp mdl-color--'.$this->config->item('material-design/colors/background/card').' mdl-cell mdl-cell--12-col"><div class="mdl-card__title mdl-color-text--'.$this->config->item('material-design/colors/text/card-title-highlight').' mdl-color--'.$this->config->item('material-design/colors/background/card-title-highlight').'">';
				}
				$content['begin'] .= '<h1 class="mdl-card__title-text">'.$introduction_title.'</h1>';
				$content['begin'] .= '</div></div>';
			}
		} else {
			$this->output->set_status_header(403);
		}
		$this->readerself_library->set_content($content);
	}
	public function read($age) {
		if(!$this->axipi_session->userdata('mbr_id')) {
			redirect(base_url());
		}

		$content = array();

		if($this->input->is_ajax_request()) {
			$this->readerself_library->set_template('_json');
			$this->readerself_library->set_content_type('application/json');

			if($this->axipi_session->userdata('items-mode')) {
				$is_folder = FALSE;
				if($this->axipi_session->userdata('items-mode') == 'folder') {
					$query = $this->db->query('SELECT flr.* FROM '.$this->db->dbprefix('folders').' AS flr WHERE flr.mbr_id = ? AND flr.flr_id = ? GROUP BY flr.flr_id', array($this->member->mbr_id, $this->axipi_session->userdata('items-id')));
					if($query->num_rows() > 0) {
						$is_folder = $query->row();
					}
				}

				$is_feed = FALSE;
				if($this->axipi_session->userdata('items-mode') == 'feed') {
					$query = $this->db->query('SELECT sub.*, fed.fed_title, fed.fed_direction FROM '.$this->db->dbprefix('subscriptions').' AS sub LEFT JOIN '.$this->db->dbprefix('feeds').' AS fed ON fed.fed_id = sub.fed_id WHERE sub.mbr_id = ? AND sub.fed_id = ? GROUP BY sub.sub_id', array($this->member->mbr_id, $this->axipi_session->userdata('items-id')));
					if($query->num_rows() > 0) {
						$is_feed = $query->row();
					}
				}

				$is_author = FALSE;
				if($this->axipi_session->userdata('items-mode') == 'author') {
					$query = $this->db->query('SELECT auh.* FROM '.$this->db->dbprefix('authors').' AS auh WHERE auh.auh_id = ? GROUP BY auh.auh_id', array($this->axipi_session->userdata('items-id')));
					if($query->num_rows() > 0) {
						$is_author = $query->row()->auh_id;
					}
				}

				$is_category = FALSE;
				if($this->axipi_session->userdata('items-mode') == 'category') {
					$query = $this->db->query('SELECT tag.* FROM '.$this->db->dbprefix('tags').' AS tag WHERE tag.tag_id = ? GROUP BY tag.tag_id', array($this->axipi_session->userdata('items-id')));
					if($query->num_rows() > 0) {
						$is_category = $query->row()->tag_id;
					}
				}

				$where = array();
				$bindings = array();

				$bindings[] = $this->member->mbr_id;
				$bindings[] = date('Y-m-d H:i:s');

				if($this->axipi_session->userdata('items-mode') == 'priority') {
					$where[] = 'itm.fed_id IN ( SELECT sub.fed_id FROM '.$this->db->dbprefix('subscriptions').' AS sub WHERE sub.fed_id = itm.fed_id AND sub.mbr_id = ? AND sub.sub_priority = ? )';
					$bindings[] = $this->member->mbr_id;
					$bindings[] = 1;
				} else {
					$where[] = '( itm.fed_id IN ( SELECT sub.fed_id FROM '.$this->db->dbprefix('subscriptions').' AS sub WHERE sub.fed_id = itm.fed_id AND sub.mbr_id = ? ) OR ( itm.itm_id IN ( SELECT shr.itm_id FROM '.$this->db->dbprefix('share').' AS shr WHERE shr.itm_id = itm.itm_id ) ) )';
						$bindings[] = $this->member->mbr_id;
				}

				if($this->axipi_session->userdata('items-mode') == 'geolocation') {
					$where[] = 'itm.itm_latitude IS NOT NULL';
					$where[] = 'itm.itm_longitude IS NOT NULL';
				}

				if($this->axipi_session->userdata('items-mode') == 'audio') {
					$where[] = 'enr.enr_type LIKE ?';
					$bindings[] = 'audio/%';
				}

				if($this->axipi_session->userdata('items-mode') == 'video') {
					$where[] = 'enr.enr_type LIKE ?';
					$bindings[] = 'video/%';
				}

				$where[] = 'itm.itm_id NOT IN ( SELECT hst.itm_id FROM '.$this->db->dbprefix('history').' AS hst WHERE hst.itm_id = itm.itm_id AND hst.mbr_id = ? )';
				$bindings[] = $this->member->mbr_id;

				if($is_folder) {
					$where[] = 'itm.fed_id IN ( SELECT sub.fed_id FROM '.$this->db->dbprefix('subscriptions').' AS sub WHERE sub.fed_id = itm.fed_id AND sub.flr_id = ? )';
					$bindings[] = $is_folder->flr_id;
				}

				if($is_feed) {
					$where[] = 'itm.fed_id IN ( SELECT sub.fed_id FROM '.$this->db->dbprefix('subscriptions').' AS sub WHERE sub.fed_id = itm.fed_id AND sub.sub_id = ? )';
					$bindings[] = $is_feed->sub_id;
				}

				if($is_author) {
					$where[] = 'itm.auh_id = ?';
					$bindings[] = $is_author;
				}

				if($is_category) {
					$where[] = 'itm.itm_id IN ( SELECT tag_itm.itm_id FROM '.$this->db->dbprefix('tags_items').' AS tag_itm WHERE tag_itm.tag_id = ? )';
					$bindings[] = $is_category;
				}

				if($this->axipi_session->userdata('items-mode') == 'nofolder') {
					$where[] = 'itm.fed_id IN ( SELECT sub.fed_id FROM '.$this->db->dbprefix('subscriptions').' AS sub WHERE sub.fed_id = itm.fed_id AND sub.flr_id IS NULL )';
				}

				if($age == 'one-day') {
					$where[] = 'itm.itm_date < ?';
					$bindings[] = date('Y-m-d H:i:s', time() - 3600 * 24 * 1);
				}
				if($age == 'one-week') {
					$where[] = 'itm.itm_date < ?';
					$bindings[] = date('Y-m-d H:i:s', time() - 3600 * 24 * 7);
				}
				if($age == 'two-weeks') {
					$where[] = 'itm.itm_date < ?';
					$bindings[] = date('Y-m-d H:i:s', time() - 3600 * 24 * 14);
				}

				$sql = 'INSERT INTO '.$this->db->dbprefix('history').' (itm_id, mbr_id, hst_real, hst_datecreated)
				SELECT itm.itm_id AS itm_id, ? AS mbr_id, \'0\' AS hst_real, ? AS hst_datecreated
				FROM '.$this->db->dbprefix('items').' AS itm ';
				if($this->axipi_session->userdata('items-mode') == 'audio' || $this->axipi_session->userdata('items-mode') == 'video') {
					$sql .= 'LEFT JOIN '.$this->db->dbprefix('enclosures').' AS enr ON enr.itm_id = itm.itm_id ';
				}
				$sql .= 'WHERE '.implode(' AND ', $where).'
				GROUP BY itm.itm_id';
				$query = $this->db->query($sql, $bindings);
			}
		} else {
			$this->output->set_status_header(403);
		}
		$this->readerself_library->set_content($content);
	}
}
