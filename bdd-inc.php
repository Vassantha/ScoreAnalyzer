<?php

	$dbhost ="localhost";
	$user ="";
	$password ="";
	$usebdd = "e_piano_score_analyzer"; 

	//connexion au serveur MySQL
	$connexion = @mysql_connect("$dbhost","$user","$password");
	if (!$connexion) {
		echo "Impossible d'effectuer la connexion";
		exit;
	}

	//s�lection de la BDD
	$db = @mysql_select_db("$usebdd", $connexion);
	if (!$db) {
		echo "Impossible de s�lectionner cette base donn�es";
		exit;
	}

?>