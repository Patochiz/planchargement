# Module `planchargement` — Guide de développement pour Claude Code

> **Rôle de ce document** : briefing complet pour qu'une instance de Claude Code
> puisse reprendre le développement du module là où l'architecture s'est arrêtée.
> À lire en entier avant toute action.

---

## 1. Contexte projet

**Client** : DIAMANT INDUSTRIE (plafonds métalliques suspendus, sous-traitance métallique)
**Stack** :
- Dolibarr **20.0.0**
- PHP **8.2**
- Hébergement **OVH mutualisé** (pas de SSH, déploiement par FTP)
- MySQL/MariaDB (version OVH shared)
- cURL disponible, Composer-as-a-service non disponible

**Dépôt GitHub** : à créer sous `Patochiz/planchargement` (convention des autres modules custom du client).

**Intégration** : le module s'adosse au module custom `colisage` déjà déployé et
en production. `colisage` ne doit **pas** être modifié par ce projet.

---

## 2. Règles de dev OBLIGATOIRES (règles maison Dolibarr v20 / PHP 8.2+)

Ces règles sont **non négociables**. Toute PR qui ne les respecte pas est à
refuser. Elles sont issues de bugs concrets rencontrés sur les modules custom
existants.

### Règle 1 — Inclusions dans les modules
Toujours utiliser `dol_include_once('/planchargement/...')`.
**Jamais** `require_once DOL_DOCUMENT_ROOT.'/custom/planchargement/...'`.

```php
// OK
dol_include_once('/planchargement/class/chargement.class.php');

// KO
require_once DOL_DOCUMENT_ROOT.'/custom/planchargement/class/chargement.class.php';
```

### Règle 2 — URLs des assets CSS/JS
Utiliser `dol_buildpath('/planchargement/css/style.css', 1)`.
Le deuxième paramètre `1` génère une URL, `0` génère un chemin filesystem.

### Règle 3 — Chemins filesystem
Utiliser `dol_buildpath('/planchargement/tpl/', 0)`.

### Règle 4 — Vérifications de permissions
Toujours `$user->hasRight('planchargement', 'perm')`.
**Jamais** `$user->rights->planchargement->perm` (deprecated depuis v16,
cassé dans certains contextes v20).

```php
// OK
if (!$user->hasRight('planchargement', 'read')) accessforbidden();

// KO
if (empty($user->rights->planchargement->read)) accessforbidden();
```

### Règle 5 — Classes de hook : pas de propriétés typées
Dolibarr instancie les hook classes dynamiquement. Les propriétés typées
PHP 7.4+ provoquent des erreurs `Typed property must not be accessed before
initialization`. Style classique uniquement :

```php
// OK
class ActionsPlanchargement
{
    public $db;
    public $error = '';
    public $errors = array();
    public $results = array();
    public $resprints = '';
    // ...
}

// KO
class ActionsPlanchargement
{
    public DoliDB $db;
    public string $error = '';
    public array $errors = [];
    public string $resprints = '';
}
```

### Règle 6 — Endpoints AJAX POST
Pour toute page qui reçoit du POST en AJAX (typiquement les `ajax/*.php`),
définir **les 5 constantes** AVANT l'inclusion de `main.inc.php`. Sans
`NOCSRFCHECK`, Dolibarr rejette le POST et renvoie du HTML au lieu de JSON,
ce qui provoque l'erreur JS « Unexpected token < in JSON ».

```php
<?php
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU',  '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML',  '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX',  '1');
if (!defined('NOCSRFCHECK'))    define('NOCSRFCHECK',    '1');

require_once '../../main.inc.php';
// ... suite du endpoint
header('Content-Type: application/json');
```

### Règle 7 — Déclaration des hooks dans `modPlanchargement.class.php`
**FORMAT PLAT UNIQUEMENT**. Le format imbriqué est mal interprété par Dolibarr 20
et les hooks ne sont alors pas enregistrés en base.

```php
// OK (format plat)
$this->module_parts = array(
    'hooks' => array('ordercard', 'main')
);

// KO (format imbriqué — cassé en v20)
$this->module_parts = array(
    'hooks' => array(
        'data' => array('ordercard', 'main'),
        'entity' => '0'
    )
);
```

