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
	// bail-out on some query types
	private $bailQueryTypes = array('count');

/**
 * Recursively translate associated data
 */
	function beforeFind (Model $model, $query) {
		if( in_array($model->findQueryType, $this->bailQueryTypes) ) {
			return true;
		}

		$recursive = 1;
		if (isset($query['recursive']) && $query['recursive'] > 0) {
			$recursive = $query['recursive'];
		}

		$contain = array();
		$hasContain = false;
		if ($model->Behaviors->enabled('Containable')) {
			if (isset($model->Behaviors->Containable->runtime[$model->alias]['contain'])) {
				$hasContain = true;
				$contain = $model->Behaviors->Containable->runtime[$model->alias]['contain'];
			}

			if (isset($query['contain'])) {
				$hasContain = true;
				if ($query['contain'] !== false) {
					$contain = array_merge($contain, (array)$query['contain']);
				}
			}

			if ($hasContain && empty($contain)) {
				$recursive = -1;
			}
		}

		if ((!$hasContain || !empty($contain)) && $recursive > 0) {
			$this->settings[$model->alias]['query'] = $query + array('recursive' => 1);
			$containments = array();
			if ($hasContain) {
				$this->settings[$model->alias]['query']['contain'] = $contain;
				$this->settings[$model->alias]['query']['containments'] = $model->Behaviors->Containable->containments($model, $contain);
			}

			foreach( array('hasMany', 'hasAndBelongsToMany') as $type ) {
				foreach ($model->{$type} as $assocKey => $assocData) {
					// we don't need the Translatable associations
					if( isset($assocData['className']) && 'I18nModel' == $assocData['className'] ) continue;

					// are we contained?
					if (isset($this->settings[$model->alias]['query']['containments']) && !isset($this->settings[$model->alias]['query']['containments']['models'][$assocKey])) continue;

					// only when associated model is Translatable
					if( !$model->{$assocKey}->Behaviors->enabled('Translate') ) continue;

					// ok, so we do step 1 of our trick
					$this->settings[$model->alias]['unbound'][$type] = $assocKey;
					$model->unbindModel( array( $type => array( $assocKey ) ) );
				}
			}
		}
		return true;
	}

	function afterFind (Model $model, $results, $primary = false) {
		if( !$primary || !is_array($results) || in_array($model->findQueryType, $this->bailQueryTypes) ) return $results;

		$singleToList = false;
		if( isset($results[$model->alias]) ) {
			$singleToList = true;
			$results = array( 0 => $results );
		}

		foreach( array('hasMany', 'hasAndBelongsToMany') as $type ) {
			foreach ($model->{$type} as $assocKey => $assocData) {
				// we don't need the Translatable associations
				if( isset($assocData['className']) && 'I18nModel' == $assocData['className'] ) continue;

				// are we contained?
				if (isset($this->settings[$model->alias]['query']['containments']) && !isset($this->settings[$model->alias]['query']['containments']['models'][$assocKey])) continue;

				// only when associated model is Translatable
				if( !$model->{$assocKey}->Behaviors->enabled('Translate') ) continue;

				// only if available in the resultset
				if ( isset($results[0][$assocKey]) ) {
					// ok, so we do our single-step trick
					$this->_translateManyAfterBurner( $model, $results, $assocKey, $assocData );
				} else if (isset($this->settings[$model->alias]['query']['recursive']) && $this->settings[$model->alias]['query']['recursive'] > 0) {
					// step 2 of our twostep trick
					$this->_translateManyByFind( $model, $results, $type, $assocKey, $assocData );
				}

			}
		}

		foreach( array('belongsTo', 'hasOne') as $type ) {
			foreach ($model->{$type} as $assocKey => $assocData) {
				// only if available in the resultset
				if( !isset($results[0][$assocKey]) ) continue;

				// are we contained?
				if (isset($this->settings[$model->alias]['query']['containments']) && !isset($this->settings[$model->alias]['query']['containments']['models'][$assocKey])) continue;

				// only when associated model is Translatable
				if( !$model->{$assocKey}->Behaviors->enabled('Translate') ) continue;

				// ok, so we do our trick
				$this->_translateOneAfterBurner( $model, $results, $assocKey, $assocData );
			}
		}

		unset($this->settings[$model->alias]['query']);

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
		$fields = $this->_retrieveFieldnames( $model->{$assocKey}->Behaviors->Translate->settings[$assocKey] );

		$ids = array();
		foreach( $results as &$item ) {
			if( !empty($item[$assocKey][$model->{$assocKey}->primaryKey]) ) {
				$ids[] = $item[$assocKey][$model->{$assocKey}->primaryKey];
			}
		}

		// when no id's are found, return
		if( count($ids) == 0 ) return;


		$translated = array();

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
			'fields' => $fields,
			'recursive' => 0,
		) );

		if( $reenable ) {
			$model->{$assocKey}->Behaviors->enable('TranslateAssociation');
		}

		$translated = Hash::combine($translated, "{n}.{$assocKey}.id", "{n}.{$assocKey}");

		$nullFill = array_fill_keys( $fields, null );
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

/**
 * We prevented find in the beforeFind, now je find the requested items
 */
	protected function _translateManyByFind( Model $model, &$results, $assocType, $assocKey, $assocData ) {
		$reenable = false;
		if($model->{$assocKey}->Behaviors->enabled('TranslateAssociation')) {
			$model->{$assocKey}->Behaviors->disable('TranslateAssociation');
			$reenable = true;
		}

		$recursive = $this->settings[$model->alias]['query']['recursive'] - 1;
		foreach( $results as &$item ) {
			$query = array('recursive' => $recursive);
			if ('hasAndBelongsToMany' == $assocType) {
				 $model->{$assocKey}->bindModel(array('hasOne' => array($assocData['with'])));
				$query['fields'] = array( sprintf('%s.*', $assocKey) );
				$query['conditions'] = array( sprintf('%s.%s', $assocData['with'], $assocData['foreignKey']) => $item[$model->alias][$model->primaryKey]);
			}
			if ('hasMany' == $assocType) {
				$query['conditions'] = array( sprintf('%s.%s', $assocKey, $assocData['foreignKey']) => $item[$model->alias][$model->primaryKey]);

				// on self-relations: only go one deep!
				if ($assocData['className'] == $model->name) {
					$query['recursive'] = 0;
				}
			}
			$item[$assocKey] = Hash::extract($model->{$assocKey}->find('all', $query), sprintf('{n}.%s', $assocKey));
		}
		if( $reenable ) {
			$model->{$assocKey}->Behaviors->enable('TranslateAssociation');
		}
	}
/**
 * An array of fields can be <index> => 'fieldname' or 'fieldname' => 'alias' in Cake.
 */
	protected function _retrieveFieldnames( $settings ) {
		$fields = array();
		foreach( $settings as $key => $value ) {
			if( is_numeric( $key ) && is_string( $value ) ) {
				$fields[] = $value;
			} else if( is_string($key) ) {
				$fields[] = $key;
			} // else { ignore...
		}
		return $fields;
	}
}
