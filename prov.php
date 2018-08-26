
<?php

	//fonctions
	include("./common.php");
	//parameters

	//on recupere l'id de la piece actuelle
	if(isset($_GET["pieceid"])) {
			$piece_id=$_GET["pieceid"];
		}
		else {
			$piece_id=1;
		}

	//on recupere le numero de la page (par defaut, on arrive sur la page 1 de la partition)
	if(isset($_GET["page"])) {
			$page=$_GET["page"];
		}
		else {
			$page=1;
		}
	
	//variables generales

	//repertoire des pieces
	define('PIECE_DIR','./pieces/');

	//chargement des informations de la piece

	//connection à la bdd
	require_once "bdd-inc.php";
	
	$query= "SELECT `titre`,`nom`,`prenom`,`opus`,`mvt`,`cours`
				FROM `pieces`, `compositeurs`
				WHERE pieces.piece_id = ".$piece_id."
				 AND compositeurs.compositeur_id = pieces.compositeur
				";
	$result=mysql_query($query);
	if(!$result){
		echo "erreur de recuperation des donnees sur la piece";
		exit;
	}

	//definition des variables
	$row = mysql_fetch_array($result, MYSQL_ASSOC);
	$currentPiece = $row['titre'];
	$currentComposerName = $row['nom'];
	$currentComposer = $row['prenom']." ".$row['nom'];
	$opus = $row['opus'];
	$mvt = $row['mvt'];
	$cours = $row['cours'];
	//chargement du topic phpbb associe a la piece
	$query4= "SELECT `topic_id`,`forum_id`
				FROM `phpbb_topics`
				WHERE topic_piece = '".$piece_id."'";

	$result4=mysql_query($query4);
	if(!$result4){
		echo "erreur de recuperation des donnees du topic associe";
		exit;
	}

	$row4 = mysql_fetch_array($result4, MYSQL_ASSOC);
	$topic_id = $row4['topic_id'];
	$forum_id = $row4['forum_id'];

	//chargement des messages (posts) du topic et de la page		

	$query7= "SELECT `post_id`, `position_id`, `post_text`, `post_time`, `username`, `score_x0`, `score_y0`, `score_x1`, `score_y1`,`post_attachment`,`fil_id`,`sujet` 
				FROM `phpbb_posts`, `phpbb_users`, `phpbb_positions`, `phpbb_fils`
				WHERE topic_id = '".$topic_id."'
				AND forum_id = '".$forum_id."'
				AND poster_id = phpbb_users.user_id	 
				AND (phpbb_positions.position_id,fil_id) = (
				SELECT `position_id`,`fil_id`
				FROM `phpbb_posts_positions`
				WHERE post_id = phpbb_posts.post_id AND page = '".$page."')
				ORDER BY `position_id`,`fil_id` ASC
				";
	$result7=mysql_query($query7);
	if(!$result7){
		echo "erreur d'affichage des messages";
		exit;
	}
	
	//booleen indiquant si la piece comporte des messages (posts).
	$nomessage = true;

	if(count($result7) != 0){
		
		$nomessage = false;
		
		//formattage des donnees des posts
		while ($row7 = mysql_fetch_array($result7, MYSQL_ASSOC)) {
			
			$message = $row7['post_text'];
			$message = str_replace("{SMILIES_PATH}","./forum/phpBB3/images/smilies",$message);
			$message = str_replace("\r","",$message);
			$message = bbcode_nl2br($message);
			$date = date('d-m-Y H:i', $row7['post_time']);
			$auteur = $row7['username'];
			$position = $row7['position_id'];
			$x0 = $row7['score_x0'];
			$y0 = $row7['score_y0'];
			$x1 = $row7['score_x1'];
			$y1 = $row7['score_y1'];
			$fil_id = $row7['fil_id'];
			$sujet = $row7['sujet'];
			//si le message se rapporte a la partition, on affiche la position de celui-ci sur l'image
			if ($position != null) {
				
				$infosMessage[] = array($position,$row7['post_id'],$message,$date,$auteur,$x0,$y0,$x1,$y1,$forum_id,$topic_id,$row7['post_attachment'],$fil_id,$sujet);
				/*echo "x0: ".$x0."<br>";
				echo "y0: ".$y0."<br>";
				echo "x1: ".$x1."<br>";
				echo "y1: ".$y1."<br>";*/

			} // fin if ($position!=null)
	
		}//fin while ($row7 = mysql_fetch_array($result7, MYSQL_ASSOC))
	}//fin if(count($result7) != 0)

	//chargement des ressources associees a la piece

	$query2= "SELECT `titre`,`fichier`,`description`,`type`, `ref`, `username`, `date`, `page`
				FROM `ressources`, `ressources_pieces`, `phpbb_users`
				WHERE ressources_pieces.piece_id = ".$piece_id."
				 AND ressources_pieces.ressource_id = ressources.ressource_id
				 AND ressources_pieces.user_id = phpbb_users.user_id
				";
	$result2=mysql_query($query2);
	if(!$result2){
		echo "erreur de recuperation des ressources associees a la piece";
		exit;
	}

	if(count($result2) != 0){
		?>
			<div id="sidebar-right">
				<h1>Ressources</h1>
				<!-- bouton d'acces au cours associé -->
				<?php 
					if($cours == "1"){
				?>
				<!-- bouton d'acces au cours associé -->
				<a href='index.php?id=1&currentPage=cours.php&pieceid=<?php echo $piece_id;?>'><img src="./css/boutonCours.png" alt="accéder au cours" class="lessonButton"/></a>
				<?php }//fin if cours ?>
				
				<div class='box'>
					<ul class="nospace">
		<?php
		$partition_ref = 0;
		$nbpages = 0;
		//affichage des resultats
		$k = 0; //compteur de boucle, num de ressource

		while ($row2 = mysql_fetch_array($result2, MYSQL_ASSOC)) {

			if ($row2['ref'] == 1 && $row2['type'] == "partition"){
				$partition_page = $row2['page'];
				//on a trouve une page de la partition de reference
				if ($partition_page == 1)
					$partition_ref = 1;
				
				$partition_file = $row2['fichier'];
				$repname = filter($currentPiece).filter($opus).filter($mvt)."_".filter($currentComposerName);
				$partition_path = PIECE_DIR.$repname."/partitions/".$partition_file;
				$partition_page = $row2['page'];
				//echo $partition_path;
				$partition_tab[$partition_page] = $partition_path;
				$nbpages++;
			}
			else {
				//traitement des autres ressources associees (videos, images, audio, ...)
				$type = $row2['type'];
				$rep_type = "/videos/";
				switch ($type) {
					case "partition":
						$rep_type = "/partitions/";
						break;
					case "image":
						$rep_type = "/images/";
						break;
					case "video":
						$rep_type = "/videos/";
						break;
					case "texte":
						$rep_type = "/textes/";
						break;
					case "son":
						$rep_type = "/sons/";
						break;
				}
				//$ressource_id = $row2['ressource_id'];
				$ressource_id = $k;
				$ressource_file = $row2['fichier'];
				$ressource_name = $row2['titre'];
				//$currentPieceWithoutSpaces = str_replace(" ", "", "$currentPiece");
				$ressource_path = PIECE_DIR.$repname.$rep_type.$ressource_file;
				$date = $row2['date'];
				$user = $row2['username'];
				$description = $row2['description'];
				//creation de l'apercu si c'est une video et si celui-ci n'existe pas deja
				if ($type == "video") {
					$preview_file = substr($ressource_file, 0, strrpos($ressource_file, ".")).".jpg";
					$preview_path = PIECE_DIR.$repname.$rep_type."previews/".$preview_file;
					if (!file_exists($preview_path)){
						if(!file_exists(PIECE_DIR.$repname.$rep_type."previews")){
							mkdir(PIECE_DIR.$repname.$rep_type."previews",0777);
						}
						extension_loaded('ffmpeg') or die('Error in loading ffmpeg');
						$ffmpegInstance = new ffmpeg_movie($ressource_path);

						//get a frame:
						$width = $ffmpegInstance->getFrameWidth();
						$height = $ffmpegInstance->getFrameHeight();
						$myframe = $ffmpegInstance->getFrame(200);
						$mygdimage = $myframe->toGDImage();
						$myjpgimage = imagecreatetruecolor(112,90);
						imagecopyresampled  ($myjpgimage,$mygdimage,0,0,0,0,112,90,$width,$height);
						imagejpeg($myjpgimage,$preview_path);
					}
				}

				else if ($type == "image") {
					$preview_file = $ressource_file;
					$preview_path = PIECE_DIR.$repname.$rep_type."previews/".$preview_file;
					if (!file_exists($preview_path)){
						if(!file_exists(PIECE_DIR.$repname.$rep_type."previews")){
							mkdir(PIECE_DIR.$repname.$rep_type."previews",0777);
						}
						createthumb($ressource_path,PIECE_DIR.$repname.$rep_type."previews/",112);
					}
				}
				echo "<li>";

				echo substr($date,0,strrpos($date, " "))." par ".$user.":<br>";
				/*echo "<form name='mediaform' action='./show_media.php' method='post'>";
				echo "<input type='hidden' name='ressource_path' value='".$ressource_path."'>";
				echo "<a href='javascript:document.mediaform.submit();'>";
				echo $ressource_name;
				if (file_exists($preview_path)){
					echo "<img src='$preview_path'>";
				}
				echo "</a>";
				echo "</form>";*/

				/*echo "<a href='".$ressource_path."'";
				if ($type == "vidéo")
					echo " rel='vidbox' title='caption'>";
				else echo ">";
				echo $ressource_name;
				if ($type == "vidéo" && file_exists($preview_path)){
					echo "<img src='$preview_path'>";
				}
				echo "</a>";*/

				
				if ($type == "video" or $type == "image")
					echo "<a href='#ressource".$ressource_id."' rel='ressource".$ressource_id."' class='lbOn'>";
				else
					echo "<a href='".$ressource_path."'>";
				echo $ressource_name;
				echo "<br>";
				if (($type == "video" or $type == "image") && file_exists($preview_path)){
					echo "<img src='$preview_path' class='thumb'>";
				}
				echo "</a>";
					
				
				
				/*echo "<a href='index.php?id=1&mediapath=".$ressource_file."&currentPage=media_display.php&pieceid=".$piece_id;
				if ($type == "vidéo")
					echo "&type=video>";
				else echo ">";
				echo $ressource_name;
				if ($type == "vidéo" && file_exists($preview_path)){
					echo "<img src='$preview_path'>";
				}
				echo "</a>";*/
				if ($type =="son"){
					$ressource_path_abs = substr($ressource_path,1);
					/*echo "<object type='application/x-shockwave-flash' 
						data='./swf/musicplayer.swf?song_url=http://localhost/e-piano.".$ressource_path_abs."'>
						<param name='movie' 
						value='./swf/musicplayer.swf?song_url=http://localhost/e-piano.".$ressource_path_abs."' />
						</object>
						";*/
				?>
					<object type="application/x-shockwave-flash"
data="http://e-piano.univ-reunion.fr/swf/musicplayer.swf?&song_url=http://e-piano.univ-reunion.fr<?php echo $ressource_path_abs;?>&" 
width="17" height="17">
<param name="movie" 
value="http://e-piano.univ-reunion.fr/swf/musicplayer.swf?&song_url=http://e-piano.univ-reunion.fr<?php echo $ressource_path_abs;?>&" />
<img src="noflash.gif" 
width="17" height="17" alt="" />
</object>
				<?php
				}// fin if son

				echo "</li>";

				if ($type == "video"){
					echo "<div id='ressource'>";
					echo "<div id='ressource".$ressource_id."' class='leightbox'>";

					echo "<h2>".$ressource_name."</h2>";
				?>
					<p class='footerleightbox'><a href='#' class='lbAction' rel='deactivate'>Fermer la fenêtre</a></p>
					<br>
					<object type="application/x-shockwave-flash" data="./swf/player_flv_multi.swf" width="450" height="360">
					<param name="movie" value="./swf/player_flv_multi.swf" />
					<param name="FlashVars" value="flv=./../<?php echo $ressource_path;?>" />
					</object>
					<p><?php echo $description;?></p>
					
				<?php
					echo "</div>";
					echo "</div>";
				}

				else if ($type == "image") {
					echo "<div id='ressource'>";
					echo "<div id='ressource".$ressource_id."' class='leightbox'>";

					echo "<h2>".$ressource_name."</h2>";
				?>
					<p class='footerleightbox'><a href='#' class='lbAction' rel='deactivate'>Fermer la fenêtre</a></p>
					<br>
					<img src="<?php echo $ressource_path;?>" alt="<?php echo $ressource_name;?>"/>
					<p><?php echo $description;?></p>
					
				<?php
					echo "</div>";
					echo "</div>";
				}
			}// fin else
		

			
			$k++;
		}// fin while ($row2 = mysql_fetch_array($result2, MYSQL_ASSOC))
		?>
					</ul>

					<a href="<?php echo append_sid('index.php?id=1&currentPage=ajout_ressource.php&pieceid=' . $piece_id);?>"><img src="./css/boutonRessource.png" alt="ajouter une ressource" class="lessonButton"/></a>

				</div> <!-- fin box -->
				
			</div> <!-- fin sidebar-right -->
		<?php
	}// fin if(count($result2) != 0)