### Règles complémentaires PHP 8.2+

- **Plus de `$conf->global->XXX`** (déprécié) → utiliser `getDolGlobalString('XXX')`,
  `getDolGlobalInt('XXX')`, `getDolGlobalBool('XXX')` selon le type attendu.
- **Échapper systématiquement** tout input utilisateur : `GETPOST('x', 'alpha')`,
  `GETPOSTINT('id')`, `GETPOSTFLOAT('qty')`. ⚠️ pour les décimales saisies par
  l'utilisateur (poids, quantités), **utiliser `GETPOSTFLOAT`** — bug connu de
  Dolibarr 20 où `GETPOSTINT` tronque silencieusement les décimales.
- **Opérateur de comparaison SQL** : utiliser `<>` et non `!=`. Note : le parser
  de rapports Dolibarr strippe les `<>` en croyant que c'est du HTML, donc si tu
  écris un rapport SQL exposé côté interface, utiliser `NOT (a = b)`.

---

## 3. Architecture fonctionnelle

### 3.1. Vocabulaire métier

| Terme | Définition |
|---|---|
| **Colis** | Entité existante du module `colisage`. Physique, a un poids, contient des items. Peut avoir un `multiplier` (N colis physiques identiques). |
| **UM** (Unité de Manutention) | Support de format fixe (palette EUR, palette 100×120, chevalet 4m, chevalet 6m…) sur lequel on regroupe des colis. C'est l'objet qui est placé dans le camion. |
| **Type d'UM** | Format catalogué. L'utilisateur en définit 4-5 une fois pour toutes. |
| **Type de camion** | Gabarit catalogué (longueur, largeur, charge utile). |
| **Chargement** | Plan de chargement d'un camion : rattache N commandes, contient N UM placées. |
| **Gerbage** | Fait de poser une UM sur une autre. Une UM gerbée hérite de la position de sa porteuse, pas de coordonnées propres. |
| **Tablier** | Avant du plancher camion (côté cabine). `pos_x = 0`. |
| **Porte** | Arrière du plancher (côté chargement). `pos_x = longueur_utile`. |

### 3.2. Workflow utilisateur cible

```
1. L'utilisateur crée un chargement
   → choisit un type de camion
   → lui rattache 1 à N commandes client

2. Le système liste tous les colis des commandes rattachées
   (via llx_colisage_packages.fk_commande)
   → l'utilisateur voit les colis "non affectés à une UM"

3. L'utilisateur crée des UM
   → choisit un type (palette, chevalet...)
   → y affecte des colis (drag & drop ou sélection multiple)
   → le poids de l'UM se calcule auto
   → alerte si dépassement de charge max du type d'UM

4. L'utilisateur place les UM dans le camion
   → soit en auto (bouton "Placement automatique" = algo Bottom-Left-Fill 2D)
   → soit en manuel (drag & drop sur un plan SVG)
   → peut gerber une UM sur une autre (drop d'une UM sur une UM existante)

5. L'ordre de déchargement est DÉDUIT de pos_x
   → LIFO : la plus grande pos_x = dernière chargée = première déchargée
   → Le code couleur par commande permet de visualiser l'ordre de tournée

6. Validation du chargement → statut = 1
   → Export PDF du plan (à joindre au BL)
   → Éventuellement statut = 2 (parti) quand le camion quitte
```

### 3.3. Contraintes importantes

- **Une commande peut être sur plusieurs chargements** (livraisons partielles).
- **Un chargement peut regrouper plusieurs commandes** (groupage : tournée
  multi-clients).
- **Un colis peut être scindé sur plusieurs UM** grâce à son `multiplier` + le
  champ `quantity` dans `llx_planchargement_um_colis`. Contrainte applicative :
  `SUM(quantity)` pour un `fk_package` donné ≤ `multiplier` du colis.
- **Une UM gerbée a `pos_x = NULL` et `pos_y = NULL`**. Elle hérite de sa
  porteuse via `fk_um_parent`. Ne pas la dessiner au sol dans la vue plan :
  la dessiner en vue latérale ou dans une bulle info.
- **Les colis individuels ne sont jamais placés directement dans le camion** ;
  ils passent toujours par une UM. C'est une simplification volontaire et
  assumée (discussion architecturale en amont).

