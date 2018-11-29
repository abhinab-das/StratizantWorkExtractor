<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
/**
 * Update these configuration variables to  match your own JIRA settings.
 * 
 * Refer to README.md for more information.
 */
$cfg = [
  'jira_host_address'  => 'https://stratizantcorp.atlassian.net',
  'jira_user_email'    => 'abhinab@aixchange.co.in',
  'jira_user_password' => 'Fordscuberia@350',
  'max_results' => '500',
  'assignee' => 'abhinab'
];

$assignee_list = array('abhinab','sharath','dinesh','mahesh1','pirthap','jayam','sidt','smahajan','geetha');



    
//    . "&maxResults=" . $cfg['max_results']
/**
 * Local Composer
 */
require 'vendor/autoload.php';

use League\Csv\Writer;

session_start();

$error = "";

function getData($worklogauthor) {
  global $cfg;
  global $error;
  global $url;

  $end_date = date('Y-m-d');
  $lastWeek = time() - (1 * 24 * 60 * 60);
  $start_date = date('Y-m-d',$lastWeek);

  $sdate = '2018-11-16';
  $edate = '2018-11-26';

  $ch = curl_init();
  $url = $cfg['jira_host_address'] . "/rest/api/2/search?jql=worklogAuthor=".$worklogauthor."%20AND%20worklogDate%3CendOfWeek()%20AND%20worklogDate%3EstartOfWeek()%20ORDER%20BY%20assignee%20ASC%20%2Cproject%20ASC";
  //echo($url);
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  $headers = array();
  $headers[] = "Authorization: Basic " . base64_encode($cfg['jira_user_email'] . ':' . $cfg['jira_user_password']);
  $headers[] = "Content-Type: application/json";
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

  $result = curl_exec($ch);

  if (curl_errno($ch)) {
    $error = 'Error: ' . curl_error($ch);
  }
  curl_close ($ch);

  return $result;
}

function buildRowFromData($data,$worklogauthor) {
  global $error;


  if (empty($data)) {
    $error = 'Error: Request did not return any results, check login information or project key'; return false;
  }

  $arr = [];

  foreach($data as $i => $issue) {
    $field = $issue['fields'];

    $arr[$i]['key'] = $issue['key'];
    $arr[$i]['assignee'] = $field['assignee']['displayName'];
    $arr[$i]['work_log_author'] = $worklogauthor;
    $arr[$i]['status']   = $field['status']['name'];
    $arr[$i]['priority'] = $field['priority']['name'];
    $arr[$i]['summary']  = $field['summary'];
    $arr[$i]['time_estimate'] = $field['timeestimate'];
    $arr[$i]['total_time_spent'] = $field['aggregatetimespent'];
    $arr[$i]['total_time_logged'] = [];
  }

  return $arr;
}

function exportData(){
  global $assignee_list;
  $rows = [];
  $rows_array = [];

  foreach($assignee_list as $a){
    $result = getData($a);
    $decodedData = json_decode($result, true);
    $rows_array = buildRowFromData($decodedData['issues'],$a);

    if(!empty($rows_array)){
      $rows=array_merge($rows,$rows_array);
    }
  }
  return $rows;

}

