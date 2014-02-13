<?php
/**
 * TranslatePaginateCustomQuery behavior class.
 *
 * Using PaginateBehavior and TranslateBehavior with a custom find queries, the
 * count query crashes.
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

App::uses('ModelBehavior', 'Model');

class TranslatePaginateCustomQueryBehavior extends ModelBehavior
{
/**
 * Fix PaginatorComponent with TranslateBehavior and custom-find-queries
 */
	function paginateCount(Model $model, $conditions = array(), $recursive = null, $extra = array()) {
		return count($model->find('all', array('conditions' => $conditions)));
	}
}
