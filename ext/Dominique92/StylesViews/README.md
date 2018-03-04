# PhpBB 3.1 Styles & Views

PhpBB3.1 extension

FUNCTIONS
=========
* Change the style depending on the domain:
```php
$config_locale = array (
	'styles' => array (
		'domain.com' => 'style_name',
	),
);
```

* Change the view of a page depending on this tag declared in the forum descriptor:
```
*view
```

INSTALL
=======
Download the zip file
Unzip & upload this package to ext/Dominique92/StylesViews
Go to phpbb administration -> personnaliser -> Gérer les extensions -> StylesViews -> Activer