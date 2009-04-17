<?php
/*
  FlixCloud API PHP Library

  Author:  Steve Heffernan <steve@sevenwire.com>
  Version: 1.0.6
  Date:    04-01-2009

  For more details on the FlixCloud API requirements visit
  http://flixcloud.com/api/

  There are two main functions: Job Requests, and Notification Handling.

  ////////////////////////////////////////////////////////////////////////////

  TRANSCODING JOB REQUEST
  The FlixCloudJob object uses cURL to send an XML formatted job request to
  https://www.flixcloud.com/jobs. It then uses the SimpleXMLElement library
  to parse any returned XML.

  ** API key is required and can be found at https://flixcloud.com/settings **
  ** Recipe ID is required and can be found at http://flixcloud.com/overviews/recipes **

  EXAMPLE 1 ------------------------------------------------------------------

  require("FlixCloud.php");

  // First parameter is API key, second is Recipe ID
  $fcj = new FlixCloudJob("2j1l:kd3add:0:ivzf:2e1y", "99");
  $fcj->set_input("http://www.example.com/videos/input.mpg"); // Add username/password if needed
  $fcj->set_output("ftp://www.example.com/httpdocs/videos/output.flv", "username", "password");
  $fcj->set_watermark("http://www.example.com/videos/watermark.png"); // Add username/password if needed

  if($fcj->send()) {
    // Job was successfully requested. Store job ID if needed.
    echo "Success. Job ID is ".$fcj->id." Submitted at ".$fcj->initialized_job_at;
  } else {
    // Do something with the errors
    foreach($fcj->errors as $error) {
      echo $error;
    }
  }


  EXAMPLE 2 (using params hash) -----------------------------------------------

  require("FlixCloud.php");

  $fcj = new FlixCloudJob("2j1l:kd3add:0:ivzf:2e1y", array(
    "recipe_id"       => 99,
    "input_url"       => "http://www.example.com/videos/input.mpg",
    // Add input_user, input_password if needed
    "output_url"      => "sftp://www.example.com/httpdocs/videos/output.flv",
    "output_user"     => "username",
    "output_password" => "password",
    "watermark_url"   => "http://www.example.com/videos/watermark.png",
    // Add watermark_user, watermark_password if needed
  ));

  if($fcj->success) {
    echo "Success. Job ID is ".$fcj->id." Submitted at ".$fcj->initialized_job_at;
  } else {
    foreach($fcj->errors as $error) {
      echo $error;
    }
  }

  ////////////////////////////////////////////////////////////////////////////

  NOTIFICATION HANDLING
  The FlixCloudNotificationHandler class is used to capture and parse XML data sent from
  FlixCloud when a job has been completed.

  The possible states of the FlixCloudJobNotification object are "successful_job",
  "cancelled_job", or "failed_job". A callback function allows you to respond accordingly.

  ** Notification URL must be set in https://flixcloud.com/settings **
  ** A file following the example below should be at that URL **

  EXAMPLE --------------------------------------------------------------------

  require("FlixCloud.php");

  $notification = FlixCloudNotificationHandler.catch_and_parse();

  switch ($notification->state) {
      case "successful_job":
        // Do something
        break;
      case "cancelled_job":
        // Do something else
        break;
      case "failed_job":
        // Do something with error message
        break;
      default:
        // Something errored. Bad XML, or lack of SimpleXMLElement parser.
  }

  ////////////////////////////////////////////////////////////////////////////
  Suggestions -> steve@sevenwire.com

  Version 1.0.6 - 4/14/09   Cleaned up the documentation a little.

  Version 1.0.5 - 4/6/09    Added options for pointing to the certificate or certificate directory.

  Version 1.0.4 - 4/6/09    Added option to connect insecurely incase SSL cert isn't working.
                              Like curl -k

  Version 1.0.3 - 4/6/09    Added backward compatability for recipe as second argument
                            Added Curl error checking
                            Added more detailed unknown error reporting
                            Formatted XML a little

  Version 1.0.2 - 4/5/09 -  * Now requiring API key on object creation, and added an optional params hash
                                for setting file info and sending on creation (example 2). *
                            * Recipe is no longer second argument *
                            * Broke up notification handling into 2 objects (see example) *
                                FlixCloudNotificationHandler => Catch and parse XML
                                FlixCloudJobNotification => Holds job notification info.
                            Added escaping of special characters when creating XML tags.
                            Removed protocol checking for now, because may want to just let the API do that.
                            On job->send changed status code handling to a switch statement.

  Version 1.0.1 - 4/2/09 -  Fixed acceptable protocols for some of the file types.

  NOTES:

  The windows version of curl will automatically look for a CA certs file named ´curl-ca-bundle.crt´, either in the same directory as curl.exe, or in the Current Working Directory, or in any folder along your PATH

*/


