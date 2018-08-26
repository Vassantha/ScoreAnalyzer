<?php

	require_once "bdd-inc.php";
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
	
	function getDifficultyComment($score){
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
	}
			
	//recuperation des ids
	$query0 = "SELECT `idPiece` FROM `pieces` WHERE 1";
	$result0 = mysql_query($query0);
	
	while($row = mysql_fetch_array($result0, MYSQL_ASSOC)){
		$idPiece = $row['idPiece'];
		echo "on traite la piece ".$idPiece."<br>";
		//récupération des ratios de difficulté de la pièce
		$query1 = "SELECT `speed`, `displacementsRH`, `displacementsLH`, `chordsRH`, `chordsLH`, `rhythm`, `harmonyRH`, `harmonyLH` FROM `criteria` WHERE `idPieceCriteria` = '".$idPiece."';";
		$result1 = mysql_query($query1);
		$row = mysql_fetch_array($result1, MYSQL_ASSOC);
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

		$query2 = "SELECT `fifths` FROM `stats` WHERE `idPieceStat` = '".$idPiece."';";
		$result2 = mysql_query($query2);
		$row = mysql_fetch_array($result2, MYSQL_ASSOC);
		if(count($row) > 0){
			$tonality = $row['fifths'];
		}
		else {
			echo "error: no stat in the base";
		}
		
		$query = "SELECT `nbpages` FROM `scores` WHERE `idPieceScore` = '".$idPiece."';";
		$result = mysql_query($query);
		$row = mysql_fetch_array($result, MYSQL_ASSOC);
		if(count($row) > 0){
			$nbpages = $row['nbpages'];
		}
		else {
			echo "error: no stat in the base";
		}
				
		//calcul des notes pour chaque critère puis moyenne générale
		$speedResult = evaluateDifficulty("speed",$rapidite,null);
		$displacementResult = evaluateDifficulty("displacement",$displacementsRH,$displacementsLH);
		$chordResult = evaluateDifficulty("chords",$chordRatioRH,$chordRatioLH);
		$harmonyResult = evaluateDifficulty("harmony",$accidentalRatioRH,$accidentalRatioLH);
		$rhythmResult = evaluateDifficulty("rhythm",$polyrhythmRatio,null);
		$lengthResult = evaluateDifficulty("nbpages",$nbpages,null);
		$tonalityResult = evaluateDifficulty("tonality",abs($tonality),null);

		$moyenne = round(($speedResult + $displacementResult + $chordResult + $harmonyResult + $rhythmResult + $lengthResult + $tonalityResult)/7,1);
		echo "oooh cette piece obtient une moyenne de ".$moyenne."!<br><br>";
		$comment = getDifficultyComment($moyenne);
		//insertion des notes
		$query3 = "INSERT INTO `levels` (`idLevels`,`idPieceLevels`,`averageMark`,`level`,`speedMark`,`displacementMark`,`chordMark`,`harmonyMark`,`rhythmMark`,`lengthMark`,`tonalityMark`,`fingeringMark`) VALUES (NULL, '".$idPiece."', '".$moyenne."', '".$comment."', '".$speedResult."', '".$displacementResult."', '".$chordResult."', '".$harmonyResult."', '".$rhythmResult."', '".$lengthResult."', '".$tonalityResult."', NULL);";
		$result3 = mysql_query($query3);
	}		
?>