CakePHP-TranslateEnhancements
=============================

CakePHP v2 plugin enhancing the Translate Behavior

This plugin currently consists of two Behaviors

TranslateAssociationBehavior
----------------------------
Will translate associated models

TranslateValidate
-----------------
Will validate all locales when inputing multiple locale field content

Usage
-----
```
<?php
App::uses('AppModel', 'Model');

class Post extends AppModel {
    public $actAs = array(
        'Translate' => array(
            'title',
            'slug' => 'allSlugs',
            'body',
        ),
        'TranslateEnhancements.TranslateValidate',
    )

    public $validate = array(
        'title' => 'notempty',
        'slug'  => array(
            'a_z0_9' => array(
                'rule' => '/^[a-z0-9][a-z0-9\-]{2,}$/',
                'message' => 'Only letters, integers or a dash (-), min 3 characters',
                'allowEmpty' => false,
                'required' => true,
            )
        ),
    )
}
```

When you have a view containing a form for both the English and Dutch title:
```
echo $Form->input('Post.title.eng');
echo $Form->input('Post.title.nld');
```

The Cake supplied Translate behavior will only validate the first occurence, but 
with `TranslateEnhancements.TranslateValidate` all supplied locale of a field will be validated.
