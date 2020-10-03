<?php

/**
 *      [Discuz!] (C)2001-2099 Comsenz Inc.
 *      This is NOT a freeware, use is subject to license terms
 *
 *      $Id: task_post.php 26754 2011-12-22 08:14:22Z zhengqingpeng $
 * 
 * 
 *      改动自 Discuz ! 3.4 官方原版任务。搭配同步修改lang文件
 *      Based on origin Discuz ! 3.4 task_post.php as well as the lang file  lang_post.php
 * 
 *      改动日志/change log
 *      2.0
 *      1. 添加对应累计完成类任务，切记，累计完成类任务请勿设置为周期任务
 *      2. 修复一个描述bug。回帖任务中，当没有限制回复指定帖子时，描述错误的显示  	回复作者“”的主题 xxx 次
 * 
 * 
 *      切记，累计完成类任务请勿设置为周期任务
 * 
 */

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

class task_post {

	var $version = '2.0';
	var $name = 'post_name';
	var $description = 'post_desc';
	var $copyright = '<a href="http://www.comsenz.com" target="_blank">Comsenz Inc.</a> & <a href="https://github.com/dog194/discuz/tree/master/task" target="_blank">Dog194.</a>';
	var $icon = '';
	var $period = '';
	var $periodtype = 0;
	var $conditions = array(
		'act' => array(
			'title' => 'post_complete_var_act',
			'type' => 'mradio',
			'value' => array(
				array('newthread', 'post_complete_var_act_newthread'),
				array('newreply', 'post_complete_var_act_newreply'),
				array('newpost', 'post_complete_var_act_newpost'),
				array('accthread', 'post_complete_var_act_accthread'),
				array('accreply', 'post_complete_var_act_accreply'),
				array('accpost', 'post_complete_var_act_accpost'),
			),
			'default' => 'newthread',
			'sort' => 'complete',
		),
		'forumid' => array(
			'title' => 'post_complate_var_forumid',
			'type' => 'select',
			'value' => array(),
			'sort' => 'complete',
		),
		'threadid' => array(
			'title' => 'post_complate_var_threadid',
			'type' => 'text',
			'value' => '',
			'sort' => 'complete',
		),
		'num' => array(
			'title' => 'post_complete_var_num',
			'type' => 'text',
			'value' => '',
			'sort' => 'complete',
		),
		'time' => array(
			'title' => 'post_complete_var_time',
			'type' => 'text',
			'value' => '',
			'sort' => 'complete',
		)
	);

	function task_post() {
		global $_G;
		loadcache('forums');
		$this->conditions['forumid']['value'][] = array(0, '&nbsp;');
		if(empty($_G['cache']['forums'])) $_G['cache']['forums'] = array();
		foreach($_G['cache']['forums'] as $fid => $forum) {
			$this->conditions['forumid']['value'][] = array($fid, ($forum['type'] == 'forum' ? str_repeat('&nbsp;', 4) : ($forum['type'] == 'sub' ? str_repeat('&nbsp;', 8) : '')).$forum['name'], $forum['type'] == 'group' ? 1 : 0);
		}
	}

	function csc($task = array()) {
		global $_G;

		$taskvars = array('num' => 0);
		foreach(C::t('common_taskvar')->fetch_all_by_taskid($task['taskid']) as $taskvar) {
			if($taskvar['value']) {
				$taskvars[$taskvar['variable']] = $taskvar['value'];
			}
		}
		$taskvars['num'] = $taskvars['num'] ? $taskvars['num'] : 1;

		$tbladd = $sqladd = '';
		if($taskvars['act'] == 'newreply' && $taskvars['threadid']) {
			$threadid = $taskvars['threadid'];
		} elseif($taskvars['act'] == 'accreply' && $taskvars['threadid']) { //累计在指定帖发表回复
			$threadid = $taskvars['threadid'];
		} else {
			if($taskvars['forumid']) {
				$forumid = $taskvars['forumid'];
			}
			if($taskvars['author']) {
				return TRUE;
			}
		}
		if($taskvars['act']) {
			if($taskvars['act'] == 'newthread' or $taskvars['act'] == 'accthread') { //计数类型
				$first = '1';
			} elseif($taskvars['act'] == 'newreply' or $taskvars['act'] == 'accreply') {
				$first = '0';
			}
		}

		if($taskvars['act'] == 'newthread' or $taskvars['act'] == 'newreply' or $taskvars['act'] == 'newpost'){
			$starttime = $task['applytime']; //仅new类任务需要限制起始时间，acc累计类任务不添加此限制
		}
		if($taskvars['time'] = floatval($taskvars['time'])) {
			$endtime = $task['applytime'] + 3600 * $taskvars['time'];
		}

		$num = C::t('forum_post')->count_by_search(0, $threadid, null, 0, $forumid, $_G['uid'], null, $starttime, $endtime, null, $first);

		if($num && $num >= $taskvars['num']) {
			return TRUE;
		} elseif($taskvars['time'] && TIMESTAMP >= $task['applytime'] + 3600 * $taskvars['time'] && (!$num || $num < $taskvars['num'])) {
			return FALSE;
		} else {
			return array('csc' => $num > 0 && $taskvars['num'] ? sprintf("%01.2f", $num / $taskvars['num'] * 100) : 0, 'remaintime' => $taskvars['time'] ? $task['applytime'] + $taskvars['time'] * 3600 - TIMESTAMP : 0);
		}
	}