// FlixCloud Transcoding Job
class FlixCloudJob {

  var $api_key;     // Provided at https://flixcloud.com/settings
  var $recipe_id;   // Can be found at http://flixcloud.com/overviews/recipes
  var $api_url = "https://www.flixcloud.com/jobs";
  
  var $input;       // FlixCloudJobInputFile Object
  var $output;      // FlixCloudJobOutputFile Object
  var $watermark;   // FlixCloudJobWatermarkFile Object

  // cURL Options
  var $timeout = 0; // Time in seconds to timeout send request. 0 is no timeout.

  // Dealing with the certificate. Still a sketchy area.
  // If you learn anything from working with it let me know.
  var $insecure;    // Bypasses verifying the certificate if needed. Like curl -k or --insecure option. Still somewhat secure.
  var $certificate; // Full path the www.flixcloud.com.pem certificate. Not required most of the time. Look up CURLOPT_CAINFO for more info.
  var $certificate_dir; // Directory where certs live. Not required most of the time. Look up CURLOPT_CAPATH for more info.

  var $success;
  var $errors = array();

  var $status_code; // Used for testing
  var $result;      // Used for testing
  var $final_xml;   // Used for testing

  var $id;                  // Provided after job has been sent
  var $initialized_job_at;  // Provided after job has been sent

  // Initialize. API key is required. Option to load up job info on initialize using params hash.
  function FlixCloudJob($api_key, $params = "") {
    $this->api_key = $api_key;
    if (is_array($params)) {
      if($params["recipe_id"]) $this->recipe_id = $params["recipe_id"];
      if($params["input_url"]) $this->set_input($params["input_url"], $params["input_user"], $params["input_password"]);
      if($params["output_url"]) $this->set_output($params["output_url"], $params["output_user"], $params["output_password"]);
      if($params["watermark_url"]) $this->set_watermark($params["watermark_url"], $params["watermark_user"], $params["watermark_password"]);

      if($params["insecure"]) $this->insecure = true;
      if($params["certificate"]) $this->certificate = $params["certificate"];
      // If params array is used it sends by default
      if($params["send"] !== false) $this->send();
    } elseif(intval($params) > 0) {
      $this->recipe_id = $params; // Backwards compatible for when recipe was second arg.
    }
  }

  // Create input file object from URL and option user credentials
  function set_input($file_url, $user = "", $password = "") {
    $this->input = new FlixCloudJobInputFile($file_url, $user, $password);
  }

  // Create output file object from URL and option user credentials
  function set_output($file_url, $user = "", $password = "") {
    $this->output = new FlixCloudJobOutputFile($file_url, $user, $password);
  }

  // Create watermark file object from URL and option user credentials
  function set_watermark($file_url, $user = "", $password = "") {
    $this->watermark = new FlixCloudJobWatermarkFile($file_url, $user, $password);
  }

  // Check that all required info is available and valid. Used before sending.
  function validate() {
    if ( !function_exists("curl_version")) $this->errors[] = "cURL is not installed.";
    if ( !$this->api_key) $this->errors[] = "API key is required.";
    if ( !$this->recipe_id || intval($this->recipe_id) <= 0) $this->errors[] = "Recipe ID is required and must be an integer.";
    // Validate the different file types.
    foreach(array($this->input, $this->output, $this->watermark) as $job_file) {
      if($job_file) {
        if ( !$job_file->validate()) $this->errors = array_merge($this->errors, $job_file->errors);
      }
    }

    // Return false if any errors.
    if(count($this->errors) > 0) return false;
    return true;
  }

