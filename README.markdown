FlixCloud API PHP Library
==========================

Author:  Steve Heffernan <steve@sevenwire.com>  
Version: 1.0.7  
Date:    04-01-2009  

For more details on the FlixCloud API requirements visit  
http://flixcloud.com/api/

There are two main functions: Job Requests, and Notification Handling.



TRANSCODING JOB REQUEST
------------------------
The FlixCloudJob object uses cURL to send an XML formatted job request to
https://www.flixcloud.com/jobs. It then uses the SimpleXMLElement library
to parse any returned XML.

 **API key is required and can be found at https://flixcloud.com/settings**  
 **Recipe ID is required and can be found at http://flixcloud.com/overviews/recipes**  


### EXAMPLE 1

    require("FlixCloud.php");

    // First parameter is API key, second is Recipe ID
    $fcj = new FlixCloudJob("2j1l:kd3add:0:ivzf:2e1y");
    $fcj->recipe_id = 99;
    $fcj->set_input("http://www.example.com/videos/input.mpg"); // Add username/password if needed
    $fcj->set_output("ftp://www.example.com/httpdocs/videos/output.flv", array("user" => "username", "password" => "password");
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


### EXAMPLE 2 (using params hash)

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



NOTIFICATION HANDLING
----------------------
The FlixCloudNotificationHandler class is used to capture and parse XML data sent from
FlixCloud when a job has been completed.

The possible states of the FlixCloudJobNotification object are "successful_job",
"cancelled_job", or "failed_job". A callback function allows you to respond accordingly.

**Notification URL must be set in https://flixcloud.com/settings**  
**A file following the example below should be at that URL**  

### EXAMPLE

    require("FlixCloud.php");

    $job_notification = FlixCloudNotificationHandler::catch_and_parse();

    switch ($job_notification->state) {
        case "successful_job":

          echo $job_notification->id."\n";

          echo $job_notification->output->url."\n";
          echo $job_notification->output->cost."\n";

          echo $job_notification->input->url."\n";
          echo $job_notification->input->cost."\n";
          // Other info available. See code.

          break;
        case "cancelled_job":
          // Do something else
          break;
        case "failed_job":
          echo $job_notification->error_message;
          break;
        default:
    }



Versions
---------

    Version 1.0.7 - 04/17/09    Updated notification object to match new XML version

    Version 1.0.6 - 04/14/09    Cleaned up the documentation a little.

    Version 1.0.5 - 04/06/09     Added options for pointing to the certificate or certificate directory.

    Version 1.0.4 - 04/06/09    Added option to connect insecurely incase SSL cert isn't working.
                                Like curl -k

    Version 1.0.3 - 04/06/09    Added backward compatability for recipe as second argument
                                Added Curl error checking
                                Added more detailed unknown error reporting
                                Formatted XML a little

    Version 1.0.2 - 04/05/09    * Now requiring API key on object creation, and added an optional params hash
                                  for setting file info and sending on creation (example 2). *
                                * Recipe is no longer second argument *
                                * Broke up notification handling into 2 objects (see example) *
                                    FlixCloudNotificationHandler => Catch and parse XML
                                    FlixCloudJobNotification => Holds job notification info.
                                Added escaping of special characters when creating XML tags.
                                Removed protocol checking for now, because may want to just let the API do that.
                                On job->send changed status code handling to a switch statement.

    Version 1.0.1 - 04/02/09    Fixed acceptable protocols for some of the file types.



NOTES:
-------
Suggestions -> steve@sevenwire.com

The windows version of curl will automatically look for a CA certs file named ´curl-ca-bundle.crt´, either in the same directory as curl.exe, or in the Current Working Directory, or in any folder along your PATH