	function view($task, $taskvars) {
		global $_G;
		$return = $value = '';
		if(!empty($taskvars['complete']['forumid'])) {
			$value = intval($taskvars['complete']['forumid']['value']);
			loadcache('forums');
			$value = '<a href="forum.php?mod=forumdisplay&fid='.$value.'"><strong>'.$_G['cache']['forums'][$value]['name'].'</strong></a>';
		} elseif(!empty($taskvars['complete']['threadid'])) {
			$value = intval($taskvars['complete']['threadid']['value']);
			$thread = C::t('forum_thread')->fetch($value);
			$value = '<a href="forum.php?mod=viewthread&tid='.$value.'"><strong>'.($thread['subject'] ? $thread['subject'] : 'TID '.$value).'</strong></a>';
		} elseif(!empty($taskvars['complete']['author'])) {
			$value = $taskvars['complete']['author']['value'];
			$authorid = C::t('common_member')->fetch_uid_by_username($value);
			$value = '<a href="home.php?mod=space&uid='.$authorid.'"><strong>'.$value.'</strong></a>';
		}
		$taskvars['complete']['num']['value'] = intval($taskvars['complete']['num']['value']);
		$taskvars['complete']['num']['value'] = $taskvars['complete']['num']['value'] ? $taskvars['complete']['num']['value'] : 1;
		if($taskvars['complete']['act']['value'] == 'newreply') {
			if($taskvars['complete']['threadid']) {
				$return .= lang('task/post', 'task_complete_act_newreply_thread', array('value' => $value, 'num' => $taskvars['complete']['num']['value']));
			} elseif($value == ''){
				$return .= lang('task/post', 'task_complete_act_newreply', array('num' => $taskvars['complete']['num']['value']));
			} else {
				$return .= lang('task/post', 'task_complete_act_newreply_author', array('value' => $value, 'num' => $taskvars['complete']['num']['value']));
			}
		} elseif($taskvars['complete']['act']['value'] == 'accreply'){
			if($taskvars['complete']['threadid']) {
				$return .= lang('task/post', 'task_complete_act_accreply_thread', array('value' => $value, 'num' => $taskvars['complete']['num']['value']));
			} else{
				$return .= lang('task/post', 'task_complete_act_accreply', array('num' => $taskvars['complete']['num']['value']));
			}
		} else {
			if($taskvars['complete']['forumid']) {
				$return .= lang('task/post', 'task_complete_forumid', array('value' => $value));
			}
			if($taskvars['complete']['act']['value'] == 'newthread') {
				$return .= lang('task/post', 'task_complete_act_newthread', array('num' => $taskvars['complete']['num']['value']));
			} elseif($taskvars['complete']['act']['value'] == 'accthread'){
				$return .= lang('task/post', 'task_complete_act_accthread', array('num' => $taskvars['complete']['num']['value']));
			} elseif($taskvars['complete']['act']['value'] == 'accpost'){
				$return .= lang('task/post', 'task_complete_act_accpost', array('num' => $taskvars['complete']['num']['value']));
			} else {
				$return .= lang('task/post', 'task_complete_act_newpost', array('num' => $taskvars['complete']['num']['value']));
			}
		}
		return $return;
	}

	function sufprocess($task) {
	}

}

?>