if (!empty($_POST)) :
  if ($_POST["submit"] === "fetch" ) {
    $rows = exportData();
    
  } else if ($_POST["submit"] === "export") {
    $rows = exportData();
    $writer = Writer::createFromFileObject(new SplTempFileObject());

    $csvHeader = array('Key', 'Assignee', 'Time Logged By', 'Status', 'Priority', 'Summary', 'Time Estimated', 'Total Time Spent');  

    $writer->insertOne($csvHeader);    
    $writer->insertAll($rows);

    $time = date('d-m-Y-H:i:s');

    $writer->output('jira-export-' . $time . '.csv');
      }
  else if ($_POST["submit"] === "sendMail") {
    $mail = new PHPMailer(true);                              // Passing `true` enables exceptions
    try {
      //Server settings
      $mail->SMTPDebug = 0;                                 // Enable verbose debug output
      $mail->isSMTP();                                      // Set mailer to use SMTP
      $mail->Host = 'smtp.gmail.com';  // Specify main and backup SMTP servers
      $mail->SMTPAuth = true;                               // Enable SMTP authentication
      $mail->Username = 'abhinabdas4u@gmail.com';                 // SMTP username
      $mail->Password = 'kavya12345';                           // SMTP password
      $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
      $mail->Port = 587;                                    // TCP port to connect to
  
      //Recipients
      $mail->setFrom('abhinabdas4u@gmail.com', 'JIRA Worklog Report');
      $mail->addAddress('abhinab@fiscalhive.com', 'Abhinab Das');     // Add a recipient
      $mail->addAddress('sidt@stratizant.com', 'Sid Tamilselvam');     // Add a recipient
      $mail->addAddress('jayam@stratizant.com', 'Jaya M');     // Add a recipient
  
      //Attachments
      $rows = exportData();
      $file = new SplTempFileObject();
      $writer = Writer::createFromFileObject($file);

      $csvHeader = array('Key', 'Assignee', 'Time Logged By', 'Status', 'Priority', 'Summary', 'Time Estimated', 'Total Time Spent');  

      $writer->insertOne($csvHeader);    
      $writer->insertAll($rows);

      //echo $writer->getContents();

      // Create the attachment with our data
      // $attachment = (new \Swift_Attachment())
      // ->setFilename('JIRA_worklog_report.csv')
      // ->setContentType('text/csv')
      // ->setBody("$writer") ;

      $time = date('d-m-Y');
      //$mail->addAttachment($file);         // Add attachments
      $mail->addStringAttachment($writer->__toString(), 'JIRA_worklog_report.csv');
      //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
  
      //Content
      $mail->isHTML(true);                                  // Set email format to HTML
      $mail->Subject = 'Stratizant : JIRA Worklog Report ( '.date("d-M-Y", strtotime('sunday last week')).' to '.date("d-M-Y", strtotime('saturday this week')).' )';
      $mail->Body    = 'Hi,<br/><br/>Please find the JIRA report attached.<br/><br/><br/>Thanks,<br/>JIRA Worklog Admin';
      $mail->AltBody = 'Please find the JIRA report attached.';
  
      $mail->send();
      //echo 'Message has been sent';
  } catch (Exception $e) {
      //echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
  }
  }
endif?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>JIRA Export</title>
    <link rel="stylesheet" href="https://unpkg.com/purecss@0.6.2/build/pure-min.css" integrity="sha384-UQiGfs9ICog+LwheBSRCt1o5cbyKIHbwjWscjemyBMT9YCUMZffs6UqUTd0hObXD" crossorigin="anonymous">  
    <style>body{font-family:Arial;padding:30px}label{margin-top:50px;width:360px;font-size:22px;font-weight:700;padding-bottom: 10px;}input{margin-top:5px;height:40px;width:400px;font-size:20px;padding-left:15px;text-transform:uppercase;vertical-align:bottom;}button{color:#fff;vertical-align:bottom;height:46px;}.button-success{background:#1cb841;color:#fff;}.button-secondary{background:#42b8dd}.button-small{font-size:85%}.button-xlarge{font-size:125%}</style>
  </head>
  <body>
  <div id="header" style="box-shadow: 0px 2px #888888;width:100%; height:20%; border-radius:25px;">
    <h2 style="text-align:center;"><u>Welcome to Stratizant JIRA Worklog</u></h2>
    <form method="POST">
      <div style="text-align:center;margin-bottom:10px;">
        <button name="submit" class="button-success button-xlarge pure-button" value="fetch">Run Report</button>
        <button name="submit" class="button-secondary button-small pure-button" value="export">Export CSV</button>
        <button name="submit" class = "button-warning button-xlarge pure-button" value ="sendMail">Send Mail</button>
      </div>
      </div>
      <div style="width:100%;height:100%;">
      <?php if (!empty($rows) && $_POST["submit"] != "sendMail" ) : ?>
        <h3>Results </h3>
        <table class="pure-table pure-table-bordered">
          <thead>
            <tr>
              <th width="100">Key</th>
              <!-- <th>Assignee</th> -->
              <th>Time Logged By</th>
              <th>Status</th>
              <th>Priority</th>
              <th>Summary</th>
              <th>Time Estimated (in Mins)</th>
              <th>Total Time Spent (in Mins)</th>
            </tr>
          </thead>  
          <tbody>    
            <?php foreach($rows as $index => $row) : ?>
              <tr>
                <td><?php echo $row['key']; ?></td>
                <!-- <td><?php echo $row['assignee']; ?></td> -->
                <td><?php echo $row['work_log_author']; ?></td>
                <td><?php echo $row['status']; ?></td>
                <td><?php echo $row['priority']; ?></td>
                <td><?php echo $row['summary']; ?></td>
                <td><?php echo ($row['time_estimate']/60); ?></td>
                <td><?php echo ($row['total_time_spent']/60); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table><?php 
      endif ?>
      </div>
    </form>  
  </body>
</html>
