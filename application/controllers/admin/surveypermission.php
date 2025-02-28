<?php

/*
 * LimeSurvey
 * Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
 * All rights reserved.
 * License: GNU/GPL License v2 or later, see LICENSE.php
 * LimeSurvey is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */

/**
* surveypermission
*
* @package LimeSurvey
* @copyright 2011
* @access public
*/

use LimeSurvey\Models\Services\PermissionManager;

class surveypermission extends Survey_Common_Action
{

    /**
     * Load survey security screen.
     *
     * @param int|string $iSurveyID
     * @return void
     * @todo Export HTML to view
     */
    public function index($iSurveyID)
    {
        $aData = array();
        $aData['surveyid'] = $iSurveyID = sanitize_int($iSurveyID);
        $oSurvey = Survey::model()->findByPk($iSurveyID);
        $aViewUrls = array();

        $imageurl = Yii::app()->getConfig('adminimageurl');

        if (!Permission::model()->hasSurveyPermission($iSurveyID, 'surveysecurity', 'read')) {
            $this->getController()->error('Access denied');
            return;
        }

        $aData['sidemenu']['state'] = false;
        $aData['title_bar']['title'] = $oSurvey->currentLanguageSettings->surveyls_title . " (" . gT("ID") . ":" . $iSurveyID . ")";
        $aData['surveybar']['closebutton']['url'] = 'surveyAdministration/view/surveyid/' . $iSurveyID; // Close button

        $aBaseSurveyPermissions = Permission::model()->getSurveyBasePermissions();
        $userList = getUserList('onlyuidarray'); // Limit the user list for the samegrouppolicy
        App()->getClientScript()->registerPackage('jquery-tablesorter');
        App()->getClientScript()->registerScriptFile(App()->getConfig('adminscripts') . 'surveypermissions.js');
        // FIXME this HTML stuff MUST BE IN VIEWS!!
        $surveysecurity = "<div id='edit-permission' class='side-body " . getSideBodyClass(false) . "'>";
        $surveysecurity .= viewHelper::getViewTestTag('surveyPermissions');

        $surveysecurity .= "<h3>" . gT("Survey permissions") . "</h3>\n";
        $surveysecurity .= '<div class="row"><div class="col-lg-12 content-right">';
        $result2 = Permission::model()->getUserDetails($iSurveyID);
        if (count($result2) > 0) {
                $surveysecurity .= ""
                . "<table class='surveysecurity table table-hover'><thead>"
                . "<tr>\n"
                . "<th>" . gT("Action") . "</th>\n"
                . "<th>" . gT("Username") . "</th>\n"
                . "<th>" . gT("User group") . "</th>\n"
                . "<th>" . gT("Full name") . "</th>\n";
            foreach ($aBaseSurveyPermissions as $sPermission => $aSubPermissions) {
                $surveysecurity .= "<th>" . $aSubPermissions['title'] . "</th>\n";
            }
            $surveysecurity .= "</tr></thead>\n";

            // Foot first

            if (Yii::app()->getConfig('usercontrolSameGroupPolicy') == true) {
                $authorizedGroupsList = getUserGroupList();
            }

            $surveysecurity .= "<tbody>\n";
            $row = 0;
            foreach ($result2 as $PermissionRow) {
                // TODO: Filter this in SQL query.
                if (!in_array($PermissionRow['uid'], $userList)) {
                    continue;
                }

                $result3 = UserInGroup::model()->with('users')->findAll('users.uid = :uid', array(':uid' => $PermissionRow['uid']));
                foreach ($result3 as $resul3row) {
                    if (
                        Yii::app()->getConfig('usercontrolSameGroupPolicy') == false ||
                        in_array($resul3row->ugid, $authorizedGroupsList)
                    ) {
                        $group_ids[] = $resul3row->ugid;
                    }
                }
                if (isset($group_ids) && $group_ids[0] != null) {
                    $group_ids_query = implode(",", $group_ids);
                    unset($group_ids);
                    $result4 = UserGroup::model()->findAll("ugid IN ($group_ids_query)");

                    foreach ($result4 as $resul4row) {
                        $group_names[] = \CHtml::encode($resul4row->name);
                    }
                    if (count($group_names) > 0) {
                        $group_names_query = implode(", ", $group_names);
                    }
                }
                //                  else {break;} //TODO Commented by lemeur
                $surveysecurity .= "<tr>\n";

                $surveysecurity .= "<td class='icon-btn-row'>\n";

                if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveysecurity', 'update')) {
                    if ($PermissionRow['uid'] != Yii::app()->user->getId() || Permission::model()->hasGlobalPermission('superadmin', 'read')) {
                        // Can not update own security
                        $surveysecurity .= CHtml::form(array("admin/surveypermission/sa/set/surveyid/{$iSurveyID}"), 'post', array('style' => "display:inline;"))
                        . "<button type='submit' class='btn btn-default btn-sm' data-toggle='tooltip' title='" . gT("Edit permissions") . "'><span class='fa fa-pencil text-success'></span></button>";
                        $surveysecurity .= \CHtml::hiddenField('action', 'setsurveysecurity');
                        $surveysecurity .= \CHtml::hiddenField('user', $PermissionRow['users_name']);
                        $surveysecurity .= \CHtml::hiddenField('uid', $PermissionRow['uid']);
                        $surveysecurity .= "</form>\n";
                    }
                }
                if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveysecurity', 'delete')) {
                    $deleteUrl = App()->createUrl("admin/surveypermission/sa/delete/surveyid/" . $iSurveyID, array(
                        'action' => 'delsurveysecurity',
                        'uid' => $PermissionRow['uid']
                    ));
                    $deleteConfirmMessage = gT("Are you sure you want to delete this entry?");
                    $surveysecurity .= "<span data-toggle='tooltip' title='" . gT("Delete") . "'><a data-target='#confirmation-modal' data-toggle='modal' data-message='{$deleteConfirmMessage}' data-post-url='{$deleteUrl}' type='submit' class='btn-sm btn btn-default'>
                        <span class='fa fa-trash text-danger'></span>
                        </a></span>";
                }

                $surveysecurity .= "</td>\n";
                $surveysecurity .= "<td>" . \CHtml::encode($PermissionRow['users_name']) . "</td>\n"
                . "<td>";

                if (isset($group_names) > 0) {
                    $surveysecurity .= $group_names_query;
                } else {
                    $surveysecurity .= "&#8211;";
                }
                unset($group_names);

                $surveysecurity .= "</td>\n"
                . "<td>\n" . \CHtml::encode($PermissionRow['full_name']) . "</td>\n";

                //Now show the permissions
                foreach ($aBaseSurveyPermissions as $sPKey => $aPDetails) {
                    unset($aPDetails['img']);
                    unset($aPDetails['description']);
                    unset($aPDetails['title']);
                    $iCount = 0;
                    $iPermissionCount = 0;
                    $sTooltip = "";
                    foreach ($aPDetails as $sPDetailKey => $sPDetailValue) {
                        if (
                            $sPDetailValue
                            && Permission::model()->hasSurveyPermission($iSurveyID, $sPKey, $sPDetailKey, $PermissionRow['uid'])
                        ) {
                            $iCount++;
                            $sTooltip .= $sPDetailKey . ", ";
                        }
                        if ($sPDetailValue) {
                            $iPermissionCount++;
                        }
                    }
                    // Remove last ',' and make first char upper-case
                    $sTooltip = substr($sTooltip, 0, -2);
                    $sTooltip = ucfirst($sTooltip);

                    // Full icon = all permissions
                    if ($iCount == $iPermissionCount) {
                        $insert = "<div data-toggle='tooltip' data-title='" . $sTooltip . "' class=\"fa fa-check\">&nbsp;</div>";
                    }
                    // Blurred icon, meaning only partial permissions
                    elseif ($iCount > 0) {
                        $insert = "<div data-toggle='tooltip' data-title='" . $sTooltip . "' class=\"fa fa-check mixed\">&nbsp;</div>";
                    } else {
                        $insert = "<div>&#8211;</div>";
                    }

                    $surveysecurity .= "<td class='text-center' >\n$insert\n</td>\n";
                }

                $surveysecurity .= "</tr>\n";
                $row++;
            }
            $surveysecurity .= "</tbody>\n"
            . "</table>\n";
        }

        if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveysecurity', 'create')) {
            $surveysecurity .= CHtml::form(array("admin/surveypermission/sa/adduser/surveyid/{$iSurveyID}"), 'post', array('class' => "form44")) . "<br/><br/><ul class='list-unstyled'>\n"
            . "<li><label class='col-sm-1 col-md-offset-2 text-right control-label' for='uidselect'>" . gT("User") . ": </label>
                 <div class='col-sm-4'>
                <select id='uidselect' name='uid'  class='form-control'>\n"
            . getSurveyUserList(false, $iSurveyID)
            . "</select></div>\n"
            . "<input style='width: 15em;' class='btn btn-default' type='submit' value='" . gT("Add user") . "'  onclick=\"if (document.getElementById('uidselect').value == -1) { alert('" . gT("Please select a user first", "js") . "'); return false;}\"/>"
            . "<input type='hidden' name='action' value='addsurveysecurity' />"
            . "</li></ul></form>\n";

            $surveysecurity .= CHtml::form(array("admin/surveypermission/sa/addusergroup/surveyid/{$iSurveyID}"), 'post', array('class' => "form44")) . "<ul class='list-unstyled'><li>\n"
            . "<label  class='col-sm-1 col-md-offset-2  text-right control-label'  for='ugidselect'>" . gT("User group") . ": </label>
                <div class='col-sm-4'>
                <select id='ugidselect' name='ugid'  class='form-control'>\n"
            . getSurveyUserGroupList('htmloptions', $iSurveyID)
            . "</select></div>\n"
            . "<input style='width: 15em;' class='btn btn-default'  type='submit' value='" . gT("Add group users") . "' onclick=\"if (document.getElementById('ugidselect').value == -1) { alert('" . gT("Please select a user group first", "js") . "'); return false;}\" />"
            . "<input type='hidden' name='action' value='addusergroupsurveysecurity' />\n"
            . "</li></ul></form>";
        }


        $aData['sidemenu']['state'] = false;

        $aData['title_bar']['title'] = $oSurvey->currentLanguageSettings->surveyls_title . " (" . gT("ID") . ":" . $iSurveyID . ")";
        $aData['subaction'] = gT("Survey permissions");

        $aData['surveybar']['closebutton']['url'] = 'surveyAdministration/view/surveyid/' . $iSurveyID; // Close button

        $surveysecurity .= '</div></div></div>';
        $aViewUrls['output'] = $surveysecurity;

        $this->_renderWrappedTemplate('authentication', $aViewUrls, $aData);
    }

    /**
     * surveypermission::addusergroup()
     * Function responsible to add usergroup.
     * @param mixed $surveyid
     * @return void
     */
    function addusergroup($surveyid)
    {
        $aData['surveyid'] = $surveyid = sanitize_int($surveyid);
        $oSurvey = Survey::model()->findByPk($surveyid);
        $aViewUrls = array();

        $action = $_POST['action'];
        $imageurl = Yii::app()->getConfig('imageurl');

        $postusergroupid = !empty($_POST['ugid']) ? $_POST['ugid'] : false;


        if ($action == "addusergroupsurveysecurity") {
            //////////////////
            $addsummary = "<div id='edit-permission' class='side-body " . getSideBodyClass(false) . "'>";
            $addsummary .= '<div class="row"><div class="col-lg-12 content-right">';

            $result = Survey::model()->findAll('sid = :surveyid AND owner_id = :owner_id', array(':surveyid' => $surveyid, ':owner_id' => Yii::app()->session['loginID']));
            if (
                Permission::model()->hasSurveyPermission($surveyid, 'surveysecurity', 'create')
                && in_array($postusergroupid, getSurveyUserGroupList('simpleugidarray', $surveyid))
            ) {
                if ($postusergroupid > 0) {
                    $result2 = User::model()->getCommonUID($surveyid, $postusergroupid); //Checked
                    $result2 = $result2->readAll();
                    if (count($result2) > 0) {
                        foreach ($result2 as $row2) {
                            $uid_arr[] = $row2['uid'];
                            $isrresult = Permission::model()->insertSomeRecords(array('entity_id' => $surveyid, 'entity' => 'survey', 'uid' => $row2['uid'], 'permission' => 'survey', 'read_p' => 1));
                            if (!$isrresult) {
                                break;
                            }
                        }

                        if ($isrresult) {
                            $addsummary .= "<div class=\"jumbotron message-box\">\n";
                            $addsummary .= "<h2>" . gT("Add user group") . "</h2>\n";
                            $addsummary .= "<p class='lead'>" . gT("User group added.") . "</p>\n";
                            $addsummary .= "<p>";

                            Yii::app()->session['uids'] = $uid_arr;
                            $addsummary .= "<br />"
                            . CHtml::form(array("admin/surveypermission/sa/set/surveyid/{$surveyid}"), 'post')
                            . "<input class='btn btn-default'  type='submit' value='" . gT("Set Survey Rights") . "' />"
                            . "<input type='hidden' name='action' value='setusergroupsurveysecurity' />"
                            . "<input type='hidden' name='ugid' value='{$postusergroupid}' />"
                            . "</form></p>\n";
                        } else {
                            // Error while adding user to the database

                            $addsummary .= "<div class=\"jumbotron message-box message-box\">\n";
                            $addsummary .= "<h2>" . gT("Add user group") . "</h2>\n";
                            $addsummary .= "<p class='lead'>" . gT("Failed to add user group.") . "</p>\n";
                            $addsummary .= "<p>";

                            $addsummary .= "<br/><input class='btn btn-default'  type=\"submit\" onclick=\"window.open('" . $this->getController()->createUrl('admin/surveypermission/sa/view/surveyid/' . $surveyid) . "', '_top')\" value=\"" . gT("Continue") . "\"/>\n";
                            $addsummary .= "</p>";
                        }
                    } else {
                        // no user to add
                        $addsummary .= "<div class=\"jumbotron message-box message-box\">\n";
                        $addsummary .= "<h2>" . gT("Add user group") . "</h2>\n";
                        $addsummary .= "<p class='lead'>" . gT("Failed to add user group.") . "</p>\n";
                        $addsummary .= "<p>";
                        $addsummary .= "<br/><input class='btn btn-default'  type=\"submit\" onclick=\"window.open('" . $this->getController()->createUrl('admin/surveypermission/sa/view/surveyid/' . $surveyid) . "', '_top')\" value=\"" . gT("Continue") . "\"/>\n";
                        $addsummary .= "</p>";
                    }
                } else {
                    $addsummary .= "<div class=\"jumbotron message-box message-box\">\n";
                    $addsummary .= "<h2>" . gT("Add user group") . "</h2>\n";
                    $addsummary .= "<p class='lead'>" . gT("Failed to add user group.") . "</p>\n";
                    $addsummary .= "<p>" . gT("No Username selected.") . "</p>\n";
                    $addsummary .= "<p>";
                    $addsummary .= "<br/><input class='btn btn-default'  type=\"submit\" onclick=\"window.open('" . $this->getController()->createUrl('admin/surveypermission/sa/view/surveyid/' . $surveyid) . "', '_top')\" value=\"" . gT("Continue") . "\"/>\n";
                    $addsummary .= "</p>";
                }
            } else {
                $this->getController()->error('Access denied');
            }
            $addsummary .= "</div>\n";

            $addsummary .= '</div></div></div>';
            $aViewUrls['output'] = $addsummary;
        }

            $aData['sidemenu']['state'] = false;
            $aData['title_bar']['title'] = $oSurvey->currentLanguageSettings->surveyls_title . " (" . gT("ID") . ":" . $surveyid . ")";


        $this->_renderWrappedTemplate('authentication', $aViewUrls, $aData);
    }

    /**
     * surveypermission::adduser()
     * Function responsible to add user.
     * @param mixed $surveyid
     * @return void
     */
    function adduser($surveyid)
    {
        $aData['surveyid'] = $surveyid = sanitize_int($surveyid);
        $oSurvey = Survey::model()->findByPk($surveyid);
        $aViewUrls = array();

        $action = $_POST['action'];


        $imageurl = Yii::app()->getConfig('imageurl');
        $postuserid = $_POST['uid'];

        if ($action == "addsurveysecurity") {
            $addsummary = "<div id='edit-permission' class='side-body " . getSideBodyClass(false) . "'>";
            $addsummary .= '<div class="row"><div class="col-lg-12 content-right">';


            $result = Survey::model()->findAll('sid = :sid AND owner_id = :owner_id AND owner_id != :postuserid', array(':sid' => $surveyid, ':owner_id' => Yii::app()->session['loginID'], ':postuserid' => $postuserid));
            if (
                Permission::model()->hasSurveyPermission($surveyid, 'surveysecurity', 'create')
                &&  in_array($postuserid, getUserList('onlyuidarray'))
            ) {
                if ($postuserid > 0) {
                    try {
                        $isrresult = Permission::model()->insertSomeRecords(array('entity_id' => $surveyid, 'entity' => 'survey', 'uid' => $postuserid, 'permission' => 'survey', 'read_p' => 1));
                    } catch (CDbException $e) {
                        // This database error happens usually if user already exists
                        $isrresult = false;
                    }



                    if ($isrresult) {
                        $addsummary .= "<div class=\"jumbotron message-box\">\n";
                        $addsummary .= "<h2>" . gT("Add user") . "</h2>\n";
                        $addsummary .= "<p class='lead'>" . gT("User added.") . "</p>\n";
                        $addsummary .= "<p>"
                        . CHtml::form(array("admin/surveypermission/sa/set/surveyid/{$surveyid}"), 'post')
                        . "<input class='btn btn-default'  type='submit' value='" . gT("Set survey permissions") . "' />"
                        . "<input type='hidden' name='action' value='setsurveysecurity' />"
                        . "<input type='hidden' name='uid' value='{$postuserid}' /></p>"
                        . "</form>\n";
                    } else {
                        // Username already exists.
                        $addsummary .= "<div class=\"jumbotron message-box message-box-error\">\n";
                        $addsummary .= "<h2>" . gT("Add user") . "</h2>\n";
                        $addsummary .= "<p class='lead'>" . gT("Failed to add user.") . "</p>\n"
                        . "<p>" . gT("User already has permissions for this survey.") . "</p>";
                        $addsummary .= "<p><input class='btn btn-default'  type=\"submit\" onclick=\"window.open('" . $this->getController()->createUrl('admin/surveypermission/sa/view/surveyid/' . $surveyid) . "', '_top')\" value=\"" . gT("Continue") . "\"/></p>\n";
                    }
                } else {
                    $addsummary .= "<div class=\"jumbotron message-box message-box-error\">\n";
                    $addsummary .= "<h2>" . gT("Add user") . "</h2>\n";
                    $addsummary .= "<p class='lead'>" . gT("Failed to add user.") . "</p>\n"
                    . "<p>" . gT("No username selected.") . "</p>\n";
                    $addsummary .= "<p><input class='btn btn-default'  type=\"submit\" onclick=\"window.open('" . $this->getController()->createUrl('admin/surveypermission/sa/view/surveyid/' . $surveyid) . "', '_top')\" value=\"" . gT("Continue") . "\"/></p>\n";
                }
            } else {
                $this->getController()->error('Access denied');
            }

            $addsummary .= "</div></div>\n";

            $aViewUrls['output'] = $addsummary;
        }

        $aData['sidemenu']['state'] = false;
        $aData['title_bar']['title'] = $oSurvey->currentLanguageSettings->surveyls_title . " (" . gT("ID") . ":" . $surveyid . ")";

        $this->_renderWrappedTemplate('authentication', $aViewUrls, $aData);
    }

    /**
     * surveypermission::set()
     * Function responsible to set permissions to a user/usergroup.
     * @param mixed $surveyid
     * @return void
     */
    function set($surveyid)
    {
        $oSurvey = Survey::model()->findByPk($surveyid);
        if (!$oSurvey->hasPermission('surveysecurity', 'update')) {
            throw new CHttpException(403, gT("You do not have permission to access this page."));
        }
        $aData['surveyid'] = $surveyid = sanitize_int($surveyid);
        $aViewUrls = array();

        $action = App()->getRequest()->getParam('action');

        $imageurl = Yii::app()->getConfig('adminimageurl');
        $postuserid = App()->getRequest()->getParam('uid'); /* Allow to get it via GET and POST */
        $postusergroupid = App()->getRequest()->getParam('ugid');
        if ($action == "setsurveysecurity") {
            if (!in_array($postuserid, getUserList('onlyuidarray'))) {
                throw new CHttpException(403, gT("You do not have permission to this user."));
            }
            if ($postuserid == App()->user->getId()) {
                throw new CHttpException(403, gT("You can not set your own permission."));
            }
        } elseif ($action == "setusergroupsurveysecurity") {
            if (!in_array($postusergroupid, getUserGroupList())) {
                throw new CHttpException(403, gT("You do not have permission to this user group."));
            }
            $postuserid = null;
        } else {
            throw new CHttpException(400, gT("Unknown action"));
        }

        $usersummary = "<div id='edit-permission' class='side-body " . getSideBodyClass(false) . "'>";
        if ($action == "setsurveysecurity") {
            $query = "select users_name from {{users}} where uid=:uid";
            $resrow = Yii::app()->db->createCommand($query)->bindParam(":uid", $postuserid, PDO::PARAM_INT)->queryRow();
            $sUsername = $resrow['users_name'];
            $usersummary .= "<h3>" . sprintf(gT("Edit survey permissions for user %s"), "<em>" . \CHtml::encode($sUsername) . "</em>") . "</h3>";
        } else {
            $resrow = UserGroup::model()->find('ugid = :ugid', array(':ugid' => $postusergroupid));
            $sUsergroupName = $resrow['name'];
            $usersummary .= "<h3>" . sprintf(gT("Edit survey permissions for group %s"), "<em>" . \CHtml::encode($sUsergroupName) . "</em>") . "</h3>";
        }
        $usersummary .= '<div class="row"><div class="col-lg-12 content-right">';
        $usersummary .= "<br />"
        . CHtml::form(array("admin/surveypermission/sa/surveyright/surveyid/{$surveyid}"), 'post');
        $PermissionManagerService = new PermissionManager(
            App()->request,
            App()->user,
            $oSurvey,
            App()
        );
        $aPermissions = $PermissionManagerService->getPermissionData($postuserid);
        $usersummary .= App()->getController()->widget(
            'ext.UserPermissionsWidget.UserPermissionsWidget',
            ['aPermissions' => $aPermissions],
            true
        );
        $usersummary .= "<p><input class='btn btn-default hidden'  type='submit' value='" . gT("Save Now") . "' />"
        . "<input type='hidden' name='perm_survey_read' value='1' />"
        . "<input type='hidden' name='action' value='surveyrights' />";

        if ($action == 'setsurveysecurity') {
            $usersummary .= "<input type='hidden' name='uid' value='{$postuserid}' />";
        } else {
            $usersummary .= "<input type='hidden' name='ugid' value='{$postusergroupid}' />";
        }
        $usersummary .= "</form>\n";

        $aViewUrls['output'] = $usersummary;

        $aData['sidemenu']['state'] = false;
        $aData['topBar']['showSaveButton'] = true;
        $aData['title_bar']['title'] = $oSurvey->currentLanguageSettings->surveyls_title . " (" . gT("ID") . ":" . $surveyid . ")";
        $aData['surveybar']['savebutton']['form'] = 'frmeditgroup';
        $aData['surveybar']['saveandclosebutton']['form'] = 'frmeditgroup'; /* Not used */
        $aData['surveybar']['closebutton']['url'] = 'surveyAdministration/view/surveyid/' . $surveyid; // Close button

        $this->_renderWrappedTemplate('authentication', $aViewUrls, $aData);
    }

    /**
     * surveypermission::delete()
     * Function responsible to delete a user/usergroup.
     * @param mixed $surveyid
     * @return void
     */
    function delete($surveyid)
    {
        $this->requirePostRequest();

        $aData['surveyid'] = $surveyid = sanitize_int($surveyid);
        $oSurvey = Survey::model()->findByPk($surveyid);
        $aViewUrls = array();

        $action = App()->getRequest()->getParam('action');

        $imageurl = Yii::app()->getConfig('imageurl');
        $uid = App()->getRequest()->getParam('uid');
        $gid = App()->getRequest()->getParam('gid');
        $postuserid = (!empty($uid)) ? $uid : false;
        $postusergroupid = (!empty($gid)) ? $gid : false; // Not used
        $userList = getUserList('onlyuidarray');

        if ($postuserid && !in_array($postuserid, $userList)) {
            $this->getController()->error('Access denied');
        } elseif ($postusergroupid && !in_array($postusergroupid, $userList)) {
            $this->getController()->error('Access denied');
        }

        if ($action == "delsurveysecurity") {
            $addsummary = "<div id='edit-permission' class='side-body " . getSideBodyClass(false) . "'>";
            $addsummary .= '<div class="row"><div class="col-lg-12 content-right">';
            $addsummary .= "<div class=\"jumbotron message-box\">\n";
            $addsummary .= "<h2>" . gT("Deleting User") . "</h2>\n";


            if (Permission::model()->hasSurveyPermission($surveyid, 'surveysecurity', 'delete')) {
                if (isset($postuserid)) {
                    $dbresult = Permission::model()->deleteAll("uid = :uid AND entity_id = :sid AND entity = 'survey'", array(':uid' => $postuserid, ':sid' => $surveyid));
                    $user = User::model()->findByPk($postuserid);
                    if (!empty($user)) {
                        $addsummary .= "<br />" . gT("Username") . ": " . sanitize_xss_string($user->users_name) . "<br /><br />\n";
                    }
                    $addsummary .= "<div class=\"successheader\">" . gT("Success!") . "</div>\n";
                } else {
                    $addsummary .= "<div class=\"warningheader\">" . gT("Could not delete user. User was not supplied.") . "</div>\n";
                }
                $addsummary .= "<br/><input class='btn btn-default'  type=\"submit\" onclick=\"window.open('" . $this->getController()->createUrl('admin/surveypermission/sa/view/surveyid/' . $surveyid) . "', '_top')\" value=\"" . gT("Continue") . "\"/>\n";
            } else {
                $this->getController()->error('Access denied');
            }
            $addsummary .= "</div></div>\n";

            $aViewUrls['output'] = $addsummary;
        }

        $aData['sidemenu']['state'] = false;
        $aData['title_bar']['title'] = $oSurvey->currentLanguageSettings->surveyls_title . " (" . gT("ID") . ":" . $surveyid . ")";
        //$aData['surveybar']['savebutton']['form'] = 'frmeditgroup';
        //$aData['surveybar']['closebutton']['url'] = 'surveyAdministration/view/surveyid/'.$surveyid;

        $this->_renderWrappedTemplate('authentication', $aViewUrls, $aData);
    }

    /**
     * surveypermission::surveyright()
     * Function responsible to process setting of permission of a user/usergroup.
     * @param int $surveyid Survey ID
     * @return void
     */
    function surveyright(int $surveyid)
    {
        $surveyid = sanitize_int($surveyid);
        $aData['surveyid'] = $surveyid;
        $oSurvey = Survey::model()->findByPk($surveyid);

        if (!$oSurvey->hasPermission('surveysecurity', 'update')) {
            throw new CHttpException(403, gT("You do not have permission to access this page."));
        }
        $aViewUrls = array();
        $imageurl = Yii::app()->getConfig('imageurl');
        /* Only post value : CRSF control */
        $postuserid = App()->getRequest()->getPost('uid');
        $postusergroupid = App()->getRequest()->getPost('ugid');

        if ($postuserid) {
            if (!in_array($postuserid, getUserList('onlyuidarray'))) {
                throw new CHttpException(403, gT("You do not have permission to this user."));
            }
            if ($postuserid == App()->user->getId()) {
                throw new CHttpException(403, gT("You can not set your own permission."));
            }
            $uids = [$postuserid => $postuserid];
        } elseif ($postusergroupid) {
            $isInArray = in_array($postusergroupid, getUserGroupList());

            if (!$isInArray) {
                throw new CHttpException(403, gT("You do not have permission to this user group."));
            }

            $permissionUserID = Permission::model()->getUserId();
            $surveysGroups    = SurveysGroups::model()->findByPk($surveyid);
            if (!$surveysGroups == null) {
                $surveysGroupsOwnerID = $surveysGroups->getOwnerId();
                $oUserInGroups = UserInGroup::model()->findAll(
                    'ugid = :ugid AND uid <> :currentUserId AND uid <> :surveygroupsOwnerId',
                    array(
                        ':ugid' => $postusergroupid,
                        ':currentUserId' => $permissionUserID, // Don't need to set to current user
                        ':surveygroupsOwnerId' => $surveysGroupsOwnerID, // Don't need to set to owner (?) , get from surveyspermission
                    )
                );
            } else {
                $oUserInGroups = UserInGroup::model()->findAll(
                    'ugid = :ugid AND uid <> :currentUserId',
                    array (
                        ':ugid' => $postusergroupid,
                        ':currentUserId' => $permissionUserID
                    )
                );
            }

            $uids = CHtml::listData($oUserInGroups, 'uid', 'uid');
        } else {
            throw new CHttpException(400, gT("Invalid parameters."));
        }

        $addsummary = "<div id='edit-permission' class='side-body " . getSideBodyClass(false) . "'>";
        $addsummary .= '<div class="row"><div class="col-lg-12 content-right">';

        $addsummary .= "<div class=\"jumbotron message-box\">\n";
        $addsummary .= "<h2>" . gT("Edit survey permissions") . "</h2>\n";

        $user = App()->user;
        $request = App()->request;
        $success = true;
        /* restrict to model (was in $set, but not needed : we have only Survey currently */
        $PermissionManagerService = new PermissionManager(
            $request,
            $user,
            $oSurvey,
            App()
        );
        foreach ($uids as $uid) {
            $success = $success && $PermissionManagerService->setPermissions($uid);
        }

        if ($postuserid) {
            /* We update an user : redirect to Permission edit or to Permsoion view */
            if ($success) {
                Yii::app()->setFlashMessage(gT("Survey permissions were successfully updated."));
            } else {
                Yii::app()->setFlashMessage(gT("Failed to update survey permissions!"));
            }
            if (App()->getRequest()->getPost('close-after-save')) {
                Yii::app()->request->redirect(Yii::app()->getController()->createUrl('admin/surveypermission/sa/view', array('surveyid' => $surveyid)));
            }
            Yii::app()->request->redirect(Yii::app()->getController()->createUrl('admin/surveypermission/sa/set', array('action' => 'setsurveysecurity', 'surveyid' => $surveyid, 'uid' => $postuserid)));
        }
        /* We update a group */
        if ($success) {
            $addsummary .= "<div class=\"successheader\">" . gT("Survey permissions for all users in this group were successfully updated.") . "</div>\n";
        } else {
            $addsummary .= "<div class=\"errorheader\">" . gT("Failed to update permissions for all users in this group.") . "</div>\n";
        }
        $addsummary .= "<br/><input class='btn btn-default' type=\"submit\" onclick=\"window.open('" . $this->getController()->createUrl('admin/surveypermission/sa/view/surveyid/' . $surveyid) . "', '_top')\" value=\"" . gT("Continue") . "\"/>\n";
        $addsummary .= "</div></div></div></div>\n";
        $aViewUrls['output'] = $addsummary;

        $aData['sidemenu']['state']  = false;
        $aData['title_bar']['title'] = $oSurvey->currentLanguageSettings->surveyls_title . " (" . gT("ID") . ":" . $surveyid . ")";

        $this->_renderWrappedTemplate('', $aViewUrls, $aData);
    }

    /**
     * Renders template(s) wrapped in header and footer
     *
     * @param string $sAction Current action, the folder to fetch views from
     * @param string|array $aViewUrls View url(s)
     * @param array $aData Data to be passed on. Optional.
     */
    protected function _renderWrappedTemplate($sAction = 'authentication', $aViewUrls = array(), $aData = array(), $sRenderFile = false)
    {
        parent::_renderWrappedTemplate($sAction, $aViewUrls, $aData, $sRenderFile);
    }
}
