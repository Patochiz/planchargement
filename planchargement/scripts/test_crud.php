#!/usr/bin/env php
<?php
/* Copyright (C) 2024 Patochiz
 *
 * CLI test script for planchargement CRUD classes.
 * Run from Dolibarr root: php htdocs/custom/planchargement/scripts/test_crud.php
 * Or from module dir:     php scripts/test_crud.php
 */

// Detect Dolibarr main.inc.php
$res = 0;
$path_to_main = '';
$trydirs = array(
	__DIR__.'/../../../..',         // htdocs/custom/planchargement/scripts -> htdocs
	__DIR__.'/../../..',            // alternate location
	__DIR__.'/../../../htdocs',     // repo root
);
foreach ($trydirs as $dir) {
	$try = realpath($dir.'/main.inc.php');
	if ($try && file_exists($try)) {
		$path_to_main = $try;
		break;
	}
	$try = realpath($dir.'/master.inc.php');
	if ($try && file_exists($try)) {
		$path_to_main = $try;
		break;
	}
}

if (empty($path_to_main)) {
	echo "ERROR: Could not find Dolibarr main.inc.php\n";
	echo "Please run this script from within a Dolibarr installation.\n";
	echo "Expected location: htdocs/custom/planchargement/scripts/test_crud.php\n";
	echo "\nAlternatively, set the DOL_DOCUMENT_ROOT environment variable.\n";

	// If DOL_DOCUMENT_ROOT env is set, try that
	$env_root = getenv('DOL_DOCUMENT_ROOT');
	if ($env_root && file_exists($env_root.'/main.inc.php')) {
		$path_to_main = $env_root.'/main.inc.php';
	} else {
		exit(1);
	}
}

// Define constants to run in CLI mode
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', '1');
}
if (!defined('NOSESSION')) {
	define('NOSESSION', '1');
}

require_once $path_to_main;
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

dol_include_once('/planchargement/class/camiontype.class.php');
dol_include_once('/planchargement/class/umtype.class.php');
dol_include_once('/planchargement/class/chargement.class.php');
dol_include_once('/planchargement/class/chargementum.class.php');

// ============================================================
// Test helpers
// ============================================================

$test_count = 0;
$test_pass = 0;
$test_fail = 0;

/**
 * Simple test assertion
 *
 * @param string $label Test description
 * @param bool   $condition Condition to check
 * @param string $detail Optional detail on failure
 */
function test($label, $condition, $detail = '')
{
	global $test_count, $test_pass, $test_fail;
	$test_count++;
	if ($condition) {
		$test_pass++;
		echo "  [OK]   $label\n";
	} else {
		$test_fail++;
		echo "  [FAIL] $label".($detail ? " - $detail" : "")."\n";
	}
}

// Load admin user for testing
$user = new User($db);
$user->fetch(1);
if (empty($user->id)) {
	echo "WARNING: Could not fetch user id=1, creating minimal user object\n";
	$user->id = 1;
	$user->login = 'admin';
}

echo "=============================================================\n";
echo " planchargement CRUD Test Suite\n";
echo " Dolibarr: ".DOL_VERSION." | PHP: ".PHP_VERSION."\n";
echo "=============================================================\n\n";

// ============================================================
// 1. Test CamionType CRUD
// ============================================================

echo "--- CamionType CRUD ---\n";

$camion = new CamionType($db);
$camion->label = 'Semi 13.6m';
$camion->longueur_utile = 13600;
$camion->largeur_utile = 2480;
$camion->hauteur_utile = 2700;
$camion->charge_utile = 26000;
$camion->active = 1;

$result = $camion->create($user);
test('CamionType::create()', $result > 0, 'result='.$result.' error='.$camion->error);
$camion_id = $camion->id;

$camion2 = new CamionType($db);
$result = $camion2->fetch($camion_id);
test('CamionType::fetch()', $result > 0 && $camion2->label == 'Semi 13.6m', 'label='.$camion2->label);
test('CamionType dimensions', $camion2->longueur_utile == 13600 && $camion2->largeur_utile == 2480, 'L='.$camion2->longueur_utile.' l='.$camion2->largeur_utile);
test('CamionType charge_utile', $camion2->charge_utile == 26000, 'charge='.$camion2->charge_utile);

$camion2->label = 'Semi remorque 13.6m';
$result = $camion2->update($user);
test('CamionType::update()', $result > 0, 'result='.$result);

$camion3 = new CamionType($db);
$camion3->fetch($camion_id);
test('CamionType update verified', $camion3->label == 'Semi remorque 13.6m', 'label='.$camion3->label);

// Create a second type for later
$porteur = new CamionType($db);
$porteur->label = 'Porteur 7m';
$porteur->longueur_utile = 7000;
$porteur->largeur_utile = 2400;
$porteur->hauteur_utile = 2500;
$porteur->charge_utile = 12000;
$porteur->active = 1;
$porteur->create($user);
test('CamionType second create', $porteur->id > 0, 'id='.$porteur->id);

// Test fetchAll
$all = $camion->fetchAll('', 'label');
test('CamionType::fetchAll()', is_array($all) && count($all) >= 2, 'count='.count($all));