  // Send job request to FlixCloud
  function send() {
    $this->success = false;
    if ( !$this->validate()) return false;

    $this->final_xml = $this->get_job_xml();

    // Set up cURL connection
    $ch = curl_init($this->api_url);
    curl_setopt_array($ch, array(
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_HEADER => 0, // Don't return the header in result
      CURLOPT_HTTPHEADER => array("Accept: text/xml", "Content-type: application/xml"), // Required headers
      CURLOPT_POST => 1,
      CURLOPT_POSTFIELDS => $this->final_xml, // XML data
      CURLOPT_CONNECTTIMEOUT => $this->timeout,
    ));

    if($this->certificate) {
      curl_setopt($ch, CURLOPT_CAINFO, $this->certificate);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    }

    if($this->certificate_dir) {
      curl_setopt($ch, CURLOPT_CAPATH, $this->certificate_dir);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    }

    if($this->insecure) {
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    // Execute send
    $this->result = curl_exec($ch);

    if (curl_errno($ch)) {
      $this->errors[] = "Curl error (".curl_errno($ch)."): ".curl_error($ch);
      return false;
    }

    // Store the HTTP status code given (201, 400, etc.)
    $this->status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Close cURL connection;
    curl_close($ch);

    // Respond based on HTTP status code.
    switch ($this->status_code) {
      case "201": // Successful
        // Parse returned XML and get vars array
        $xml_obj = get_object_vars(new SimpleXMLElement($this->result));
        // Set job ID
        $this->id = $xml_obj["id"];
        // Set job's initialized time in UTC using the format YYYY-MM-DDTHH:MM:SSZ
        $this->initialized_job_at = $xml_obj["initialized-job-at"];
        $this->success = true;
        return true;

      case "200":
        $xml_obj = get_object_vars(new SimpleXMLElement($this->result));
        $this->errors[] = $xml_obj["error"];
        return false;

      case "302":
        $this->errors[] = "Redirection occurred (302). There's an error in the API URL.";
        return false;

      case "400":
        $xml_obj = get_object_vars(new SimpleXMLElement($this->result));
        $this->errors[] = $xml_obj["description"].$xml_obj["error"];
        return false;

      case "401":
        $this->errors[] = "Access denied (401). Probably a bad API key.";
        return false;

      case "404":
        $this->errors[] = "Page not found (404). The API url may be wrong, or the site is down.";
        return false;

      case "500":
        $this->errors[] = "The server returned an error (500). The API may be down.";
        return false;

      default:
        $temp_status_code = ($this->status_code) ? $this->status_code : "none";
        $temp_result = ($this->result) ? "\"".$this->result."\"" : "empty";
        $this->errors[] = "An unknown error has occurred."." Status Code: ".$temp_status_code.". Result ".$temp_result;
        return false;
    }
  }

  // Organize job data needed for API into a hash, then convert to XML.
  function get_job_xml() {

    // Watermark is optional. If left blank it won't be included in XML.
    if($this->watermark) {
      $watermark_hash = $this->watermark->get_data_hash();
    }

    // API XML in hash form
    // If key is an integer (0 included) value will be added to XML as is.
    $xml_hash = array(
      0 => '<?xml version="1.0" encoding="UTF-8"?>',
      "api-request"       => array(
        "api-key"           => $this->api_key,
        "recipe-id"         => $this->recipe_id,
        "file-locations"  => array(
          "input"           => $this->input->get_data_hash(),
          "output"          => $this->output->get_data_hash(),
          "watermark"       => $watermark_hash,
        ),
      ),
    );

    return $this->hash_to_xml($xml_hash);
  }

  // Create XML from a hash
  // Does some uneccessary formatting of the XML, but it's nice for debugging.
  function hash_to_xml($hash, $starting_indent = 0) {
    $xml = "";
    $indent = "";
    for($i=0; $i<$starting_indent; $i++) { $indent .= "  "; }
    foreach($hash as $tag_name => $tag_contents) {
      $xml .= $indent;
      // If key is an integer (0 included) value will be added to XML as is.
      if (is_int($tag_name)) {
        $xml .= $tag_contents."\n";
      } elseif (is_array($tag_contents)) {
        $xml .= $this->tag($tag_name, "\n".$this->hash_to_xml($tag_contents, $starting_indent+1).$indent)."\n";
      } elseif ($tag_contents) {
        $xml .= $this->tag($tag_name, htmlspecialchars($tag_contents))."\n";
      }
    }

    return $xml;
  }

  // Create a simple XML tag from a name and content. Escape special characters (&<>).
  function tag($tag_name, $tag_content) {
    return '<'.$tag_name.'>'.$tag_content.'</'.$tag_name.'>';
  }

}

// Class for holding media file info (file url, user, pass)
class FlixCloudJobFile {

