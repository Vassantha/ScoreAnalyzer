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
		<h1>Score Analyzer</h1>
		<?php
			//tableau des pieces
			$pieces = scandir("./pieces");
			
			//piece courante
			if (isset($_POST['choixPiece']))
				$piece = $_POST['choixPiece'];
			else if (isset($_GET['choixPiece']))
				$piece = $_GET['choixPiece'];
			else if (isset($_POST['choixPiece2']))
				$piece = $_POST['choixPiece2'];
			else
				$piece = "arabesque1";
			
			if (isset($_POST['calculate']))
				$calculationAsked = true;
			else
				$calculationAsked = false;
				
			//repertoire des pieces (xml et images)
			$xmlPiecePath = "pieces/";
			$imagePiecePath = "images/"; //dans le repertoire de chaque piece
			
			//repertoire de la piece selectionnee
			$selectedPiecePath = $piece."/";
			
			//on recupere le numero de la page (par defaut, on arrive sur la page 1 de la partition)
			if(isset($_GET["page"])) {
				$page=$_GET["page"];
			}
			else {
				$page=1;
			}
			

			//fonctions
			
			//pitch2int : convertit la hauteur d'une note (do,re,mi,fa,sol,la,si, avec octave et aleration) en nombre pour des calculs (do milieu = 1).
			function pitch2int($pitch,$noteOctave,$alter)
			{
				$pitchValue = 1;
				switch ($pitch) {
					case "A":
						$pitchValue = 10;
						break;
					case "B":
						$pitchValue = 12;
						break;
					case "C":
						$pitchValue = 1;
						break;
					case "D":
						$pitchValue = 3;
						break;
					case "E":
						$pitchValue = 5;
						break;
					case "F":
						$pitchValue = 6;
						break;
					case "G":
						$pitchValue = 8;
						break;
				}
				if($alter != null)
					$pitchValue = $pitchValue + $alter;
				switch ($noteOctave) {
					case 1:
						$pitchValue = $pitchValue - 3*12;
						break;
					case 2:
						$pitchValue = $pitchValue - 2*12;
						break;
					case 3:
						$pitchValue = $pitchValue - 1*12;
						break;
					case 5:
						$pitchValue = $pitchValue + 1*12;
						break;
					case 6:
						$pitchValue = $pitchValue + 2*12;
						break;
					case 7:
						$pitchValue = $pitchValue + 3*12;
						break;
					case 8:
						$pitchValue = $pitchValue + 4*12;
						break;
				}
				return $pitchValue;
			}
			
			//semitone2octave : convertit un écartement x en demi-tons (0<x<88) en écartement en octaves. Retourne un couple (nbOctaves,reste)
			function semitone2octave($semitoneNum)
			{
				$nbOctaves = floor($semitoneNum/12);
				$reste = $semitoneNum % 12;
				return array($nbOctaves,$reste);
			}
			
			//atSameTime : renvoie un tableau de notes attaquées en même temps que la note donnée en entrée. Permet de déterminer quelles notes sont pressées en même temps aux deux mains. Pour les notes sur la même portée, il suffit de repérer l'élément <chord/>. On part du principe qu'un changement de portée (staff) induit un retour au premier temps
			function atSameTime($note)
			{
				$result = array();
				$measureNodes = $note->xpath('parent::*');
				$measureNode = $measureNodes[0];
				$theNotes = $measureNode->note;
				//$staff = $note->staff;
				//notesOnRH = $measureNode->xpath('//note[staff='.$staff.' and chord');
				$notesTimecodes = array();
				$notesTimecodes[] = array($theNotes[0],0); //la toute première note de la mesure est jouée au timecode 0
				$currentTimecode = 0;
				$previousNote = $theNotes[0];
				$myNoteTimecode = 0;
				for($i=1;$i<count($theNotes);$i++){
					$currentNote = $theNotes[$i];
					if((int)$previousNote->staff != (int)$currentNote->staff){ //changement de portée
						$currentTimecode = 0;
					}
					else if(!($currentNote->chord)){
						$currentTimecode = $currentTimecode + $previousNote->duration;
					}
					$notesTimecodes[] = array($currentNote,$currentTimecode);
					$previousNote = $currentNote;
					if($currentNote == $note)
						$myNoteTimecode = $currentTimecode;
				}
				
				for($i=0;$i<count($notesTimecodes);$i++){
					$tied = $notesTimecodes[$i][0]->xpath("./notations/tied");
					if (count($tied) != 0){
						$tied = $tied[0]->attributes();
						if($tied != 'stop')
							$tied = false;
					}

					if(($notesTimecodes[$i][0] != $note) && ($notesTimecodes[$i][1] == $myNoteTimecode) && !($notesTimecodes[$i][0]->rest) && !$tied)
						$result[] = $notesTimecodes[$i][0];
				}
				//print "<br/>";
				return $result;
				//return $notesTimecodes;
			}
			
			//getNote : renvoie le nième élément note (hors silences) à la mesure $mes de la portée $staff (=1 ou =2), dans le fichier musicXML $xml. Dans un accord, on respecte l'ordre de lecture solfégique (la première note est celle la plus basse). Retourne null si une telle note n'existe pas
			function getNote($xml,$mes,$n,$staff)
			{
				$theMeasure = $xml->xpath('//measure[@number='.$mes.']');
				$theNotes = $theMeasure[0]->xpath('./note[staff='.$staff.' and not(rest)]');
				if(count($theNotes) >= $n)
					return $theNotes[$n-1];
				else
					return null;
			}
			
			//getMeasure : renvoie l'élément measure de l'élément note passé en argument (retourne son parent donc)
			function getMeasure($xml,$note)
			{
				$measureNodes = $note->xpath('parent::*');
				$measureNode = $measureNodes[0];
				return $measureNode;
			}
			
			//getMeasure : renvoie le numéro de mesure de la note passée en argument (retourne son parent donc)
			function getMeasureNumber($xml,$note)
			{
				$theMeasure = getMeasure($xml,$note);
				$measureAttributes = $theMeasure->attributes();
				$measureNum = $measureAttributes["number"];
				return $measureNum;
			}
			
			//getNotePosInMes : renvoie la position $i de la note $note de la mesure $mes sur la portée $staff. Les silences sont comptés comme des notes
			function getNotePosInMes($xml,$note)
			{
				$staff = $note->staff;
				$theMeasure = getMeasure($xml,$note);
				$theNotes = $theMeasure->xpath('./note[staff='.$staff.']');
				$i=0;
				$found = false;
				while($i < count($theNotes) and !$found){
					if($theNotes[$i] == $note)
						$found = true;
					$i++;
				}
				if ($found){
					//echo "on a trouvé la position ".$i;
					return $i;
				}
				else{
					//echo "on a pas trouvé !!!";
					return null;
				}
			}
			
			//getNotePosInMes : renvoie l'élément note précédant la note donnée en argument, qu'il s'agisse d'un silence ou pas. S'il n'existe pas de telle note, il renvoit la note donnée en argument
			function getPreviousNoteElement($xml,$note)
			{
				//echo "ouaix on entre dans la fonction getPreviousNoteElement, on analyse la note ".$note->pitch->step.$note->pitch->octave."<br>";
				$previousNoteProv = $note;
				$pos = getNotePosInMes($xml,$note); //position de la note actuelle dans la mesure (silence compris)
				$staff = $note->staff;
				if($pos != null){
					if($pos == 1){ //si c'est la première note, la note précédente est à chercher dans la mesure précédente
						$mes = getMeasureNumber($xml,$note);
						if($mes > 1){
							//echo "$mes=".$mes."<br>";
							$theMeasure = $xml->xpath('//measure[@number='.($mes-1).']');
							$theNotesProv = $theMeasure[0]->xpath('./note[staff='.$staff.']');
							if(count($theNotesProv) > 0)
								$previousNoteProv = $theNotesProv[count($theNotesProv)-1];
							else { //une mesure sans élément note signifie que la melodie de la main droite est écrite sur la portée 2-> on prend alors la dernière note de celle-ci
								while(count($theNotesProv) == 0){
									//echo "oula y a pas de notes : ".$mes."<br>";
									$mes=$mes-1;
									$theMeasure = $xml->xpath('//measure[@number='.($mes-1).']');
									$theNotesProv = $theMeasure[0]->xpath('./note[staff='.$staff.']');
								}
								if(count($theNotesProv) > 0)
									$previousNoteProv = $theNotesProv[count($theNotesProv)-1];
							}
						}
					}
					else{
						$mesNode = getMeasure($xml,$note);
						$theNotesProv = $mesNode->xpath('./note[staff='.$staff.']'); //silences compris
						if(count($theNotesProv) > 0)
							$previousNoteProv = $theNotesProv[$pos-2];
					}
				}
				return $previousNoteProv;
			}
			
			//evaluateDifficulty : renvoie un score de 1 à 4 évaluant la difficulté du critère $criteria de valeur $value
			function evaluateDifficulty($criteria,$value,$valueLH)
			{
				$result = 0;
				$resultLH = 0;
				switch ($criteria) {
				
					case "speed":
						switch (true) {
							case ($value <= 20):
								return 1;
								break;
							case ($value > 20 && $value <= 50):
								return 2;
								break;
							case ($value > 50 && $value <= 80):
								return 3;
								break;
							case ($value > 80):
								return 4;
								break;
						}
						break;
						
					case "displacement":
						switch (true) {
							case ($value <= 5):
								$result = 1;
								break;
							case (($value > 5) && ($value <= 10)):
								$result = 2;
								break;
							case ($value > 10 && $value <= 20):
								$result = 3;
								break;
							case ($value > 20):
								$result = 4;
								break;
						}
						switch (true) {
							case ($valueLH <= 10):
								$resultLH = 1;
								break;
							case ($valueLH > 10 && $valueLH <=20):
								$resultLH = 2;
								break;
							case ($valueLH > 20 && $valueLH <= 55):
								$resultLH = 3;
								break;
							case ($valueLH > 55):
								$resultLH = 4;
								break;
						}
						if($result == 2 && $resultLH == 2)
							return 3;
						else if ($result == 3 && $resultLH == 3)
							return 4;
						else
							return max($result,$resultLH);
						break;
						
					case "chords":
						switch (true) {
							case ($value <= 10):
								$result = 1;
								break;
							case ($value > 10 && $value <=30):
								$result = 2;
								break;
							case ($value > 30 && $value <= 60):
								$result = 3;
								break;
							case ($value > 60):
								$result = 4;
								break;
						}
						switch (true) {
							case ($valueLH <= 10):
								$resultLH = 1;
								break;
							case ($valueLH > 10 && $valueLH <=30):
								$resultLH = 2;
								break;
							case ($valueLH > 30 && $valueLH <= 60):
								$resultLH = 3;
								break;
							case ($valueLH > 60):
								$resultLH = 4;
								break;
						}
						if($result == 2 && $resultLH == 2)
							return 3;
						else if ($result == 3 && $resultLH == 3)
							return 4;
						else
							return max($result,$resultLH);
						break;
						
					case "harmony":
						switch (true) {
							case ($value <= 5):
								$result = 1;
								break;
							case ($value > 5 && $value <=20):
								$result = 2;
								break;
							case ($value > 20 && $value <= 30):
								$result = 3;
								break;
							case ($value > 30):
								$result = 4;
								break;
						}
						switch (true) {
							case ($valueLH <= 5):
								$resultLH = 1;
								break;
							case ($valueLH > 5 && $valueLH <=20):
								$resultLH = 2;
								break;
							case ($valueLH > 20 && $valueLH <= 30):
								$resultLH = 3;
								break;
							case ($valueLH > 30):
								$resultLH = 4;
								break;
						}
						if ($result == 3 && $resultLH == 3)
							return 4;
						else
							return max($result,$resultLH);
						break;
						
					case "rhythm":
						switch (true) {
							case ($value == 0):
								return 1;
								break;
							case ($value > 0 && $value <=20):
								return 2;
								break;
							case ($value > 20 && $value <= 60):
								return 3;
								break;
							case ($value > 60):
								return 4;
								break;
						}
						break;
					case "nbpages":
						switch (true) {
							case ($value > 0 && $value <=2):
								return 1;
								break;
							case ($value > 2 && $value <= 4):
								return 2;
								break;
							case ($value > 4 && $value <= 6):
								return 3;
								break;
							case ($value > 6):
								return 4;
								break;
						}
						break;
					case "tonality":
						switch (true) {
							case ($value >= 0 && $value <=1):
								return 1;
								break;
							case ($value > 1 && $value <= 3):
								return 2;
								break;
							case ($value > 3 && $value <= 5):
								return 3;
								break;
							case ($value > 5):
								return 4;
								break;
						}
						break;
				}
			}
			
			function getDifficultyComment($criteria,$score)
			{
				switch ($criteria) {
				
					case "speed":
						switch ($score) {
							case 1:
								return "slow";
								break;
							case 2:
								return "moderate";
								break;
							case 3:
								return "fast";
								break;
							case 4:
								return "presto!";
								break;
						}
						break;
						
					case "displacement":
						switch ($score) {
							case 1:
								return "sporadic";
								break;
							case 2:
								return "several";
								break;
							case 3:
								return "many";
								break;
							case 4:
								return "numerous!";
								break;
						}
						break;
						
					case "chords":
						switch ($score) {
							case 1:
								return "sporadic";
								break;
							case 2:
								return "several";
								break;
							case 3:
								return "many";
								break;
							case 4:
								return "numerous!";
								break;
						}
						break;
						
					case "harmony":
						switch ($score) {
							case 1:
								return "easy";
								break;
							case 2:
								return "average";
								break;
							case 3:
								return "difficult";
								break;
							case 4:
								return "indecipherable!";
								break;
						}
						break;
						
					case "rhythm":
						switch ($score) {
							case 1:
								return "easy";
								break;
							case 2:
								return "average";
								break;
							case 3:
								return "difficult";
								break;
							case 4:
								return "awkward!";
								break;
						}
						break;
					case "nbpages":
						switch ($score) {
							case 1:
								return "short";
								break;
							case 2:
								return "average";
								break;
							case 3:
								return "long";
								break;
							case 4:
								return "very long!";
								break;
						}
						break;
					case "tonality":
						switch ($score) {
							case 1:
								return "easy";
								break;
							case 2:
								return "average";
								break;
							case 3:
								return "complex";
								break;
							case 4:
								return "complex";
								break;
						}
						break;
					case "general":
						switch (true) {
							case ($score < 1.5):
								return "beginner";
								break;
							case ($score >= 1.5 && $score < 2.5):
								return "intermediate";
								break;
							case ($score >= 2.5 && $score < 3.5):
								return "advanced";
								break;
							case ($score >= 3.5):
								return "virtuoso";
								break;
						}
						break;
				}
			}
			
			function getDifficultyImage($value)
			{
					switch ($value) {
						case 1:
							return "barEasy.png";
							break;
						case 2:
							return "barIntermediate.png";
							break;
						case 3:
							return "barAdvanced.png";
							break;
						case 4:
							return "barvirtuoso.png";
							break;
					}
					return null;
			}
			
			function getDifficultyImageFromComment($comment)
			{
				switch ($comment) {
						case "beginner":
							return "barEasy.png";
							break;
						case "intermediate":
							return "barIntermediate.png";
							break;
						case "advanced":
							return "barAdvanced.png";
							break;
						case "virtuoso":
							return "barvirtuoso.png";
							break;
					}
					return null;
			}
			
			function getTonality($fifths, $mode)
			{
				if($mode == "major"){
					switch ($fifths) {
							case 0:
								return "C";
								break;
							case 1:
								return "G";
								break;
							case 2:
								return "D";
								break;
							case 3:
								return "A";
								break;
							case 4:
								return "E";
								break;
							case 5:
								return "B";
								break;
							case 6:
								return "F#";
								break;
							case 7:
								return "C#";
								break;
							case -1:
								return "F";
								break;
							case -2:
								return "Bb";
								break;
							case -3:
								return "Eb";
								break;
							case -4:
								return "Ab";
								break;
							case -5:
								return "Db";
								break;
							case -6:
								return "Gb";
								break;
							case -7:
								return "B";
								break;
						}
					}
					else {
						switch ($fifths) {
							case 0:
								return "A";
								break;
							case 1:
								return "E";
								break;
							case 2:
								return "B";
								break;
							case 3:
								return "F#";
								break;
							case 4:
								return "C#";
								break;
							case 5:
								return "G#";
								break;
							case 6:
								return "D#";
								break;
							case 7:
								return "Bb";
								break;
							case -1:
								return "D";
								break;
							case -2:
								return "G";
								break;
							case -3:
								return "C";
								break;
							case -4:
								return "F";
								break;
							case -5:
								return "Bb";
								break;
							case -6:
								return "Eb";
								break;
							case -7:
								return "G#";
								break;
						}
					}
					return null;
			}

		?>
		<div id="score">
			<FORM method="post" action="./readScoreTemp.php?page=1">
			  <SELECT name="choixPiece" size="1">
			  <?php
				for($i=2;$i<count($pieces);$i++){
					echo "<option";
					if($pieces[$i] == $piece)
						echo " selected ";
					echo ">".$pieces[$i]."</option>";
				}
			  ?>
			  </SELECT>
			  <input type="submit" value="OK"/>
			</FORM>
			
			<FORM method="post" action="./readScoreTemp.php?page=1">
				<input name="calculate" type="hidden" value="calculateButton"> 
				<input name="choixPiece2" type="hidden" value="<?php echo $piece; ?>"> 
				<input type="submit" value="recalculate"/>
			</FORM>

			<?php
				//affichage de la partition
				$scoreFiles = scandir($xmlPiecePath.$selectedPiecePath.$imagePiecePath); //tableau contenant les fichiers images représentant la partition
				//si on n'a qu'un fichier pdf ...
				$fileExt = substr($scoreFiles[2],count($scoreFiles[2])-4);
				if($fileExt == "pdf"){
			?>

<object data="<?php echo "./".$xmlPiecePath.$selectedPiecePath.$imagePiecePath.$scoreFiles[2]; ?>" type="application/pdf" width="600" height="800"></object>

			<?php
				}
				else {
					//print_r($scoreFiles);
					$nbpages = count($scoreFiles)-2;
					
			?>
					<img src="<?php echo "./".$xmlPiecePath.$selectedPiecePath.$imagePiecePath.$scoreFiles[$page+1]; ?>" alt="partition"/>
					<!-- navigation pages -->
					<div id="navigationPages">
						<?php 
							if ($page > 1){
								echo "<a href='readScoreTemp.php?page=".($page-1)."&choixPiece=".$piece."'>< </a>";
							}
						?>
						page <?php echo $page." / ".$nbpages; ?>
						<?php 
							if ($page < $nbpages){
								echo "<a href='readScoreTemp.php?page=".($page+1)."&choixPiece=".$piece."'> ></a>";
							}
						?>
					</div> <!-- End navigationPages -->
				<?php } ?>
		</div> <!-- End score -->
		
		<!-------------------------------------------- Analyse de la partition XML ------------------------------------------------------------->
		<?php
			
			//ouverture du fichier xml créé
			$xmlFilePathSearch = scandir($xmlPiecePath.$selectedPiecePath);
			$i = 0;
			while(substr($xmlFilePathSearch[$i],count($xmlFilePathSearch[$i])-4) != "xml" && $i<count($xmlFilePathSearch)){
				$i++;
			}
			$pieceXmlFile = "";
			if($i<count($xmlFilePathSearch)){
				$pieceXmlFile = $xmlFilePathSearch[$i];
			}
			else{
				echo "error ! no xml score found for this piece";
				exit;
			}
			
			//vérification que le fichier xml ne soit pas déjà en base : si c'est le cas, il n'est pas nécessaire de faire tous les calculs ci-dessous, on fait juste les requêtes SQL nécessaires
			//connection à la bdd
			// require_once "bdd-inc.php";

			// $query= "SELECT `idPieceScore`, `nbpages` FROM `scores` WHERE `xmlFile` = '".$pieceXmlFile."';";
			// $result=mysql_query($query);
			$idPiece = "";
			// $row = mysql_fetch_array($result, MYSQL_ASSOC);
			// if(count($row)>0){
				// $idPiece = $row['idPieceScore'];
				// $nbpages = $row['nbpages'];
			// }
			/*while($row = mysql_fetch_array($result, MYSQL_ASSOC) and $idPiece == ""){
				if($row['xmlFile'] == $pieceXmlFile){
					$idPiece = $row['idPieceScore'];
				}
			}*/
			
			//echo $idPiece;
			
			$calculated = false; //booléen indiquant si les résultats ont été calculés par la page php, ou bien sont juste chargés depuis la BD
			
			if($idPiece == "" or $calculationAsked) {
				if($calculationAsked) {
					//on supprime l'entrée déjà en base
					$query = "DELETE FROM `pieces` WHERE `pieces`.`idPiece` = ".$idPiece;
					$result=mysql_query($query);
					$query = "DELETE FROM `scores` WHERE `scores`.`idPieceScore` = ".$idPiece;
					$result=mysql_query($query);
					$query = "DELETE FROM `criteria` WHERE `criteria`.`idPieceCriteria` = ".$idPiece;
					$result=mysql_query($query);
					$query = "DELETE FROM `stats` WHERE `stats`.`idPieceStat` = ".$idPiece;
					$result=mysql_query($query);
					$query = "DELETE FROM `structure` WHERE `structure`.`idPieceStruct` = ".$idPiece;
					$result=mysql_query($query);
				}
				$calculated = true;
				//la piecec n'est pas en base, il faut faire tous les calculs
				//echo $pieceXmlFile."<br/>";
				$xmlFile = $xmlPiecePath.$selectedPiecePath.$pieceXmlFile;
				$xml = simplexml_load_file($xmlFile);
				
				//recuperation du titre
				//$xml->registerXPathNamespace('mmd', 'http://musicbrainz.org/ns/mmd-1.0#');
				$identification = $xml->xpath('//identification');
				
				//calcul nb de pages (tel que déclaré dans le fichier XML, sinon, on compte les fichiers images)
				$pages = $xml->xpath('//print[@page-number]');
				$numPages = array();
				foreach ($pages as $page) {
					$pageAttributes = $page->attributes();
					$numPages[] = $pageAttributes['page-number'];
				}
				
				if (count($numPages) != 0){
					$nbpages = count($numPages);
				}
				else{
					$pages = $xml->xpath('//print[@new-page="yes"]');
					if (count($pages) != 0)
						$nbpages = count($pages)+1;
					else
						$nbpages = 1;
				}
				//calcul nb de mesures
				$mesures = $xml->xpath('//measure');
				$nbMesures = count($mesures);
				
				//calcul de la quantise et de la durée la plus courte (durée de base)
				$notes = $xml->xpath('//note[not(rest)]');
				$quantise = 4; //ronde
				$noteValues = array("whole"=>1,"half"=>2,"quarter"=>4,"eighth"=>8,"16th"=>16,"32nd"=>32,"64th"=>64,"128th"=>128,"256th"=>256);
				$noteProportions = array(); // tableau contenant le nombre de notes par valeur (ex : 4->142, 8->57, 16->27, etc) il peut y avoir des valeurs ternaires (ex: 8*2/3 = 5.33 -> c'est la croche ternaire)
				$noteCounter = 0; //nbre de notes du morceau, toutes portées confondues
				$noteCounterRH = 0; //nbre de notes à la main droite
				$noteCounterLH = 0; //nbre de notes à la main gauche
				//$baseDurations = $notes[0]->xpath('//duration[not(time-modification)]'); //valeur de durée de base (la plus courte du morceau)
				$baseDuration = 10000;
				foreach ($notes as $note) {
					$noteCounter++;
					if($note->voice == 1)
						$noteCounterRH++;
					else
						$noteCounterLH++;
					
					$currentNoteValue = 0;
					$noteType = $note->type;
					$currentNoteValue = $noteValues[(string)$noteType];
					if(count($note->xpath('time-modification')) > 0){
						$actualNote = $note->xpath('time-modification/actual-notes');
						//echo "actual Note : ".$actualNote[0]."<br>";
						$normalNote = $note->xpath('time-modification/normal-notes');
						//echo "normal Note : ".$normalNote[0]."<br>";
						$currentNoteValue = $currentNoteValue * $actualNote[0] / $normalNote[0]; //on a un rythme ternaire dans du binaire ou vice-versa
						//echo "current Note : ".$currentNoteValue."<br>";
					}
					$k=0;
					while(($k<count($noteProportions)) && ($noteProportions[$k][0] != $currentNoteValue)) {
						$k++;
					}
					if($k<count($noteProportions)){
						//echo "woo on rajoute pour l'indice ".$k." qui represente la valeur ".$noteProportions[$k][0]."<br>";
						$noteProportions[$k][1] = $noteProportions[$k][1] + 1;
					}
					else {
						$noteProportions[] = array($currentNoteValue,1);
					}
					
					//$noteProportions[(string)$noteType]++;
					
					/*$measureNode = $note->xpath('parent::*');
							$measureAttributes = $measureNode[0]->attributes();
							$measure = $measureAttributes["number"];
							echo "mesure : ".$measure."<br/>";*/
					
					
					if(count($note->xpath('time-modification')) == 0) { //on n'utilise pas les valeurs 3 pour 2 comme duration de base
						$theNoteDuration = $note->duration;
						if($theNoteDuration != ""){
							if((int)$theNoteDuration < (int)$baseDuration){
								$baseDuration = $note->duration;
							}
						}
						//mise à jour de la quantise (on ne prend pas non plus les valeurs ternaires)
						if($currentNoteValue > $quantise)
							$quantise = $currentNoteValue;
					}
				}
				
				//calcul de la tonalité
				$tonalityP = $xml->xpath('//key/fifths');
				$tonality = $tonalityP[0];
				$modeP = $xml->xpath('//key/mode');
				$mode = $modeP[0];
				$tonalityString = getTonality($tonality,$mode)." ".$mode;
				
				//identification des grandes parties du morceau (une partie est définie par un couple (mes début, mes fin, certitude))
				//parcours mesure par mesure
				$mesures = $xml->xpath('//measure');
				$currentPart = array(1,count($mesures),100); //pour l'instant une seule partie qui fait tout le morceau. On raffine au fur et à mesure du parsing
				$parts = array();
				$parts[] = $currentPart;
				for ($i=1;$i<count($mesures);$i++) {
					$newPartScore = 0; //pourcentage de chance pour que la mesure actuelle débute une nouvelle partie, la présence de chaque critère (changement de tempo, tonalité, etc ...) l'incrémente. Seuil à fixer pour assurer le changement de partie
					$currentMesure = $mesures[$i]; //on commence à la mesure 2
					if($currentMesure->attributes->key){
						//on a un changement de tonalité
						$newPartScore += 100;
					}
					if($currentMesure->direction){
						if(count($currentMesure->xpath('./direction[@placement="above" and staff=1]')) != 0){
							//echo count($currentMesure->xpath('./direction[@placement="above" and staff=1]'))."<br/>";
							if(count($currentMesure->xpath('./direction/direction-type/metronome')) != 0){
								$newPartScore += 80;
							}
							/*$words = $currentMesure->xpath('./direction/direction-type/words');
							if(count($words) != 0){
								if($words[0] != "cresc." && $words[0] != "crescendo" && $words[0] != "decresc." && $words[0] != "decrescendo")
									$newPartScore += 60;
							}*/
						}
					}
					if(count($currentMesure->xpath('./sound[@tempo]')) != 0){
						$newPartScore += 20;
					}
					$addonemeasure = false;
					if(count($currentMesure->xpath('./barline[@location="right"]')) != 0){
						//on a un changement de tonalité
						$newPartScore += 50;
						$addonemeasure = true;
					}
					
					//estimation finale
					if($newPartScore >= 50){
						if($addonemeasure){
							$parts[count($parts)-1][1] = $i+1;
							$parts[] = array($i+2,count($mesures),floor($newPartScore/260*100)); //la certitude est un pourcentage du score maximal (tous les critères présents)
						}
						else{
							$parts[count($parts)-1][1] = $i;
							$parts[] = array($i+1,count($mesures),floor($newPartScore/260*100)); //la certitude est un pourcentage du score maximal (tous les critères présents)
						}	
					}
					
				}
				//elimination des parties < 2 mesures
				$aeffacer = array();
				for ($i=1;$i<count($parts);$i++) {
					if($parts[$i][1] - $parts[$i][0] < 2){
						$parts[$i-1][1] = $parts[$i][1];
						$aeffacer[] = $i;
					}
				}
				for ($i=count($aeffacer)-1;$i>=0;$i--) {
					//unset($parts[$aeffacer[$i]]);
					array_splice($parts,$aeffacer[$i],1);
				}
				
				//------------------estimation de la rapidité du morceau-----------------------------------------------------------------------
				$tempoP = $xml->xpath('//@tempo');
				$tempo = $tempoP[0];
				$beatsP = $xml->xpath('//beats');
				$beats = $beatsP[0];
				$beatTypeP = $xml->xpath('//beat-type');
				$beatType = $beatTypeP[0];
				$quickestValue = 1; //la ronde comme point de départ
				$quickestValueReal = 1; //y compris les trilles et autres "petites notes rapides"
				//echo "zoeifjzoieh ".count($noteProportions)."<br>";
				for ($i=0;$i<count($noteProportions);$i++) {
					//si il y a un nombre conséquent (>15%) de notes plus rapides dans la pièce, on met à jour la valeur de $quickestValue
					$key = $noteProportions[$i][0];
					$value = $noteProportions[$i][1];
					//echo $key."<br>";
					//echo $value."<br><br>";
					if($key>$quickestValue){
						if($value > (0.15 * $noteCounter)){
							$quickestValue = $key;
						}
						else
							$quickestValueReal = $key;
					}
				}
				
				$rapidite = $tempo*$quickestValue*100/2816; //2816 est la valeur du morceau "le plus rapide" -> 176 * 16 (176 à la noire, avec une valeur la plus courte étant la double croche)
				
				//----------------------estimation de la difficulté des déplacements de mains (droite et gauche)---------------------------
				//-------------boucle main droite-------------------
				$notesRH = $xml->xpath('//note[staff=1 and not(rest)]'); //on ne prend pas les silences
				//echo count($notesRH);
				
				$measure = 1; //la mesure courante
				$noteNum = 2; //le numéro de note au sein de la mesure courante, on commence à la 2ème note du morceau
				$accord = false; //booleen indiquant si l'on se situe a sein d'un accord ou non
				$deplacementNum = 0; //nombre total de déplacements de main droite, de position en position (!une position peut être un accord comme une note seule, et avoir n'importe quelle durée!)
				$diffDeplacements = array(); //on retient les déplacements difficiles sous la forme (n de Mes, n de note dans la Mes, valeur Dep)
				$accidentalNotes = array(); //on retient les notes avec des altérations accidentelles sous la forme (n de Mes, n de note dans la Mes, alter type) alter type = sharp/flat/natural
				$timeModifications = array(); //on détecte les mesures comportant du 3 pour 2
				
				//calcul de hauteur pour la première note
				$notePitchLetter = $notesRH[0]->pitch->step;
				$noteOctave = $notesRH[0]->pitch->octave;
				$alter = $notesRH[0]->pitch->alter; // -1 -> bémol, 1 -> dièse
				
				//on vérifie si la note a une altération accidentelle, si oui, on l'ajoute à la liste
				if($notesRH[0]->accidental){
					$accidentalNotes[] = array($measure,1,$notesRH[0]->accidental);
				}
				//on vérifie si on a une modification du temps (temps ternaire sur du binaire ou vice versa)
				if(count($notesRH[0]->xpath('time-modification')) != 0){
					$timeModifications[] = $measure;
				}
				
				$numChordRH = 0; //nombres d'accords à la main droite
				$numOctavesRH = 0; //nombre d'octaves à la main droite
				$chordBaseValue = 0; //hauteur de la base d'un accord
				$previousNote = $notesRH[0];
				$previousNotePitchLetter = $previousNote->pitch->step;
				$previousNoteOctave = $previousNote->pitch->octave;
				$previousNotealter = $previousNote->pitch->alter; // -1 -> bémol, 1 -> dièse
				$previousNotePitchValue = pitch2int($previousNotePitchLetter,$previousNoteOctave,$previousNotealter); 
				for ($i=1;$i<count($notesRH);$i++) {
					//infos de base sur la note
					$note = $notesRH[$i];
					$notePitchLetter = $note->pitch->step;
					$noteOctave = $note->pitch->octave;
					$alter = $note->pitch->alter; // -1 -> bémol, 1 -> dièse
					$notePitchValue = pitch2int($notePitchLetter,$noteOctave,$alter); //entier représentant la hauteur de la note (C4 = 1, C4# = 2, D4 = 3, etc...)
					
					//recherche de la précédente note non jouée en même temps (pas au sein du même accord le cas échéant)
					if(!($note->chord)){
						$deplacementNum ++;
						$previousNote = getPreviousNoteElement($xml,$note);
						$gapDuration = $previousNote->duration; //durée du saut (temps écoulé entre les deux attaques).
						//on élimine maintenant les silences successifs(tout en mémorisant le temps écoulé)
						while($previousNote->rest){
							$previousNote = getPreviousNoteElement($xml,$previousNote); //uniquement pour la duration ! sinon, c'est la base de l'accord qui compte, donc $notePrecedenteValue
							$gapDuration = $gapDuration + $previousNote->duration;
						}
						while($previousNote->chord){
							$previousNote = getPreviousNoteElement($xml,$previousNote); //on remonte à la base de l'accord le cas échéant
						}
						$previousNotePitchLetter = $previousNote->pitch->step;
						$previousNoteOctave = $previousNote->pitch->octave;
						$previousNotealter = $previousNote->pitch->alter; // -1 -> bémol, 1 -> dièse
						$previousNotePitchValue = pitch2int($previousNotePitchLetter,$previousNoteOctave,$previousNotealter); 
					}
					$currentEcart = abs($notePitchValue - $previousNotePitchValue); //écart entre la note actuelle et la dernière note non jouée en même temps sur la même portée
					
					//Calcul de la mesure courante, mise à jour du numéro de note
					$measureTemp = getMeasureNumber($xml,$note);
					
					if($measureTemp != $measure){
						//changement de mesure, on réinitialise $noteNum
						$noteNum = 1;
						$measure = $measureTemp;
					}
					//echo "on est à la mesure ".$measureTemp." , note ".$noteNum." (".$note->pitch->step.$note->pitch->octave.")<br>";
					//echo "la previous note est un ".$test->pitch->step.$test->pitch->octave."<br>";
					if($currentEcart > 12){
						//on vérifie que la note précédente soit courte (qui peut être un silence). Si ce n'est pas le cas, alors on a le temps de faire le déplacement, même s'il est grand. Ce temps nécessaire est estimé à deux temps (ex : 2 noires en 4/4, 2 croches en 6/8)
						//echo "a la mesure ".$measure.", note numero ".$noteNum." (".$note->pitch->step.$note->pitch->octave.", pitchvalue=".$notePitchValue.") , le gap duration est de ".$gapDuration." pour un ecart valant ".$currentEcart.". La note précédente est un ".$previousNote->pitch->step.$previousNote->pitch->octave." de pitchvalue=".$previousNotePitchValue."<br>";
						if($gapDuration <=  $baseDuration * $quickestValue/$beatType * 2){
							//on ajoute cet écart à la liste des écarts importants ($diffDeplacements)
							$diffDeplacements[] = array($measure,$noteNum,$currentEcart);
							//echo "wo le grand écart à la mesure ".$measure." avec la note ".$noteNum." et de valeur ".$currentEcart."<br/>";
						}
					}
					
					//on regarde si la note est affectée par une altération accidentelle, si oui, on la rajoute à la liste
					if($note->accidental){
						$accidentalNotes[] = array($measure,$noteNum,$note->accidental);
					}
					
					//on regarde si on a une modification de temps (binaire sur ternaire ou vice versa)
					if(count($note->xpath('time-modification')) != 0){
						$timeModifications[] = $measure;
					}
					//est-on dans un accord ?
					if($i < count($notesRH)-1){
						if(($note->chord) or (!($note->chord) and $notesRH[$i+1]->chord)){
							$accord = true;
							if(!($note->chord)){ //c'est la première note de l'accord (ne comporte pas d'élément <chord/>)
								$numChordRH ++;
								$chordBaseValue = $notePitchValue; //on retient la valeur de la note de base ($notePrecedenteValue retient la valeur de la position, et non de la note, précédente)
							}
							if($notePitchValue == $chordBaseValue + 12){ //c'est un octave
								$numOctavesRH ++;
							}
						}
						else{
							$accord = false;
						}
					}
					
					$noteNum ++;
					
				}//fin for parcours des notes de la main droite
				
				//recherche du déplacement maximal au sein de l'ensemble des déplacements remarquables (>12 demi-tons)
				if(count($diffDeplacements) > 0) {
					$maxEcartDroite = $diffDeplacements[0];
					for ($i=1;$i<count($diffDeplacements);$i++) {
						$currentDep = $diffDeplacements[$i];
						if($currentDep[2]>$maxEcartDroite[2]){
							$maxEcartDroite = $currentDep;
						}
					}
					$maxIntervalRH = $maxEcartDroite[0];
				}
				else {
					$maxEcartDroite = 0;
					$maxIntervalRH = 0;
				}
				//ratio déplacements main droite
				//echo count($diffDeplacements)." ".$deplacementNum;
				$displacementsRH = count($diffDeplacements)/$deplacementNum * 100;
				
				
				//--------------boucle main gauche-----------------------------------
				$notesLH = $xml->xpath('//note[staff=2 and not(rest)]'); //on ne prend pas les silences
				//echo count($notesLH);
				
				$measure = 1; //la mesure courante
				$noteNum = 2; //le numéro de note au sein de la mesure courante, on commence à la 2ème note du morceau
				$accord = false; //booleen indiquant si l'on se situe a sein d'un accord ou non
				$deplacementNumLH = 0;
				$diffDeplacementsLH = array(); //on retient les déplacements difficiles sous la forme n de Mes, n de note dans la Mes, valeur Dep
				$accidentalNotesLH = array(); //on retient les notes avec des altérations accidentelles sous la forme (n de Mes, n  de note dans la Mes, alter type) alter type = sharp/flat/natural
				$timeModificationsLH = array(); //on détecte les mesures comportant du 3 pour 2
				
				//calcul de hauteur pour la première note
				$notePitchLetter = $notesLH[0]->pitch->step;
				$noteOctave = $notesLH[0]->pitch->octave;
				$alter = $notesLH[0]->pitch->alter; // -1 -> bémol, 1 -> dièse
				//initialisation de la previous note sur la première note, en cas de départ sur accord
				$previousNoteOctave = $noteOctave;
				$previousNotealter = $alter;
				$previousNotePitchValue = $notePitchLetter;
						
				if($notesLH[0]->accidental){
					$accidentalNotesLH[] = array($measure,1,$notesLH[0]->accidental);
				}
				//on vérifie si on a une modification du temps (temps ternaire sur du binaire ou vice versa)
				if(count($notesLH[0]->xpath('time-modification')) != 0){
					$timeModificationsLH[] = $measure;
				}
				$numChordLH = 0; //nombres d'accords à la main gauche
				$numOctavesLH = 0; //nombre d'octaves à la main gauche
				$chordBaseValue = 0; //hauteur de la base d'un accord
				$previousNote = $notesLH[0];
				for ($i=1;$i<count($notesLH);$i++) {
					//infos de base sur la note
					$note = $notesLH[$i];
					$notePitchLetter = $note->pitch->step;
					$noteOctave = $note->pitch->octave;
					$alter = $note->pitch->alter; // -1 -> bémol, 1 -> dièse
					$notePitchValue = pitch2int($notePitchLetter,$noteOctave,$alter); //entier représentant la hauteur de la note (C4 = 1, C4# = 2, D4 = 3, etc...)
					
					//recherche de la précédente note non jouée en même temps (pas au sein du même accord le cas échéant)
					if(!($note->chord)){
						$deplacementNumLH ++;
						$previousNote = getPreviousNoteElement($xml,$note);
						$gapDuration = $previousNote->duration; //durée du saut (temps écoulé entre les deux attaques).
						//on élimine maintenant les silences successifs(tout en mémorisant le temps écoulé)
						while($previousNote->rest){
							$previousNote = getPreviousNoteElement($xml,$previousNote); //uniquement pour la duration ! sinon, c'est la base de l'accord qui compte, donc $notePrecedenteValue
							$gapDuration = $gapDuration + $previousNote->duration;
						}
						while($previousNote->chord){
							$previousNote = getPreviousNoteElement($xml,$previousNote); //on remonte à la base de l'accord le cas échéant
						}
						$previousNotePitchLetter = $previousNote->pitch->step;
						$previousNoteOctave = $previousNote->pitch->octave;
						$previousNotealter = $previousNote->pitch->alter; // -1 -> bémol, 1 -> dièse
						$previousNotePitchValue = pitch2int($previousNotePitchLetter,$previousNoteOctave,$previousNotealter); 
					}
					$currentEcart = abs($notePitchValue - $previousNotePitchValue); //écart entre la note actuelle et la dernière note non jouée en même temps sur la même portée
					
					//Calcul de la mesure courante, mise à jour du numéro de note
					$measureTemp = getMeasureNumber($xml,$note);
					
					if($measureTemp != $measure){
						//changement de mesure, on réinitialise $noteNum
						$noteNum = 1;
						$measure = $measureTemp;
					}
					//echo "on est à la mesure ".$measureTemp." , note ".$noteNum." (".$note->pitch->step.$note->pitch->octave.")<br>";
					//echo "la previous note est un ".$test->pitch->step.$test->pitch->octave."<br>";
					if($currentEcart > 12){
						//on vérifie que la note précédente soit courte (qui peut être un silence). Si ce n'est pas le cas, alors on a le temps de faire le déplacement, même s'il est grand. Ce temps nécessaire est estimé à deux temps (ex : 2 noires en 4/4, 2 croches en 6/8)
						//echo "a la mesure ".$measure.", note numero ".$noteNum." (".$note->pitch->step.$note->pitch->octave.", pitchvalue=".$notePitchValue.") , le gap duration est de ".$gapDuration." pour un ecart valant ".$currentEcart.". La note précédente est un ".$previousNote->pitch->step.$previousNote->pitch->octave." de pitchvalue=".$previousNotePitchValue."<br>";
						if($gapDuration <=  $baseDuration * $quickestValue/$beatType * 2){
							//on ajoute cet écart à la liste des écarts importants ($diffDeplacements)
							$diffDeplacementsLH[] = array($measure,$noteNum,$currentEcart);
							//echo "wo le grand écart à la mesure ".$measure." avec la note ".$noteNum." et de valeur ".$currentEcart."<br/>";
						}
					}
					
					//on regarde si la note est affectée par une altération accidentelle, si oui, on la rajoute à la liste
					if($note->accidental){
						$accidentalNotesLH[] = array($measure,$noteNum,$note->accidental);
					}
					
					//on regarde si on a une modification de temps (binaire sur ternaire ou vice versa)
					if(count($note->xpath('time-modification')) != 0){
						$timeModificationsLH[] = $measure;
					}
					//est-on dans un accord ?
					if($i < count($notesLH)-1){
						if(($note->chord) or (!($note->chord) and $notesLH[$i+1]->chord)){
							$accord = true;
							if(!($note->chord)){ //c'est la première note de l'accord (ne comporte pas d'élément <chord/>)
								$numChordLH ++;
								$chordBaseValue = $notePitchValue; //on retient la valeur de la note de base ($notePrecedenteValue retient la valeur de la position, et non de la note, précédente)
							}
							if($notePitchValue == $chordBaseValue + 12){ //c'est un octave
								$numOctavesLH ++;
							}
						}
						else{
							$accord = false;
						}
					}
					
					$noteNum ++;
					
				}//fin for parcours des notes de la main gauche
				
				//recherche du déplacement maximal au sein de l'ensemble des déplacements remarqueables (>12 demi-tons)
				if(count($diffDeplacementsLH) > 0) {
					$maxEcartGauche = $diffDeplacementsLH[0];
					for ($i=1;$i<count($diffDeplacementsLH);$i++) {
						$currentDep = $diffDeplacementsLH[$i];
						if($currentDep[2]>$maxEcartGauche[2]){
							$maxEcartGauche = $currentDep;
						}
					}
					$maxIntervalLH = $maxEcartGauche[0];
				}
				else {
					$maxEcartGauche = 0;
					$maxIntervalLH = 0;
				}
				//ratio déplacements main droite
				//echo count($diffDeplacements)." ".$deplacementNum;
				$displacementsLH = count($diffDeplacementsLH)/$deplacementNumLH * 100;
				
				//détection du 3 pour 2 : un time-modification à une main mais pas l'autre
				$polyrhythm = array(); //tableau de mesures comportant du 3 pour 2 sous la forme (n de mesure,n de note)
				for($i=0;$i<count($timeModifications);$i++){
					$j = 0;
					$found = false;
					while($j<count($timeModificationsLH) && !$found && ($timeModificationsLH[$j] <= $timeModifications[$i])){
						if($timeModificationsLH[$j] == $timeModifications[$i])
							$found = true;
						$j++;
					}
					if(!$found){
						//print "on a trouvé un 3 pour 2 à la mesure ".$timeModifications[$i]."<br/>";
						if(count($polyrhythm) == 0)
							$polyrhythm[] = $timeModifications[$i];
						else if ($timeModifications[$i] != $polyrhythm[count($polyrhythm)-1])
							$polyrhythm[] = $timeModifications[$i];
					}
				}
				
				//ratio des accords
				if($deplacementNum != 0)
					$chordRatioRH = $numChordRH/$deplacementNum*100;
				else
					$chordRatioRH = 0;
				if($deplacementNumLH != 0)
					$chordRatioLH = $numChordLH/$deplacementNumLH*100;
				else
					$chordRatioLH = 0;
				if($numChordRH != 0)
					$octavesRatioRH = $numOctavesRH/$numChordRH*100;
				else
					$octavesRatioRH = 0;
				if($numChordLH != 0)
					$octavesRatioLH = $numOctavesLH/$numChordLH*100;
				else
					$octavesRatioLH = 0;
				
				//ratio des altérations accidentelles
				$accidentalRatioRH = count($accidentalNotes)/$noteCounterRH*100;
				$accidentalRatioLH = count($accidentalNotesLH)/$noteCounterLH*100;
				
				//ratio des 3 pour 2
				$polyrhythmRatio = count($polyrhythm)/$nbMesures * 100;
				
				//----------------------------- ENREGISTREMENT DANS LA BD ------------------------------------------------------------------------------------------------------
				//enregistrement de la piece
				//recherche du titre
				$title = "";
				if($xml->work->{"work-title"} != "" or $xml->{"movement-title"} != ""){
					$title = $xml->work->{"work-title"}." ".$xml->{"movement-title"};
				}
				else {
					$titles = $xml->xpath('//credit/credit-words');
					$title = $titles[0];
					for($i=1;$i<count($titles);$i++){
						$title = $title.", ".$titles[$i];
					}
				}
				//recherche du compositeur
				$composer = $identification[0]->creator;
				//mouvement
				$movement = $xml->{"movement-title"};
				$opus = null;
				$collection = null;
				$comment = null;
				
				$query = "INSERT INTO `pieces` (`idPiece`, `title`, `composer`, `movement`, `opus`, `collection`, `comment`) VALUES (NULL , '".addslashes($title)."', '".addslashes($composer)."', '".addslashes($movement)."', '".addslashes($opus)."', '".addslashes($collection)."', '".addslashes($comment)."');";
				$result = mysql_query($query);
	
				$idPiece = mysql_insert_id();
				//enregistrement du fichier xml
				$query = "INSERT INTO `scores` (`idScore`, `idPieceScore`, `xmlFile`, `pdfFile`, `nbpages`) VALUES (NULL, '".$idPiece."', '".$pieceXmlFile."', '".$scoreFiles[2]."', '".$nbpages."');";
				$result = mysql_query($query);
				
				//enregistrement des stats
				$query = "INSERT INTO `stats` (`idStat`, `idPieceStat`, `fifths`, `mode`, `nbmeasures`, `quantise`, `baseDuration`,`tempo`,`beats`,`beatType`,`quickestValue`,`maxIntervalRH`,`maxIntervalLH`,`octavesRatioRH`,`octavesRatioLH`,`noteCounterRH`,`noteCounterLH`,`nbPositionsRH`,`nbPositionsLH`) VALUES (NULL, '".$idPiece."', '".$tonality."', '".$mode."','".$nbMesures."', '".$quantise."', '".$baseDuration."', '".$tempo."', '".$beats."', '".$beatType."', '".$quickestValue."', '".$maxEcartDroite[0]."', '".$maxEcartGauche[0]."', '".$octavesRatioRH."', '".$octavesRatioLH."', '".$noteCounterRH."', '".$noteCounterLH."', '".$deplacementNum."', '".$deplacementNumLH."');";
				$result = mysql_query($query);
				
				//enregistrement des différentes parties détectées
				for ($i=0;$i<count($parts);$i++) {
					$query = "INSERT INTO `structure` (`idStruct`, `idPieceStruct`, `beginMeasure`, `endMeasure`, `beginTime`, `endTime`, `certitude`) VALUES (NULL, '".$idPiece."', '".$parts[$i][0]."', '".$parts[$i][1]."', NULL, NULL, '".$parts[$i][2]."');";
					$result = mysql_query($query);
				}
				
				//enregistrement des critères
				$speedRecord = $tempo*$quickestValue;
				//echo $speedRecord;
				$query = "INSERT INTO `criteria` (`idCriteria`, `idPieceCriteria`, `speed`, `displacementsRH`, `chordsRH`, `fingeringRH`, `harmonyRH`, `displacementsLH`, `chordsLH`, `fingeringLH`, `harmonyLH`, `rhythm`, `length`) VALUES (NULL, '".$idPiece."', '".$speedRecord."', '".$displacementsRH."', '".$chordRatioRH."', NULL, '".$accidentalRatioRH."', '".$displacementsLH."', '".$chordRatioLH."', NULL, '".$accidentalRatioLH."', '".$polyrhythmRatio."', '".$nbpages."');";
				$result = mysql_query($query);
				
				
				
			} //fin if (piece pas en BD)
			else {
				//on va chercher les taux ci-dessous dans la base
				
				//récupération des informations d'identification de la pièce
				$query = "SELECT `title`, `composer` FROM `pieces` WHERE `idPiece` = '".$idPiece."';";
				$result = mysql_query($query);
				$row = mysql_fetch_array($result, MYSQL_ASSOC);
				$title = "";
				$composer = "";
				if(count($row) > 0){
					$title = $row['title'];
					$composer = $row['composer'];
				}
				else {
					echo "error: no piece in the base";
				}
				
				//récupération des stats générales de la pièce
				$query = "SELECT `fifths`,`mode`, `nbmeasures`, `quantise`, `baseDuration`,`tempo`,`beats`,`beatType`,`quickestValue`,`maxIntervalRH`,`maxIntervalLH`,`octavesRatioRH`,`octavesRatioLH`,`noteCounterRH`,`noteCounterLH`,`nbPositionsRH`,`nbPositionsLH` FROM `stats` WHERE `idPieceStat` = '".$idPiece."';";
				$result = mysql_query($query);
				$row = mysql_fetch_array($result, MYSQL_ASSOC);
				$tonalityString = "";
				$nbMesures = "";
				$quantise = "";
				$baseDuration = "";
				$tempo = "";
				$beats = "";
				$beatType = "";
				$quickestValue = "";
				$maxIntervalRH = "";
				$maxIntervalLH = "";
				$octavesRatioRH = "";
				$octavesRatioLH = "";
				$noteCounterRH = "";
				$noteCounterLH = "";
				$nbPositionsRH = "";
				$nbPositionsLH = "";
				if(count($row) > 0){
					$tonalityString = getTonality($row['fifths'],$row['mode'])." ".$row['mode'];
					$tonality = $row['fifths'];
					$nbMesures = $row['nbmeasures'];
					$quantise = $row['quantise'];
					$baseDuration = $row['baseDuration'];
					$tempo = $row['tempo'];
					$beats = $row['beats'];
					$beatType = $row['beatType'];
					$quickestValue = $row['quickestValue'];
					$maxIntervalRH = $row['maxIntervalRH'];
					$maxIntervalLH = $row['maxIntervalLH'];
					$octavesRatioRH = $row['octavesRatioRH'];
					$octavesRatioLH = $row['octavesRatioLH'];
					$noteCounterRH = $row['noteCounterRH'];
					$noteCounterLH = $row['noteCounterLH'];
					$nbPositionsRH = $row['nbPositionsRH'];
					$nbPositionsLH = $row['nbPositionsLH'];
				}
				else {
					echo "error: no stat in the base";
				}
				
				//récupération des différentes parties de la pièce
				$query = "SELECT `beginMeasure`, `endMeasure`, `certitude` FROM `structure` WHERE `idPieceStruct` = '".$idPiece."';";
				$result = mysql_query($query);
				$parts = array();
				while($row = mysql_fetch_array($result, MYSQL_ASSOC)){
					$parts[] = array($row['beginMeasure'],$row['endMeasure'],$row['certitude']);
				}
				
				//récupération des ratios de difficulté de la pièce
				$query = "SELECT `speed`, `displacementsRH`, `displacementsLH`, `chordsRH`, `chordsLH`, `rhythm`, `harmonyRH`, `harmonyLH` FROM `criteria` WHERE `idPieceCriteria` = '".$idPiece."';";
				$result = mysql_query($query);
				$row = mysql_fetch_array($result, MYSQL_ASSOC);
				$rapidite = "";
				$displacementsRH = "";
				$displacementsLH = "";
				$chordRatioRH = "";
				$chordRatioLH = "";
				$polyrhythmRatio = "";
				$accidentalRatioRH = "";
				$accidentalRatioLH = "";
				if(count($row) > 0){
					$rapidite = $row['speed'] * 100 / 2816; // rappel : on avait enregistré que le produit bpm * shortest value pour ne pas déformer les données
					$displacementsRH = $row['displacementsRH'];
					$displacementsLH = $row['displacementsLH'];
					$chordRatioRH = $row['chordsRH'];
					$chordRatioLH = $row['chordsLH'];
					$polyrhythmRatio = $row['rhythm'];
					$accidentalRatioRH = $row['harmonyRH'];
					$accidentalRatioLH = $row['harmonyLH'];
				}
				else {
					echo "error: no difficulty ratio in the base";
				}
				
				
				
			}// fin else (cas de partition déjà en base)
			
			
			//calcul des notes pour chaque critère puis moyenne générale
			$speedResult = evaluateDifficulty("speed",$rapidite,null);
			$displacementResult = evaluateDifficulty("displacement",$displacementsRH,$displacementsLH);
			$chordResult = evaluateDifficulty("chords",$chordRatioRH,$chordRatioLH);
			$harmonyResult = evaluateDifficulty("harmony",$accidentalRatioRH,$accidentalRatioLH);
			$rhythmResult = evaluateDifficulty("rhythm",$polyrhythmRatio,null);
			$lengthResult = evaluateDifficulty("nbpages",$nbpages,null);
			$tonalityResult = evaluateDifficulty("tonality",abs($tonality),null);
			
			$moyenne = round(($speedResult + $displacementResult + $chordResult + $harmonyResult + $rhythmResult + $lengthResult + $tonalityResult)/7,1);

		?>
			
		<!------------------------------------------------ Affichage des infos ----------------------------------------------------------------->
		<div id="analysis">
		
		<!-------------------------------------------- Identification de l'oeuvre ------------------------------------------------------------->
			<div id="identification">
				<h2>Identification</h2>
				<ul>
				<?php
					
					print "<li> Title : ".$title."</li>";
					print "<li> Composer : ".$composer."</li>";
				?>
				</ul>
			</div> <!-- End identification -->
		
			<!-------------------------------------------- Statistiques ------------------------------------------------------------->
			<div id="statistics">
				<h2>Statistics</h2>
				<ul>
				<?php
					print "<li> Tonality : ".$tonalityString."</li>";
					print "<li> Number of pages : ".$nbpages."</li>";
					print "<li> Number of measures : ".$nbMesures."</li>";
					print "<li> Quantise : ".$quantise." (base duration : ".$baseDuration.")</li>";
					/*print "<li> Number of notes : ".$noteCounter."</li>";
					print "<li> Number of notes on Right Hand : ".$noteCounterRH."</li>";
					print "<li> Number of notes on Left Hand : ".$noteCounterLH."</li>";
					print "<li> ratio whole : ".round($noteProportions["whole"]/$noteCounter*100,2)."%</li>";
					print "<li> ratio half : ".round($noteProportions["half"]/$noteCounter*100,2)."%</li>";
					print "<li> ratio quarter : ".round($noteProportions["quarter"]/$noteCounter*100,2)."%</li>";
					print "<li> ratio eighth : ".round($noteProportions["eighth"]/$noteCounter*100,2)."%</li>";
					print "<li> ratio 16th : ".round($noteProportions["16th"]/$noteCounter*100,2)."%</li>";
					print "<li> ratio 32nd : ".round($noteProportions["32nd"]/$noteCounter*100,2)."%</li>";
					print "<li> ratio 64th : ".round($noteProportions["64th"]/$noteCounter*100,2)."%</li>";
					print "<li> ratio 128th : ".round($noteProportions["128th"]/$noteCounter*100,2)."%</li>";
					print "<li> ratio 256th : ".round($noteProportions["256th"]/$noteCounter*100,2)."%</li>";*/
					//echo "nbre artistes : ".count($artists);
					/*foreach ($artists as $artist) {
						$artistAttributes = $artist->attributes();
						print "<li>".$artist->name." (".$artistAttributes['type'].") ";
						$artist->registerXPathNamespace('mmd', 'http://musicbrainz.org/ns/mmd-1.0#');
						$lifeSpan=$artist->xpath('mmd:life-span');
						if(count($lifeSpan)>0){
							$lifeSpanAttributes=$lifeSpan[0]->attributes();
							print $lifeSpanAttributes['begin']." - ".$lifeSpanAttributes['end'];
						}
						print "</li>";
					}*/

				?>
				</ul>
			</div> <!-- End statistics -->
			
			
			<!-------------------------------------------- Structure ------------------------------------------------------------->
			<div id="structure">
				<h2>Structure</h2>
				<ul>
				<?php
					for ($i=0;$i<count($parts);$i++) {
						echo "<li>part ".($i+1)." : mesures ".$parts[$i][0]." à ".$parts[$i][1]." (certitude : ".$parts[$i][2]." )</li>";
						//echo "<br/>";
					}
				?>
				</ul>
			</div>
			
			
			<!-------------------------------------------- Difficulté de l'oeuvre ------------------------------------------------------------->
			<div id="difficulty">
				<h2>Difficulty</h2>
				<div class="section">
					<h3>General</h3>
					<div class="detail">
						Marks summary (/4) :
						<ul>
							<li>speed difficulty: <?php print $speedResult;?></li>
							<li>displacements difficulty: <?php print $displacementResult;?></li>
							<li>chords difficulty: <?php print $chordResult;?></li>
							<li>alterations difficulty: <?php print $harmonyResult;?></li>
							<li>rhythm difficulty: <?php print $rhythmResult;?></li>
						</ul>
					</div>
					<div class="graphic">
						Result: <br/>
						<img src="<?php echo getDifficultyImageFromComment(getDifficultyComment("general",$moyenne));?>"/>
						<p><?php echo $moyenne."<br/>".getDifficultyComment("general",$moyenne);?></p>
					</div>
				</div>
				<div class="section">
					<h3>Playing speed</h3>
					<div class="detail">
						<p>
							tempo : <?php print $tempo; ?> , measure : <?php print $beats."/".$beatType; ?> (<?php if ($beatType == 4) print "binary"; else print "compound"; ?>)
							<br/>
							shortest significant value : <?php print $quickestValue; ?>
							<br/>
							estimated playing speed ratio : <?php print round($rapidite); ?> %
						</p>
					</div>
					<div class="graphic">
						Result: <br/>
						<img src="<?php echo getDifficultyImage($speedResult);?>"/>
						<p><?php echo getDifficultyComment("speed",$speedResult);?></p>
					</div>
				</div>
				<div class="section">
					<h3>Hand Displacement</h3>
					<div class="detail">
						<p>
							Largest displacement at right hand (in semitones) : 
							<?php
								if($displacementsRH != 0){	
									if($calculated) {
										//on est en mode "calcul", les informations ci-dessous sont donc dispos
										print $maxEcartDroite[2]; 
										$inOctaves = array();
										$inOctaves = semitone2octave($maxEcartDroite[2]); 
										print " (".$inOctaves[0]." octaves and ".$inOctaves[1]." semitones), at measure ".$maxEcartDroite[0];
									}
									else {
										print "at measure ".$maxIntervalRH; 
									}
							?>
									<br/>
									estimated displacements ratio at right hand (>12 semitones) : <?php print round($displacementsRH); ?> %
									
							<?php
								}
								else
									print "no major displacement";
								
							?>
							<br/><br/>
							Largest displacement at left hand (in semitones) :  
							<?php
								if($displacementsLH != 0){
									if($calculated) {								
										print $maxEcartGauche[2]; 
										$inOctaves = array();
										$inOctaves = semitone2octave($maxEcartGauche[2]); 
										print " (".$inOctaves[0]." octaves and ".$inOctaves[1]." semitones), at measure ".$maxEcartGauche[0];
									}
									else {
										print "at measure ".$maxIntervalLH; 
									}
							?>
									<br/>
									Estimated displacements ratio at left hand (>12 semitones) : <?php print round($displacementsLH); ?> %
									<br/><br/>
							<?php
									/*for($i=0;$i<count($diffDeplacementsLH);$i++){
										print "mesure ".$diffDeplacementsLH[$i][0].", note ".$diffDeplacementsLH[$i][1].", espacement ".$diffDeplacementsLH[$i][2].".<br/>";
									}*/
								}
								else
									print "no major displacement";
								
							?>
						</p>
					</div>
					<div class="graphic">
						Result: <br/>
						<img src="<?php echo getDifficultyImage($displacementResult);?>"/>
						<p><?php echo getDifficultyComment("displacement",$displacementResult);?></p>
					</div>
				</div>
				<div class="section">
					<h3>Chords</h3>
					<div class="detail">
						<p>
							Chords ratio at right hand : <?php print round($chordRatioRH); ?> % (<?php print round($octavesRatioRH); ?> % of which are octaves)
							<br/>
							Chords ratio at left hand : <?php print round($chordRatioLH); ?> % (<?php print round($octavesRatioLH); ?> % of which are octaves)
						</p>
					</div>
					<div class="graphic">
						Result: <br/>
						<img src="<?php echo getDifficultyImage($chordResult);?>"/>
						<p><?php echo getDifficultyComment("chords",$chordResult);?></p>
					</div>
				</div>
				<div class="section">
					<h3>Harmony</h3>
					<div class="detail">
						<p>
							Ratio of accidental alterations at right hand : <?php print round($accidentalRatioRH); ?> %
							<br/>
							Ratio of accidental alterations at left hand : <?php print round($accidentalRatioLH); ?> %
						</p>
					</div>
					<div class="graphic">
						Result: <br/>
						<img src="<?php echo getDifficultyImage($harmonyResult);?>"/>
						<p><?php echo getDifficultyComment("harmony",$harmonyResult);?></p>
					</div>
				</div>
				<div class="section">
					<h3>Rhythm</h3>
					<div class="detail">
						<p>
						<?php
							if($idPiece == "") {
						?>
							Time modifications (binary/compound) at right hand : 
							<?php 
								if(count($timeModifications) == 0){
									print "none";
								}
								else {
									print "measures ".$timeModifications[0]." ";
									$previousTM = $timeModifications[0];
									for($i=1;$i<count($timeModifications);$i++){
										if($timeModifications[$i] != $previousTM)
											print $timeModifications[$i]." ";
										$previousTM = $timeModifications[$i];
									}
								}
							?> 
							<br/>
							Time modifications (binary/compound) at left hand : 
							<?php 
								if(count($timeModificationsLH) == 0){
									print "none";
								}
								else {
									print "measures ".$timeModificationsLH[0]." ";
									$previousTM = $timeModificationsLH[0];
									for($i=1;$i<count($timeModificationsLH);$i++){
										if($timeModificationsLH[$i] != $previousTM)
											print $timeModificationsLH[$i]." ";
										$previousTM = $timeModificationsLH[$i];
									}
								}
							?> 
							<br/>
							Three for twos : 
							<?php 
								if(count($polyrhythm) == 0){
									print "none";
								}
								else {
									for($i=0;$i<count($polyrhythm);$i++){
											print $polyrhythm[$i]." ";
									}
								}
							?> 
						<?php
							} //fin if($idPiece == "") {
							else {
							 print "Three for twos ratio : ".round($polyrhythmRatio);
							}
						?>
						</p>
						<br/>
						<?php
							/*$myNoteProv = getNote($xml,10,5,2);
							$myTimecodes = atSameTime($myNoteProv);
							for($i=0;$i<count($myTimecodes);$i++){
								$noteProv = $myTimecodes[$i][0];
								print "note ".$i." (".$noteProv->pitch->step.")<br/>";
							}*/
							
						?>
					</div>
					<div class="graphic">
						Result: <br/>
						<img src="<?php echo getDifficultyImage($rhythmResult);?>"/>
						<p><?php echo getDifficultyComment("rhythm",$rhythmResult);?></p>
					</div>
				</div>
				<div class="section">
					<h3>Other indicators</h3>
					<div class="graphic">
						Length: <br/>
						<img src="<?php echo getDifficultyImage($lengthResult);?>"/>
						<p><?php echo $nbpages." pages<br/>".getDifficultyComment("nbpages",$lengthResult);?></p>
					</div>
					<div class="graphic">
						Tonality: <br/>
						<img src="<?php echo getDifficultyImage($tonalityResult);?>"/>
						<p>
							<?php
								echo abs($tonality[0]);
								if($tonality[0] < 0)
									echo " b<br/>";
								else
									echo " #<br/>";
								echo getDifficultyComment("tonality",$tonalityResult);
							?>
						</p>
					</div>
				</div>
			</div> <!-- End difficulty -->
			
			
		</div> <!-- End analysis -->
	</div> <!-- End content -->
 </body>
</html>