echo "\n";

// ============================================================
// 2. Test UmType CRUD
// ============================================================

echo "--- UmType CRUD ---\n";

$umt_palette = new UmType($db);
$umt_palette->label = 'Palette EUR';
$umt_palette->longueur = 1200;
$umt_palette->largeur = 800;
$umt_palette->hauteur = 1500;
$umt_palette->charge_max = 1500;
$umt_palette->gerbable = 1;
$umt_palette->active = 1;
$result = $umt_palette->create($user);
test('UmType::create() Palette EUR', $result > 0, 'result='.$result.' error='.$umt_palette->error);

$umt_chevalet = new UmType($db);
$umt_chevalet->label = 'Chevalet 4m';
$umt_chevalet->longueur = 4000;
$umt_chevalet->largeur = 800;
$umt_chevalet->hauteur = 1200;
$umt_chevalet->charge_max = 2000;
$umt_chevalet->gerbable = 0;
$umt_chevalet->active = 1;
$result = $umt_chevalet->create($user);
test('UmType::create() Chevalet 4m', $result > 0, 'result='.$result);

$umt_check = new UmType($db);
$result = $umt_check->fetch($umt_palette->id);
test('UmType::fetch()', $result > 0 && $umt_check->label == 'Palette EUR', 'label='.$umt_check->label);
test('UmType dimensions', $umt_check->longueur == 1200 && $umt_check->largeur == 800, 'L='.$umt_check->longueur.' l='.$umt_check->largeur);
test('UmType gerbable', $umt_check->gerbable == 1, 'gerbable='.$umt_check->gerbable);

echo "\n";

// ============================================================
// 3. Test Chargement CRUD
// ============================================================

echo "--- Chargement CRUD ---\n";

$chg = new Chargement($db);
$chg->fk_camion_type = $camion_id;
$chg->date_chargement = dol_now();

$result = $chg->create($user);
test('Chargement::create()', $result > 0, 'result='.$result.' error='.$chg->error);
test('Chargement ref is (PROV)', strpos($chg->ref, '(PROV') !== false, 'ref='.$chg->ref);
test('Chargement statut is DRAFT', $chg->statut == Chargement::STATUS_DRAFT, 'statut='.$chg->statut);
$chg_id = $chg->id;

$chg2 = new Chargement($db);
$result = $chg2->fetch($chg_id);
test('Chargement::fetch()', $result > 0 && $chg2->fk_camion_type == $camion_id, 'fk_camion_type='.$chg2->fk_camion_type);

// Test addCommande (with a fake commande ID since we don't have colisage)
// We'll use ID 99999 as a test
$result = $chg2->addCommande(99999);
test('Chargement::addCommande()', $result > 0, 'result='.$result.' error='.$chg2->error);

$result = $chg2->addCommande(99998);
test('Chargement::addCommande() second', $result > 0, 'result='.$result);

// Test duplicate detection
$result = $chg2->addCommande(99999);
test('Chargement addCommande duplicate blocked', $result < 0, 'error='.$chg2->error);

// Test fetchCommandes
$chg2->fetchCommandes();
test('Chargement::fetchCommandes()', count($chg2->commandes) == 2, 'count='.count($chg2->commandes));
test('Chargement commandes content', in_array(99999, $chg2->commandes) && in_array(99998, $chg2->commandes));

// Test addUm
$um_id = $chg2->addUm($umt_palette->id);
test('Chargement::addUm() palette', $um_id > 0, 'um_id='.$um_id.' error='.$chg2->error);

$um_id2 = $chg2->addUm($umt_chevalet->id);
test('Chargement::addUm() chevalet', $um_id2 > 0, 'um_id='.$um_id2);

// Test fetchUms
$result = $chg2->fetchUms();
test('Chargement::fetchUms()', $result > 0 && count($chg2->lines) == 2, 'count='.count($chg2->lines));

if (count($chg2->lines) >= 2) {
	test('UM 1 ref_um set', !empty($chg2->lines[0]->ref_um), 'ref_um='.$chg2->lines[0]->ref_um);
	test('UM 1 type correct', $chg2->lines[0]->fk_um_type == $umt_palette->id);
	test('UM 2 type correct', $chg2->lines[1]->fk_um_type == $umt_chevalet->id);
}

// Test calculateTotals (should be 0 since no colis assigned)
$result = $chg2->calculateTotals();
test('Chargement::calculateTotals()', $result > 0 && $chg2->poids_total == 0, 'poids='.$chg2->poids_total);

echo "\n";

// ============================================================
// 4. Test ChargementUm CRUD
// ============================================================

echo "--- ChargementUm CRUD ---\n";

$um = new ChargementUm($db);
$result = $um->fetch($um_id);
test('ChargementUm::fetch()', $result > 0, 'result='.$result);
test('ChargementUm fk_chargement', $um->fk_chargement == $chg_id, 'fk_chargement='.$um->fk_chargement);
test('ChargementUm fk_um_type', $um->fk_um_type == $umt_palette->id, 'fk_um_type='.$um->fk_um_type);
test('ChargementUm pos_x null', $um->pos_x === null || $um->pos_x === '', 'pos_x='.$um->pos_x);

