<?php
include("settings.php");

function getPTtasks($type,$owner) {
  global $ptToken;
  if($type == "accepted" || $type == "unstarted") {
    $curl = curl_init();
    $addFilter = "";
    if($type == "accepted"){
      $addFilter = "+accepted_since:" . date(m) ."/01/2020";
    }

    curl_setopt_array($curl, [
      CURLOPT_URL => "https://www.pivotaltracker.com/services/v5/projects/407849/stories?filter=owner:$owner+state:$type$addFilter",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_POSTFIELDS => "",
      CURLOPT_HTTPHEADER => [
        "x-trackertoken: $ptToken"
      ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
      echo "cURL Error #:" . $err;
    } else {

      $response = json_decode($response);
      $max = sizeof($response);
      #print_r($response[0]->{'id'});

      #loop through list of PT stories and process
      for($i = 0; $i < $max;$i++)
      {
        #get ML task by CF value
        $mlTask = mlCheck($response[$i]->{'id'});

        #no ml task + accepted OR ML + unstarted: do nothing
        if(($mlTask == 0 && $type == "accepted") || ($mlTask != 0 && $type == "unstarted")){
          echo "PT ID: " . $response[$i]->{'id'} . " & ML ID: " . $mlTask->{'results'}{0}->{'id'} . " - no action needed \n";

          #print_r($mlTask->{'stories'}->{$mlTask->{'results'}{0}->{'id'}}->{'state'});

        }
        #no ml task + unstarted: create task and set CF value
        elseif($mlTask == 0 && $type == "unstarted") {
          echo "PT ID: " . $response[$i]->{'id'} . " - Create ML task \n";

          $start_date = explode("T", $response[$i]->{'created_at'});

          $ptData = [
            "id" => $response[$i]->{'id'},
            "title" => $response[$i]->{'name'},
            "created_at" => $start_date[0],
            "url" => $response[$i]->{'url'}
          ];
          mlSync("POST", $ptData, true);
        }
        #ml task + accepted: check and update ML task (compare status)
        elseif($mlTask != 0 && $type == "accepted") {
          echo "PT ID: " . $response[$i]->{'id'} . " & ML ID: " . $mlTask->{'results'}{0}->{'id'} .  " - check and update ML task \n";
          if($mlTask->{'stories'}->{$mlTask->{'results'}{0}->{'id'}}->{'state'} == "resolved"){
            echo "... no action needed (status = resolved) \n";
          }
          else {
            mlSync("PUT", $mlTask->{'results'}{0}->{'id'}, true);
          }
        }
        else {
          echo "PT ID: " . $response[$i]->{'id'} . " & Type: $type - no mathing logic, something went wrong.\n";
        }

      } #end loop
    }
  }
  else {
    return "Wrong action type: $type";
  }
}

#Check tasks in ML by CF value and create/update
function mlCheck($id) {
  global $mlToken, $cfId;
  $curl = curl_init();

  curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.mavenlink.com/api/v1/stories?by_custom_text_value=$cfId:$id",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_POSTFIELDS => "",
    CURLOPT_HTTPHEADER => [
      "authorization: Bearer $mlToken"
    ],
  ]);

  $response = curl_exec($curl);
  $err = curl_error($curl);

  curl_close($curl);

  if ($err) {
    echo "cURL Error #:" . $err;
  } else {
    $response = json_decode($response);
    if($response->{'count'} == 0) {

      return $response->{'count'};
    }
    else {
      return $response;
    }

  }


}

# create and update ml tasks
function mlSync($method, $ptData, $send) {
  global $mlToken, $workspaceId, $cfId;
  $method = strtoupper($method);

  $taskId = "";

  if($method == "PUT") {
    $taskId = "/$ptData";
    $payload = "{\"story\": {\"state\": \"resolved\"}}";
    $rEnd = "updated";
  } elseif($method == "ARCHIVE") {
    $method= "PUT";
    $taskId = "/$ptData";
    $payload = "{\"story\": {\"archived\": true}}";
    $rEnd = "archived";
  } else {
    $payload = "{\"story\": { \"workspace_id\": $workspaceId, \"title\": \"" . str_replace('"', "''", $ptData['title']) . "\", \"story_type\": \"issue\", \"description\": \"" . $ptData['url'] . "\", \"start_date\": \"" . $ptData['created_at'] . "\"}}";
    $rEnd = "created";
  }

  #use $send (true/false) to debug $ptData
  if($send == false) {
    echo "$payload \n";
    print_r($ptData);
  }
  else {
    $curl = curl_init();

    curl_setopt_array($curl, [
      CURLOPT_URL => "https://api.mavenlink.com/api/v1/stories" . $taskId,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_HTTPHEADER => [
        "authorization: Bearer $mlToken",
        "content-type: application/json"
      ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
      echo "cURL Error #:" . $err;
    } else {
      $response = json_decode($response);
      echo "ML Task " . $response->{'results'}[0]->{'id'} . " $rEnd successfully.\n";
      #print_r($response);

      #after task creation insert CFV
      if($response->{'count'} > 0 && $method == "POST") {
        $payload = "{ \"custom_field_value\": {  \"subject_type\": \"Story\", \"subject_id\": " . $response->{'results'}[0]->{'id'} . ", \"custom_field_id\": " . $cfId . ", \"value\": \"" . $ptData['id'] . "\" }}";

        newCfv($payload, true);

      }


    }# end cURL
  } #end debug
}


#create csuotm field values
function newCfv($payload, $send){
  global $mlToken;

  if($send == false) {
    echo $payload;
  }
  else {
    $curl = curl_init();

    curl_setopt_array($curl, [
      CURLOPT_URL => "https://api.mavenlink.com/api/v1/custom_field_values",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_HTTPHEADER => [
        "authorization: Bearer $mlToken",
        "content-type: application/json"
      ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
      echo "cURL Error #:" . $err;
    } else {
      $response = json_decode($response);
      echo "Custom field value created.\n";
    }
  }

}

#ML get all resolved issues (unarchived)
function getResolved($month) {
  global $mlToken, $workspaceId;

  $curl = curl_init();

  curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.mavenlink.com/api/v1/stories?workspace_id=$workspaceId&by_status=resolved&per_page=200",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_POSTFIELDS => "",
    CURLOPT_HTTPHEADER => [
      "authorization: Bearer $mlToken"
    ],
  ]);

  $response = curl_exec($curl);
  $err = curl_error($curl);

  curl_close($curl);

  if ($err) {
    echo "cURL Error #:" . $err;
  } else {
    $response = json_decode($response);
    $max = $response->{'count'};
    echo "Count: " . $response->{'count'} . "\n";

    #loop through list of stories and process
    for($i = 0; $i < $max;$i++)
    {
      $createdMonth = explode("-", $response->{'stories'}->{$response->{'results'}{$i}->{'id'}}->{'created_at'})[1];
      if($createdMonth == 12 && $month == 1) {
        $createdMonth = 0;
      }
      if($createdMonth < $month) {
        mlSync("ARCHIVE", $response->{'results'}{$i}->{'id'}, true);
      };

    } #end loop
  }
}


?>
