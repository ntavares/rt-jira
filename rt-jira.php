<?php

/*
 * DOC RT : https://github.com/dersam/RTPHPLib/blob/master/example.php
 * DOC JIRA : https://github.com/cpliakas/jira-client
 * 
 * 
 * 
 * 
 * 
 * 
 * 
 * 
 * */
 
 
require 'vendor/autoload.php';
require 'vendor/chobie/jira-api-restclient/Jira/Autoloader.php';
Jira_Autoloader::register();

use Jira\JiraClient;
//**use Jira\Remote\RemoteIssue;
use \RequestTracker;


define('RT_URL', 'https://your.rt.url');
define('RT_USER', '');
define('RT_PASSWORD', '');

define('JIRA_URL', 'https://your.jira.url');

define('JIRA_USER', 'youruser');
define('JIRA_PASSWORD', 'yourpassword');
define('JIRA_TICKET_TYPE', 3 /* Task */);

$JIRA_WATCHERS = array('ntavares');

 
 
function printSyntax() {
    global $_SERVER;
    print "Syntax: ".$_SERVER['SCRIPT_FILENAME']." <rt_id> <jira_project_key>\n";
}

if ( $_SERVER['argc'] < 3 ) {
    printSyntax();
    exit(1);
}

$RT_ID = intval($_SERVER['argv'][1]);
$JIRA_KEY = $_SERVER['argv'][2];






/*
* ****************************** INIT ******************************
*/

// Create a client and provide a base URL
$rt = new RequestTracker(RT_URL, RT_USER, RT_PASSWORD);
$jira = new JiraClient(JIRA_URL);

/*
* ***************************** RT PART ******************************
*/
print "Retrieving RT #$RT_ID\n";
$tkUrl = RT_URL."/Ticket/Display.html?id=".$RT_ID;
print " + ".$tkUrl."\n";

$tkDetails = $rt->getTicketProperties($RT_ID);
//print_r($response); 
if ( !isset($tkDetails['Queue']) ) {
    error_log(" + ERROR: Couldn't find RT #$RT_ID\n");
    die(1);
}


print "Found ticket #$RT_ID\n";
print " + Subject: {$tkDetails['Subject']}\n";
print "   + Queue..: {$tkDetails['Queue']}\n";
print "   + Creator: {$tkDetails['Creator']}\n";


$response = $rt->getTicketAttachments($RT_ID);
//print_r($response);
$tkAttachments = isset($response['Attachments'])?$response['Attachments']:array();
print "   + Attachments (".count($tkAttachments)."): ".implode(', ', array_keys($tkAttachments))."\n";

$tkBodyKey = -1;
$tkBodySize = -1;
foreach ($tkAttachments as $key => $val) {
    //**print "KEY: $key, VAL= $val\n";
    if ( preg_match('/text\/plain/', $val) ) {
        list ($mime, $size) = explode(' / ', trim($val));
        $size = str_replace(')', '', trim($size));
        if ( trim($size) != '0b' ) {
            //print " + @ $key ($mime, $size)\n";
            $tkBodyKey = $key;
            $tkBodySize = $size;
            break;
        }
    }
}
print "   + Selected attachment as body: $tkBodyKey ($tkBodySize)\n";
if ( $tkBodyKey === -1 ) {
    print " + ERROR: Can't fetch issue content\n";
    die(1);
}
$response = $rt->getAttachmentContent($RT_ID, $tkBodyKey);
//print "BODY:\n$response\n";
$tkBody = $response;


/*
* ***************************** JIRA PART ******************************
*/

print "Publishing to JIRA as: ".JIRA_USER."\n";
try {
    $jira2 = new Jira_Api(
        JIRA_URL,
        new Jira_Api_Authentication_Basic(JIRA_USER, JIRA_PASSWORD)
    );

    $tkBody = "{code}\n".$tkBody."\n{code}";
    $options = array( 'description' => $tkBody );
    $tkLabel = 'RT #'.$RT_ID.' - '.$tkDetails['Subject'];
    $result = $jira2->createIssue($JIRA_KEY, $tkLabel, JIRA_TICKET_TYPE, $options);
    $issueDetails = $result->getResult();
    if ( !isset($issueDetails['key']) ) {
        print " + ERROR: Failed to acquire new issue key.\n";
        die(2);
    }
    $issueKey = $issueDetails['key'];
    print " + Created JIRA Issue: ".$issueKey."\n";
    $issueUrl = JIRA_URL."/browse/{$issueKey}";
    print " + URL: $issueUrl\n";
      
    $result = $jira2->addRemoteLinks($issueKey, $tkUrl, $tkLabel);
    $linkDetails = $result->getResult();
    print " + Created remote link w/ id={$linkDetails['id']}\n";
    
/*    for ($i=0; $i<count($JIRA_WATCHERS); $i++) {
        print " + Adding watcher: {$JIRA_WATCHERS[$i]}\n";
        $jira2->addWatcher($issueKey, $JIRA_WATCHERS[$i]);
    }
*/    
} catch (Exception $e) {
    print " + ERROR: ".$e->getMessage()."\n";
    die (2);
}

/*
* ***************************** RT BACKLINK ******************************
*/
print "Back to RT\n";
$commentBody = " This issue has been linked with JIRA: $issueUrl";
$response = $rt->doTicketComment($RT_ID, array('Text' => htmlentities($commentBody)) );
//**print_r($response);
print " + Added comment reference to: $issueUrl\n";