// Test position update
$um->pos_x = 0;
$um->pos_y = 0;
$result = $um->update($user);
test('ChargementUm::update() position', $result > 0, 'result='.$result);

$um_check = new ChargementUm($db);
$um_check->fetch($um_id);
test('ChargementUm position saved', $um_check->pos_x == 0 && $um_check->pos_y == 0, 'pos_x='.$um_check->pos_x);

// Test fetchByChargement
$ums = $um->fetchByChargement($chg_id);
test('ChargementUm::fetchByChargement()', is_array($ums) && count($ums) == 2, 'count='.count($ums));

// Note: assignColis/removeColis/calculatePoids depend on colisage module
// We test them structurally but expect them to fail gracefully without colisage data
echo "\n  (assignColis/removeColis tests require colisage module data - skipping)\n";

echo "\n";

// ============================================================
// 5. Test Chargement validation
// ============================================================

echo "--- Chargement Status Workflow ---\n";

// Test validation
$result = $chg2->valid($user);
test('Chargement::valid()', $result > 0, 'result='.$result.' error='.$chg2->error);
test('Chargement ref after valid', strpos($chg2->ref, '(PROV') === false && !empty($chg2->ref), 'ref='.$chg2->ref);
test('Chargement statut after valid', $chg2->statut == Chargement::STATUS_VALID, 'statut='.$chg2->statut);

// Double validation should fail
$result = $chg2->valid($user);
test('Chargement double valid blocked', $result < 0, 'error='.$chg2->error);

// Test setStatut to DEPARTED
$result = $chg2->setStatut(Chargement::STATUS_DEPARTED, $user);
test('Chargement setStatut DEPARTED', $result > 0, 'result='.$result);
test('Chargement statut = DEPARTED', $chg2->statut == Chargement::STATUS_DEPARTED, 'statut='.$chg2->statut);

// Delete should fail on non-DRAFT
$result = $chg2->delete($user);
test('Chargement delete on non-DRAFT blocked', $result < 0, 'error='.$chg2->error);

echo "\n";

// ============================================================
// 6. Test getNomUrl / getLibStatut
// ============================================================

echo "--- Display Methods ---\n";

$nomurl = $chg2->getNomUrl(1);
test('Chargement::getNomUrl()', !empty($nomurl) && strpos($nomurl, $chg2->ref) !== false, 'nomurl='.strip_tags($nomurl));

$libstatut = $chg2->getLibStatut(0);
test('Chargement::getLibStatut()', !empty($libstatut), 'libstatut='.strip_tags($libstatut));

$camion3_url = $camion3->getNomUrl(0);
test('CamionType::getNomUrl()', !empty($camion3_url), 'url='.strip_tags($camion3_url));

echo "\n";

// ============================================================
// 7. Cleanup
// ============================================================

$do_cleanup = in_array('--cleanup', $argv ?? array());

if ($do_cleanup) {
	echo "--- Cleanup ---\n";

	// Reset chargement to DRAFT for deletion
	$sql = "UPDATE ".MAIN_DB_PREFIX."planchargement_chargement SET statut = 0 WHERE rowid = ".((int) $chg_id);
	$db->query($sql);
	$chg2->statut = Chargement::STATUS_DRAFT;

	$result = $chg2->delete($user);
	test('Chargement::delete() cascade', $result > 0, 'result='.$result.' error='.$chg2->error);

	// Verify cascade: UMs should be gone
	$sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."planchargement_um WHERE fk_chargement = ".((int) $chg_id);
	$resql = $db->query($sql);
	$obj = $db->fetch_object($resql);
	test('Cascade: UMs deleted', $obj->nb == 0, 'remaining='.$obj->nb);

	// Verify cascade: commande links should be gone
	$sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."planchargement_commande WHERE fk_chargement = ".((int) $chg_id);
	$resql = $db->query($sql);
	$obj = $db->fetch_object($resql);
	test('Cascade: commande links deleted', $obj->nb == 0, 'remaining='.$obj->nb);

	// Delete types
	// First delete the porteur (not referenced)
	$result = $porteur->delete($user);
	test('CamionType Porteur delete', $result > 0, 'result='.$result);

	// CamionType referenced by the (now deleted) chargement should be deletable
	$result = $camion3->delete($user);
	test('CamionType Semi delete', $result > 0, 'result='.$result);

	$result = $umt_palette->delete($user);
	test('UmType Palette delete', $result > 0, 'result='.$result);

	$result = $umt_chevalet->delete($user);
	test('UmType Chevalet delete', $result > 0, 'result='.$result);

	echo "\n";
}

// ============================================================
// Summary
// ============================================================

echo "=============================================================\n";
echo " Results: $test_pass/$test_count passed";
if ($test_fail > 0) {
	echo " ($test_fail FAILED)";
}
echo "\n";
echo "=============================================================\n";

if (!$do_cleanup) {
	echo "\nTip: Run with --cleanup to delete test data after tests.\n";
}

exit($test_fail > 0 ? 1 : 0);
