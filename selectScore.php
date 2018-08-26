<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
 <head>
  <title> Score Analyzer </title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <meta name="generator" content="EditPlus">
  <meta name="author" content="Veronique SEBASTIEN">
  <meta name="keywords" content="score music analyzer musicxml piano difficulty e-learning">
  <meta name="description" content="">
  <link rel="stylesheet" media="screen" type="text/css" href="style.css">
 </head>
 <body>
	<div id="content">
		<h1>Score Analyzer v2</h1>
		<?php
			include 'functions.php';

			$scoresCounter = 20; //empty pages have no <score> elements
			$scoresTab = array(); //table with id, title and secret for each retrieved score
			
			if (isset($_GET['pageRequest'])){
				$pageRequest = $_GET['pageRequest'];
			}
			else{
				$pageRequest = 0;
			}
			
			//requesting all classical piano scores, excluding compositions and transcriptions (= "score" format)
			$request = "http://api.musescore.com/services/rest/score.xml?format=1&genre=3&part=0&parts=1&page=".$pageRequest."&oauth_consumer_key=BRAZxD4bAw3mPV5DnTPpPk3yJwoVV35k";
			
			$data = curl($request);
			$xml = simplexml_load_string($data);
			//$xml = simplexml_load_file($request);
			if ($xml ===  FALSE){
				exit('Error: resource not found');
			}
			else{
				$scores = $xml->score;
				$scoresCounter = count($scores);
				for($i=0;$i<$scoresCounter;$i++){
					$score = $scores[$i];
					$idScore = $score->id;
					$title = $score->title;
					$secret = $score->secret;
					$user = $score->user->username;
					$userid = $score->user->uid;
					//add to scoresTab
					$scoresTab[] = array($idScore,$title,$secret,$user,$userid);
				}
				//go to next page
				$pageCounter++;
			}
			
		?>
		<FORM method="get" action="./searchResults.php">
			  <input type="text" name="searchbar" size="30" maxlength="50">
			  <input type="submit" value="search"/>
		</FORM>
		<p>Please choose a score for analysis: </p>
		<ul>
		<?php
			for($i=0;$i<count($scoresTab);$i++){
				echo "<li><a href='./readScore5.php?piece=".$scoresTab[$i][0].".".$scoresTab[$i][2]."&user=".$scoresTab[$i][3]."&userid=".$scoresTab[$i][4]."'>".$scoresTab[$i][1]." </a>by <a href='http://musescore.com/user/".$scoresTab[$i][4]."'>".$scoresTab[$i][3]."</a></li>";
			}
		?>
		</ul>
		<p>
			pages :
			<?php
				$savePageRequest = 0;
				while($savePageRequest < $pageRequest){
					echo "<a href='./selectScore.php?pageRequest=".$savePageRequest."'>".$savePageRequest."</a> ";
					$savePageRequest++;
				}
				echo "<a href='./selectScore.php?pageRequest=".$pageRequest."' color='red'>".$pageRequest."</a> ";
				for($i=1;$i<6;$i++){
					echo "<a href='./selectScore.php?pageRequest=".($pageRequest+$i)."'>".($pageRequest+$i)."</a> ";
				}
			?>
		</p>
	</div> <!-- End content -->
 </body>
</html>