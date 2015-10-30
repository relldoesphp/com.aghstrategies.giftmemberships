<?php

/**
 * @file
 * Gift Memberships extension for CiviCRM.
 */

require_once 'giftmemberships.civix.php';

/**
 * Implements hook_civicrm_buildForm().
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_buildForm
 */
function giftmemberships_civicrm_buildForm($formName, &$form) {
    if ($formName == "CRM_Price_Form_Field") {
        // Add gift membership to select for html_type.
        $html = $form->getElement('html_type');
        $html->addOption('Gift Memberhship Field', 'gift_membership');
        $html->addOption('Redeem Gift Membership', 'redeem_membership');
        // Add hidden field that changes value if gift memberhsip is selected.
        $form->addElement('hidden', 'gift-check', '0');
        $form->addElement('hidden', 'redeem-check', '0');
        // Add membership type dropdown using api.
        $membershipSelect = array();
        try {
            $result = civicrm_api3('MembershipType', 'get', array( 'sequential' => 1, ));
        } catch (CiviCRM_API3_Exception $e) {
            $error = $e->getMessage();
        }
        foreach ($result['values'] as $membershipType) {
            $membershipSelect[$membershipType['id']] = $membershipType['name'].': '.$membershipType['minimum_fee'];
        }
        $form->add('select', "membershipselect", ts('Select Membership Type'), $membershipSelect);
        // Add template for select field to form .
        $templatePath = realpath(dirname(__FILE__)."/templates");
        CRM_Core_Region::instance('page-body')->add(array(
                                                        'template' => "{$templatePath}/pricefieldOthersignup.tpl"
                                                    ));
        // If Editing existing price field check for memberhsip type .
        $pfid = $form->getVar('_fid');
        if (!empty($pfid)) {
            // First check for name .
            try {
                $result = civicrm_api3('PriceField', 'getsingle', array(
                                           'sequential' => 1,
                                           'id' => $pfid,
                                       ));
                if ($result['name'] == "_gift_membership") {
                    // Set value of html_type .
                    $form->getElement('html_type')->setValue('gift_membership');
                    // Check for membership type in DB .
                    $sql = "SELECT pfid FROM civicrm_gift_membership_price_fields WHERE pfid = {$pfid};";
                    $dao = CRM_Core_DAO::executeQuery($sql);
                    if ($dao->fetch()) {
                        $membershipType = $dao->membership_type_id;
                        $form->getElement('membershipselect')->setValue($membershipType);
                    }
                }
            } catch (CiviCRM_API3_Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
    if ($formName == "CRM_Contribute_Form_Contribution_Main") {
        $priceSet = $form->_priceSet['fields'];
        foreach ($priceSet as $key => $value) {
            if ($value['name'] == '_gift_membership') {
                $fid = $key."_gift-codes";
                $form->addElement('hidden', $fid, "");
            }
            if ($value['name'] == '_redeem_membership') {
                $fid = $key."_redeem-code";
                $form->addElement('hidden', $fid, "");
            }
        }
        // Load javascript file for the form.
        CRM_Core_Resources::singleton()->addScriptFile('com.aghstrategies.giftmemberships', 'js/giftpricefield.js', 'html-header');
    }
    // Close Contribution Page.
}

/**
 * Implements hook_civicrm_postProcess().
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postProcess
 */
function giftmemberships_civicrm_postProcess($formName, &$form) {
    if ($formName == "CRM_Price_Form_Field" && ($form->_submitValues['gift-check'] == 1 || $form->_submitValues['redeem-check'] == 1)) {
        $priceSetId = $form->get('sid');
        $label = $form->_submitValues['label'];
        /* Get price fields belonging to price set */
        try {
            $result = civicrm_api3('PriceField', 'get', array(
                                       'sequential' => 1,
                                       'price_set_id' => $priceSetId,
                                   ));
            foreach ($result['values'] as $value) {
                if ($value['label'] == $label) {
                    // Update Name in Pricefield table .
                    $priceFieldId = $value['id'];
                    if ($form->_submitValues['gift-check'] == 1) {
                        $name = '_gift_membership';
                    } elseif ($form->_submitValues['redeem-check'] == 1) {
                        $name = '_redeem_membership';
                    }
                    $update = civicrm_api3('PriceField', 'create', array(
                                               'sequential' => 1,
                                               'price_set_id' => $priceSetId,
                                               'id' => $priceFieldId,
                                               'name' => $name,
                                           ));
                    // Save membership_type in gift_membership_price_fields table .
                    if ($update['is_error'] != 1 && $form->_submitValues['gift-check'] == 1) {
                        $memType = $form->_submitValues['membershipselect'];
                        $sql = "SELECT pfid FROM civicrm_gift_membership_price_fields WHERE pfid = {$priceFieldId};";
                        $dao = CRM_Core_DAO::executeQuery($sql);
                        if ($dao->fetch()) {
                            $giftPFID = $dao->pfid;
                        }
                        if ($giftPFID) {
                            $sql = "UPDATE civicrm_gift_membership_price_fields SET membership_type_id={$memType} WHERE pfid={$giftPFID};";
                        } else {
                            $sql = "INSERT INTO civicrm_gift_membership_price_fields (pfid, membership_type_id) VALUES ({$priceFieldId}, {$memType});";
                        }
                        $dao = CRM_Core_DAO::executeQuery($sql);
                    }
                }
            }
        } catch (CiviCRM_API3_Exeception $e) {
            $error = $e->getMessage();
        }
    }
    if ($formName == "CRM_Contribute_Form_Contribution_Confirm") {
        $submitValues = $form->_params;
        $contributionId = $form->_contributionID;
        $giverId = $form->_contactID;
        foreach ($submitValues as $key => $value) {
            if (strpos($key, '_gift-codes') !== false) {
                $giftcodes = true;
                $price = explode("_", $key);
                $pfid = $price[0];
                $sql = "SELECT membership_type_id FROM civicrm_gift_membership_price_fields WHERE pfid = {$pfid};";
                $dao = CRM_Core_DAO::executeQuery($sql);
                if ($dao->fetch()) {
                    $membershipType = $dao->membership_type_id;
                }
                if (strpos($value, '::')) {
                    $codes = explode('::', $value);
                    foreach ($codes as $code) {
                        $sql = "INSERT INTO civicrm_gift_membership_codes (membership_id, code, membership_type, contribution_id, giver_id) VALUES (NULL, '{$code}', '{$membershipType}', '{$contributionId}','{$giverId}' );";
                        $dao = CRM_Core_DAO::executeQuery($sql);
                    }
                } else {
                    if ($value != "") {
                        $sql = "INSERT INTO civicrm_gift_membership_codes (membership_id, code, membership_type, contribution_id, giver_id) VALUES (NULL, '{$value}', '{$membershipType}', '{$contributionId}', '{$giverId}');";
                        $dao = CRM_Core_DAO::executeQuery($sql);
                    }
                }
            }
            if (strpos($key, '_redeem-code') !== false) {
                //Create Membership using api
                $contact_id = $form->_contactID;
                $price = explode("_", $key);
                $pfid = $price[0];
                $priceName = "price_".$pfid;
                $code = $submitValues[$priceName];
                $sql = "SELECT * FROM civicrm_gift_membership_codes WHERE code = '{$code}'";
                $dao = CRM_Core_DAO::executeQuery($sql);
                if ($dao->fetch()) {
                    $memType = $dao->membership_type;
                    $giver = $dao->giver_id;
                    $displayName = CRM_Contact_BAO_Contact::displayName($giver);
                    $source = "Gift Membership from ".$displayName;
                    $result = civicrm_api3('Membership', 'create', array(
                                               'sequential' => 1,
                                               'membership_type_id' => $memType,
                                               'contact_id' => $contact_id,
                                               'source' => $source,
                                           ));
                    if ($result['is_error'] != 1) {
                        $memId = $result['id'];
                        $sql = "UPDATE civicrm_gift_membership_codes SET membership_id='{$memId}' WHERE code='{$code}';";
                        $dao = CRM_Core_DAO::executeQuery($sql);
                    }
                }
            }
        }//end foreach
        /**** Send Email With Codes ****/
        if (isset($giftcodes) && $giftcodes == true) {
            // Make beginning of Code Table .
            $codeTable = "<h3>Gift Membership Codes</h3><table width='500px' style='border:1px solid #999;margin:1em 0em 1em;border-collapse:collapse'><thead><tr><th style='text-align:left;padding:4px;border-bottom:1px solid #999;background-color:#eee'>Membership</th><th style='text-align:left;padding:4px;border-bottom:1px solid #999;background-color:#eee'>Code</th></tr></thead><tbody>";
            $sql = "SELECT * FROM civicrm_gift_membership_codes WHERE contribution_id = '{$contributionId}'";
            $dao = CRM_Core_DAO::executeQuery($sql);
            // Add row for each code with corresponding membership purchased .
            while ($dao->fetch()) {
                $code = $dao->code;
                $memType = $dao->membership_type;
                $result = civicrm_api3('MembershipType', 'getsingle', array('sequential' => 1,'id' => $memType));
                $memName = $result['name']." Membership";
                $codeTable .= "<tr><td>{$memName}</td><td>{$code}</td></tr>";
            }
            $codeTable .= "</tbody></table>";
            // get organization id of site .
            $sql = "SELECT contact_id FROM civicrm_domain WHERE id = 1";
            $dao = CRM_Core_DAO::executeQuery($sql);
            if ($dao->fetch()) {
                $orgEmail = CRM_Contact_BAO_Contact::getPrimaryEmail($dao->contact_id);
            }
            // Prepare Params and Send Email .
            $email = CRM_Contact_BAO_Contact::getPrimaryEmail($giverId);
            $contactName = CRM_Contact_BAO_Contact::displayName($giverId);
            $mailParams = array();
            $mailParams['from'] = $orgEmail;
            $mailParams['toName'] = $contactName;
            $mailParams['subject'] = 'Gift Membership codes';
            $mailParams['toEmail'] = $email;
            $mailParams['html'] = $codeTable;
            $mailed = CRM_Utils_Mail::send($mailParams);
        }
    }
}

/**
 * Implements hook_civicrm_validateForm().
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_validatForm
 */
function giftmemberships_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
    if ($formName == "CRM_Contribute_Form_Contribution_Main") {
        // Search through Price fields for redeem-code hidden field .
        foreach ($fields as $key => $field) {
            if (strpos($key, '_redeem-code') !== false) {
                unset($form->_errors['_qf_default']);
                $price = explode("_", $key);
                $pfid = $price[0];
                $priceName = "price_".$pfid;
                $code = $fields[$priceName];
                // Check for Code in database .
                $sql = "SELECT membership_id FROM civicrm_gift_membership_codes WHERE code = '{$code}'";
                $dao = CRM_Core_DAO::executeQuery($sql);
                if ($dao->fetch()) {
                    $membership_id = $dao->membership_id;
                    $membership_type = $dao->membership_type;
                    if ($membership_id != null) {
                        // If Membership ID is not NULL then it has already been used, return error .
                        $form->setElementError($priceName, null);
                        $errors[$priceName] = ts('Validation code has already been used');
                    } else {
                        $form->setElementError($priceName, null);
                    }
                } else {
                    // If fetch fails then code is not in the system, return error .
                    $form->setElementError($priceName, null);
                    $errors[$priceName] = ts('Validation code in not our system');
                }
            }
        }
    }
}

/**
 * Implements hook_civicrm_alterContent()
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterContent
 */
function giftmemberships_civicrm_alterContent(&$content, $context, $tplName, &$object) {
    // Add code table to view contribution page .
    if ($tplName == "CRM/Contribute/Page/Tab.tpl") {
        $contributionId = $object->_id;
        // Prepare table .
        $codeTable = "<div id='codeTable'><h3>Gift Membership Codes</h3><table width='100%' style=><thead><tr><th>Membership</th><th>Code</th><th>Status</th></tr></thead><tbody>";
        $sql = "SELECT * FROM civicrm_gift_membership_codes WHERE contribution_id = '{$contributionId}'";
        $dao = CRM_Core_DAO::executeQuery($sql);
        while ($dao->fetch()) {
            $codeExists = true;
            $code = $dao->code;
            $memType = $dao->membership_type;
            $memId = $dao->membership_id;
            // If membership is available say so,if not change status to redeemed .
            if ($memId == null) {
                $status = "available";
            } else {
                $status = "redeemed";
            }
            // Add row for each code with corresponding membership .
            $result = civicrm_api3('MembershipType', 'getsingle', array('sequential' => 1,'id' => $memType));
            $memName = $result['name']." Membership";
            $codeTable .= "<tr><td>{$memName}</td><td>{$code}</td><td>{$status}</td></tr>";
        }
        $codeTable .= "</tbody></table></div>";
        // Add table to page and javascript to reposition the table .
        if ($codeExists == true) {
            $content .= $codeTable;
            $content .= '<script>cj(".crm-info-panel:first").after(cj("#codeTable"));</script>';
        }
    }
    // Adds display of codes purchased to ThankYou Page .
    if ($tplName == "CRM/Contribute/Form/Contribution/ThankYou.tpl") {
        $params = $object->_params;
        $giftExists = false;
        $codeDisplay = "<fieldset id='gift-code_group' class='label-left crm-profile-view'><div class='header-dark'>Gift Membership Codes</div>";
        foreach ($params as $key => $value) {
            if (strpos($key, '_gift-codes') !== false && $value != "") {
                $giftExists = true;
                $price = explode("_", $key);
                $pfid = $price[0];
                $sql = "SELECT membership_type_id FROM civicrm_gift_membership_price_fields WHERE pfid = {$pfid};";
                $dao = CRM_Core_DAO::executeQuery($sql);
                if ($dao->fetch()) {
                    $membershipType = $dao->membership_type_id;
                }
                try {
                    $result = civicrm_api3('MembershipType', 'getsingle', array('sequential' => 1,'id' => $membershipType));
                    $name = $result['name'];
                    $codes = explode('::', $value);
                    $codeDisplay .= "<div class='crm-section form-item'><div class='label'>{$name} Membership Codes</div><div class='content'>";
                    foreach ($codes as $code) {
                        $codeDisplay .= "<span>".$code."</span></br>";
                    }
                    $codeDisplay .= "<div class='clear'></div></div>";
                } catch (CiviCRM_API3_Exeception $e) {
                    // @TODO Handle error?
                    $error = $e->getMessage();
                }
            }
        }
        $codeDisplay .= "</fieldset>";
        if ($giftExists !== false) {
            $content .= $codeDisplay;
            $content .= '<script>cj("#gift-code_group").prependTo(".amount_display-group");</script>';
        }
    }
    if ($tplName == "CRM/Contribute/Form/Contribution/Confirm.tpl" || $tplName == "CRM/Contribute/Form/Contribution/ThankYou.tpl") {
        $fields = $object->_params;
        foreach ($fields as $key => $field) {
            if (strpos($key, '_redeem-code') !== false) {
                $price = explode("_", $key);
                $pfid = $price[0];
                $priceName = "price_".$pfid;
                $code = $fields[$priceName];
                $sql = "SELECT membership_type FROM civicrm_gift_membership_codes WHERE code = '{$code}'";
                $dao = CRM_Core_DAO::executeQuery($sql);
                if ($dao->fetch()) {
                    $memtype = $dao->membership_type;
                    $result = civicrm_api3('MembershipType', 'getsingle', array('sequential' => 1,'id' => $memtype));
                    $memName = $result['name']." Membership";
                    try {
                        $result2 = civicrm_api3('PriceField', 'getsingle', array('sequential' => 1, 'id' => $pfid));
                        $label = $result2['label'];
                        $content .= '<script>
                        var oldhtml = cj("div.amount_display-group").html();
                        var newhtml = oldhtml.replace(/'.$label.'/g, "'.$memName.'");
                        var newerhtml = newhtml.replace(/Qty/g, "Code");
                        cj(".amount_display-group ").html(newerhtml);
                      </script>';
                    } catch (CiviCRM_API3_Exeception $e) {
                        // @TODO Handle error?
                        $error = $e->getMessage();
                    }
                }
            }
        }
    }
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civihacrm_config
 */
function giftmemberships_civicrm_config(&$config) {
    _giftmemberships_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function giftmemberships_civicrm_xmlMenu(&$files) {
    _giftmemberships_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function giftmemberships_civicrm_install() {
    return _giftmemberships_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function giftmemberships_civicrm_uninstall() {
    return _giftmemberships_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function giftmemberships_civicrm_enable() {
    return _giftmemberships_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function giftmemberships_civicrm_disable() {
    return _giftmemberships_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function giftmemberships_civicrm_upgrade($op, CRM_Queue_Queue $queue = null) {
    return _giftmemberships_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function giftmemberships_civicrm_managed(&$entities) {
    return _giftmemberships_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_caseTypes
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function giftmemberships_civicrm_caseTypes(&$caseTypes) {
    _giftmemberships_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implementation of hook_civicrm_alterSettingsFolders
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function giftmemberships_civicrm_alterSettingsFolders(&$metaDataFolders = null) {
    _giftmemberships_civix_civicrm_alterSettingsFolders($metaDataFolders);
}
