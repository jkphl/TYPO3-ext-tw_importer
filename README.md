# tollwerk Import Extension

tbd

## Registering your own extension

### Requirements


* Folder with readable .ods file inside *fileadmin/user_upload/tw_importer/*. Example: *fileadmin/user_upload/tw_importer/yourextensionkey/import_me.ods*

* ext_localconf.php inside your extension directory registering the extension via *$TYPO3_CONF_VARS['EXTCONF']['tw_importer']['registeredImports']['yourextensionkey']*

* Valid array for the 'registeredImports' hook, see [Hooks > registeredImports](#hooks_registeredImports)

* There must be a column named "tx\_twimporter\_id" for every model you wish to be importable in it's corresponding database table. So change your *ext_tables.sql* accordingly. Alternatively you can change the mapping inside your *ext\_typoscript\_setup.txt*. Example:


		config.tx_extbase.persistence.classes {
			Tollwerk\TwRws\Domain\Model\Product {
				mapping {
					tableName = tx_twrws_domain_model_product
					columns {

						# SKU is the column name in YOUR database, so change it
						sku.mapOnProperty = txTwimporterId
						
						# All this other columns are there by default if your models are defined to be translateble 
						sys_language_uid.mapOnProperty = translationLanguage
						l10n_parent.mapOnProperty = translationParent
						hidden.mapOnProperty = hidden
						deleted.mapOnProperty = deleted
					}
				}
			}


* All corresponding models must implement *\Tollwerk\TwImporter\Domain\Model\AbstractImportable*

* All models extending *\Tollwerk\TwImporter\Domain\Model\AbstractImportable* must overide the procted property *$translationParent* with "@var \yourvendor\yournamespace\domain\model\yourmodel" (fully qualified namespace is mandatory!)

* All corresponding repositories must implement *\Tollwerk\TwImporter\Domain\Repository\AbstractImportableRepository*

* Put a *ext_typoscript_setup.txt* in your extension directory. Each importable class must map some fields (tx_twimporter_id, sys_language_uid, l10n_parent, hidden, deleted).

* All your importable classes must have translation enabled must be disabable and deletable. So the following columns must exist in each corresponding table: sys_language_uid, l10n_parent, hidden, deleted

* Your repositories extending \Tollwerk\TwImporter\Domain\Repository\AbstractImportableRepository must have a protected property named "_tablename" which stores the corresponding tablename, e.g. "*tx\_yourextensionkey\_domain\_model\_yourmodelclassname*".

* For nested imports ('children' inside 'hierarchy' inside your ext_localconf.php) you must override the "addImportChild($object,$objectConf) defined in \Tollwerk\TwImporter\Domain\Model\AbstractImportable. Alternatively, the import will try to call the "add[YourChildModelClassname]" method on the parent object

* You can overwrite the **protected _prepareImport()** of models to clean up the database etc. before the actual import of the current object. Will be called for each import row / model.

### Checklist
* Import Folder and File
* ext_localconf.php with valid hook / mapping / hierarchy
* ext\_typoscript\_setup.txt with valid mapping
* Models must implement AbstractImportable
* Models must have **protected $translationParent**
* Repositories  must implement AbstractImportableRepository
* Repositories must have **protected $_tablename**

**Special Case:  Other import ID field than 'tx_twimporter_id'**

* Repositories must have **protected  $field_sku** set to table column name
* Set 'importIdField' in hierarchy

**Special Case: Inline / IRRE Children, Value-Objects as Children etc.**

*  Define value for **parentAddImportChild** inside the children hierarchy.
*  Then, inside the parenet model, implement the **addImportChild(..)** function like this

				
		/**
		 * @param \Tollwerk\TwImporter\Domain\Model\AbstractImportable $child
		 * @param array $childConf
		 * @param int $sysLanguage
		 */
		public function addImportChild($child, $childConf, $sysLanguage)
		{
	
			// 'Employees' is the value you defined for "parentAddImportChild" inside the child hierarchy
			if($childConf['parentAddImportChild'] == 'Employee'){
				$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
					'tx_twimportertest_domain_model_employee',
					'uid = '.$child->getUid(),
					array(
						'company' => $this->uid
					)
				);
			}
		}

### Fehlerquellen

* **SQL Error message in TYPO3 backend or empty import table** Mapping. Column names inside import .ods-file with whitespaces or special characters? (E.g."ä,ö,?,!" etc.)


**Important:** Don't forget to clear all caches via the **install tool** after addding or changing stuff inside your ext_localconf.php!  

## Hooks

Register for tw\_importer hooks for inside the following global array. The last "[ ]" can be empty or must be filled with an array key / index of your choice, depending on the hook.
 
      $TYPO3_CONF_VARS['EXTCONF']['tw_importer']['HOOK NAME'][] = YOUR VALUES OR CLASSES

**Important:** Don't forget to clear all caches via the **install tool** after registering or changing hooks!   

<a name="hooks_registeredImports"></a>
### registeredImports
Use this hook to register your own extension for import. You must set the extension key of your extenion as index for the array. Example:

    $TYPO3_CONF_VARS['EXTCONF']['tw_importer']['registeredImports']['tx_news'] = array(
		'title' => 'News Extensios, baby!',
		'mapping' => array(
			// see "mapping" in this manual..
		)
	);

##TODO

* Propper l10n_mode and language handling. Currently everything is treated like l10n_mode = "exclude"

* Inline / IRRE objects vs. propper relation handling..

* FlashMessage:NOTICE messages with step counter or something for better troubleshooting with help of this manual (e.g. "Error after Step 4? Could be wrong mapping")

* Propper handling of value objects

* Multpiple children of same class / model on the same level. E.g University->Professors (Model: Person) **AND** University-Student (Model: Person) ! ! !   

* Auswählen der gewünschten Import-Datei