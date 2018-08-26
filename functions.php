<?php

		
	//Write distant mxl file on server for extraction
	function downloadFile ($url, $path) {
	  /*$newfname = $path;
	  $data = curl($url);
	  $file = fopen ($data, "rb");
	  if ($data) {
		$newf = fopen ($newfname, "wb");
		if ($newf){
			while(!feof($data)) {
				fwrite($newf, fread($data, 1024 * 8 ), 1024 * 8 );
			}
		}
		else{
			echo "file could not be created";
			exit;
		 }
	  }
	  else{
		echo "resource not found";
		exit;
	  }
	  /*if ($file) {
		fclose($file);
	  }*/
	  /*if ($newf) {
		fclose($newf);
	  }*/
	  $data = curl2($url,$path);
	}
	
	/* ------------------------------ MusicXML Data manipulation functions -------------------------------------------------*/
	
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
							//echo "y a pas de notes : ".$mes."<br>";
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
	
	/*------------------------------------ Difficulty evaluation functions -------------------------------------------------*/
	
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
	
	/* ------------------------------ END FUNCTIONS -------------------------------------------------*/
?>