---

## 4. Modèle de données

### 4.1. Les 6 tables du module

Voir les fichiers `sql/planchargement_schema.sql` et `sql/planchargement_keys.sql`
livrés avec l'architecture. Résumé :

| Table | Rôle |
|---|---|
| `llx_planchargement_camion_type` | Catalogue des types de camions (statique, saisi une fois). |
| `llx_planchargement_um_type` | Catalogue des types d'UM (statique, saisi une fois). |
| `llx_planchargement_chargement` | En-tête d'un plan de chargement. |
| `llx_planchargement_commande` | Liaison n-n chargement ↔ commande. |
| `llx_planchargement_um` | Instances d'UM dans un chargement (avec position). |
| `llx_planchargement_um_colis` | Affectation colis → UM (avec quantité). |

### 4.2. Dépendance externe : module `colisage`

Le module `planchargement` lit **en lecture seule** les tables suivantes du
module `colisage`. Il ne doit jamais les modifier.

```
llx_colisage_packages
  ├── rowid
  ├── fk_commande      → llx_commande.rowid
  ├── multiplier       (nombre de colis physiques identiques)
  ├── is_free          (0=standard, 1=libre)
  ├── livraison_num    (pour livraisons partielles)
  ├── total_weight     (kg)
  └── total_surface    (m²)

llx_colisage_items
  ├── rowid
  ├── fk_package       → llx_colisage_packages.rowid
  ├── fk_commandedet   → llx_commandedet.rowid (NULL si libre)
  ├── quantity
  ├── longueur         (mm)
  ├── largeur          (mm)
  ├── weight_unit      (kg)
  └── surface_unit     (m²)
```

Méthode déjà existante à réutiliser :
```php
$pkg = new ColisagePackage($db);
$pkg->fetchByCommande($fk_commande); // renvoie tous les colis d'une commande
```

### 4.3. Requête utile : colis non affectés d'un chargement

Dès le début on aura besoin de cette requête. À encapsuler dans une méthode
`Chargement::getColisNonAffectes()` :

```sql
-- Colis des commandes rattachées au chargement,
-- MOINS ceux déjà placés dans une UM de ce chargement,
-- en tenant compte du multiplier et des quantities déjà posées.

SELECT p.rowid AS fk_package,
       p.fk_commande,
       p.multiplier,
       p.total_weight,
       p.total_surface,
       COALESCE(SUM(umc.quantity), 0) AS qty_affectee,
       (p.multiplier - COALESCE(SUM(umc.quantity), 0)) AS qty_restante
FROM llx_colisage_packages p
INNER JOIN llx_planchargement_commande pc ON pc.fk_commande = p.fk_commande
LEFT JOIN llx_planchargement_um_colis umc
       ON umc.fk_package = p.rowid
      AND umc.fk_um IN (
          SELECT rowid FROM llx_planchargement_um WHERE fk_chargement = pc.fk_chargement
      )
WHERE pc.fk_chargement = {FK_CHARGEMENT}
GROUP BY p.rowid
HAVING qty_restante > 0;
```

---

## 5. État du développement

### ✅ Fait (livré avec l'architecture)
- `core/modules/modPlanchargement.class.php` — descripteur module
- `sql/planchargement_schema.sql` — 6 CREATE TABLE
- `sql/planchargement_keys.sql` — index et FK
- Validation de l'architecture 2D + gerbage

### 🚧 À faire (par ordre recommandé)

1. **Classes CRUD** (`class/`) — prioritaire, bloque tout le reste
   - `class/camiontype.class.php` — `CamionType extends CommonObject`
   - `class/umtype.class.php` — `UmType extends CommonObject`
   - `class/chargement.class.php` — `Chargement extends CommonObject` (objet principal)
   - `class/chargementum.class.php` — `ChargementUm extends CommonObject`
   - Méthodes standards à inclure : `create()`, `fetch()`, `update()`, `delete()`,
     `fetchAll()`, `getLibStatut()`, `getNomUrl()`.
   - Pour `Chargement`, ajouter : `addCommande($fk_commande)`, `removeCommande()`,
     `addUm($fk_um_type)`, `getColisNonAffectes()`, `calculateTotals()`,
     `valid()`, `setStatut()`.

