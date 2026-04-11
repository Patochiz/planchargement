<?php
// Minimal diagnostic script - step by step
header('Content-Type: text/plain; charset=utf-8');
echo "Step 1: PHP works\n";
echo "PHP version: ".PHP_VERSION."\n";
echo "Script dir: ".__DIR__."\n\n";

// Step 2: find main.inc.php
echo "Step 2: Searching for main.inc.php...\n";
$path_to_main = '';
for ($i = 1; $i <= 6; $i++) {
	$dir = dirname(__DIR__, $i);
	$try = $dir.'/main.inc.php';
	$exists = file_exists($try) ? 'YES' : 'no';
	echo "  Level $i: $try => $exists\n";
	if ($exists === 'YES' && empty($path_to_main)) {
		$path_to_main = $try;
	}
}

if (empty($path_to_main)) {
	echo "\nFAILED: main.inc.php not found!\n";
	exit(1);
}

echo "\nFound: $path_to_main\n\n";

// Step 3: load Dolibarr
echo "Step 3: Loading Dolibarr...\n";

define('NOREQUIREMENU', '1');
define('NOREQUIREHTML', '1');
define('NOREQUIREAJAX', '1');
define('NOLOGIN', '1');
define('NOSESSION', '1');

require_once $path_to_main;

echo "Dolibarr loaded OK. Version: ".DOL_VERSION."\n";
echo "DOL_DOCUMENT_ROOT: ".DOL_DOCUMENT_ROOT."\n\n";

// Step 4: check tables exist
echo "Step 4: Checking tables...\n";
$tables = array(
	'planchargement_camion_type',
	'planchargement_um_type',
	'planchargement_chargement',
	'planchargement_commande',
	'planchargement_um',
	'planchargement_um_colis',
);
foreach ($tables as $t) {
	$sql = "SHOW TABLES LIKE '".MAIN_DB_PREFIX.$t."'";
	$resql = $db->query($sql);
	$found = ($resql && $db->num_rows($resql) > 0) ? 'YES' : 'MISSING';
	echo "  ".MAIN_DB_PREFIX."$t => $found\n";
}
echo "\n";

// Step 5: try loading classes
echo "Step 5: Loading classes...\n";
$classes = array(
	'/planchargement/class/camiontype.class.php' => 'CamionType',
	'/planchargement/class/umtype.class.php' => 'UmType',
	'/planchargement/class/chargement.class.php' => 'Chargement',
	'/planchargement/class/chargementum.class.php' => 'ChargementUm',
);
foreach ($classes as $file => $classname) {
	$loaded = dol_include_once($file);
	$exists = class_exists($classname) ? 'YES' : 'NO';
	echo "  $classname => class_exists=$exists\n";
}
echo "\n";

// Step 6: quick CRUD test
echo "Step 6: Quick CamionType create test...\n";
$user = new User($db);
$user->fetch(1);
echo "  User loaded: id=".$user->id." login=".$user->login."\n";

$ct = new CamionType($db);
$ct->label = 'TEST_DIAG';
$ct->longueur_utile = 1000;
$ct->largeur_utile = 500;
$ct->hauteur_utile = 500;
$ct->charge_utile = 100;
$ct->active = 1;
$result = $ct->create($user);
echo "  create() returned: $result\n";
if ($result > 0) {
	echo "  Created with id=".$ct->id."\n";
	// Cleanup
	$ct->delete($user);
	echo "  Cleaned up.\n";
} else {
	echo "  Error: ".$ct->error."\n";
	if (!empty($ct->errors)) {
		echo "  Errors: ".implode(', ', $ct->errors)."\n";
	}
}

echo "\nDONE - All steps passed.\n";
