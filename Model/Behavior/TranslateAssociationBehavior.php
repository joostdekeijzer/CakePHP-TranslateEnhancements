<?php
/**
 * TranslateAssociation behavior class.
 *
 * Makes Translate behave recursive on associations.
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
 * - Find a way to make the used Translate behavior configurable per
 *   (associated) model
 * - Don't just add all translated fields to belongsTo and hasOne associations,
 *   maybe by finding the requested fields in a beforeFind?
 * - Maybe _translateManyAfterBurner can be optimised?
 * - Tests, tests, tests
 */

App::uses('ModelBehavior', 'Model');

class TranslateAssociationBehavior extends ModelBehavior {

/**
 * Recursively translate associated data
 */
	function afterFind (Model $model, $results, $primary) {
		if( !$primary || !is_array($results) ) return $results;

		$singleToList = false;
		if( isset($results[$model->alias]) ) {
			$singleToList = true;
			$results = array( 0 => $results );
		}

		foreach( array('hasMany', 'hasAndBelongsToMany') as $type ) {
			foreach ($model->{$type} as $assocKey => $assocData) {
				// we don't need the Translatable associations
				if( isset($assocData['className']) && 'I18nModel' == $assocData['className'] ) continue;

				// only if available in the resultset
				if( !isset($results[0][$assocKey]) ) continue;

				// only when associated model is Translatable
				if( !$model->{$assocKey}->Behaviors->enabled('Translate') ) continue;

				// ok, so we do our trick
				$this->_translateManyAfterBurner( $model, $results, $assocKey, $assocData );
			}
		}

		foreach( array('belongsTo', 'hasOne') as $type ) {
			foreach ($model->{$type} as $assocKey => $assocData) {
				// only if available in the resultset
				if( !isset($results[0][$assocKey]) ) continue;

				// only when associated model is Translatable
				if( !$model->{$assocKey}->Behaviors->enabled('Translate') ) continue;

				// ok, so we do our trick
				$this->_translateOneAfterBurner( $model, $results, $assocKey, $assocData );
			}
		}

		if( $singleToList ) {
			return $results[0];
		} else {
			return $results;
		}
	}

/**
 * Content must be fetched from database
 */
	protected function _translateOneAfterBurner( Model $model, &$results, $assocKey, $assocData ) {
		$settings = $model->{$assocKey}->Behaviors->Translate->settings[$assocKey];

		$ids = array();
		foreach( $results as &$item ) {
			if( !empty($item[$assocKey][$model->{$assocKey}->primaryKey]) ) {
				$ids[] = $item[$assocKey][$model->{$assocKey}->primaryKey];
			}
		}

		$translated = array();
		if( count($ids) > 0 ) {
			// problems can occur with self-joins
			$model->{$assocKey}->unbindModel( array( 'belongsTo' => array( $assocKey ) , 'hasOne' => array( $assocKey ) ) );

			$reenable = false;
			if($model->{$assocKey}->Behaviors->enabled('TranslateAssociation')) {
				$model->{$assocKey}->Behaviors->disable('TranslateAssociation');
				$reenable = true;
			}

			$translated = $model->{$assocKey}->find('all', array(
				'conditions' => array(
					$model->{$assocKey}->escapeField($model->{$assocKey}->primaryKey) => $ids
				),
				'fields' => array_keys($settings),
				'recursive' => 0,
			) );

			if( $reenable ) {
				$model->{$assocKey}->Behaviors->enable('TranslateAssociation');
			}

			$translated = Hash::combine($translated, "{n}.{$assocKey}.id", "{n}.{$assocKey}");
		}

		$nullFill = array_fill_keys( array_keys($settings), null );
		foreach( $results as &$item ) {
			if( isset($translated[ $item[$assocKey][$model->{$assocKey}->primaryKey] ]) ) {
				$item[$assocKey] = array_merge( $item[$assocKey], $translated[ $item[$assocKey][$model->{$assocKey}->primaryKey] ] );
			} else {
				$item[$assocKey] = array_merge( $item[$assocKey], $nullFill );
			}
		}
	}

/**
 * We have the content, we just need to re-order
 */
	protected function _translateManyAfterBurner( Model $model, &$results, $assocKey, $assocData ) {
		$settings = $model->{$assocKey}->Behaviors->Translate->settings[$assocKey];
		foreach( $results as &$item ) {
			if( is_array($item[$assocKey]) && isset($item[$assocKey][0]) ) {
				foreach($item[$assocKey] as &$assocItem) {
					foreach( $settings as $field => $alias ) {
						if( isset($assocItem[$alias]) ) {
							// field is defined
							if( isset($assocItem[$alias][$model->locale]['content']) ) {
								$assocItem[$field] = $assocItem[$alias][$model->locale]['content'];
							} else if( is_array($assocItem[$alias]) && isset($assocItem[$alias][0]) ) {
								// again: a list...
								foreach($assocItem[$alias] as $translation) {
									if( $translation['locale'] == $model->locale ) {
										$assocItem[$field] = $translation['content'];
										break;
									}
								}
							} // else { not needed
						}
					}
				}
			} // else { not needed
		}
	}
}
