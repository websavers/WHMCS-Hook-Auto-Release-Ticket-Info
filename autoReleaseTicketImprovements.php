<?php

// Version 0.1

use WHMCS\Database\Capsule;

// Ticket subject text that triggers this hook to proceed
define('SUBJECT', 'Service Provisioned');

// Regular expression to search for
define('BODY_REGEX', '/(Service|Addon) ID # (\d+) was just auto provisioned/');

// https://developers.whmcs.com/hooks-reference/ticket/#ticketopenadmin
add_hook('TicketOpenAdmin', 1, function($vars) {

    //logActivity('[Hook autoReleaseTicketImprovements] Ticket Opened');

    $ticketid   = $vars['ticketid'];
    $userid     = $vars['userid'];
    $deptid     = $vars['deptid'];
    $subject    = $vars['subject'];
    $message    = $vars['message'];
	
    // Only proceed if there's a subject match
    if ($subject != SUBJECT){
        return true;
    }

    function log_error($msg){
        logActivity('[Hook autoReleaseTicketImprovements] Error: ' . $msg);
    }
	
    $found = preg_match(BODY_REGEX, $message, $match);

    if (!$found || $match == null){
        log_error('preg_match did not result in required number of matches');
        return true;
    }

    if (count($match) != 3){ //$match[0] is always the complete string, then follows specific capture groups
        log_error('preg_match did not result in required number of matches');
        return true;
    }

    $type = $match[1];
    $relid = $match[2];
        

    if ($relid === 0){
        log_error('ID not found in message body');
        return true;
    }

    if ($type == "Addon"){
        $addon = Capsule::table('tblhostingaddons')
            ->join('tbladdons', 'tblhostingaddons.addonid', '=', 'tbladdons.id')
            ->where('tblhostingaddons.id', $relid)
            ->first();

        $serviceid = $addon->hostingid;
        $servicename = $addon->name;
    }
    else if ($type == "Service"){
        $service = Capsule::table('tblhosting')
            ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
            ->where('tblhosting.id', $relid)
            ->first();

        $serviceid = $relid;
        $servicename = $service->name . "(" . $service->domain . ")";
    }
    else{
        log_error('Incorrect type found');
        return true;
    }

    $postData = array(
        'ticketid'  => $ticketid,
        'subject'   => "Setup of $servicename",
        'message'   => "This ticket has been created to track the setup of new service: $servicename.\n\n$message",
    );

    // Update the actual ticket data.
    $results = localAPI('UpdateTicket', $postData);
    if ($results['result'] !== 'success'){
        log_error('Failure to update ticket data');
    }
    
    return true;

});