2. **Langfiles** (`langs/fr_FR/planchargement.lang` et `en_US/`)
   - Toutes les chaînes UI passent par `$langs->trans()`.
   - Clés préfixées par `Planchargement` : `PlanchargementNew`, `PlanchargementList`, etc.

3. **Admin du catalogue** (`admin/`)
   - `admin/setup.php` — page de config générale
   - `admin/camiontype_list.php` + `camiontype_card.php`
   - `admin/umtype_list.php` + `umtype_card.php`
   - Seuls les users avec `hasRight('planchargement', 'admin')` y accèdent.
   - **Livrable critique** : l'utilisateur doit pouvoir saisir 4-5 types de
     camions et 4-5 types d'UM avant de pouvoir utiliser le reste du module.

4. **Pages principales**
   - `list.php` — liste des chargements avec filtres (statut, date, camion)
   - `card.php` — fiche d'un chargement
     * onglet **Général** : en-tête, camion, commandes rattachées
     * onglet **Composition** : UM et colis affectés (= interface phase 1)
     * onglet **Plan** : vue SVG du camion avec placement des UM (phase 2)
     * onglet **Info** : traçabilité standard Dolibarr

5. **Interface de constitution des UM** (`tpl/composition.tpl.php`)
   - Colonne gauche : colis non affectés des commandes rattachées,
     groupés par commande, avec couleur de commande.
   - Colonne droite : UM existantes, dépliables pour voir leur contenu.
   - Bouton "Nouvelle UM" → modal qui demande le type d'UM puis l'ajoute.
   - Drag & drop des colis depuis la colonne gauche vers les UM.
   - Utiliser `interact.js` (lib unique, ~75 Ko, à mettre dans `/js/interact.min.js`).
   - Endpoints AJAX dans `ajax/` — **respecter la règle 6** (5 constantes).

