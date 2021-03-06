<?php
!defined('M_COM') && exit('No Permission');
load_cache('permissions,vcps,channels,cotypes,acatalogs');
$aid = empty($aid) ? 0 : max(0,intval($aid));
$chid = $db->result_one("SELECT chid FROM {$tblprefix}archives WHERE aid='$aid'");
if(!($channel = read_cache('channel',$chid))) exit('choosearctype');

if(empty($channel['umdetail'])){
	load_cache('catalogs',$sid);
	//分析页面设置
	$nimuid = empty($nimuid) ? 0 : max(0,intval($nimuid));
	if($nimuid && $u_url = read_cache('inmurl',$nimuid)){
		$u_tplname = $u_url['tplname'];
		$u_onlyview = empty($u_url['onlyview']) ? 0 : 1;
		$u_mtitle = $u_url['mtitle'];
		$u_guide = $u_url['guide'];
		$vars = array('lists',);
		foreach($vars as $var) if(!empty($u_url['setting'][$var])) ${'u_'.$var} = explode(',',$u_url['setting'][$var]);
	}
	if(empty($u_tplname) || !empty($u_onlyview)){
		include_once M_ROOT."./include/fields.fun.php";
		include_once M_ROOT."./include/fields.cls.php";
		include_once M_ROOT."./include/upload.cls.php";
		include_once M_ROOT."./include/arcedit.cls.php";
		include_once M_ROOT."./include/commu.fun.php";
		include_once M_ROOT."./include/notice.cls.php";
		
		$aedit = new cls_arcedit;
		$aedit->set_aid($aid);
		$aedit->detail_data();
		!$aedit->aid && mcmessage('choosearchive');
		$aedit->archive['mid'] != $memberid && mcmessage('chooseyourarchive');
		if($sid != $aedit->archive['sid']){
			switch_cache($aedit->archive['sid']);
			$sid = $aedit->archive['sid'];
		}
		//模型与合辑信息是不会变化的
		$chid = $aedit->archive['chid'];
		$forward = empty($forward) ? M_REFERER : $forward;
		$forwardstr = '&forward='.urlencode($forward);
		
		$freeupdate = $curuser->check_allow('freeupdatecheck') || !$aedit->archive['checked'];
		$channel = &$aedit->channel;
		$fields = read_cache('fields',$chid);
		foreach(array('ccoids','citems','coidscp','cpkeeps','useredits') as $var) $$var = $channel[$var] ? explode(',',$channel[$var]) : array();
		
		
		if(!submitcheck('barchivedetail')){
			if(empty($u_tplname)){
			//显示端不限控制权限，添加时，或审后是否允许修改，都显示出来。
			
				$submitstr = '';
				$no_view = true;
				mtabheader($channel['cname'].'&nbsp; -&nbsp; '.(empty($u_mtitle) ? lang('contentsetting') : $u_mtitle),'archivedetail',"?action=archive&aid=$aid$param_suffix$forwardstr",2,1,1,1);
				//TODO 编辑小说栏目显示
				//if(empty($u_lists) || in_array('caid',$u_lists)){
					mtrbasic(lang('belongcatalog'),'',$acatalogs[$aedit->archive['caid']]['title'],'');
				//}
				//分类设置
				foreach($cotypes as $k => $v){
					if(empty($u_lists) || in_array("ccid$k",$u_lists)){
						if(!$v['self_reg'] && !in_array($k,$ccoids)){
							$noedit = noedit("ccid$k");
							mtrcns($v['cname'].$noedit,"archivenew[ccid$k]",$aedit->archive["ccid$k"],$aedit->archive['sid'],$k,$chid,0,lang('p_choose'));
							!$noedit && $submitstr .= makesubmitstr("archivenew[ccid$k]", $v['notblank'],0,0,0,'common');
							$no_view = false;
						}
					
					}
				}
				$a_field = new cls_field;
				$subject_table = 'archives';
				//var_dump($fields);exit();
				//TODO 删除编辑小说的一些显示
				unset($fields['source']);	//删除来源
				unset($fields['keywords']);	//删除关键词subtitle
				unset($fields['subtitle']);	//删除副标题keywords
				unset($fields['author']);	
				unset($fields['abstract']);	
				echo '<pre>';
				
				foreach($fields as $k => $field){
					if(empty($u_lists) || in_array($k,$u_lists)){
						if($field['available'] && !$field['isadmin'] && !$field['isfunc']){
							$a_field->init(1);
							$a_field->field = read_cache('field',$chid,$k);
							$a_field->oldvalue = isset($aedit->archive[$k]) ? $aedit->archive[$k] : '';
							$noedit = noedit($k,!$curuser->pmbypmids('field',$a_field->field['pmid']));
							if ($k == 'subject' && isset($_GET['nimuid'])) {
								echo '
								<tr><td width="25%" class="item1"><b>*作品名&nbsp; <img align="absmiddle" src="images/common/lock.gif"></b></td>
<td class="item2"><input type="text" value="'.$a_field->oldvalue.'" name="archivenew[subject]" id="archivenew[subject]" readonly="true" size="60">&nbsp;&nbsp;<div class="red" name="alert_archivenew[subject]" id="alert_archivenew[subject]"></div></td></tr>
								';
							} else {
								$a_field->trfield('archivenew',$noedit);
							}
							
							!$noedit && $submitstr .= $a_field->submitstr;
							$no_view = false;
							//ar_dump($a_field);
						}
					}
				}
				//个人分类设置
				//var_dump($u_lists);
				if (!empty($u_lists)) {
					foreach ($u_lists as $_key => $v) {	//删除浏览收费的修改
						if ($v == 'salecp' || $v == 'fsalecp') unset($u_lists[$_key]);
					}
				}
				//var_dump($u_lists);
				
				
				if(empty($u_lists) || in_array('ucid',$u_lists)){
					if(!in_array('ucid',$citems)){
						$uclasses = loaduclasses($curuser->info['mid']);
						$ucidsarr = array();
						foreach($uclasses as $k => $v) if(!$v['cuid']) $ucidsarr[$k] = $v['title'];
						//mtrbasic(lang('mycoclass'),'archivenew[ucid]',makeoption(array('0' => lang('nosettingcoclass')) + $ucidsarr,$aedit->archive['ucid']),'select');
						$no_view = false;
					}
				}
				if(empty($u_lists) || in_array('salecp',$u_lists)){
					if(!in_array('salecp',$citems)){
						$noedit = noedit('salecp');
						//mtrbasic(lang('archivesaleprice').$noedit,'archivenew[salecp]',makeoption(array('' => lang('freesale')) + $vcps['sale'],$aedit->archive['salecp']),'select');
						$no_view = false;
					}
				}
				if(empty($u_lists) || in_array('fsalecp',$u_lists)){
					if(!in_array('fsalecp',$citems)){
						$noedit = noedit('fsalecp');
						mtrbasic(lang('adjunctsaleprice').$noedit,'archivenew[fsalecp]',makeoption(array('' => lang('freesale')) + $vcps['fsale'],$aedit->archive['fsalecp']),'select');
						$no_view = false;
					}
				}
				if(empty($u_lists) || in_array('cpupdate',$u_lists)){
					if(!in_array('copy',$citems)){//副本不需要同步
						$cpupdatearr = array(0 => lang('noupdate'),1 => lang('cpupdate1'),2 => lang('cpupdate2'),);
						mtrbasic(lang('cpupdate'),'',makeradio('archivenew[cpupdate]',$cpupdatearr,0),'');
						$no_view = false;
					}
				}
				
				mtabfooter($no_view ? '' : 'barchivedetail');
				check_submit_func($submitstr);
				m_guide(@$u_guide);
			}else include(M_ROOT.$u_tplname);
		}else{
			if(isset($archivenew['caid'])){
				if(!noedit('caid')){
					$aedit->arc_caid($archivenew['caid']);
				}
			}
			foreach($cotypes as $k => $v){
				if(isset($archivenew["ccid$k"])){
					if(!$v['self_reg'] && !in_array($k,$ccoids) && !noedit("ccid$k")){
						$archivenew["ccid$k"] = empty($archivenew["ccid$k"]) ? 0 : $archivenew["ccid$k"];
						$aedit->arc_ccid($archivenew["ccid$k"],$k);
					}
				}
			}
			if(isset($archivenew['ucid'])){
				if(!in_array('ucid',$citems)){
					$aedit->updatefield('ucid',$archivenew['ucid'],'main');
				}
			}
		
			if(isset($archivenew['salecp'])){
				if(!in_array('salecp',$citems) && !noedit('salecp')){
					$aedit->updatefield('salecp',$archivenew['salecp'],'main');
				}
			}
			if(isset($archivenew['fsalecp'])){
				if(!in_array('fsalecp',$citems) && !noedit('fsalecp')){
					$aedit->updatefield('fsalecp',$archivenew['fsalecp'],'main');
				}
			}
			$aedit->sale_define();
		
			$c_upload = new cls_upload;	
			$fields = fields_order($fields);
			$a_field = new cls_field;
			foreach($fields as $k => $field){
				if(isset($archivenew[$k])){
					if($field['available'] && !$field['isadmin'] && !$field['isfunc'] && !noedit($k)){
						$a_field->init(1);
						$a_field->field = read_cache('field',$chid,$k);
						if($curuser->pmbypmids('field',$a_field->field['pmid'])){//字段附加权限设置
							$a_field->oldvalue = isset($aedit->archive[$k]) ? $aedit->archive[$k] : '';
							$a_field->deal('archivenew');
							if(!empty($a_field->error)){
								$c_upload->rollback();
								mcmessage($a_field->error,axaction(2,M_REFERER));
							}
							$archivenew[$k] = $a_field->newvalue;
						}
					}
				
				}
			}
			unset($a_field);
			$cu_ret = cu_fields_deal($aedit->channel['cuid'],'archivenew',$aedit->archive);//需要受$useredits的控制
			!empty($cu_ret) && mcmessage($cu_ret,axaction(2,M_REFERER));
			$aedit->edit_cudata($archivenew,1);
			
			//TODO 编辑小说 摘要赋值为内容值
			$archivenew['abstract'] = $archivenew['content'];
			
			if(isset($archivenew['keywords']) && ($freeupdate || in_array('keywords',$useredits))) $archivenew['keywords'] = keywords($archivenew['keywords'],$aedit->archive['keywords']);
			if($fields['abstract']['available'] && !$fields['abstract']['isadmin'] && $aedit->channel['autoabstract'] && empty($archivenew['abstract']) && isset($archivenew[$aedit->channel['autoabstract']])){
				$archivenew['abstract'] = autoabstract($archivenew[$aedit->channel['autoabstract']]);
			}
			if($fields['thumb']['available'] && !$fields['thumb']['isadmin'] && $aedit->channel['autothumb'] && empty($archivenew['thumb']) && isset($archivenew[$aedit->channel['autothumb']])){
				$field = read_cache('field',$chid,'thumb');
				$archivenew['thumb'] = $c_upload->thumb_pick(stripslashes($archivenew[$aedit->channel['autothumb']]),$fields[$aedit->channel['autothumb']]['datatype'],$field['rpid']);
			}
			if($aedit->channel['autosize'] && !empty($archivenew[$aedit->channel['autosize']])){
				$archivenew['atmsize'] = atm_size(stripslashes($archivenew[$aedit->channel['autosize']]),$fields[$aedit->channel['autosize']]['datatype'],$aedit->channel['autosizemode']);
				$aedit->updatefield('atmsize',$archivenew['atmsize'],'main');
			}
			if($channel['autobyte'] && isset($archivenew[$channel['autobyte']])){
				$archivenew['bytenum'] = atm_byte(stripslashes($archivenew[$channel['autobyte']]),$fields[$channel['autobyte']]['datatype']);
				$aedit->updatefield('bytenum',$archivenew['bytenum'],'main');
			}
			
			foreach($fields as $k => $field){//需要分析是否有字段的编辑权
				if(isset($archivenew[$k])){
					if($field['available'] && !$field['isadmin'] && !$field['isfunc'] && !noedit($k)){
						$field = read_cache('field',$chid,$k);
						if($curuser->pmbypmids('field',$field['pmid'])){
							if(!empty($field['istxt'])) $archivenew[$k] = saveastxt(stripslashes($archivenew[$k]),$aedit->namepres[$k]);
							$aedit->updatefield($k,$archivenew[$k],$field['tbl']);
						}
					}
				}
			}
			if($aedit->archive['needupdate']) $aedit->updatefield('overupdate',$timestamp,'sub');//申请更新的状态下标记已经执行更新。
			$aedit->channel['autocheck'] && $aedit->arc_check('1');
			$aedit->updatefield('updatedate',$timestamp,'main');
			$c_notice = new cls_notice;
			$aedit->updatedb();
			$c_notice->deal();
		
			if(!empty($archivenew['cpupdate'])) $aedit->updatecopy($archivenew['cpupdate']);
		
			if($channel['autostatic']){
				include_once M_ROOT."./include/arc_static.fun.php";
				arc_static($aid);
				unset($arc);
			}
		
			$c_upload->closure(1, $aedit->aid, 'archives');
			unset($aedit);
			$c_upload->saveuptotal(1);
			mcmessage('arceditfinish',axaction(4,$forward));
		}
	}else include(M_ROOT.$u_tplname);
}else include(M_ROOT.$channel['umdetail']);
?>
