<?php

ini_set("display_errors", 1);
ini_set('error_reporting', E_ALL);

/* Global options */
$config['global']['name']				= "Xtions"; 		// Projectname
$config['global']['lrf']				= "../"; 			// Local root folder
$config['global']['models']				= "\Model"; 		// Model folder (with backslash)
$config['global']['schema_filename']	= "schema.xml"; 	// Schema filename

/* Search and replace options */
$config['sar']['search']				= 'defaultPhpNamingMethod="underscore"';

/* MySQL options */
$config['mysql']['host']				= "127.0.0.1";		// Database host
$config['mysql']['dbname']				= "xtions";			// Database name
$config['mysql']['dbuser']				= "root";			// Database user
$config['mysql']['dbpwd']				= "root";			// Database password

if ( $_SERVER['REQUEST_METHOD'] == "POST") {

	$exec_time = microtime(true);

	// Reverse the database
	$reverse_db = shell_exec("cd " . $config['global']['lrf'] . "; rm " . $config['global']['schema_filename'] . "; vendor/propel/propel/bin/propel reverse 'mysql:host=" . $config['mysql']['host'] . ";dbname=" . $config['mysql']['dbname'] . ";user=" . $config['mysql']['dbuser'] . ";password=" . $config['mysql']['dbpwd'] . "' --database-name='" . $config['mysql']['dbname'] . "' --output-dir='./'");
	
	if ( $reverse_db == true ) {
		echo "1. Reverse the database - SUCCESS<br />";
	} else {
		echo "1. Reverse the database - FAIL<br />";
	}	

	// Open schema and add namespace
	$open_schema = file_get_contents($config['global']['lrf'] . $config['global']['schema_filename']);
	$open_schema = str_replace($config['sar']['search'], $config['sar']['search'] . ' namespace="' . $config['global']['name'] . $config['global']['models'] .'"', $open_schema);
	$write_schema = file_put_contents($config['global']['lrf'] . $config['global']['schema_filename'], $open_schema);
	
	if ( $write_schema == true ) {
		echo "2. Schema rebuild - SUCCESS<br />";
	} else {
		echo "2. Schema rebuild - FAIL<br />";
	}	

	// Build the models
	$build_models = shell_exec("cd " . $config['global']['lrf'] . "; rm -r src/" . $config['global']['name'] . "/Model/*; vendor/propel/propel/bin/propel build --output-dir='src';");

	if ( $build_models == true ) {
		echo "3. Models rebuild - SUCCES<br />";
	} else {
		echo "3. Models rebuild - FAIL<br />";
	}

	echo "<p><strong>4. Done - time: " . number_format(microtime(true) - $exec_time, 2) . "</strong></p>";

} else {
	
	echo '<p>Reverse database schema and build new models</p>';
	echo '<form method="post">';
	echo '<button type="submit">Lets do it.</button>';
	echo '</form>';

}

?>