6. **Algo de placement 2D** (`class/placement.class.php`)
   - Algo **Bottom-Left-Fill** :
     1. Récupérer toutes les UM non placées du chargement (`pos_x IS NULL` et
        `fk_um_parent IS NULL`).
     2. Les trier par surface (`longueur × largeur`) décroissante.
     3. Pour chaque UM, tester successivement chaque position (x,y) candidate
        (commencer à 0,0, puis les coins des UM déjà placées) et chaque
        rotation (0° et 90°).
     4. Choisir la première position qui : (a) ne chevauche aucune UM placée,
        (b) rentre dans les dimensions du camion, (c) est le plus en bas-gauche
        possible (d'abord min-Y, puis min-X).
     5. Si aucune position ne fonctionne → l'UM reste non placée et remonte
        dans l'UI en erreur "ne rentre pas".
   - ~200 lignes de PHP pur, zéro dépendance.
   - Tests à prévoir : cas UM unique, cas UM identiques, cas rotation nécessaire,
     cas débordement camion.

7. **Vue plan SVG** (`tpl/plan.tpl.php`)
   - SVG généré côté PHP, `viewBox` calculé depuis les dimensions du camion
     en mm (échelle naturelle : 1 mm SVG = 1 mm réel, le navigateur rescale).
   - Un `<rect>` par UM, `fill` = couleur de la commande dominante.
   - Label au centre : `CO1234` (ref commande raccourcie) + ref UM.
   - Tablier marqué à gauche, porte à droite (conformément au schéma manuel).
   - UM gerbées affichées en vignette à côté de leur porteuse ou dans une
     bulle au survol.
   - Drag & drop via `interact.js` avec snapping sur une grille (par défaut
     10 mm), recalcul de `pos_x`/`pos_y` envoyé en AJAX.

8. **Export PDF** (`core/modules/doc/pdf_plan_standard.modules.php`)
   - Générer un PNG du plan côté serveur (GD ou Imagick — tester ce qui est
     dispo sur OVH), puis l'injecter dans un TCPDF via `Image()`.
   - Header : ref chargement, date, camion.
   - Contenu : plan à l'échelle + tableau récap des UM + liste des commandes
     dans l'ordre de déchargement.

---

## 6. Arborescence cible du module

```
htdocs/custom/planchargement/
├── core/
│   ├── modules/
│   │   ├── modPlanchargement.class.php        ✅ fait
│   │   └── doc/
│   │       └── pdf_plan_standard.modules.php  (étape 8)
│   └── triggers/
│       └── interface_99_modPlanchargement_Planchargementtriggers.class.php
├── class/
│   ├── camiontype.class.php                   (étape 1)
│   ├── umtype.class.php                       (étape 1)
│   ├── chargement.class.php                   (étape 1)
│   ├── chargementum.class.php                 (étape 1)
│   └── placement.class.php                    (étape 6)
├── sql/
│   ├── planchargement_schema.sql              ✅ fait
│   └── planchargement_keys.sql                ✅ fait
├── admin/
│   ├── setup.php                              (étape 3)
│   ├── camiontype_list.php                    (étape 3)
│   ├── camiontype_card.php                    (étape 3)
│   ├── umtype_list.php                        (étape 3)
│   └── umtype_card.php                        (étape 3)
├── ajax/
│   ├── assign_colis.php                       (étape 5)
│   ├── create_um.php                          (étape 5)
│   ├── move_um.php                            (étape 7)
│   └── auto_place.php                         (étape 6)
├── tpl/
│   ├── composition.tpl.php                    (étape 5)
│   └── plan.tpl.php                           (étape 7)
├── js/
│   ├── interact.min.js                        (dépendance externe)
│   └── planchargement.js                      (logique UI maison)
├── css/
│   └── planchargement.css
├── img/
│   └── object_planchargement.png              (icône 16×16)
├── langs/
│   ├── fr_FR/
│   │   └── planchargement.lang                (étape 2)
│   └── en_US/
│       └── planchargement.lang                (étape 2)
├── list.php                                   (étape 4)
├── card.php                                   (étape 4)
└── README.md
```

---

## 7. Conventions de code

### 7.1. Style PHP

- **PSR-12** sauf contraintes Dolibarr (notamment : pas de namespaces, le core
  Dolibarr ne les utilise pas dans les modules).
- Encodage **UTF-8 sans BOM**.
- Fin de ligne **LF** (pas de CRLF).
- Indentation **tab** (convention Dolibarr core).
- Docblocks `/** */` sur toutes les méthodes publiques, en anglais.
- Messages utilisateur (exceptions, dol_syslog) en **anglais** — la traduction
  passe par `$langs->trans()`.

### 7.2. Classes CRUD : modèle à suivre

Hériter de `CommonObject` et déclarer la propriété `$fields` à la sauce
Dolibarr 20 (le core utilise ça pour générer automatiquement les formulaires
et les listes). Exemple minimal :

```php
<?php
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class Chargement extends CommonObject
{
    public $module = 'planchargement';
    public $element = 'planchargement_chargement';
    public $table_element = 'planchargement_chargement';
    public $picto = 'generic';

    public $fields = array(
        'rowid' => array(
            'type' => 'integer',
            'label' => 'TechnicalID',
            'visible' => -2,
            'notnull' => 1,
            'position' => 1
        ),
        'ref' => array(
            'type' => 'varchar(32)',
            'label' => 'Ref',
            'visible' => 1,
            'notnull' => 1,
            'showoncombobox' => 1,
            'position' => 10
        ),
        'date_chargement' => array(
            'type' => 'date',
            'label' => 'DateChargement',
            'visible' => 1,
            'position' => 20
        ),
        'fk_camion_type' => array(
            'type' => "integer:CamionType:planchargement/class/camiontype.class.php",
            'label' => 'CamionType',
            'visible' => 1,
            'notnull' => 1,
            'position' => 30
        ),
        'statut' => array(
            'type' => 'smallint',
            'label' => 'Status',
            'visible' => 1,
            'notnull' => 1,
            'default' => 0,
            'arrayofkeyval' => array(
                0 => 'Brouillon',
                1 => 'Valide',
                2 => 'Parti',
                9 => 'Annule'
            ),
            'position' => 500
        ),
        // ... autres champs
    );

    public $rowid;
    public $ref;
    public $date_chargement;
    public $fk_camion_type;
    public $statut;

    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    public function create(User $user, $notrigger = 0)
    {
        return $this->createCommon($user, $notrigger);
    }

    public function fetch($id, $ref = null)
    {
        return $this->fetchCommon($id, $ref);
    }

    public function update(User $user, $notrigger = 0)
    {
        return $this->updateCommon($user, $notrigger);
    }

    public function delete(User $user, $notrigger = 0)
    {
        return $this->deleteCommon($user, $notrigger);
    }

    // Méthodes métier spécifiques
    public function addCommande($fk_commande) { /* ... */ }
    public function addUm($fk_um_type) { /* ... */ }
    public function getColisNonAffectes() { /* ... */ }
    public function calculateTotals() { /* ... */ }
}
```

Les méthodes `createCommon`, `fetchCommon`, etc. de `CommonObject` utilisent
automatiquement `$fields` pour générer le SQL. C'est le pattern à suivre,
gain de temps énorme.

### 7.3. Numérotation des chargements

Utiliser un numéroteur Dolibarr standard : créer
`core/modules/planchargement/mod_chargement_standard.php` qui génère des refs
du type `CH2604-0001` (préfixe CH + AAMM + compteur). Hériter de
`CommonNumRefGenerator`. Stocker le numéroteur actif dans la constante
`PLANCHARGEMENT_CHARGEMENT_ADDON`.

### 7.4. Système de droits

Toutes les pages doivent vérifier :
```php
if (!$user->hasRight('planchargement', 'read'))    accessforbidden();
if (!$user->hasRight('planchargement', 'write'))   accessforbidden();
if (!$user->hasRight('planchargement', 'delete'))  accessforbidden();
if (!$user->hasRight('planchargement', 'admin'))   accessforbidden();
```

---

## 8. Points d'attention et pièges connus

### 8.1. `GETPOSTFLOAT` pour les décimales
Bug avéré de Dolibarr 20 : `GETPOSTINT` tronque les décimales sans avertir.
Pour un poids (`12.5 kg`), utiliser impérativement `GETPOSTFLOAT('weight')`.

### 8.2. Clés étrangères et OVH mutualisé
Certaines configs MySQL d'OVH mutualisé ne supportent pas `ON DELETE CASCADE`
sur les tables InnoDB avec certaines collations mixtes. Si l'install du module
plante à ce niveau :
- tester sans les FK (garder juste les INDEX)
- l'intégrité sera assurée au niveau applicatif dans les méthodes `delete()`

### 8.3. `const` entries orphelines à la désinstallation
Bug historique rencontré sur d'autres modules custom : la désinstallation
ne nettoie pas toujours `llx_const`. Prévoir dans `remove()` un nettoyage
explicite :
```php
public function remove($options = '')
{
    $sql = array();
    $sql[] = "DELETE FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'PLANCHARGEMENT_%'";
    return $this->_remove($sql, $options);
}
```

### 8.4. CSS du thème
Dolibarr 20 les lignes paires/impaires des tables utilisent des **variables
CSS** (`--colorbacklineimpair1` etc.), pas des variables PHP de thème. Si tu
veux personnaliser l'affichage des lignes d'UM, passer par du CSS dans
`css/planchargement.css` avec des sélecteurs `nth-child`, pas par des
surcharges PHP.

### 8.5. Upgrades Dolibarr v21/v22
Le client suit les upgrades Dolibarr. Garder le module aussi isolé que possible :
- Pas de patch des fichiers core
- Hooks bien commentés, format plat (cf. règle 7)
- Référence soft vers `llx_colisage_packages` (pas de FK dure)
- Utiliser exclusivement l'API publique de Dolibarr (pas d'accès direct aux
  tables `llx_commande*` — passer par la classe `Commande`)

