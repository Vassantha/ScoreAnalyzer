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

	//sélection de la BDD
	$db = @mysql_select_db("$usebdd", $connexion);
	if (!$db) {
		echo "Impossible de sélectionner cette base données";
		exit;
	}

?>