  var $name = "base"; // Used in errors
  var $file_url;      // Location of file including tranfer protocol (http, ftp, etc.)
  var $user;          // Username
  var $password;      // Password
  var $protocol;      // Set and used in validating.
  var $errors = array();

  // Initialize
  function FlixCloudJobFile($file_url, $user = "", $password = "") {
    $this->file_url = trim($file_url);
    $this->user = trim($user);
    $this->password = trim($password);
  }

  // Validate that all needed data is available.
  function validate() {
    if( !$this->file_url) $this->errors[] = $this->name." file url required.";
    if($this->user && !$this->password) $this->errors[] = $this->name." password needed (user supplied).";
    if($this->password && !$this->user) $this->errors[] = $this->name." user needed (password supplied).";
    // Check that an appropriate protocol is being used.
    /*if( !$this->check_protocol()) {
      $protocol_error = "'".$this->protocol."' is not an accepted protocol for ".strtolower($this->name)." files. ";
      $protocol_error .= "Accepted protocols include: ";
      foreach($this->acceptable_protocols as $key => $proto) {
        $protocol_error .= $proto;
        // Add commas after all but last
        $protocol_error .= ($key+1 < count($this->acceptable_protocols)) ? ", " : ".";
      }
      $this->errors[] = $protocol_error;
    }*/

    // Return false if any errors.
    if(count($this->errors) > 0) return false;
    return true;
  }

  // Check if protocal supplied matches an accepted protocol for the file type
  function check_protocol() {
    preg_match ("/^[^:]+/i", $this->file_url, $matches);
    $this->protocol = $matches[0];
    if( !in_array($this->protocol, $this->acceptable_protocols)) {
      return false;
    }
    return true;
  }

  // Return a hash of values to be turned into XML later
  function get_data_hash() {
    $hash = array("url" => $this->file_url);
    if($this->user) {
      $hash["parameters"] = array("user" => $this->user, "password" => $this->password);
    }
    return $hash;
  }
}

// Input File Data Object
class FlixCloudJobInputFile extends FlixCloudJobFile {
  var $name = "Input";
  //var $acceptable_protocols = array("http", "https", "ftp", "sftp", "s3");
}

// Output File Data Object
class FlixCloudJobOutputFile extends FlixCloudJobFile {
  var $name = "Output";
  //var $acceptable_protocols = array("ftp", "sftp", "s3");
}

// Watermark File Data Object
class FlixCloudJobWatermarkFile extends FlixCloudJobFile {
  var $name = "Watermark";
  //var $acceptable_protocols = array("http", "https", "ftp", "sftp", "s3");
}

class FlixCloudNotificationHandler {
  function catch_and_parse() {
    $incoming = file_get_contents('php://input');
    $hash = get_object_vars(new SimpleXMLElement($incoming));
    return new FlixCloudJobNotification($hash);
  }
}

// Class for catching and parsing XML data sent from FlixCloud when job is finished.
// Notification URL must be set in https://flixcloud.com/settings
class FlixCloudJobNotification {

  var $error_message;          // Error message if there was one.
  var $finished_job_at;        // When the job finished. UTC YYYY-MM-DDTHH:MM:SSZ
  var $id;                     // The job's ID
  var $initialized_job_at;     // When the job was initialized. UTC YYYY-MM-DDTHH:MM:SSZ
  var $recipe_id;              // ID of recipe
  var $state;                  // failed_job, cancelled_job, or successful_job
  var $recipe_name;            // Name of recipe used
  var $watermark_file;         // Location of watermark file
  var $input_media_file;       // Location of input file
  var $output_media_file;      // Location of output file

  function FlixCloudJobNotification($hash) {
    $this->error_message      = trim($hash["error-message"]);
    $this->finished_job_at    = trim($hash["finished-job-at"]);
    $this->id                 = trim($hash["id"]);
    $this->initialized_job_at = trim($hash["initialized-job-at"]);
    $this->recipe_id          = trim($hash["recipe-id"]);
    $this->state              = trim($hash["state"]);
    $this->recipe_name        = trim($hash["recipe-name"]);
    $this->watermark_file     = trim($hash["watermark-file"]);
    $this->input_media_file   = trim($hash["input-media-file"]);
    $this->output_media_file  = trim($hash["output-media-file"]);
  }
}

?>