---

## 9. Tests et validation

### 9.1. Test d'installation
1. Copier le dossier `planchargement/` dans `htdocs/custom/`
2. Aller dans Accueil → Configuration → Modules
3. Activer le module "Plan de chargement"
4. Vérifier dans phpMyAdmin que les 6 tables `llx_planchargement_*` existent
5. Vérifier qu'une entrée apparaît dans le menu Commercial à gauche
6. Désactiver/réactiver le module : ne doit pas perdre de données
7. Désinstallation complète puis réinstallation : doit rejouer les SQL

### 9.2. Scénario de recette fonctionnelle (dès que les pages sont dispo)
1. Saisir 2 types de camions (semi 13.6m, porteur 7m)
2. Saisir 4 types d'UM (EUR, 100×120, chevalet 4m, chevalet 6m)
3. Créer une commande de test avec 5 colis dans le module colisage
4. Créer un chargement, lui rattacher la commande
5. Créer 2 UM et y répartir les colis
6. Lancer le placement auto
7. Déplacer une UM à la main
8. Gerber une UM sur une autre
9. Valider le chargement et exporter le PDF
10. Vérifier l'ordre de déchargement calculé

### 9.3. Non-régression colisage
Après chaque déploiement, vérifier que le module `colisage` continue de
fonctionner normalement (création/édition de colis sur une commande).

