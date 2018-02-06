<?php


 define("DB_HOST", 	"localhost");				// hostname
 define("DB_USER", 	"username");		// database username
 define("DB_PASSWORD", 	"password");		// database password
 define("DB_NAME", 	"dbname");	// database name
 
 $cryptobox_private_keys = array();




 define("CRYPTOBOX_PRIVATE_KEYS", implode("^", $cryptobox_private_keys));
 unset($cryptobox_private_keys); 

?>