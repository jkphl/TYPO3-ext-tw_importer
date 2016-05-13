# tollwerk Import Extension

tbd

## Registering your own extension

tbd

**Important:** Don't forget to clear all caches via the **install tool** after registering or changing hooks!  

## Hooks

Register for tw\_importer hooks for inside the following global array. The last "[ ]" can be empty or must be filled with an array key / index of your choice, depending on the hook.
 
      $TYPO3_CONF_VARS['EXTCONF']['tw_importer']['HOOK NAME'][] = YOUR VALUES OR CLASSES

**Important:** Don't forget to clear all caches via the **install tool** after registering or changing hooks!   

### registeredImports
Use this hook to register your own extension for import. You must set the extension key of your extenion as index for the array. Example:

    $TYPO3_CONF_VARS['EXTCONF']['tw_importer']['registeredImports']['tx_news'] = array(
		'title' => 'News Extensios, baby!',
		'mapping' => array(
			// see "mapping" in this manual..
		)
	);