---

## 10. Plan de travail recommandé pour Claude Code

Étapes séquentielles, chacune produit un livrable testable :

| # | Livrable | Estimation | Condition de "done" |
|---|---|---|---|
| 1 | Classes CRUD + langfiles | 1-2 j | Chaque classe instanciable, `create/fetch/update/delete` OK en SQL direct |
| 2 | Admin types camion + UM | 1 j | Utilisateur peut saisir/modifier son catalogue depuis l'admin |
| 3 | `list.php` + `card.php` basiques | 1 j | Créer un chargement, rattacher commandes, afficher infos |
| 4 | Composition UM (affectation colis) | 2 j | Drag & drop fonctionnel en UI, poids recalculés côté serveur |
| 5 | Algo placement auto 2D | 1 j | Bouton "placement auto", résultat visible en vue plan |
| 6 | Vue plan SVG + drag & drop | 2 j | Plan à l'échelle, déplacement persisté en base |
| 7 | Gerbage UI | 0,5 j | Drop d'une UM sur une UM existante = gerbage |
| 8 | Export PDF | 1 j | PDF joignable au BL, plan lisible |

**Total estimé** : ~10 jours effectifs.

---

## 11. Démarche itérative recommandée

1. **Commencer par la classe `Chargement`** en entier, car c'est l'objet
   central. Les autres classes (`CamionType`, `UmType`, `ChargementUm`)
   peuvent être plus minimales au début.
2. **Tester chaque classe en SQL direct** via un petit script CLI dans
   `scripts/test_crud.php` avant de construire l'UI dessus.
3. **Livrer un module activable à chaque étape**. Il vaut mieux avoir une V0.1
   qui fait peu mais qui s'installe sans erreur qu'une V1.0 branchée à moitié.
4. **Committer fréquemment sur GitHub** (`Patochiz/planchargement`) avec des
   messages clairs. Convention commit : `feat:`, `fix:`, `chore:`, `docs:`.
5. **Documenter les déviations** : si Claude Code doit s'écarter du plan pour
   une raison technique (ex. Imagick absent sur OVH → basculer sur GD),
   noter la décision dans un `DECISIONS.md` à la racine du module.

---

## 12. Contacts et ressources

- **Développeur principal** : Patrice (Patochiz sur GitHub)
- **Modules voisins à consulter pour les conventions** :
  - `Patochiz/easyvariant`
  - `Patochiz/planningproduction`
  - module `colisage` (non public, en prod chez le client)
- **Doc Dolibarr** : https://wiki.dolibarr.org/index.php/Module_development

---

## 13. Checklist avant chaque PR / livraison

- [ ] Aucune violation des 7 règles maison
- [ ] Pas de `$conf->global->XXX`, uniquement `getDolGlobalXxx()`
- [ ] Toutes les chaînes UI via `$langs->trans()`
- [ ] Permissions vérifiées sur chaque page
- [ ] Échappement des POST/GET (`GETPOSTFLOAT` pour les décimales !)
- [ ] Docblocks sur les méthodes publiques
- [ ] Pas de code mort ni de `console.log` / `var_dump` oubliés
- [ ] Testé sur Dolibarr 20.0.0, PHP 8.2
- [ ] Le module s'active et se désactive proprement
- [ ] `colisage` fonctionne toujours après activation

---

**Fin du briefing**. Claude Code peut maintenant attaquer l'étape 1
(classes CRUD + langfiles). En cas de doute sur une décision architecturale
non couverte par ce document, demander avant d'implémenter.
