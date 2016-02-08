CakePHP-TranslateEnhancements
=============================

CakePHP v2 plugin enhancing the Translate Behavior

This plugin currently consists of two Behaviors

TranslateValidate
-----------------
Will validate all locales when inputing multiple locale field content

### Usage

```
<?php
App::uses('AppModel', 'Model');

class Article extends AppModel {
    public $actAs = array(
        'Translate' => array(
            'title',
            'slug' => 'allSlugs',
            'body',
        ),
        'TranslateEnhancements.TranslateValidate',
    );

    public $validate = array(
        'title' => 'notBlank',
        'slug'  => array(
            'a_z0_9' => array(
                'rule' => '/^[a-z0-9][a-z0-9\-]{2,}$/',
                'message' => 'Only letters, integers or a dash (-), min 3 characters',
                'allowEmpty' => false,
                'required' => true,
            )
        ),
    );
}
```

When you have a view containing a form for both the English and Dutch title:
```
echo $Form->input('Article.title.eng');
echo $Form->input('Article.title.nld');
```

The Cake supplied Translate behavior will only validate the first occurence, but 
with `TranslateEnhancements.TranslateValidate` all supplied locale of a field 
will be validated.

TranslateAssociationBehavior
----------------------------
Will translate associated models

### Usage

Say we modify `Article` above to hasMany `Attachment` :

```
<?php
App::uses('AppModel', 'Model');

class Attachment extends AppModel {
    public $actAs = array(
        'Translate' => array(
            'description',
        ),
    );
}
```

The modified Article will look like:

```
<?php
App::uses('AppModel', 'Model');

class Article extends AppModel {
    public $actAs = array(
        'Translate' => array(
            'title',
            'slug' => 'allSlugs',
            'body',
        ),
        'TranslateEnhancements.TranslateValidate',
        'TranslateEnhancements.TranslateAssociation',
    );

    public $validate = array(
        'title' => 'notBlank',
        'slug'  => array(
            'a_z0_9' => array(
                'rule' => '/^[a-z0-9][a-z0-9\-]{2,}$/',
                'message' => 'Only letters, integers or a dash (-), min 3 characters',
                'allowEmpty' => false,
                'required' => true,
            )
        ),
    );

    public $hasMany = array(
        'Attachment',
    );
}
```

Now, when we do `$result = $Article->find('all', array('recursive' => 2));`, the translated 
Attachment description will be available.

TranslatePaginateCustomQueryBehavior
------------------------------------


License
-------
MIT License (http://www.opensource.org/licenses/mit-license.php)