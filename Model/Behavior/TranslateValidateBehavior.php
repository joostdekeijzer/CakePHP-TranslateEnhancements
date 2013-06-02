<?php
/**
 * TranslateValidate behavior class.
 *
 * Tries to solve https://cakephp.lighthouseapp.com/projects/42648/tickets/2463-multi-language-forms
 *
 * Apply validationrules to Translated items, but only if field is saved as
 * a "localised" array, eg:
 *
 * @@@
 * $Model->data[ModelAlias][TranslatedField] = array(
 *     'eng' => 'English content',
 *     'nld' => 'Nederlandse inhoud'
 * );
 * @@@
 *
 * PHP 5
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Joost de Keijzer et. al. (http://dekeijzer.org)
 * @link          http://dekeijzer.org Joost de Keijzer
 * @package       App.Behavior
 * @since         4-may-2013
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
 * TODO:
 * - Tests, tests, tests
 * - add CakeValidationSet calls to setMethods ?
 */

App::uses('ModelBehavior', 'Model');

class TranslateValidateBehavior extends ModelBehavior {

	public function afterValidate(Model $Model) {
		if( !$Model->Behaviors->enabled('Translate') || empty($Model->validate) ) {
			return true;
		}

		$settings = $Model->Behaviors->Translate->settings[$Model->alias];
		$runtime = $Model->Behaviors->Translate->runtime[$Model->alias];
		$fields = array_merge($settings, $runtime['fields']);

		if( isset( $runtime['beforeSave'] ) ) {
			// translatable has run, $Model->data has been modified
			$data = $runtime['beforeSave'];
		} else {
			$data = $Model->data;
		}

		$valid = true;
		foreach ($fields as $key => $value) {
			$field = (is_numeric($key)) ? $value : $key;
			if( !isset($Model->validate[$field]) ) continue;

			$fieldValidator = $Model->validator()->offsetGet($field);
			if (isset($data[$field])) {
				if (is_array($data[$field])) {
					foreach($data[$field] as $locale => $content) {
						/**
						 * Logic copied from ModelValidator->errors()
						 * ???: add $fieldValidator->setMethods(ModelValidator->getMethods()) ?
						 */
						$fieldValidator->setValidationDomain($Model->validationDomain);
						$errors = $fieldValidator->validate( array( $field => $content ), $Model->exists() );
						foreach ($errors as $error) {
							$Model->validator()->invalidate($fieldValidator->field, $error);
							$valid = false;
						}
					}
				}
			}
			if( isset($Model->validationErrors[$field]) ) {
				$Model->validationErrors[$field] = array_unique ( $Model->validationErrors[$field] );
			}
		}

		return $valid;
	}

}
