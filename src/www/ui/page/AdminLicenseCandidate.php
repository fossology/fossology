<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\UI\Page;

use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminLicenseCandidate extends DefaultPlugin
{
  const NAME = "admin_license_candidate";

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "Admin License Candidates",
        self::MENU_LIST => "Admin::License Admin::Candidates",
        self::REQUIRES_LOGIN => true,
        self::DEPENDENCIES => array(\ui_menu::NAME),
        self::PERMISSION => self::PERM_ADMIN
    ));
  }

  /**
   * @param Request $request
   * @throws \Exception
   * @return Response
   */
  protected function handle(Request $request)
  {
    $rf = intval($request->get('rf'));
    if ($rf<1)
    {
      $vars = array(
          'aaData' => json_encode($this->getArrayArrayData())
      );
      return $this->render('admin_license_candidate.html.twig', $this->mergeWithDefault($vars));
    }

    $vars = $this->getDataRow($rf);
    if ($vars === false)
    {
      throw new \Exception('invalid license candidate');
    }
    $shortname = $request->get('shortname') ?: $vars['rf_shortname'];
    $vars['shortname'] = $shortname;
    $suggest = intval($request->get('suggest_rf')) ?: $this->suggestLicenseId($vars['rf_text']);
    $suggestLicense = $suggest ? $this->getDataRow($suggest, 'ONLY license_ref') : false;
    if($suggestLicense!==false){
      $vars['suggest_rf'] = $suggest;
      $vars['suggest_shortname'] = $suggestLicense['rf_shortname'];
      $vars['suggest_fullname'] = $suggestLicense['rf_fullname'];
      $vars['suggest_text'] = $suggestLicense['rf_text'];
      $vars['suggest_url'] = $suggestLicense['rf_url'];
      $vars['suggest_notes'] = $suggestLicense['rf_notes'];
    }

    /** @var LicenseDao */
    $licenseDao = $this->getObject('dao.license');
    $vars['licenseArray'] = $licenseDao->getLicenseArray(0);
    $vars['scripts'] = js_url();

    $ok = true;
    switch ($request->get('do'))
    {
      case 'verify':
        $ok = $this->verifyCandidate($rf,$shortname,$vars);
        if($ok)
        {
          $vars = array(
              'aaData' => json_encode($this->getArrayArrayData()),
              'message' => 'Successfully verified candidate '.$shortname);
          return $this->render('admin_license_candidate.html.twig', $this->mergeWithDefault($vars));
        }
        $vars['message'] = 'Short name must be unique';
        break;
      case 'merge':
        $ok = $this->mergeCandidate($rf,$suggest,$vars);
        if($ok)
        {
          $vars = array(
              'aaData' => json_encode($this->getArrayArrayData()),
              'message' => "Successfully merged candidate <i>$vars[suggest_shortname]</i> ($suggest) into <i>$vars[rf_shortname]</i> ($rf)");
          return $this->render('admin_license_candidate.html.twig', $this->mergeWithDefault($vars));
        }
        $vars['message'] = 'Sorry, this feature is not ready yet.';
        break;
    }
      
    return $this->render('admin_license_candidate-merge.html.twig', $this->mergeWithDefault($vars));
  }


  private function getArrayArrayData()
  {
    $sql = "SELECT rf_pk,rf_shortname,rf_fullname,rf_text,group_name,group_pk "
            . "FROM license_candidate, groups "
            . "WHERE group_pk=group_fk AND marydone";
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');
    $dbManager->prepare($stmt = __METHOD__, $sql);
    $res = $dbManager->execute($stmt);
    $aaData = array();
    while ($row = $dbManager->fetchArray($res))
    {
      $link = Traceback_uri() . '?mod=' . Traceback_parm() . '&rf=' . $row['rf_pk'];
      $edit = '<a href="' . $link . '"><img border="0" src="images/button_edit.png"></a>';
      $aaData[] = array($edit, htmlentities($row['rf_shortname']),
          htmlentities($row['rf_fullname']),
          '<div style="overflow-y:scroll;max-height:150px;margin:0;">' . nl2br(htmlentities($row['rf_text'])) . '</div>',
          htmlentities($row['group_name'])
          );
    }
    $dbManager->freeResult($res);
    return $aaData;
  }


  private function getDataRow($licId,$table='license_candidate')
  {
    $sql = "SELECT rf_pk,rf_shortname,rf_fullname,rf_text,rf_url,rf_notes,rf_notes";
    if ($table == 'license_candidate')
    {
      $sql .= ',group_name,group_pk FROM license_candidate LEFT JOIN groups ON group_pk=group_fk '
              . 'WHERE rf_pk=$1 AND marydone';
    }
    else
    {
      $sql .= " FROM $table WHERE rf_pk=$1";
    }
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');
    $row = $dbManager->getSingleRow($sql, array($licId), __METHOD__.".$table");
    return $row;
  }

  private function suggestLicenseId($str){
    $tmpfname = tempnam("/tmp", "monk");
    $handle = fopen($tmpfname, "w");
    fwrite($handle, $str);
    fclose($handle);

    $cmd = dirname(dirname(dirname(__DIR__))).'/monk/agent/monk '.$tmpfname;
    exec($cmd, $output, $return_var);
    unlink($tmpfname);
    if ($return_var)
    {
      return false;
    }
    if (empty($output))
    {
      return 0;
    }
    // found diff match between "$tmpfname" and "EUPL-1.0" (rf_pk=424); rank 99; diffs: {t[0+42] M0 s[0+46], ...
    if (preg_match('/\\(rf_pk=(?P<rf>[0-9]+)\\)/', $output[0], $matches))
    {
      return $matches['rf'];
    } else
    {
      return 0;
    }
  }
  
  
  /**
   * @param int $rf
   * @param string $shortname
   * @return bool
   */
  private function verifyCandidate($rf, $shortname)
  {
    /** @var LicenseDao */
    $licenseDao = $this->getObject('dao.license');
    if (!$licenseDao->isNewLicense($shortname))
    {
      return false;
    }
    
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');
    $dbManager->begin();
    $dbManager->getSingleRow('INSERT INTO license_ref (SELECT rf_pk, $2 as rf_shortname, rf_text, rf_url, now() as rf_add_date, rf_copyleft,
        "rf_OSIapproved", rf_fullname, "rf_FSFfree", "rf_GPLv2compatible", "rf_GPLv3compatible", rf_notes, "rf_Fedora",
        false AS marydone, rf_active, rf_text_updatable, md5(rf_text) rf_md5 , 1 rf_detector_type
  FROM license_candidate WHERE rf_pk=$1)',array($rf,$shortname),__METHOD__.'.insert');
    $dbManager->getSingleRow('DELETE FROM license_candidate WHERE rf_pk=$1',array($rf),__METHOD__.'.delete');
    $dbManager->commit();
    return true;
  }

  private function mergeCandidate($candidate, $suggest, $vars)
  {
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');
    $tableColumnMap = array("clearing_licenses"=>"rf_fk",
        "license_file"=>"rf_fk",
        "license_ref_bulk"=>"rf_fk",
        "clearing_event"=>"rf_fk");
    foreach($tableColumnMap as $table=>$column){
      $dbManager->prepare($stmt=__METHOD__.".$table","UPDATE $table SET $column=$1 WHERE $column=$2");
      $dbManager->freeResult( $dbManager->execute($stmt,array($suggest,$candidate)) );
    }
    $updates = array();
    if (empty($vars['suggest_url']) && $vars['rf_url'])
    {
      $updates[$vars['rf_url']] = 'rf_url=$' . (count($updates)+1);
    }
    if (!$vars['rf_notes'])
    {
      $updates[$vars['suggest_notes']."\n".$vars['rf_notes']] = 'rf_notes=$' . (count($updates)+1);
    }
    if(count($updates))
    {
      $sql = 'UPDATE license_ref SET '.implode(',', $updates).' WHERE rf_pk=$'.(count($updates)+1);
      $dbManager->prepare($stmt=__METHOD__.'.update',$sql);
      $params = array_keys($updates);
      $params[] = $suggest;
      $dbManager->freeResult( $dbManager->execute($stmt,$params) );
    }
    $dbManager->prepare($stmt=__METHOD__.'.delete','DELETE FROM license_candidate WHERE rf_pk=$1');
    $dbManager->freeResult( $dbManager->execute($stmt,array($candidate)) );
    return TRUE;
  }

}

register_plugin(new AdminLicenseCandidate());
