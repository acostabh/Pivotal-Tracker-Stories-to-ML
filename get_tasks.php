<?php
include("functions.php");
include("settings.php");
#loop through the owners
for($i = 0; $i < count($owner);$i++)

{
  echo "PT Owner:" . $owner[$i] . "\n";
  if(htmlspecialchars($_GET["type"]) == "accepted" || $argv[1] == "accepted"){
    getPTtasks("accepted",$owner[$i]);
  }
  else {
    getPTtasks("unstarted",$owner[$i]);
  }
}
?>
