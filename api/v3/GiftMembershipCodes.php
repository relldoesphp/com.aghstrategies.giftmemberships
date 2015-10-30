<?php

/**
 * Get fields for GiftMembershipCodes api calls.
 *
 * @TODO Suspect this is not how CiviCRM extensions should implement
 * getFields(), since very few core API methods do the same. But it
 * works, so it'll do for now.
 *
 * @param array $params
 *
 * @return array
 */
function civicrm_api3_gift_membership_codes_getfields($params) {
    $result = array(
        'id' => array(
            'name' => 'id',
            'title' => 'ID',
            'type' => CRM_Utils_Type::T_INT,
        ),
        'code' => array(
            'name' => 'code',
            'title' => 'Gift Code',
            'type' => CRM_Utils_Type::T_STRING,
        ),
        'contribution_id' => array(
            'name' => 'contribution_id',
            'title' => 'Contribution ID',
            'type' => CRM_Utils_Type::T_INT,
        ),
        'membership_id' => array(
            'name' => 'membership_id',
            'title' => 'Membership ID',
            'type' => CRM_Utils_Type::T_INT,
        ),
        'membership_type' => array(
            'name' => 'membership_type',
            'title' => 'Membership Type ID',
            'type' => CRM_Utils_Type::T_INT,
        ),
        'giver_id' => array(
            'name' => 'giver_id',
            'title' => 'Giver ID',
            'type' => CRM_Utils_Type::T_INT,
        ),
    );
    return civicrm_api3_create_success($result, $params, 'GiftMembershipCodes', 'getfields');
}
