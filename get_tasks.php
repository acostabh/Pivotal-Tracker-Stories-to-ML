<?php
include("functions.php");
include("settings.php");

if(htmlspecialchars($_GET["type"]) == "accepted" || $argv[1] == "accepted"){
  getPTtasks("accepted",$owner);
}
else {
  getPTtasks("unstarted",$owner);
}
?>