?>
<div id="piece">
	
	<h1>Forum musical: <?php echo $currentPiece; ?></h1>
	<h2><?php echo $currentComposer; ?></h2>
	<div id="toppiece">
		<a href="#" onclick="javascript:window.open ('aide.html', 'aide', config='height=300, width=400, toolbar=no, menubar=no, scrollbars=no, resizable=no, location=no, directories=no, status=no');" class="float-right">aide <img src="./css/aide.png"/></a>
		<form name='scoreform' action="<?php echo append_sid('./forum/phpBB3/posting.php?mode=reply&f=' . $forum_id . '&t=' . $topic_id); ?>" method="post">
			<input type='hidden' name='score_img' value='<?php echo $partition_tab[$page]; ?>'>
			<input type='hidden' name='piece_id' value='<?php echo $piece_id; ?>'>
			<input type='hidden' name='page' value='<?php echo $page; ?>'>
			<a href='javascript:document.scoreform.submit();' class="float-left"><img src="./css/boutonpost.png" alt="poster un message"/></a>
		</form>
		<p>
			<?php 
				if ($page > 1){
					echo "<a href='./index.php?id=1&currentPage=piece.php&pieceid=".$piece_id."&page=".($page-1)."'>< </a>";
				}
			?>
			page <?php echo $page." / ".$nbpages; ?>
			<?php 
				if ($page < $nbpages){
					echo "<a href='./index.php?id=1&currentPage=piece.php&pieceid=".$piece_id."&page=".($page+1)."'> ></a>";
				}
			?>
		</p>
	</div>
	<!--<div id="mydiv2" class="leightbox">
		<p>coucou</p>
	</div>-->
	

	<br>

		

		<?php
			if ($partition_ref == 1) {
		?>

	
		<div id="rectanglewrap"></div>
		<div id="filboxwrap"></div>
		<div id="messageboxwrap">
		
</div>
		<div id="pieceimg">
			<img src="<?php echo $partition_tab[$page]; ?>" id="mouseCapt" alt='<?php echo $currentPiece; ?>'></img>
		</div>
		
			<?php if($nomessage == false){ ?>
			<script type="text/javascript" language="javascript" charset="utf-8">
				
					

					findScore();

					

					//drawRectangle(parseInt(114,10), parseInt(112,10), 43, 49, 106, scorepos[0], scorepos[1]);
					//addMessage(106,106, <?php echo "'".addslashes("Je créé un nouveau message et j'y attache une photo.")."'"; ?>, '23-03-2009 12:02', 'Vassantha', '3', '12', parseInt(114,10), parseInt(112,10), 43, 49);


//					drawRectangle(parseInt(<?php echo $infosMessage[0][5]; ?>,10), parseInt(<?php echo $infosMessage[0][6]; ?>,10), <?php echo $infosMessage[0][7]-$infosMessage[0][5]; ?>, <?php echo $infosMessage[0][8]-$infosMessage[0][6]; ?>, <?php echo $infosMessage[0][1]; ?>, scorepos[0], scorepos[1]);
//
//					addFil(<?php echo $infosMessage[0][1]; ?>,<?php echo "'".addslashes(substr($infosMessage[0][2],0,30))."...'"; ?>, '<?php echo $infosMessage[0][3]; ?>', '<?php echo $infosMessage[0][4]; ?>', <?php echo $infosMessage[0][12]; ?>,<?php echo "'".htmlentities($infosMessage[0][13])."'"; ?>,<?php echo $infosMessage[0][9]; ?>,<?php echo $infosMessage[0][10]; ?>, parseInt(<?php echo $infosMessage[0][5]; ?>,10), parseInt(<?php echo $infosMessage[0][6]; ?>,10), <?php echo $infosMessage[0][7]-$infosMessage[0][5]; ?>, <?php echo $infosMessage[0][8]-$infosMessage[0][6]; ?>, '<?php echo $partition_tab[$page]; ?>', '<?php echo $piece_id; ?>', '<?php echo $page; ?>');
//
//					//creatediv(2);
//
//					addMessage(<?php echo $infosMessage[0][12]; ?>,<?php echo $infosMessage[0][1]; ?>, <?php echo "'".addslashes($infosMessage[0][2])."'"; ?>, '<?php echo $infosMessage[0][3]; ?>', '<?php echo $infosMessage[0][4]; ?>');
					
				</script>

			

			<?php
				$numparent = $infosMessage[0][12]; //numero du premier fil = numero de la premiere messagebox
				$numparentfilbox = $infosMessage[0][1]; //numero du premier message = numero de la premiere filbox
				//affichage des cadres des messages sur la partition
				for($i=0;$i<sizeof($infosMessage);$i++) {
					
						//si le message a la meme position que le precedent mais n'appartient pas au meme fil de discussion, il faut creer ce fil et l'y ajouter
						if ($infosMessage[$i][0] == $infosMessage[$i-1][0] && $infosMessage[$i][12] != $infosMessage[$i-1][12]){
							$numparent = $infosMessage[$i][12];
			?>
							<script type="text/javascript" language="javascript" charset="utf-8">
							
								addFil(<?php echo $numparentfilbox; ?>,<?php echo "'".addslashes(substr($infosMessage[$i][2],0,30))."...'"; ?>, '<?php echo $infosMessage[$i][3]; ?>', '<?php echo $infosMessage[$i][4]; ?>', <?php echo $infosMessage[$i][12]; ?>,<?php echo "'".htmlentities($infosMessage[$i][13])."'"; ?>,<?php echo $infosMessage[$i][9]; ?>,<?php echo $infosMessage[$i][10]; ?>, parseInt(<?php echo $infosMessage[$i][5]; ?>,10), parseInt(<?php echo $infosMessage[$i][6]; ?>,10), <?php echo $infosMessage[$i][7]-$infosMessage[$i][5]; ?>, <?php echo $infosMessage[$i][8]-$infosMessage[$i][6]; ?>, '<?php echo $partition_tab[$page]; ?>', '<?php echo $piece_id; ?>', '<?php echo $page; ?>');

								addMessage(<?php echo $numparent; ?>,<?php echo $infosMessage[$i][1]; ?>, <?php echo "'".addslashes($infosMessage[$i][2])."'"; ?>, '<?php echo $infosMessage[$i][3]; ?>', '<?php echo $infosMessage[$i][4]; ?>');
								
							</script>
			<?php
						}
						//si le message a la meme position que le precedent -> il faut le placer dans la message box existante, en dessous du message precedent
						else if ($infosMessage[$i][0] == $infosMessage[$i-1][0]){
						

			?>

							<script type="text/javascript" language="javascript" charset="utf-8">
							
								addMessage(<?php echo $numparent; ?>,<?php echo $infosMessage[$i][1]; ?>, <?php echo "'".addslashes($infosMessage[$i][2])."'"; ?>, '<?php echo $infosMessage[$i][3]; ?>', '<?php echo $infosMessage[$i][4]; ?>');
								
							</script>

			<?php
						//fin if ($infosMessage[$i][0] == $infosMessage[$i-1][0])
						}
						else {
							$numparent = $infosMessage[$i][12];
							$numparentfilbox = $infosMessage[$i][1];
							
							//dessin du rectangle rouge de position et ajout du premier message
			?>
							<script type="text/javascript" language="javascript" charset="utf-8">
										drawRectangle(parseInt(<?php echo $infosMessage[$i][5]; ?>,10), parseInt(<?php echo $infosMessage[$i][6]; ?>,10), <?php echo $infosMessage[$i][7]-$infosMessage[$i][5]; ?>, <?php echo $infosMessage[$i][8]-$infosMessage[$i][6]; ?>, <?php echo $infosMessage[$i][1]; ?>, scorepos[0], scorepos[1]);

						addFil(<?php echo $infosMessage[$i][1]; ?>,<?php echo "'".addslashes(substr($infosMessage[$i][2],0,30))."...'"; ?>, '<?php echo $infosMessage[$i][3]; ?>', '<?php echo $infosMessage[$i][4]; ?>', <?php echo $infosMessage[$i][12]; ?>,<?php echo "'".htmlentities($infosMessage[$i][13])."'"; ?>,<?php echo $infosMessage[$i][9]; ?>,<?php echo $infosMessage[$i][10]; ?>, parseInt(<?php echo $infosMessage[$i][5]; ?>,10), parseInt(<?php echo $infosMessage[$i][6]; ?>,10), <?php echo $infosMessage[$i][7]-$infosMessage[$i][5]; ?>, <?php echo $infosMessage[$i][8]-$infosMessage[$i][6]; ?>, '<?php echo $partition_tab[$page]; ?>', '<?php echo $piece_id; ?>', '<?php echo $page; ?>');

						//creatediv(2);

						addMessage(<?php echo $numparent; ?>,<?php echo $infosMessage[$i][1]; ?>, <?php echo "'".addslashes($infosMessage[$i][2])."'"; ?>, '<?php echo $infosMessage[$i][3]; ?>', '<?php echo $infosMessage[$i][4]; ?>');
							</script>
			<?php
						} //fin else
						
						//affichage des attachments
						if ($infosMessage[$i][11] == 1){
							
							//recuperation des infos de l'attachment
							$query3= "SELECT `attach_id`,`physical_filename`,`real_filename`,`extension`,`filesize`,`filetime`,`thumbnail`
										FROM `phpbb_attachments`
										WHERE post_msg_id = ".$infosMessage[$i][1]
										;
							$result3=mysql_query($query3);
							if(!$result3){
								echo "erreur de requete sur la table phpbb_attachments";
								exit;
							}
							
							//preparation des variables et affichage des attachments du post
							while ($row3 = mysql_fetch_array($result3, MYSQL_ASSOC)) {
								$attach_id = $row3['attach_id'];
								$name = $row3['real_filename'];
								$filedir = "./forum/phpBB3/".$config['upload_path']."/"; 
								$physical_filename = $row3['physical_filename'];
								$path = $filedir.$physical_filename;
								
								//$path2 = "./forum/phpBB3/download/file.php?id=".$row3['attach_id']."&mode=view";
								//chmod($path, 0777);
								$extension = $row3['extension'];
								if ($extension == "jpg" || $extension == "gif" || $extension == "png")
									$type = "image";
								else if ($extension == "flv"){
									$type = "video";
									$path=$physical_filename;
								}
								else
									$type = "unknown";

								//si c'est une video, on va creer sois meme le thumbnail avec ffmpeg
								$preview_file = substr($physical_filename, 0, strrpos($physical_filename, ".")).".jpg";
								$preview_path = $filedir."thumb_".$preview_file;
								if ($row3['thumbnail'] == 1)
									//$thumbpath = "./forum/phpBB3/download/file.php?id=".$row3['attach_id']."&amp;t=1";
									$thumbpath = $preview_path;
								else if ($row3['thumbnail'] == 0 && $type == "video"){
									
//									if (!file_exists($preview_path)){
//										extension_loaded('ffmpeg') or die('Error in loading ffmpeg');
//										
//										$ffmpegInstance = new ffmpeg_movie(dirname(__FILE__) . $path);
//
//										//get a frame:
//										$width = $ffmpegInstance->getFrameWidth();
//										$height = $ffmpegInstance->getFrameHeight();
//										$myframe = $ffmpegInstance->getFrame(100);
//										$mygdimage = $myframe->toGDImage();
//										$myjpgimage = imagecreatetruecolor(112,90);
//										imagecopyresampled  ($myjpgimage,$mygdimage,0,0,0,0,112,90,$width,$height);
//										imagejpeg($myjpgimage,$preview_path, 70);
//									}
//									//mise a jour de la base de donnees:
//									$query5= "UPDATE `phpbb_attachments` SET thumbnail=1 WHERE attach_id=".$attach_id.";";
//									$result5=mysql_query($query5);
//									if(!$result5){
//										echo "impossible de mettre a jour la table phpbb_attachments";
//										exit;
//									}
									$thumbpath = $preview_path;

								}
								else $thumbpath = "";
								
								//chmod($thumbpath, 0777);
						
			?>
							<script type="text/javascript" language="javascript" charset="utf-8">
								addAttachment2 (<?php echo $infosMessage[$i][1]; ?>, '<?php echo $name; ?>', '<?php echo $thumbpath; ?>', '<?php echo $path; ?>', '<?php echo $type; ?>');
							</script>
		<?php

							}//fin while $row3
						}//fin if attachments
				//fin for($i=0;$i<sizeof($infosMessage);$i++)
				}
			}//fin if ($nomessage == false)
		?>

			
		
		
</div> <!-- fin div piece -->
		<?php
			} //fin if ($partition_ref == 1)
			else {
				echo "<p>pas de forum musical pour cette pièce pour le moment</p>";
			}
		?>
		
		<!--<form name='inputForm2' action='./forum/phpBB3/posting.php?mode=reply&f=3&t=12' method='post'>
			<input type='hidden' name='clickX0' value='5'>
			<input type='hidden' name='clickY0' value='5'>
			<input type='hidden' name='clickX1' value='15'>
			<input type='hidden' name='clickY1' value='15'>
			<a href='javascript:document.inputForm2.submit();'>essai</a>
		</form>-->

	<!--<object classid="CLSID:07000E2B-6AAD-497D-8E5B-5976560AD429"
				border="0"
				height="550"
				width="780">
		 <param name="src" value="TeaForTwo.mxl" />
		 <param name="width" value="780" />
		 <param name="height" value="550" />
		 <param name="type" value="application/x-myriad-music" />
		 <param name="pluginspage" value="http://www.myriad-online.com/cgi-bin/mmplug.pl" />
		 <embed src="TeaForTwo.mxl"
					  width="780"
					  height="550"
					  type="application/x-myriad-music"
					  pluginspage="http://www.myriad-online.com/cgi-bin/mmplug.pl" FULLWIDTH=ON>
		 </embed>
	</object>-->

</div>

